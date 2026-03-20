# Vamo al Game

Aplicacion web para la gestion de ligas deportivas. El proyecto parte de una base Laravel 13 con Inertia.js, Vue 3 y TypeScript, y servira como plataforma para administrar ligas, equipos, temporadas, partidos, tablas de posiciones y la operacion diaria alrededor de una competencia.

## Estado actual

El repositorio esta en una fase inicial. Hoy incluye:

- Backend con Laravel 13 y PHP 8.3.
- Frontend con Vue 3, Inertia.js, TypeScript y Vite.
- Tailwind CSS 4 y componentes UI basados en `shadcn-vue`.
- Autenticacion con Laravel Fortify.
- Base de datos SQLite por defecto para desarrollo local.
- Suite de pruebas con Pest.

Por ahora el proyecto conserva pantallas base del starter kit, como bienvenida, autenticacion, dashboard y configuracion de perfil/seguridad.

## Stack principal

- PHP 8.3+
- Laravel 13
- Inertia.js
- Vue 3 + TypeScript
- Vite 8
- Tailwind CSS 4
- Pest
- SQLite por defecto en desarrollo

## Requisitos

- PHP 8.3 o superior
- Composer
- Node.js 20+ con npm
- SQLite disponible localmente

## Puesta en marcha

1. Instala dependencias de backend:

```bash
composer install
```

2. Instala dependencias de frontend:

```bash
npm install
```

3. Crea el archivo de entorno si aun no existe:

```bash
cp .env.example .env
```

En Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

4. Genera la clave de aplicacion:

```bash
php artisan key:generate
```

5. Asegura la base SQLite local:

```bash
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
```

En Windows PowerShell:

```powershell
if (-not (Test-Path database\database.sqlite)) { New-Item database\database.sqlite -ItemType File | Out-Null }
```

6. Ejecuta migraciones y seeders:

```bash
php artisan migrate --seed
```

7. Inicia el entorno de desarrollo:

```bash
composer run dev
```

Ese comando levanta el servidor Laravel, el listener de colas y Vite en paralelo.

## Usuario de prueba

El `DatabaseSeeder` actual crea un usuario inicial:

- Email: `test@example.com`
- Password: define una mediante registro normal o ajusta el seeder si necesitas credenciales fijas para desarrollo

Nota: el starter actual crea el usuario de ejemplo sin contrasena explicita en el seeder. Si vas a depender de una cuenta demo, conviene endurecer ese flujo antes de compartir entornos.

## Scripts utiles

- `composer run dev`: servidor Laravel + queue listener + Vite
- `composer run test`: limpia config, valida formato PHP y ejecuta tests
- `composer run ci:check`: lint, format check, types check y pruebas
- `npm run dev`: servidor Vite
- `npm run build`: build de frontend
- `npm run lint`: corrige problemas ESLint cuando es posible
- `npm run format`: formatea `resources/` con Prettier
- `npm run types:check`: validacion de tipos Vue/TypeScript

## Estructura base

- `app/`: logica de dominio, modelos, acciones HTTP y servicios
- `database/migrations`: esquema de base de datos
- `database/seeders`: datos iniciales
- `resources/js/pages`: paginas Inertia/Vue
- `resources/js/components`: componentes compartidos
- `routes/`: rutas web y configuracion relacionada
- `tests/Feature`: pruebas funcionales
- `tests/Unit`: pruebas unitarias

## Direccion del producto

La meta de `Vamo al Game` es evolucionar hacia una app para:

- Gestionar ligas y temporadas
- Registrar equipos, jugadores y staff
- Crear calendarios y jornadas
- Registrar resultados, estadisticas y standings
- Asignar roles operativos a administradores y organizadores
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

## Licencia

Este proyecto se distribuye bajo la licencia MIT, salvo que el equipo defina una politica distinta mas adelante.
