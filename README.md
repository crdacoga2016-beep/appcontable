# Sistema de Recibos y Comprobantes

Sistema para registrar compradores, subir comprobantes de transferencia y generar reportes en PDF/Excel.

## Estructura
- `api/` — Backend PHP + MySQL (subir a Hostinger)
- `app/` — App móvil Capacitor (compilar APK con GitHub Actions)
- `.github/workflows/build-apk.yml` — CI para generar el APK

## Backend (Hostinger)
1. Sube todo el contenido de `api/` a tu hosting (ej. `public_html/api/`).
2. Crea una base de datos MySQL y edita `api/config.php` con tus credenciales.
3. Importa el esquema (se crea automáticamente al acceder a `api/index.php?action=init`).
4. Asegúrate de que `api/uploads/` tenga permisos de escritura (755/777).

## App móvil (APK)
1. En tu PC o GitHub, abre `app/`.
2. `npm install`
3. `npx cap add android`
4. `npx cap sync android`
5. `cd android && ./gradlew assembleDebug` → genera `app-debug.apk`
6. O usa el workflow de GitHub Actions para compilar automáticamente al subir a GitHub.

## Uso
- La app permite login, registrar clientes, subir comprobantes (foto + monto + método + fecha) y exportar reportes por cliente en PDF y Excel.