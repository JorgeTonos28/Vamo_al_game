# Vamo al Game

Aplicacion web y movil para la gestion de ligas deportivas. El proyecto parte de una base Laravel 13 con Inertia.js, Vue 3 y TypeScript, y ahora ya incluye una base real para operar cuentas, ligas y acceso multi-tenant.

## Estado actual

El repositorio ya incluye:

- Backend con Laravel 13 y PHP 8.3.
- Frontend web con Vue 3, Inertia.js, TypeScript y Vite.
- API versionada en `routes/api.php` bajo `/api/v1`.
- Autenticacion web con Fortify y autenticacion movil por token con Sanctum.
- Modelo de cuentas con roles globales y membresias por liga.
- Arquitectura multi-tenant para administradores y miembros de ligas.
- Centro de mando exclusivo para administradores generales.
- Flujo de invitaciones por correo con onboarding por password o Google.
- Shell movil en [`mobile/`](./mobile) con Ionic Vue + Capacitor.
- Contrato OpenAPI en [`packages/contracts/openapi.json`](./packages/contracts/openapi.json) y tipos TypeScript generados en [`packages/contracts/generated/api.d.ts`](./packages/contracts/generated/api.d.ts).
- Tokens visuales compartidos en [`packages/design-tokens/theme.css`](./packages/design-tokens/theme.css).
- Base de datos SQLite por defecto para desarrollo local.
- Suite de pruebas con Pest.

La web actual separa el landing promocional del shell operativo. En esta etapa, los usuarios regulares ven `Panel` y `Ajustes`, mientras que los administradores generales ven un Centro de mando independiente.

## Arquitectura dual web + movil

La base de trabajo queda definida asi:

- La web vive en `routes/web.php` y `resources/js`.
- La API vive en `routes/api.php` y expone endpoints bajo `/api/v1`.
- Los controladores web viven en `app/Http/Controllers/Web`.
- Los controladores API viven en `app/Http/Controllers/Api/V1`.
- La validacion va por `FormRequest` en `app/Http/Requests/Web` y `app/Http/Requests/Api`.
- La salida JSON de la API se normaliza con `API Resources` y un envelope consistente: `success`, `message`, `data`, `errors`, `meta`.
- La logica reusable de negocio se concentra en `app/Actions` y `app/Services`.
- La app movil en `mobile/` es cliente de la API. No replica reglas de negocio.
- El contrato compartido vive en `packages/contracts`.

### Endpoints base disponibles

