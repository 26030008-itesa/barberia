"""
╔══════════════════════════════════════════════════════════════════╗
║         CRYAI — API REST (FastAPI)                              ║
║                                                                  ║
║  Endpoint principal:                                             ║
║    POST /analizar                                                ║
║      Body: multipart/form-data con campo "foto" (imagen)         ║
║      Response: JSON con rostro, cabello y recomendaciones        ║
║                                                                  ║
║  Instalación:                                                    ║
║    pip install fastapi uvicorn python-multipart pillow           ║
║    pip install torch torchvision                                 ║
║                                                                  ║
║  Uso:                                                            ║
║    python api_ia.py                                              ║
║    → Corre en http://localhost:8000                              ║
║    → Docs en  http://localhost:8000/docs                         ║
╚══════════════════════════════════════════════════════════════════╝
"""

import json
import random
import torch
import torch.nn as nn
import uvicorn
from fastapi import FastAPI, File, UploadFile, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from torchvision import models, transforms
from pathlib import Path
from PIL import Image
from io import BytesIO

# ─────────────────────────────────────────────────────────────
# CONFIGURACIÓN DE RUTAS
# ─────────────────────────────────────────────────────────────

BASE_DIR    = Path(__file__).parent
MODELS_DIR  = BASE_DIR / "modelos"
CRYAI_JSON  = BASE_DIR / "dataset_cryai" / "dataset_limpio.json"

DEVICE      = torch.device("cuda" if torch.cuda.is_available() else "cpu")
IMG_SIZE    = 224
MAX_FILE_MB = 5

# ─────────────────────────────────────────────────────────────
# TEXTOS LEGIBLES PARA LA UI DE LA BARBERÍA
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
        print(f"  ⚠️  Modelo '{nombre}' no encontrado — se usará modo simulación")
        return None, None

    with open(ruta_meta, 'r') as f:
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
    checkpoint = torch.load(ruta_pth, map_location=DEVICE, weights_only=True)
    modelo.load_state_dict(checkpoint["model_state"])
    modelo.to(DEVICE)
    modelo.eval()
    print(f"  ✅ {nombre} cargado (val_acc={checkpoint.get('val_acc', 0):.4f}) — {num_clases} clases")
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
    with open(CRYAI_JSON, 'r', encoding='utf-8') as f:
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
# FUNCIONES DE INFERENCIA
# ─────────────────────────────────────────────────────────────

def predecir(modelo, meta, imagen_pil: Image.Image, top_k: int = 3):
    tensor = inferencia_transform(imagen_pil).unsqueeze(0).to(DEVICE)
    with torch.no_grad():
        salida = modelo(tensor)
        probs  = torch.softmax(salida, dim=1)[0]

    top_probs, top_idxs = torch.topk(probs, min(top_k, len(meta["clases"])))
    return [
        {
            "clase":      meta["clases"][idx.item()],
            "nombre":     NOMBRES_ROSTRO.get(meta["clases"][idx.item()],
                          NOMBRES_CABELLO.get(meta["clases"][idx.item()],
                          meta["clases"][idx.item()])),
            "confianza":  round(prob.item() * 100, 1)
        }
        for prob, idx in zip(top_probs.cpu(), top_idxs.cpu())
    ]

def simular_prediccion_rostro():
    """Fallback cuando no hay modelo entrenado."""
    opciones = [
        {"clase": "rostro__oval",        "nombre": "Ovalado",     "confianza": round(random.uniform(45, 75), 1)},
        {"clase": "rostro__round",       "nombre": "Redondo",     "confianza": round(random.uniform(45, 75), 1)},
        {"clase": "rostro__square",      "nombre": "Cuadrado",    "confianza": round(random.uniform(45, 75), 1)},
        {"clase": "rostro__rectangular", "nombre": "Rectangular", "confianza": round(random.uniform(45, 75), 1)},
    ]
    elegida = random.choice(opciones)
    resto   = [o for o in opciones if o != elegida]
    random.shuffle(resto)
    # Ajustar que sumen ~100
    elegida["confianza"] = round(random.uniform(50, 70), 1)
    resto[0]["confianza"] = round(random.uniform(15, 25), 1)
    resto[1]["confianza"] = round(100 - elegida["confianza"] - resto[0]["confianza"] - 5, 1)
    return [elegida] + resto[:2]

def simular_prediccion_cabello():
    opciones = [
        {"clase": "cabello__straight",   "nombre": "Lacio",     "confianza": 0},
        {"clase": "cabello__wavy",       "nombre": "Ondulado",  "confianza": 0},
        {"clase": "cabello__curly",      "nombre": "Rizado",    "confianza": 0},
        {"clase": "cabello__kinky",      "nombre": "Afro",      "confianza": 0},
        {"clase": "cabello__dreadlocks", "nombre": "Dreadlocks","confianza": 0},
    ]
    elegida = random.choice(opciones)
    elegida["confianza"] = round(random.uniform(40, 65), 1)
    resto   = [o for o in opciones if o != elegida]
    random.shuffle(resto)
    resto[0]["confianza"] = round(random.uniform(15, 25), 1)
    resto[1]["confianza"] = round(100 - elegida["confianza"] - resto[0]["confianza"] - 5, 1)
    return [elegida] + resto[:2]

