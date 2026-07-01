# HID Authentication Device Client (U.areU)

## ¿Por qué no va el .exe dentro del código de HAY?

El instalador **no se incluye en Git** ni se recomienda servirlo desde el sitio web de producción:

- HID publica **actualizaciones** en su página oficial; una copia fija quedaría obsoleta.
- Los ejecutables en servidores web generan desconfianza y ocupan mucho espacio.
- El driver debe instalarse **en cada PC Windows de recepción**, no en el hosting PHP.

## Descarga oficial (siempre preferida)

https://digitalpersona.hidglobal.com/lite-client/

En HAY, recepción verá el botón **«Descargar driver HID (oficial)»** en:

- Terminal de checada
- Registrar huella digital
- Pantalla de Asistencias

## Copia local opcional (solo red interna)

Si necesita una copia en USB o red local **sin internet**, puede colocar el instalador aquí:

```
downloads/hid/HID Authentication Device Client.exe
```

Ese archivo **no se sube a Git** (está en `.gitignore`). Si existe en el servidor, HAY muestra un segundo enlace «Copia local CNCM» además del enlace oficial.

## Instalación en recepción

1. Descargar e instalar el **HID Authentication Device Client**.
2. Conectar el lector **U.areU 5300** por USB.
3. Verificar que el icono del cliente aparece en la **bandeja de Windows** (debe estar en ejecución).
4. Usar **Chrome** o **Edge** para HAY.
