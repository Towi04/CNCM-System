# Poppler (pdftotext) en hosting compartido

HAY usa **dos programas** de Poppler para indexar libros PDF:

| Programa | Para qué |
|----------|----------|
| **pdftotext** | Extraer texto de cada página (Tutor IA) |
| **pdfinfo** | Contar páginas del PDF |

No basta con subir solo `pdftotext`: hacen falta **ambos**.

---

## Importante: no use el .exe de Windows

El servidor compartido casi seguro es **Linux**. El Poppler que descargó en su PC (Windows) **no funcionará** allí.

Necesita binarios **Linux** (misma arquitectura que el hosting: normalmente `x86_64`).

---

## Dónde subir los archivos en HAY

Estructura recomendada dentro del proyecto:

```
hay_system/
  bin/
    poppler/
      pdftotext      ← ejecutable Linux (sin .exe)
      pdfinfo        ← ejecutable Linux
      lib/           ← bibliotecas .so (si no son estáticos)
        libpoppler.so
        libpoppler-cpp.so
        … (otras .so que pida el paquete)
```

HAY busca automáticamente en `bin/poppler/` (relativo a la raíz del proyecto).

O defina la ruta absoluta en `config.local.php`:

```php
/** Ruta absoluta en el servidor, ej. /home/usuario/public_html/hay/bin/poppler */
define('HAY_POPPLER_BIN_DIR', '/home/TU_USUARIO/public_html/hay/bin/poppler');

/** Solo si las .so están en otra carpeta */
define('HAY_POPPLER_LIB_DIR', '/home/TU_USUARIO/public_html/hay/bin/poppler/lib');
```

---

## Permisos (desde el administrador de archivos del hosting)

- Carpeta `bin/poppler`: **755**
- Archivos `pdftotext` y `pdfinfo`: **755** (ejecutables)

En cPanel / File Manager: clic derecho → Change Permissions → marcar "Execute" para owner.

---

## ¿Solo pdftotext o todo Poppler?

| Escenario | Qué subir |
|-----------|-----------|
| Binarios **estáticos** (poco comunes) | Solo `pdftotext` + `pdfinfo` |
| Binarios **normales** (lo usual) | `pdftotext`, `pdfinfo` + carpeta `lib/` con todas las `.so` |
| Hosting ya tiene Poppler | Nada; a veces basta que el hosting lo tenga en PATH |

Si al indexar aparece error tipo `libpoppler.so: cannot open shared object file`, faltan las bibliotecas en `lib/`.

---

## Hosting compartido: otras limitaciones

1. **`shell_exec` deshabilitado**  
   Muchos hostings bloquean `shell_exec` / `exec`. Poppler **no podrá correr** aunque suba los archivos.  
   - Pregunte al soporte: *¿Pueden habilitar shell_exec para mi cuenta o instalar poppler-utils?*  
   - Mientras tanto: los alumnos **sí pueden leer** el PDF en **Mis libros**; solo falla la indexación para el Tutor IA.

2. **Indexar desde su PC** (alternativa sin terminal en el servidor)  
   - En su PC con Poppler: extraiga texto y use `scripts/importar_material_csv.php`  
   - O suba el PDF por el panel **Libros y materiales** y indexe después cuando el hosting permita Poppler.

---

## Cómo comprobar que funciona

1. Suba los binarios a `bin/poppler/` con permisos 755.  
2. Entre al panel **Académico → Libros y materiales**.  
3. Si ya no aparece el aviso *"pdftotext no detectado"*, está listo.  
4. Suba/indexe un libro de prueba.

---

## Resumen rápido

| Pregunta | Respuesta |
|----------|-----------|
| ¿Qué carpeta? | `hay_system/bin/poppler/` |
| ¿Qué archivos? | `pdftotext` + `pdfinfo` (+ `lib/*.so` si aplica) |
| ¿Desde mi PC Windows? | **No** — use binarios Linux para el servidor |
| ¿Solo lectura alumno sin Poppler? | **Sí** — Mis libros funciona igual |
| ¿Tutor IA sin Poppler? | No indexa páginas hasta tener Poppler o importar CSV |