def buscar_recomendaciones(clase_rostro: str, clases_cabello: list, max_r: int = 6):
    if not DATASET:
        return []

    def norm(s):
        return s.lower().replace(" face shape", "").replace(" ", "_").strip()

    slug_rostro  = clase_rostro.replace("rostro__", "")
    slugs_cab    = {c.replace("cabello__", "") for c in clases_cabello}

    scored = []
    for item in DATASET:
        score = 0
        for e in item.get("etiquetas_rostro", []):
            if norm(e) == slug_rostro:
                score += 2
        for e in item.get("etiquetas_cabello", []):
            if norm(e) in slugs_cab:
                score += 1
        if score > 0:
            scored.append((score, item))

    scored.sort(key=lambda x: x[0], reverse=True)

    if scored:
        max_score = scored[0][0]
        top = [i for s, i in scored if s == max_score]
        resto = [i for s, i in scored if s < max_score]
        random.shuffle(top)
        resultados = top + resto
    else:
        resultados = []

    # Serializar solo lo necesario para la web
    salida = []
    for item in resultados[:max_r]:
        salida.append({
            "titulo":    item.get("titulo", "Corte Recomendado"),
            "img_url":   item.get("img_url", ""),
            "page_url":  item.get("page_url", ""),
            "analisis":  item.get("analisis_opcional", "")[:200],
            "etiquetas_rostro":  item.get("etiquetas_rostro", []),
            "etiquetas_cabello": item.get("etiquetas_cabello", []),
        })
    return salida

# ─────────────────────────────────────────────────────────────
# FASTAPI APP
# ─────────────────────────────────────────────────────────────

app = FastAPI(
    title="CRYAI API",
    description="API de recomendación de cortes de cabello por visión computacional",
    version="1.0.0"
)

# CORS: permite que la web PHP (en cualquier origen local) consuma la API
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],      # En producción: reemplazar con el dominio exacto
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
        "servicio": "CRYAI API",
        "modelos": {
            "rostro":  "cargado" if MODELO_ROSTRO  else "simulación",
            "cabello": "cargado" if MODELO_CABELLO else "simulación",
        },
        "dataset_cortes": len(DATASET),
        "docs": "/docs"
    }

@app.get("/health")
def health():
    """Endpoint de salud para verificar que la API está activa."""
    return {"status": "ok", "device": str(DEVICE)}


@app.post("/analizar")
async def analizar(foto: UploadFile = File(...)):
    """
    Analiza una fotografía y devuelve:
    - Forma de rostro detectada (top 3 con confianza)
    - Tipo de cabello detectado (top 3 con confianza)
    - Consejo personalizado
    - Hasta 6 cortes recomendados del dataset
    """
    # ── Validar archivo ──────────────────────────────────────
    if foto.content_type not in ["image/jpeg", "image/png", "image/webp"]:
        raise HTTPException(
            status_code=400,
            detail="Formato no soportado. Usa JPG, PNG o WEBP."
        )

    contenido = await foto.read()

    if len(contenido) > MAX_FILE_MB * 1024 * 1024:
        raise HTTPException(
            status_code=413,
            detail=f"Imagen demasiado grande. Máximo {MAX_FILE_MB}MB."
        )

    try:
        imagen = Image.open(BytesIO(contenido)).convert("RGB")
    except Exception:
        raise HTTPException(status_code=400, detail="No se pudo leer la imagen.")

    # ── Inferencia de Rostro ─────────────────────────────────
    if MODELO_ROSTRO and META_ROSTRO:
        pred_rostro = predecir(MODELO_ROSTRO, META_ROSTRO, imagen, top_k=3)
        modo = "modelo"
    else:
        pred_rostro = simular_prediccion_rostro()
        modo = "simulacion"

    # ── Inferencia de Cabello ────────────────────────────────
    if MODELO_CABELLO and META_CABELLO:
        pred_cabello = predecir(MODELO_CABELLO, META_CABELLO, imagen, top_k=3)
    else:
        pred_cabello = simular_prediccion_cabello()

    # ── Extraer clases top ───────────────────────────────────
    clase_rostro_top  = pred_rostro[0]["clase"]
    clases_cab_top    = [p["clase"] for p in pred_cabello[:3]]

    # ── Recomendaciones ──────────────────────────────────────
    recomendaciones = buscar_recomendaciones(clase_rostro_top, clases_cab_top)

    # ── Respuesta ────────────────────────────────────────────
    return JSONResponse(content={
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
    })


# ─────────────────────────────────────────────────────────────
# MAIN
# ─────────────────────────────────────────────────────────────

if __name__ == "__main__":
    print("\n╔══════════════════════════════════════════════════════════════╗")
    print("║              CRYAI — API REST                               ║")
    print("╚══════════════════════════════════════════════════════════════╝")
    print(f"  🖥️  Device  : {DEVICE}")
    print(f"  📚 Cortes  : {len(DATASET)}")
    print(f"  🌐 URL     : http://localhost:8000")
    print(f"  📖 Docs    : http://localhost:8000/docs")
    print(f"  🔁 CORS    : habilitado para todos los orígenes\n")

    uvicorn.run(app, host="0.0.0.0", port=8000, reload=False)
