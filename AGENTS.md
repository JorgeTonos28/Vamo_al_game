# AGENTS.md

Guia operativa para agentes que contribuyen en Vamo al Game.

## 1) Migraciones, esquema y seeders (obligatorio)

Si tus cambios impactan el modelo de datos o la persistencia, debes:

- Generar las migraciones de Laravel necesarias.
- Ajustar `database/seeders/DatabaseSeeder.php` y los seeders relacionados cuando aplique.
- Mantener coherencia entre modelos, migraciones, factories, validaciones y reglas de negocio.
- Garantizar que el proyecto pueda arrancar sin pasos manuales inesperados una vez configurado el entorno.

Objetivo: que, tras instalar dependencias y ejecutar `php artisan migrate --seed`, la base quede lista para trabajar en local.

Si agregas o cambias entidades, relaciones, constraints, indices, enums, casts o defaults, revisa tambien:

- Modelos en `app/Models`
- Factories en `database/factories`
- Validaciones en requests, controllers o actions
- Pruebas afectadas

## 2) Seguridad, vulnerabilidades e integridad de datos (obligatorio)

Todo cambio debe considerar como minimo:

- Autenticacion y autorizacion correctas con middleware, policies, gates o checks de rol.
- No exponer secretos, tokens, credenciales ni datos sensibles en el repositorio, logs o respuestas.
- Validacion server-side siempre; la validacion client-side solo complementa.
- Proteccion frente a asignacion masiva, acceso indebido a recursos y fuga de informacion entre ligas, equipos o usuarios.
- Integridad de datos con foreign keys, indices unicos, constraints y reglas de negocio consistentes.
- Manejo de errores y logging util sin filtrar informacion sensible.
- Dependencias seguras y compatibles, evitando regresiones por upgrades o downgrades arbitrarios.

En Laravel/Vue esto implica prestar atencion especial a:

- Requests validados o validacion explicita en controllers/actions.
- Politicas de acceso cuando una accion opera sobre recursos de negocio.
- Props de Inertia: compartir solo los datos necesarios.
- Formularios Vue: no confiar en restricciones del frontend como unico control.

## 3) README siempre actualizado (obligatorio)

Si tu cambio altera funcionalidades, flujos tecnicos, configuracion, despliegue, dependencias, variables de entorno o setup:

- Actualiza `README.md` en la misma entrega.
- Elimina o corrige documentacion obsoleta.
- Incluye pasos de ejecucion, configuracion y troubleshooting cuando corresponda.

## 4) Setup script recomendado para sandbox/CI

Usa este script como baseline en entornos Linux limpios para reducir fallos de setup, dependencias PHP/Node y capturas UI.

