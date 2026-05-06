"""
╔══════════════════════════════════════════════════════════════════╗
║         CRYAI — API UNIFICADA                                   ║
║                                                                  ║
║  UN SOLO endpoint /analizar que acepta DOS formatos:             ║
║                                                                  ║
║  Modo A — Archivo (PHP ia_recomendacion.php):                    ║
║    POST /analizar                                                ║
║    Content-Type: multipart/form-data                             ║
║    Body: campo "foto" con el archivo de imagen                   ║
║                                                                  ║
║  Modo B — Base64 (cámara en tiempo real):                        ║
║    POST /analizar                                                ║
║    Content-Type: application/json                                ║
║    Body: { "imagen": "data:image/jpeg;base64,..." }              ║
║                                                                  ║
║  La API detecta automáticamente qué formato llegó.               ║
║                                                                  ║
║  Protección contra saturación:                                   ║
║    - Semáforo asyncio (max 3 inferencias simultáneas)            ║
║    - Throttle por IP: 1 petición de cámara cada 10s mínimo       ║
║                                                                  ║
║  Instalación:                                                    ║
║    pip install fastapi uvicorn python-multipart pillow pydantic  ║
║    pip install torch torchvision                                 ║
╚══════════════════════════════════════════════════════════════════╝
"""

import json
import random
import base64
import asyncio
import time
import torch
import torch.nn as nn
import uvicorn

from fastapi import FastAPI, File, UploadFile, Request, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from torchvision import models, transforms
from pathlib import Path
from PIL import Image
from io import BytesIO
from typing import Optional

# ─────────────────────────────────────────────────────────────
# CONFIGURACIÓN
# ─────────────────────────────────────────────────────────────

BASE_DIR   = Path(__file__).parent
MODELS_DIR = BASE_DIR / "modelos"
CRYAI_JSON = BASE_DIR / "dataset_cryai" / "dataset_limpio.json"

DEVICE     = torch.device("cuda" if torch.cuda.is_available() else "cpu")
IMG_SIZE   = 224
MAX_FILE_MB = 5

# Máximo de inferencias corriendo al mismo tiempo (evita saturar la GPU/CPU)
MAX_CONCURRENT = 3
semaforo = asyncio.Semaphore(MAX_CONCURRENT)

# Throttle por IP para el modo cámara (evita que un cliente inunde el servidor)
# Guarda: { ip: timestamp_ultimo_request }
throttle_camara: dict[str, float] = {}
THROTTLE_SEGUNDOS = 10   # mínimo entre peticiones de cámara por IP

# ─────────────────────────────────────────────────────────────
# TEXTOS DE UI
# ─────────────────────────────────────────────────────────────

NOMBRES_ROSTRO = {
    "rostro__oval":        "Ovalado",
    "rostro__round":       "Redondo",
    "rostro__square":      "Cuadrado",
    "rostro__rectangular": "Rectangular",
    "rostro__oblong":      "Oblongo",
    "rostro__diamond":     "Diamante",
    "rostro__heart":       "Corazón",
    "rostro__triangle":    "Triangular",
}

NOMBRES_CABELLO = {
    "cabello__straight":   "Lacio",
    "cabello__wavy":       "Ondulado",
    "cabello__curly":      "Rizado",
    "cabello__kinky":      "Afro",
    "cabello__dreadlocks": "Dreadlocks",
    "cabello__coily":      "Muy Rizado",
    "cabello__short":      "Corto",
    "cabello__medium":     "Mediano",
    "cabello__long":       "Largo",
    "cabello__thick":      "Grueso",
    "cabello__fine":       "Fino",
    "cabello__normal":     "Normal",
}

CONSEJOS_ROSTRO = {
    "rostro__oval":        "Tienes el rostro ideal — casi cualquier corte te favorece. Experimenta con libertad.",
    "rostro__round":       "Busca cortes con volumen en la cima y lados cortos para alargar visualmente el rostro.",
    "rostro__square":      "Los cortes con textura suavizan los ángulos de la mandíbula. Evita líneas muy rectas.",
    "rostro__rectangular": "Cortes con volumen en los lados equilibran la longitud. Evita estilos muy altos.",
    "rostro__oblong":      "Un flequillo o fringe ayuda a acortar visualmente el rostro. Volumen lateral recomendado.",
    "rostro__diamond":     "Volumen en frente y mentón equilibra los pómulos prominentes.",
    "rostro__heart":       "Cortes medianos con volumen inferior compensan la frente más ancha.",
    "rostro__triangle":    "Volumen en la parte superior compensa la mandíbula más ancha.",
}

