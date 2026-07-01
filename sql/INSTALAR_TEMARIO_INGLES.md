# Instalar temario de Inglés en el servidor

## Orden de ejecución en phpMyAdmin

1. Asegúrate de que existan las especialidades **ING** e **ING-EXT** y las fases con códigos (`A1-1`, `A1+3`, `PF-1`, etc.).  
   Carga el sistema una vez o ejecuta `fase_sync` vía login.

2. **`nucleo_temario_schema.sql`** — crea tabla `fase_temario_semana` y columnas en `especialidad_fases`.  
   Si alguna columna ya existe, omite el error o comenta esa línea del `ALTER`.

3. **`ingles_temario_seed.sql`** — carga objetivos, habilidades a evaluar y 4 semanas por parcial (puede tardar 1–2 min).

## Regenerar el SQL desde Excel

En tu PC (con el archivo `TEMARIO COMPLETO 2025 (2).xlsx` en Descargas):

```bash
python scripts/generar_temario_ingles_sql.py
```

Sube de nuevo `sql/ingles_temario_seed.sql` al hosting.

## Estructura

| Tabla / campo | Contenido |
|---------------|-----------|
| `especialidad_fases.id_fase` | ID de cada parcial |
| `objetivo_parcial` | Objetivo general del parcial |
| `eval_*` | Habilidades a evaluar (listening, reading, …) |
| `fase_temario_semana` | 4 filas por parcial: vocabulario, gramática, objetivo por semana |