```bash
#!/usr/bin/env bash
set -Eeuo pipefail

export DEBIAN_FRONTEND=noninteractive

log() { printf "\n[%s] %s\n" "$(date +'%H:%M:%S')" "$*"; }

# -----------------------------
# 0) Utilidades base
# -----------------------------
if command -v apt-get >/dev/null 2>&1; then
  log "Instalando utilidades base..."
  sudo apt-get update -y || true
  sudo apt-get install -y --no-install-recommends \
    ca-certificates curl wget git unzip jq sqlite3 \
    libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 libxkbcommon0 \
    libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libgbm1 libasound2 \
    libx11-xcb1 libxshmfence1 libxext6 libx11-6 libglib2.0-0 libnspr4 \
    xvfb fonts-liberation fonts-dejavu-core fonts-noto-color-emoji || true
fi

# -----------------------------
# 1) PHP 8.3 + Composer
# -----------------------------
if ! command -v php >/dev/null 2>&1; then
  log "Instalando PHP CLI y extensiones comunes..."
  sudo apt-get install -y --no-install-recommends \
    php8.3-cli php8.3-mbstring php8.3-xml php8.3-sqlite3 php8.3-curl \
    php8.3-zip php8.3-bcmath php8.3-intl || true
fi

if ! command -v composer >/dev/null 2>&1; then
  log "Instalando Composer..."
  curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
  php /tmp/composer-setup.php --install-dir="$HOME/.local/bin" --filename=composer
  export PATH="$HOME/.local/bin:$PATH"
fi

log "php version: $(php -v | head -n 1 || echo 'N/A')"
log "composer version: $(composer --version || echo 'N/A')"

# -----------------------------
# 2) Node.js + npm
# -----------------------------
if ! command -v node >/dev/null 2>&1; then
  log "Instalando Node.js 22..."
  curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
  sudo apt-get install -y nodejs || true
fi

log "node version: $(node --version || echo 'N/A')"
log "npm version: $(npm --version || echo 'N/A')"

# -----------------------------
# 3) Restore backend/frontend
# -----------------------------
if [ ! -f composer.json ]; then
  log "ERROR: no se encontro composer.json"
  exit 1
fi

if [ ! -f package.json ]; then
  log "ERROR: no se encontro package.json"
  exit 1
fi

log "Instalando dependencias Composer..."
composer install --no-interaction --prefer-dist

log "Instalando dependencias npm..."
npm install

if [ -d mobile ] && [ -f mobile/package.json ]; then
  log "Instalando dependencias npm de mobile..."
  npm --prefix mobile install
fi

# -----------------------------
# 4) Entorno Laravel
# -----------------------------
if [ ! -f .env ]; then
  log "Creando .env desde .env.example..."
  cp .env.example .env
fi

php artisan key:generate --ansi || true

if [ ! -f database/database.sqlite ]; then
  log "Creando database/database.sqlite..."
  mkdir -p database
  touch database/database.sqlite
fi

log "Ejecutando migraciones y seeders..."
php artisan migrate --seed --force

# -----------------------------
# 5) Build de frontend
# -----------------------------
if [ -f packages/contracts/openapi.json ]; then
  log "Regenerando tipos de contrato..."
  npm run contracts:generate
fi

log "Compilando assets..."
npm run build

if [ -d mobile ] && [ -f mobile/package.json ]; then
  log "Compilando shell mobile..."
  npm run mobile:build
fi

# -----------------------------
# 6) Playwright opcional para screenshots
# -----------------------------
if command -v python3 >/dev/null 2>&1; then
  log "Configurando Playwright para screenshots..."
  python3 -m pip install --user --upgrade pip >/dev/null 2>&1 || true
  python3 -m pip install --user playwright >/dev/null 2>&1 || true
  python3 -m playwright install chromium >/dev/null 2>&1 || true
  python3 -m playwright install-deps chromium >/dev/null 2>&1 || true
fi

log "Setup sandbox completado."
```

Notas para screenshots en sandbox:

- Verifica que Laravel y Vite esten levantados antes de capturar.
- Si el navegador headless falla, prueba `xvfb-run`.
- No cambies herramientas de captura sin necesidad justificada.

## 5) Checklist minimo antes de cerrar una entrega

- Si hubo cambios de esquema: migracion, seeder, factory y pruebas coherentes.
- Si se tocaron aspectos funcionales o tecnicos: `README.md` actualizado.
- Si el cambio impacta flujos de negocio, payloads, endpoints, auth, navegacion principal o UI compartible: se evaluo impacto en movil y se implemento o se justifico por que no aplica.
- Se validaron seguridad, autorizacion e integridad de datos.
- El cambio compila o corre en el entorno objetivo.
- Se ejecutaron las validaciones relevantes del cambio.
- Si hubo UI modificada: se reviso responsive y, cuando aplique, se intento evidencia visual.

## 6) Responsividad UI/UX (obligatorio en cambios visuales)

Si modificas layout, estilos, componentes visuales o experiencia de usuario:

- El diseno debe responder correctamente en movil, tablet, laptop y desktop.
- Verifica que no existan desbordes horizontales, solapamientos, dialogos inaccesibles ni tablas o formularios fuera del viewport.
- Aprovecha utilidades responsive de Tailwind y componentes accesibles del sistema UI existente.
- Manten consistencia con Inertia/Vue: estados de carga, errores, vacios y feedback visual deben funcionar tambien en pantallas reducidas.
- Documenta en la entrega que validaciones responsive realizaste y, si aplica, adjunta screenshots.

## 7) Arquitectura dual web + movil (obligatorio)

Este repositorio debe tratarse por defecto como una plataforma dual: web + movil.

Regla operativa principal:

- No esperes a que el usuario recuerde mencionar movil en cada sesion.
- Si un cambio razonablemente deberia impactar movil, debes tomarlo en cuenta automaticamente.
- Solo puedes omitir el impacto movil cuando el cambio sea claramente exclusivo de web, exclusivo de infraestructura interna o el usuario indique explicitamente que no desea tocar movil en esa entrega.

Asuncion por defecto para cambios futuros:

- Todo modulo de negocio nuevo debe nacer backend-first y mobile-ready.
- Web y movil consumen la misma logica de negocio del backend.
- El movil no replica reglas de negocio; consume la API.

Esto implica:

