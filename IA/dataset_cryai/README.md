# Dataset CRYAI — Recomendador de Cortes de Cabello

## Estadísticas
- Total muestras: 710
- Total clases  : 17
- Split train   : 80%
- Split val     : 20%

## Estructura
```
dataset_cryai/
├── images/          ← Imágenes originales descargadas
├── train/           ← Carpetas por clase (80%)
│   ├── rostro__oval/
│   ├── rostro__round/
│   ├── cabello__curly/
│   └── ...
├── val/             ← Carpetas por clase (20%)
├── dataset_limpio.json
├── labels.csv
└── class_map.json
```

## Clases disponibles
### Forma de Rostro
- rostro__diamond_face_shape
- rostro__long
- rostro__long_face_shape
- rostro__oval_face_shape
- rostro__rectangle_face_shape
- rostro__round_face_shape
- rostro__square_face_shape
- rostro__triangle_face_shape

### Tipo/Textura de Cabello
- cabello__curly
- cabello__fine
- cabello__medium
- cabello__normal
- cabello__short
- cabello__straight
- cabello__texture
- cabello__thick
- cabello__wavy

## Carga con PyTorch (ImageFolder — clasificación simple)
```python
from torchvision import datasets, transforms

transform = transforms.Compose([
    transforms.Resize((224, 224)),
    transforms.ToTensor(),
    transforms.Normalize([0.485, 0.456, 0.406],
                         [0.229, 0.224, 0.225])
])

train_ds = datasets.ImageFolder("dataset_cryai/train", transform=transform)
val_ds   = datasets.ImageFolder("dataset_cryai/val",   transform=transform)
```

## Carga con pandas (multi-etiqueta)
```python
import pandas as pd
df = pd.read_csv("dataset_cryai/labels.csv")
# Columnas clave: etiquetas_rostro, etiquetas_cabello, clases_idx, imagen_local
```