# ─────────────────────────────────────────────────────────────
# CARGA DE MODELOS
# ─────────────────────────────────────────────────────────────

def cargar_modelo(nombre: str):
    ruta_pth  = MODELS_DIR / f"{nombre}_best.pth"
    ruta_meta = MODELS_DIR / f"{nombre}_meta.json"

    if not ruta_pth.exists() or not ruta_meta.exists():
        print(f"  ⚠️  Modelo '{nombre}' no encontrado — modo simulación")
        return None, None

    with open(ruta_meta, "r") as f:
        meta = json.load(f)

    num_clases = len(meta["clases"])
    modelo = models.resnet50(weights=None)
    in_f   = modelo.fc.in_features
    modelo.fc = nn.Sequential(
        nn.Dropout(0.4),
        nn.Linear(in_f, 512),
        nn.BatchNorm1d(512),
        nn.ReLU(inplace=True),
        nn.Dropout(0.25),
        nn.Linear(512, num_clases)
    )
    ckpt = torch.load(ruta_pth, map_location=DEVICE, weights_only=True)
    modelo.load_state_dict(ckpt["model_state"])
    modelo.to(DEVICE)
    modelo.eval()
    print(f"  ✅ {nombre} cargado — val_acc={ckpt.get('val_acc', 0):.4f} — {num_clases} clases")
    return modelo, meta

print("\n🔄 Cargando modelos CRYAI...")
MODELO_ROSTRO,  META_ROSTRO  = cargar_modelo("modelo_rostro")
MODELO_CABELLO, META_CABELLO = cargar_modelo("modelo_cabello")

# ─────────────────────────────────────────────────────────────
# DATASET DE CORTES
# ─────────────────────────────────────────────────────────────

def cargar_dataset():
    if not CRYAI_JSON.exists():
        print(f"  ⚠️  JSON no encontrado: {CRYAI_JSON}")
        return []
    with open(CRYAI_JSON, "r", encoding="utf-8") as f:
        data = json.load(f)
    print(f"  📚 Dataset: {len(data)} cortes cargados")
    return data

DATASET = cargar_dataset()

# ─────────────────────────────────────────────────────────────
# TRANSFORM DE INFERENCIA
# ─────────────────────────────────────────────────────────────

inferencia_transform = transforms.Compose([
    transforms.Resize((IMG_SIZE, IMG_SIZE)),
    transforms.ToTensor(),
    transforms.Normalize([0.485, 0.456, 0.406],
                         [0.229, 0.224, 0.225])
])

# ─────────────────────────────────────────────────────────────
# FUNCIONES DE INFERENCIA Y SIMULACIÓN
# ─────────────────────────────────────────────────────────────

def predecir(modelo, meta, imagen_pil: Image.Image, top_k: int = 3):
    tensor = inferencia_transform(imagen_pil).unsqueeze(0).to(DEVICE)
    with torch.no_grad():
        salida = modelo(tensor)
        probs  = torch.softmax(salida, dim=1)[0]

    top_probs, top_idxs = torch.topk(probs, min(top_k, len(meta["clases"])))
    return [
        {
            "clase":     meta["clases"][idx.item()],
            "nombre":    NOMBRES_ROSTRO.get(meta["clases"][idx.item()],
                         NOMBRES_CABELLO.get(meta["clases"][idx.item()],
                         meta["clases"][idx.item()])),
            "confianza": round(prob.item() * 100, 1)
        }
        for prob, idx in zip(top_probs.cpu(), top_idxs.cpu())
    ]

def simular_rostro():
    ops = [
        {"clase": "rostro__oval",        "nombre": "Ovalado",     "confianza": 0.0},
        {"clase": "rostro__round",       "nombre": "Redondo",     "confianza": 0.0},
        {"clase": "rostro__square",      "nombre": "Cuadrado",    "confianza": 0.0},
        {"clase": "rostro__rectangular", "nombre": "Rectangular", "confianza": 0.0},
    ]
    random.shuffle(ops)
    ops[0]["confianza"] = round(random.uniform(50, 70), 1)
    ops[1]["confianza"] = round(random.uniform(15, 25), 1)
    ops[2]["confianza"] = round(100 - ops[0]["confianza"] - ops[1]["confianza"] - 5, 1)
    return ops[:3]