- Mantener la web en `routes/web.php` y `resources/js`.
- Mantener la API en `routes/api.php` bajo `/api/v1`.
- Mantener separacion entre controladores web y API:
  - `app/Http/Controllers/Web`
  - `app/Http/Controllers/Api/V1`
- Mantener validaciones con `FormRequest` para web/API cuando aplique:
  - `app/Http/Requests/Web`
  - `app/Http/Requests/Api`
- Mantener la logica reusable fuera de controladores/componentes:
  - `app/Actions`
  - `app/Services`
  - `app/Domain` cuando el modulo lo justifique
- Mantener `Policies` para autorizacion de recursos de negocio.
- Mantener `API Resources` para respuestas JSON.
- Mantener el formato JSON consistente del proyecto para exito/error/meta.
- Mantener el contrato compartido en `packages/contracts`.
- Mantener la app movil en `mobile/` conectada a endpoints reales.
- Mantener tokens visuales compartidos en `packages/design-tokens` cuando el cambio afecte lenguaje visual reutilizable.

Cuando un cambio SI debe considerar movil:

- Nuevos endpoints o cambios en payloads JSON.
- Nuevos modulos de negocio o cambios en reglas de negocio.
- Cambios en autenticacion, permisos o datos del usuario autenticado.
- Cambios en estados compartidos como perfil, ligas, equipos, partidos, standings, cobros o dashboards operativos.
- Cambios visuales que definan patrones reutilizables entre web y movil.

Cuando el agente detecte impacto movil, debe hacer como minimo:

- Evaluar si el backend/API ya expone correctamente el cambio para movil.
- Actualizar `packages/contracts/openapi.json` si cambia el contrato.
- Regenerar tipos con `npm run contracts:generate` cuando corresponda.
- Ajustar `mobile/` si ya existe una pantalla/flujo afectado o si el cambio amerita una representacion movil minima.
- Documentar en la entrega que se hizo respecto a movil, o justificar brevemente por que no aplicaba.

Workflow obligatorio recomendado por modulo:

1. Backend reusable:
   - migracion
   - modelo
   - request
   - policy
   - action/service
   - resource
   - endpoint API
   - test
2. Web:
   - ruta web
   - pagina/componente Inertia
3. Movil:
   - consumo desde `mobile/`
   - ajuste de contrato/tipos si aplica

## 8) Reglas especificas para este repositorio

- Stack esperado: Laravel + Inertia + Vue 3 + TypeScript + Vite.
- Stack movil esperado: Ionic Vue + Capacitor dentro de `mobile/`.
- Manten los cambios alineados con la estructura actual del proyecto, sin introducir otra arquitectura frontend sin justificacion fuerte.
- Si agregas una nueva pantalla, define claramente la ruta Laravel, la pagina Inertia y las validaciones backend asociadas.
- Si agregas una nueva capacidad consumible por movil, define claramente el endpoint API, el `Resource`, el contrato y el punto de consumo en `mobile/` cuando aplique.
- Si agregas modulos de negocio para ligas deportivas, prioriza nombres claros de dominio y cobertura de pruebas sobre soluciones rapidas acopladas al starter.
- Evita dejar texto del starter kit cuando el modulo ya pertenezca al dominio real de `Vamo al Game`.

## 9) Linea grafica y animaciones (obligatorio en UI)

La referencia detallada de diseno vive en `docs/linea-grafica.md`. Si tocas UI, debes seguirla.

Resumen operativo obligatorio:

- La app es mobile-first y debe sentirse como una app deportiva contenida: `max-width: 480px`, gutters de `16px` y espaciado basado en multiplos de `4` y `8`.
- La base visual es oscura y sin sombras: fondo `#0A0F1D`, cards `#131B2F`, modales `#1A243A`, con bordes sutiles en lugar de `box-shadow`.
- La tipografia de identidad y marcadores usa `Bebas Neue` o `Teko` en mayusculas; la UI usa `Inter` o `Roboto`. No mezclar familias arbitrariamente.
- Los botones deben medir al menos `48px` de alto, respetar la semantica de color definida y no inventar variantes visuales fuera del sistema.
- Tabs, listados, badges financieros, bottom sheets y teclados PIN deben seguir los patrones definidos en la guia de linea grafica.
- Toda animacion debe ser funcional, corta y tactil. Los controles presionables usan `scale(0.97)` y `opacity: 0.8` con `0.1s ease-out`.
- Los modales y bottom sheets deben entrar desde abajo con `0.3s cubic-bezier(0.16, 1, 0.3, 1)`.
- Si una propuesta visual se aparta de esta linea, debe justificarse explicitamente antes de implementarse.