- `GET /api/v1/health`
- `GET /api/v1/branding`
- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/google/exchange`
- `POST /api/v1/auth/two-factor-challenge`
- `POST /api/v1/auth/logout`
- `GET /api/v1/me`
- `PATCH /api/v1/me/active-league`
- `GET /api/v1/command-center/dashboard`
- `GET|POST /api/v1/command-center/users`
- `GET /api/v1/command-center/leagues`
- `PATCH /api/v1/command-center/leagues/{league}`
- `GET|POST /api/v1/command-center/settings`
- `PATCH|DELETE /api/v1/settings/profile`
- `PUT /api/v1/settings/password`
- `POST /api/v1/settings/email/verification-notification`
- `GET|POST|DELETE /api/v1/settings/two-factor`
- `GET /api/v1/users/{user}` como recurso protegido de ejemplo con policy

## Stack principal

- PHP 8.3+
- Laravel 13
- Sanctum
- Inertia.js
- Vue 3 + TypeScript
- Vite 8
- Tailwind CSS 4
- Ionic Vue 8
- Capacitor 8
- Pest
- SQLite por defecto en desarrollo

## Requisitos

- PHP 8.3 o superior
- Composer
- Node.js 22+ con npm
- SQLite disponible localmente

## Puesta en marcha

1. Instala dependencias de backend:

```bash
composer install
```

2. Instala dependencias del frontend web y del tooling del monorepo:

```bash
npm install
```

3. Instala dependencias de la app movil:

```bash
npm --prefix mobile install
```

4. Crea el archivo de entorno si aun no existe:

```bash
cp .env.example .env
```

En Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

5. Genera la clave de aplicacion:

```bash
php artisan key:generate
```

6. Asegura la base SQLite local:

```bash
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
```

En Windows PowerShell:

```powershell
if (-not (Test-Path database\database.sqlite)) { New-Item database\database.sqlite -ItemType File | Out-Null }
```

7. Regenera los tipos del contrato compartido:

```bash
npm run contracts:generate
```

8. Ejecuta migraciones y seeders:

```bash
php artisan migrate --seed
```

Para actualizaciones locales sin resetear la base:

```bash
php artisan migrate
php artisan db:seed
```

9. Inicia backend + web:

```bash
composer run dev
```

10. En otra terminal, inicia la app movil:

```bash
npm run mobile:dev
```

El comando `composer run dev` levanta el servidor Laravel, el listener de colas y Vite del frontend web. La app movil corre por separado desde `mobile/`.

## Seeders seguros y despliegue

El proyecto ya no mezcla datos estructurales con datos demo dentro de `DatabaseSeeder`.

- `DatabaseSeeder` ahora solo ejecuta seeders seguros para actualizaciones, como defaults estructurales requeridos por la app.
- Los usuarios demo y las ligas starter viven en `LocalStarterDataSeeder`.
- El switch se controla con `APP_ENABLE_STARTER_DATA`.
- En local, `APP_ENABLE_STARTER_DATA=true` permite mantener cuentas y ligas de prueba al correr `php artisan migrate --seed`.
- En produccion, deja `APP_ENABLE_STARTER_DATA=false` para evitar insertar demos en cada despliegue.

Flujo recomendado en terminal:

- Desarrollo local con starter data:

```bash
php artisan migrate
php artisan db:seed
```

- Cargar solo los datos demo de forma explicita:

```bash
php artisan db:seed --class=LocalStarterDataSeeder
```

- Actualizacion segura en produccion:

```bash
php artisan migrate --force
php artisan db:seed --force
```

Notas operativas:

- No uses `migrate:fresh --seed` para actualizar una base con datos reales; ese comando elimina todas las tablas y recrea la base desde cero.
- Los comentarios operativos del approach viven en `config/starter-data.php` y `database/seeders/DatabaseSeeder.php`.

## Cuentas, roles y tenancy

La autenticacion queda separada por canal:

- Web: sesion con Fortify en las rutas tradicionales.
- Movil/API: Bearer token con Sanctum en `/api/v1/auth/*`.

### Roles globales

- `general_admin`: entra solo al Centro de mando y no usa la app operativa regular.
- `league_admin`: entra a la app regular y puede cambiar entre las ligas activas que administra.
- `member`: entra a la app regular y puede cambiar entre las ligas activas donde es miembro.
- `guest`: entra a la app regular en modo invitado si no pertenece a ninguna liga; puede luego convertirse en miembro o administrador dentro de una liga especifica.

### Modelo multi-tenant

- El rol global vive en `users.account_role` y define el acceso base al sistema.
- Los roles por liga viven en `league_memberships.role`.
- Una misma cuenta puede ser administrador en una liga y miembro en otra.
- La liga activa vive en `users.active_league_id`.
- `GET /api/v1/me` y `PATCH /api/v1/me/active-league` exponen el contexto tenant para web y movil.
- Los invitados sin membresias no hacen switch de liga y operan en modo personal.
- El switch de ligas muestra tambien ligas con acceso revocado para que el usuario entienda su estado actual.
- Si un usuario tiene membresias pero todas sus ligas estan inactivas, la app regular muestra:

```text
Ups! Lo sentimos, ha ocurrido un problema accediendo a la app. Comuniquese con la administracion para mas detalles.
```

### Centro de mando para administradores generales

Los administradores generales acceden a un shell separado de la app regular. Por ahora incluye:

- `Panel`: metricas basicas del sistema como usuarios totales, ligas activas, administradores de ligas, miembros, invitados y ligas inactivas.
- `Usuarios`: formulario para invitar nuevos usuarios con nombre, apellido, cedula, telefono, direccion, correo y rol.
- `Ligas`: listado de ligas, administradores, cantidad de miembros, fecha de creacion y toggle de acceso.
- `Ajustes`: misma estructura de cuenta del shell regular (`Perfil`, `Seguridad` y `Apariencia`), con el branding global de logo y favicon integrado dentro de `Apariencia`.

### Invitaciones y onboarding

- Los administradores generales crean usuarios desde `Usuarios`.
- Si el rol no se define, la cuenta se crea como `guest`.
- Si el rol seleccionado es `league_admin` o `member`, el formulario exige una `Liga inicial`.
- Esa `Liga inicial` crea de inmediato la primera fila en `league_memberships` y tambien define `users.active_league_id`.
- El sistema envia un correo con enlace para completar onboarding por password o continuar con Google.
- Durante el onboarding, el usuario puede completar o corregir los datos basicos precargados.
- Mientras la invitacion siga pendiente, la cuenta no puede iniciar sesion ni usar recuperacion de contrasena fuera del flujo de aceptacion.
- Al terminar el onboarding, la cuenta queda habilitada segun su rol global y sus membresias activas.
- Si el enlace de invitacion con Google falla por token expirado o por elegir otra cuenta, web y movil redirigen de vuelta al login con el error normalizado.
- Los correos usan el branding configurado por los administradores generales cuando existe un logo personalizado.

### Cuentas seed locales

Cuando `APP_ENABLE_STARTER_DATA=true`, `LocalStarterDataSeeder` crea o actualiza estas cuentas de prueba ya verificadas:

- `admingentest@vamoalgame.com` / `TestUSER12345678`
- `adminleaguetest@vamoalgame.com` / `TestUSER12345678`
- `membertest@vamoalgame.com` / `TestUSER12345678`
- `guestest@vamoalgame.com` / `TestUSER12345678`

Ademas, el seeder crea ligas de muestra y membresias para probar el contexto multi-tenant localmente.

### Reglas de acceso vigentes

- Registro tradicional con email y password.
- Registro movil por API con verificacion de correo obligatoria antes del primer login.
- Verificacion obligatoria de correo antes de usar la app web o de recibir token movil.
- Acceso web con Google mediante Socialite.
- Acceso movil con Google mediante handoff OAuth y token Sanctum.
- Si la cuenta tiene 2FA activa, el login movil por password o por Google devuelve un reto 2FA antes de emitir el token.
- Si la administracion general desactiva la liga que el usuario tenia seleccionada, el sistema intenta moverlo automaticamente a otra liga activa donde tenga membresia.
- Si el usuario selecciona manualmente una liga revocada desde el switch, la app mantiene esa seleccion y muestra la pantalla de acceso no disponible hasta que cambie a otra liga valida.

## Flujo movil minimo

1. `POST /api/v1/auth/register` si la cuenta aun no existe.
2. Verificar correo desde el enlace enviado por Laravel.
3. `POST /api/v1/auth/login`.
4. Si la cuenta usa 2FA, completar `POST /api/v1/auth/two-factor-challenge`.
5. Guardar `data.token`.
6. Enviar `Authorization: Bearer <token>`.
7. Consultar `GET /api/v1/me` para obtener perfil y contexto tenant.
8. Cambiar de liga con `PATCH /api/v1/me/active-league` cuando aplique.
9. Si la cuenta es `general_admin`, consumir el shell mobile del Centro de mando con `GET /api/v1/command-center/*`.
10. Si la liga activa fue revocada, la app movil muestra la misma pantalla de acceso no disponible que web y conserva el switch de ligas.
11. Cerrar sesion con `POST /api/v1/auth/logout`.

Al abrir la app movil, el flujo visible arranca con un starter breve de marca y luego entra directo a `Login` o al shell autenticado segun exista sesion. Los administradores generales ven el Centro de mando mobile con `Panel`, `Usuarios`, `Ligas` y `Ajustes`. Los administradores de ligas, miembros e invitados ven el shell regular con `Panel` y `Ajustes`, branding compartido, hamburger menu y switch multi-tenant de ligas.

Variables relevantes para web y movil:

- `WEB_APP_URL`
- `MOBILE_APP_URL`
- `MOBILE_API_URL`
- `SANCTUM_EXPIRATION`
- `SANCTUM_TOKEN_PREFIX`
- `CORS_ALLOWED_ORIGINS`
- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`
- `GOOGLE_REDIRECT_URI`

## Correos en local

En local, la configuracion recomendada queda orientada a:

- `MAIL_MAILER=failover`
- SMTP local en `127.0.0.1:1025`
- Fallback automatico a `log` si no hay servidor SMTP disponible

Con esto, si tienes un capturador local como Mailpit levantado, veras los correos en una bandeja web. Si no esta levantado, Laravel no rompe el flujo y deja el mensaje en:

- `storage/logs/laravel.log`

### Opcion recomendada: Mailpit

Si tienes Docker, puedes levantar Mailpit asi:

```bash
docker run --rm -d --name vamo-mailpit -p 1025:1025 -p 8025:8025 axllent/mailpit
```

Luego abre [http://127.0.0.1:8025](http://127.0.0.1:8025).

Y en tu `.env` local usa:

```env
MAIL_MAILER=failover
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="no-reply@vamoalgame.local"
MAIL_FROM_NAME="${APP_NAME}"
```

Despues limpia configuracion:

```bash
php artisan config:clear
```

Si no vas a usar Mailpit todavia, puedes dejar `MAIL_MAILER=log` y revisar el enlace en `storage/logs/laravel.log`.

## Correos en servidor con cPanel

Si despliegas la app en un hosting con cPanel, lo normal es usar una cuenta SMTP creada dentro del mismo panel, por ejemplo `no-reply@tu-dominio.com`.

### Paso 1: Crear la cuenta de correo en cPanel

1. Entra a cPanel.
2. Ve a `Email Accounts`.
3. Crea la cuenta que usara la app, por ejemplo `no-reply@tu-dominio.com`.
4. Guarda la contrasena del buzon.

### Paso 2: Consultar los datos SMTP en cPanel

En la cuenta creada, abre `Connect Devices` o la seccion equivalente. cPanel suele mostrar:

- Servidor SMTP
- Puerto SMTP
- Tipo de cifrado
- Usuario completo

Valores habituales:

- Host SMTP: `mail.tu-dominio.com`
- Puerto `465` con `ssl`
- O puerto `587` con `tls`
- Usuario: el correo completo, por ejemplo `no-reply@tu-dominio.com`

### Paso 3: Configurar las variables en el servidor

En el `.env` del servidor, deja algo como esto:

```env
MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=mail.tu-dominio.com
MAIL_PORT=587
MAIL_USERNAME=no-reply@tu-dominio.com
MAIL_PASSWORD=tu-password-smtp
MAIL_FROM_ADDRESS=no-reply@tu-dominio.com
MAIL_FROM_NAME="${APP_NAME}"
```

Si tu hosting te exige SSL implicito en lugar de TLS, usa:

```env
MAIL_SCHEME=ssl
MAIL_PORT=465
```

### Paso 4: Aplicar configuracion en Laravel

Despues de actualizar variables en el servidor:

```bash
php artisan config:clear
php artisan config:cache
```

### Paso 5: Verificar envio real

Prueba uno de estos flujos:

- Registro de usuario nuevo
- Invitacion de usuario desde el Centro de mando
- Reenvio de verificacion de correo
- Recuperacion de contrasena

Si falla, revisa:

- `storage/logs/laravel.log`
- Que el puerto SMTP este permitido por el hosting
- Que usuario y password del buzon sean correctos
- Que `MAIL_FROM_ADDRESS` coincida con una cuenta valida del dominio

### Recomendaciones de entregabilidad en cPanel

Para reducir que los correos caigan en spam, revisa tambien:

- SPF configurado
- DKIM configurado
- DMARC configurado si ya manejas correo del dominio

En muchos hostings con cPanel, SPF y DKIM se activan desde `Email Deliverability`.

## Configurar acceso con Google

Si quieres que el login con Google funcione, hay configuracion externa obligatoria. El codigo ya queda listo en el proyecto, pero Google exige credenciales OAuth validas.

Para este flujo basico con `openid`, `email` y `profile`, normalmente no necesitas habilitar una API extra en Google Cloud. Lo importante es tener bien configurados la pantalla de consentimiento OAuth, el cliente OAuth y las redirect URIs exactas.

Paso a paso:

1. Entra a [Google Cloud Console](https://console.cloud.google.com/).
2. Crea un proyecto nuevo o usa uno existente para `Vamo al Game`.
3. Ve a `APIs y servicios` -> `Pantalla de consentimiento OAuth`.
4. Configura la app:
   - Tipo: `External` si la usaran cuentas personales de Google.
   - Nombre de la app, correo de soporte y dominio si aplica.
   - Agrega tu correo como usuario de prueba mientras la app no este publicada.
5. Luego ve a `APIs y servicios` -> `Credenciales`.
6. Crea una credencial de tipo `ID de cliente OAuth`.
7. Elige `Aplicacion web`.
8. En `URIs de redireccion autorizados` agrega:
   - `http://localhost:8000/auth/google/callback` si ese es tu `APP_URL`
   - `http://127.0.0.1:8000/auth/google/callback` si abres la app asi
   - La URL real de tu app en produccion, por ejemplo `https://tu-dominio.com/auth/google/callback`
9. En `Origenes autorizados de JavaScript` agrega los origenes base equivalentes:
   - `http://localhost:8000`
   - `http://127.0.0.1:8000`
   - Tu dominio real en produccion si aplica
10. Copia el `Client ID` y el `Client Secret`.
11. Pegalos en tu `.env`:

```env
GOOGLE_CLIENT_ID=tu-client-id
GOOGLE_CLIENT_SECRET=tu-client-secret
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

12. Verifica que `APP_URL` coincida exactamente con la URL real desde la que abres la app.
13. No mezcles `localhost` y `127.0.0.1`: el navegador, `APP_URL`, `GOOGLE_REDIRECT_URI` y Google Cloud deben usar el mismo host.
14. Limpia cache de configuracion si cambias variables:

```bash
php artisan config:clear
```

15. Si usas produccion, registra esas mismas variables como secrets o variables de entorno del servidor.

Notas importantes:

- Si la URI de callback no coincide exactamente con la configurada en Google, el login fallara.
- Si el host cambia entre `localhost` y `127.0.0.1`, puedes romper el `state` de OAuth o la sesion del navegador.
- Si quieres probar Google desde el shell movil en local, `MOBILE_APP_URL` debe apuntar al host y puerto reales del frontend movil, por defecto `http://localhost:8100`.
- Si la app esta en modo testing en Google, solo podran entrar usuarios agregados como testers.
- Las cuentas creadas con Google tambien quedan obligadas a verificar email antes de entrar al sistema.

## Scripts utiles

- `composer run dev`: servidor Laravel + queue listener + Vite web
- `php artisan test`: suite de pruebas backend, web y API
- `npm run build`: build de frontend web
- `npm run contracts:generate`: regenera tipos TypeScript desde `packages/contracts/openapi.json`
- `npm run mobile:dev`: levanta el shell movil Ionic en desarrollo
- `npm run mobile:build`: build del shell movil
- `npm run mobile:android`: abre la plataforma Android en Android Studio
- `npm run mobile:ios`: abre la plataforma iOS en Xcode
- `npm run mobile:sync`: sincroniza Capacitor
- `npm run mobile:typecheck`: validacion de tipos del shell movil
- `npm run lint`: corrige problemas ESLint cuando es posible
- `npm run format`: formatea `resources/` con Prettier
- `npm run types:check`: validacion de tipos Vue y TypeScript del frontend web

## Estructura base

- `app/Actions`: acciones reutilizables del backend
- `app/Http/Controllers/Web`: controladores para la web Inertia
- `app/Http/Controllers/Api/V1`: controladores para la API movil
- `app/Http/Requests/Web`: validacion web
- `app/Http/Requests/Api`: validacion API
- `app/Http/Resources`: transformadores JSON para la API
- `app/Policies`: autorizacion
- `database/migrations`: esquema de base de datos
- `database/seeders`: datos iniciales
- `resources/js/pages`: paginas Inertia y Vue de la web
- `resources/js/components`: componentes compartidos web
- `mobile/`: app Ionic Vue + Capacitor
- `packages/contracts`: contrato OpenAPI y tipos generados
- `packages/design-tokens`: tokens visuales compartidos
- `routes/web.php`: rutas web
- `routes/api.php`: API versionada `/api/v1`
- `tests/Feature`: pruebas funcionales
- `tests/Unit`: pruebas unitarias

## Direccion del producto

La meta de `Vamo al Game` es evolucionar hacia una app para:

- Gestionar ligas y temporadas
- Registrar equipos, jugadores y staff
- Crear calendarios y jornadas
- Registrar resultados, estadisticas y standings
- Administrar roles operativos por liga y a nivel sistema
- Centralizar configuraciones, comunicacion y reportes

## Calidad y convenciones

- Todo cambio que altere funcionalidad, setup, dependencias o flujos tecnicos debe reflejarse en este `README.md`.
- Si se modifica el esquema de datos, deben incluirse migraciones y ajustes de seeders cuando aplique.
- Los cambios visuales deben validarse en movil y desktop.
- Antes de cerrar una entrega, ejecuta como minimo las validaciones relevantes del cambio: pruebas, lint, types o build.

## Troubleshooting rapido

- Si la app no inicia, verifica que `.env` exista y que `APP_KEY` haya sido generada.
- Si falla la base de datos, confirma que `database/database.sqlite` exista y que la conexion en `.env` siga apuntando a SQLite.
- Si los assets no cargan, reinicia `npm run dev` o ejecuta `npm run build`.
- Si una migracion nueva falla, prueba `php artisan migrate:fresh --seed` solo en entornos locales descartables.
- Si un usuario regular ve la pantalla de indisponibilidad, revisa si sus ligas activas fueron deshabilitadas desde el Centro de mando.
- Si Google OAuth falla en Windows con errores SSL, descarga un `cacert.pem` confiable, guardalo por ejemplo en `C:\php\extras\ssl\cacert.pem` y configura en `C:\php\php.ini`:

```ini
curl.cainfo="C:\php\extras\ssl\cacert.pem"
openssl.cafile="C:\php\extras\ssl\cacert.pem"
```

- Luego reinicia tu terminal, ejecuta `php artisan config:clear` y valida la salida con:

```bash
php -r "echo file_get_contents('https://www.googleapis.com/oauth2/v3/certs') !== false ? 'ok' : 'fail';"
```

## Licencia

Este proyecto se distribuye bajo la licencia MIT, salvo que el equipo defina una politica distinta mas adelante.
