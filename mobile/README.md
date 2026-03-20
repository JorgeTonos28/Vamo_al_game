# Vamo al Game Mobile

Shell movil base con Ionic Vue + Capacitor.

## Objetivo

- consumir la API de Laravel bajo `/api/v1`
- autenticar por token Sanctum
- reutilizar el contrato TypeScript de `../packages/contracts/generated/api.d.ts`
- reutilizar tokens visuales de `../packages/design-tokens/theme.css`

## Variables

Crea `mobile/.env` desde `mobile/.env.example` cuando necesites cambiar la URL del backend:

```env
VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
```

## Scripts

- `npm run dev`
- `npm run build`
- `npm run sync`
- `npm run android`
- `npm run ios`
