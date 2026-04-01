# Vamo al Game Mobile

Shell movil base con Ionic Vue + Capacitor.

## Objetivo

- consumir la API de Laravel bajo `/api/v1`
- autenticar por token Sanctum
- registrar cuentas nuevas via API y exigir verificacion de correo
- soportar acceso con Google desde login y registro usando el callback web de Laravel y un handoff hacia token movil
- reutilizar el contrato TypeScript de `../packages/contracts/generated/api.d.ts`
- reutilizar tokens visuales de `../packages/design-tokens/theme.css`

## Pantallas actuales

- landing publico
- login
- registro
- reto 2FA para password o Google cuando aplica
- panel/dashboard autenticado
- ajustes de perfil
- ajustes de seguridad con password y 2FA
- ajustes de apariencia
- health

## Variables

Crea `mobile/.env` desde `mobile/.env.example` cuando necesites cambiar la URL del backend:

```env
VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
```

Para builds Android de produccion, este repo ya deja separado:

- `mobile/.env`: desarrollo local
- `mobile/.env.production`: build del APK apuntando a `https://vamoalgame.com/api/v1`

Vite usa automaticamente `mobile/.env.production` cuando compilas con `npm run build` dentro de `mobile/`, asi que no necesitas editar la URL local para sacar el APK.

El dev server movil queda fijado en `http://localhost:8100`. Si vas a probar Google OAuth desde movil en local, alinea:

- `MOBILE_APP_URL=http://localhost:8100` en el `.env` del backend
- `GOOGLE_REDIRECT_URI=${APP_URL}/auth/google/callback`
- el host que uses en navegador, Google Cloud y Laravel

Si la cuenta tiene 2FA activa, el flujo movil hace esto:

1. login tradicional o retorno de Google
2. respuesta `202` con `challenge_token`
3. pantalla movil de reto 2FA
4. emision del token Sanctum solo despues de validar el codigo o recovery code

## Scripts

- `npm run dev`
- `npm run build`
- `npm run sync`
- `npm run android`
- `npm run ios`

## Android

El proyecto ya incluye la plataforma nativa en [`mobile/android`](./android).

Flujo recomendado:

1. Desde la raiz, compila y sincroniza:

```bash
npm run mobile:build
npm run mobile:sync
```

2. Abre Android Studio:

```bash
npm run mobile:android
```

3. Ejecuta un emulador o dispositivo desde Android Studio.

### Generar APK para pruebas

1. Verifica prerequisitos en Windows:

- Android Studio instalado
- Android SDK instalado
- un JDK compatible disponible desde Android Studio

2. Instala dependencias:

```bash
npm install
npm --prefix mobile install
```

3. Compila el shell movil en modo produccion:

```bash
npm run mobile:build
```

4. Sincroniza el build web dentro del proyecto Android nativo:

```bash
npm run mobile:sync
```

5. Abre Android Studio:

```bash
npm run mobile:android
```

6. En Android Studio espera a que termine el indexado y luego genera el APK:

- menu `Build`
- opcion `Build Bundle(s) / APK(s)`
- opcion `Build APK(s)`

7. Cuando termine, Android Studio mostrara un enlace tipo `locate`. El APK de debug suele quedar en:

```text
mobile/android/app/build/outputs/apk/debug/app-debug.apk
```

8. Instala ese APK en tu celular:

- conecta el telefono por USB con `Depuracion USB` habilitada
- o comparte el archivo por WhatsApp, Drive o correo
- en Android permite instalar apps desde esa fuente si el sistema lo pide

### Generar APK firmado para compartir fuera de pruebas internas

Si luego quieres distribuirlo mas formalmente, no uses el debug APK. En ese caso:

1. En Android Studio ve a `Build > Generate Signed Bundle / APK`
2. Elige `APK`
3. Crea o selecciona tu `keystore`
4. Completa alias, passwords y tipo `release`
5. El APK firmado suele quedar en:

```text
mobile/android/app/build/outputs/apk/release/app-release.apk
```

Prerequisitos locales:

- Android Studio instalado
- Android SDK instalado
- JDK disponible en `PATH` o configurado por Android Studio
- si usas emulador Android y el backend corre en tu PC, usa `10.0.2.2` en lugar de `127.0.0.1`
