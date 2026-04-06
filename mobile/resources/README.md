Coloca aqui el icono fuente para Android e iOS.

Recomendacion base:
- Archivo: `mobile/resources/icon.png`
- Formato: PNG cuadrado
- Tamano: `1024x1024`
- Fondo: preferiblemente transparente, con margen de seguridad para que no se recorte en launchers maskable

Flujo sugerido:
1. Coloca o reemplaza `mobile/resources/icon.png`.
2. Desde la raiz del proyecto ejecuta `npx @capacitor/assets generate --android --assetPath mobile/resources`.
3. Ejecuta `npm run mobile:sync`.
4. Abre Android Studio con `npm run mobile:android` y compila normalmente.

Los iconos generados terminan en `mobile/android/app/src/main/res/mipmap-*`.