def simular_cabello():
    ops = [
        {"clase": "cabello__straight",   "nombre": "Lacio",     "confianza": 0.0},
        {"clase": "cabello__wavy",       "nombre": "Ondulado",  "confianza": 0.0},
        {"clase": "cabello__curly",      "nombre": "Rizado",    "confianza": 0.0},
        {"clase": "cabello__kinky",      "nombre": "Afro",      "confianza": 0.0},
        {"clase": "cabello__dreadlocks", "nombre": "Dreadlocks","confianza": 0.0},
    ]
    random.shuffle(ops)
    ops[0]["confianza"] = round(random.uniform(40, 65), 1)
    ops[1]["confianza"] = round(random.uniform(15, 25), 1)
    ops[2]["confianza"] = round(100 - ops[0]["confianza"] - ops[1]["confianza"] - 5, 1)
    return ops[:3]

def buscar_recomendaciones(clase_rostro: str, clases_cabello: list, max_r: int = 6):
    if not DATASET:
        return []

    def norm(s):
        return s.lower().replace(" face shape", "").replace(" ", "_").strip()

    slug_r   = clase_rostro.replace("rostro__", "")
    slugs_c  = {c.replace("cabello__", "") for c in clases_cabello}

    scored = []
    for item in DATASET:
        score = 0
        for e in item.get("etiquetas_rostro", []):
            if norm(e) == slug_r:
                score += 2
        for e in item.get("etiquetas_cabello", []):
            if norm(e) in slugs_c:
                score += 1
        if score > 0:
            scored.append((score, item))

    scored.sort(key=lambda x: x[0], reverse=True)

    if scored:
        max_score = scored[0][0]
        top   = [i for s, i in scored if s == max_score]
        resto = [i for s, i in scored if s < max_score]
        random.shuffle(top)
        resultados = top + resto
    else:
        resultados = []

    return [
        {
            "titulo":            item.get("titulo", "Corte Recomendado"),
            "img_url":           item.get("img_url", ""),
            "page_url":          item.get("page_url", ""),
            "analisis":          item.get("analisis_opcional", "")[:200],
            "etiquetas_rostro":  item.get("etiquetas_rostro", []),
            "etiquetas_cabello": item.get("etiquetas_cabello", []),
        }
        for item in resultados[:max_r]
    ]

# ─────────────────────────────────────────────────────────────
# LÓGICA CENTRAL DE ANÁLISIS (compartida entre modos)
# ─────────────────────────────────────────────────────────────

def analizar_imagen(imagen_pil: Image.Image) -> dict:
    """
    Recibe un PIL Image ya abierto y listo.
    Devuelve el dict de respuesta completo.
    Independiente del formato de entrada (archivo o base64).
    """
    if MODELO_ROSTRO and META_ROSTRO:
        pred_rostro = predecir(MODELO_ROSTRO, META_ROSTRO, imagen_pil, top_k=3)
        modo = "modelo"
    else:
        pred_rostro = simular_rostro()
        modo = "simulacion"

    if MODELO_CABELLO and META_CABELLO:
        pred_cabello = predecir(MODELO_CABELLO, META_CABELLO, imagen_pil, top_k=3)
    else:
        pred_cabello = simular_cabello()

    clase_rostro_top = pred_rostro[0]["clase"]
    clases_cab_top   = [p["clase"] for p in pred_cabello[:3]]
    recomendaciones  = buscar_recomendaciones(clase_rostro_top, clases_cab_top)

    return {
        "modo": modo,
        "rostro": {
            "clase":     clase_rostro_top,
            "nombre":    pred_rostro[0]["nombre"],
            "confianza": pred_rostro[0]["confianza"],
            "consejo":   CONSEJOS_ROSTRO.get(clase_rostro_top, ""),
            "top3":      pred_rostro,
        },
        "cabello": {
            "clase":     clases_cab_top[0] if clases_cab_top else "",
            "nombre":    pred_cabello[0]["nombre"],
            "confianza": pred_cabello[0]["confianza"],
            "top3":      pred_cabello,
        },
        "recomendaciones": recomendaciones,
    }

# ─────────────────────────────────────────────────────────────
# FASTAPI APP
# ─────────────────────────────────────────────────────────────

app = FastAPI(
    title="CRYAI API Unificada",
    description="Acepta imagen como archivo (multipart) o como base64 (JSON)",
    version="2.0.0"
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["POST", "GET"],
    allow_headers=["*"],
)

# ─────────────────────────────────────────────────────────────
# ENDPOINTS
# ─────────────────────────────────────────────────────────────

@app.get("/")
def root():
    return {
        "status": "ok",
        "version": "2.0 — unificada",
        "modos_soportados": ["multipart/form-data (campo 'foto')", "application/json (campo 'imagen' base64)"],
        "modelos": {
            "rostro":  "cargado" if MODELO_ROSTRO  else "simulación",
            "cabello": "cargado" if MODELO_CABELLO else "simulación",
        },
        "dataset_cortes": len(DATASET),
    }

@app.get("/health")
def health():
    return {"status": "ok", "device": str(DEVICE)}


@app.post("/analizar")
async def analizar(request: Request, foto: Optional[UploadFile] = File(default=None)):
    """
    Endpoint unificado.

    Detecta automáticamente el formato de la petición:
    - Si llega multipart/form-data con campo 'foto' → modo archivo (PHP)
    - Si llega application/json con campo 'imagen'  → modo base64 (cámara)
    """
    content_type = request.headers.get("content-type", "")
    ip_cliente   = request.client.host

    # ── Obtener imagen PIL según el modo ────────────────────
    imagen_pil: Optional[Image.Image] = None

    # MODO A: multipart/form-data (viene del PHP con un archivo subido)
    if "multipart" in content_type:
        if foto is None:
            raise HTTPException(status_code=400, detail="Se esperaba el campo 'foto' en el formulario.")

        if foto.content_type not in ["image/jpeg", "image/png", "image/webp"]:
            raise HTTPException(status_code=400, detail="Formato no soportado. Usa JPG, PNG o WEBP.")

        contenido = await foto.read()
        if len(contenido) > MAX_FILE_MB * 1024 * 1024:
            raise HTTPException(status_code=413, detail=f"Imagen demasiado grande. Máximo {MAX_FILE_MB}MB.")

        try:
            imagen_pil = Image.open(BytesIO(contenido)).convert("RGB")
        except Exception:
            raise HTTPException(status_code=400, detail="No se pudo leer el archivo de imagen.")

    # MODO B: application/json con base64 (viene de la cámara en tiempo real)
    elif "application/json" in content_type:
        # Throttle: evitar que un mismo cliente inunde la API desde la cámara
        ahora = time.time()
        ultimo = throttle_camara.get(ip_cliente, 0)
        if ahora - ultimo < THROTTLE_SEGUNDOS:
            espera = round(THROTTLE_SEGUNDOS - (ahora - ultimo), 1)
            raise HTTPException(
                status_code=429,
                detail=f"Demasiadas peticiones. Espera {espera}s antes de la siguiente."
            )
        throttle_camara[ip_cliente] = ahora

        try:
            body = await request.json()
            b64  = body.get("imagen", "")
            if not b64:
                raise HTTPException(status_code=400, detail="Se esperaba el campo 'imagen' en el JSON.")

            # Quitar encabezado data:image/...;base64, si existe
            if "," in b64:
                b64 = b64.split(",")[1]

            contenido  = base64.b64decode(b64)
            imagen_pil = Image.open(BytesIO(contenido)).convert("RGB")
        except HTTPException:
            raise
        except Exception as e:
            raise HTTPException(status_code=400, detail=f"No se pudo decodificar la imagen Base64: {str(e)}")

    else:
        raise HTTPException(
            status_code=415,
            detail="Content-Type no soportado. Usa 'multipart/form-data' o 'application/json'."
        )

    # ── Inferencia con semáforo (máx MAX_CONCURRENT simultáneas) ──
    async with semaforo:
        # Correr la inferencia (que es síncrona/CPU-bound) en un thread pool
        # para no bloquear el event loop de FastAPI
        loop   = asyncio.get_event_loop()
        result = await loop.run_in_executor(None, analizar_imagen, imagen_pil)

    return JSONResponse(content=result)


# ─────────────────────────────────────────────────────────────
# MAIN
# ─────────────────────────────────────────────────────────────

if __name__ == "__main__":
    print("\n╔══════════════════════════════════════════════════════════════╗")
    print("║         CRYAI — API UNIFICADA v2.0                          ║")
    print("╠══════════════════════════════════════════════════════════════╣")
    print(f"║  Device    : {str(DEVICE):<47}║")
    print(f"║  Cortes    : {str(len(DATASET)):<47}║")
    print(f"║  Concurrentes máx: {str(MAX_CONCURRENT):<42}║")
    print(f"║  Throttle cámara : {str(THROTTLE_SEGUNDOS)+'s por IP':<42}║")
    print("╠══════════════════════════════════════════════════════════════╣")
    print("║  Modo A (PHP)    → multipart/form-data campo 'foto'         ║")
    print("║  Modo B (Cámara) → application/json  campo 'imagen' base64  ║")
    print("╠══════════════════════════════════════════════════════════════╣")
    print("║  URL  : http://0.0.0.0:8000                                 ║")
    print("║  Docs : http://localhost:8000/docs                          ║")
    print("╚══════════════════════════════════════════════════════════════╝\n")

    uvicorn.run(app, host="0.0.0.0", port=8000, reload=False)