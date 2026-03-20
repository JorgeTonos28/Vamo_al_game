# Linea Grafica y Motion System

Guia oficial de interfaz para `Vamo al Game`.

Este documento define la linea visual y de animacion que deben seguir todas las pantallas nuevas o modificadas. La app debe sentirse como una herramienta deportiva mobile-first, compacta, rapida de operar y visualmente consistente en contextos de uso "en caliente" durante una jornada.

## 1. Principios rectores

- Mobile-first siempre. Se disena primero para telefono y luego se adapta hacia arriba.
- Interfaz contenida y predecible. La app no debe expandirse sin control en desktop.
- Ritmo visual estricto. La separacion entre elementos debe responder a una escala consistente.
- Jerarquia clara. El sistema debe diferenciar rapido accion, estado, marcador y contexto.
- Contraste sin ruido. No se usan sombras para elevar superficies; la separacion se resuelve con color, borde y luminosidad.
- Tacto primero. Todo elemento interactivo debe sentirse inmediato, estable y claro al presionarlo.

## 2. Layout y grid

### Contenedor principal

- `max-width: 480px`
- `width: 100%`
- centrado horizontalmente en pantallas grandes
- pensado como app-shell principal de la experiencia

### Gutters y areas seguras

- gutter lateral fijo de `16px` a izquierda y derecha
- ningun elemento debe tocar el borde de pantalla
- excepcion: tabs deslizables o carruseles horizontales controlados
- incluir soporte para safe areas:
  - `padding-top: env(safe-area-inset-top)`
  - `padding-bottom: env(safe-area-inset-bottom)`

### Escala de espaciado

Usar una escala basada en multiplos de `4` y `8`.

Valores de referencia obligatorios:

- padding interno de cards y secciones: `16px`
- separacion entre filas de listas: `12px`
- separacion entre bloques o secciones mayores: `24px`

Regla: no introducir espaciados arbitrarios si no se pueden justificar dentro de esta escala.

## 3. Sistema de superficies

### Fondo base

- `body`: `#0A0F1D`

### Cards y secciones

- fondo: `#131B2F`
- `border-radius: 12px`
- borde: `1px solid rgba(255, 255, 255, 0.04)`
- sin `box-shadow`

### Modales tipo bottom sheet

- fondo: `#1A243A`
- `border-radius: 24px 24px 0 0`
- borde superior: `1px solid rgba(255, 255, 255, 0.08)`
- anclados al fondo de la pantalla

### Overlay

- `background: rgba(0, 0, 0, 0.6)`
- `backdrop-filter: blur(4px)`

## 4. Paleta base

### Colores principales

- fondo base: `#0A0F1D`
- superficie: `#131B2F`
- superficie elevada: `#1A243A`
- texto principal: `#F8FAFC`
- texto secundario: `#94A3B8`
- acento dorado: `#E5B849`
- exito: `#4ADE80`
- error: `#F87171`

### Reglas de uso

- El dorado se reserva para marca, accion principal, tabs activas y estados destacados.
- El verde se reserva para marcadores positivos y estados semanticos favorables.
- El rojo se usa con tinte y control; evitar bloques de rojo puro de alta saturacion en fondos amplios.
- El blanco roto `#F8FAFC` es el color principal para cifras, nombres y contenido de mayor peso.

## 5. Tipografia

La interfaz usa dos familias con funciones distintas.

### Tipografia de identidad y marcador

Fuente objetivo:

- `Bebas Neue`

Fallback permitido mientras se incorpora la fuente final:

- `Teko`

Uso:

- solo en mayusculas
- branding
- headers de impacto
- marcadores o numeracion deportiva protagonista

Escalas base:

- logo o header principal: `28px`, color `#E5B849`, `letter-spacing: 1px`
- marcador grande: `96px`
  - equipo A: `#4ADE80`
  - equipo B: `#E5B849`

### Tipografia de interfaz

Fuente objetivo:

- `Inter`

Fallback permitido:

- `Roboto`

Uso:

- formularios
- labels
- nombres
- listas
- ayudas
- botones
- datos operativos

Escalas base:

- titulos de tarjetas: `12px`, `700`, uppercase, `letter-spacing: 0.5px`, color `#F8FAFC`
- texto principal: `16px`, `500`, color `#F8FAFC`
- texto secundario: `13px`, `400`, color `#94A3B8`

### Regla tipografica

- No introducir una tercera familia visual sin una decision explicita de sistema.
- No mezclar tipografia display en labels de interfaz.
- No usar lowercase estilizado para elementos de identidad deportiva si pertenecen a la capa display.

## 6. Sistema de botones y CTAs

### Regla general

- todo control tactil debe medir al menos `48px` de alto
- `border-radius: 8px` por defecto en botones estandar
- estados interactivos consistentes en toda la app

### Boton primario

- fondo: `#E5B849`
- texto: `#0A0F1D`
- tamano de texto: `15px`
- peso: `700`
- sin borde adicional

Uso:

- acciones principales por pantalla
- confirmaciones de alto peso
- CTA final del flujo

### Boton secundario

- fondo: `#1E293B`
- texto: `#F8FAFC`
- tamano de texto: `15px`
- peso: `500`

Uso:

- acciones secundarias
- cancelar no destructivo
- herramientas auxiliares

### Boton semantico positivo

- fondo: `rgba(74, 222, 128, 0.12)`
- borde: `1px solid rgba(74, 222, 128, 0.3)`
- texto: `#4ADE80`
- tamano de texto: `15px`
- peso: `500`

### Boton semantico negativo o destructivo

- fondo: `rgba(248, 113, 113, 0.12)`
- borde: `1px solid rgba(248, 113, 113, 0.3)`
- texto: `#F87171`
- tamano de texto: `15px`
- peso: `500`

### Botones de puntuacion gigante

- alto: `88px`
- `border-radius: 12px`
- estado normal: fondo semantico con `12%` de opacidad
- estado activo: misma tinta a `30%` de opacidad

Uso:

- sumar puntos
- registrar eventos de juego
- acciones tactiles urgentes

## 7. Reglas por componente

### Tabs superiores

- navegacion horizontal deslizante permitida
- `overflow-x: auto`
- `scroll-behavior: smooth`
- `scroll-snap-type: x mandatory`
- evitar scrollbar visible o ruido visual

Tab activa:

- texto `#E5B849`
- linea inferior de `2px`
- `border-radius: 2px 2px 0 0`

Tab inactiva:

- texto `#94A3B8`

### Listados de miembros

- separacion visual entre items con `border-bottom: 1px solid rgba(255,255,255,0.06)`
- el ultimo item no lleva borde inferior
- filas compactas y legibles; no sobrecargar con iconografia innecesaria

Boton de quitar:

- `32px x 32px`
- forma circular
- fondo `#1E293B`
- icono de menos de `16px`
- icono en `#94A3B8`

### Bottom sheets y modales operativos

- preferir bottom sheet sobre modal centrado cuando la accion es tactil y de continuidad rapida
- mantener el contenido visualmente separado del teclado numerico o de acciones secundarias

### Teclado PIN de administrador

- grilla: `display: grid`
- columnas: `repeat(3, 1fr)`
- `gap: 12px`

Botones numericos:

- alto: `64px`
- `border-radius: 12px`
- fondo: `#1E293B`
- tipografia: `Inter`
- tamano: `24px`
- peso: `500`
- color: `#F8FAFC`

Boton cancelar:

- fuera de la grilla numerica
- ancho completo
- estilo de boton secundario
- `margin-top: 24px`

### Tarjetas financieras y resumen

- las cifras principales deben ir en `#F8FAFC`
- el significado semantico se comunica con un badge, no con un bloque entero de color agresivo

Ejemplo para gastos:

- monto en blanco
- badge con fondo `rgba(248, 113, 113, 0.15)`
- texto `#FCA5A5`
- icono de flecha hacia abajo

## 8. Motion system

Las animaciones deben comunicar tacto, velocidad y control. Nunca deben sentirse decorativas o lentas.

### Regla general de movimiento

- animaciones cortas
- easing suave pero firme
- nada de rebotes exagerados ni duraciones largas
- el movimiento debe reforzar jerarquia, no distraer

### Active state de controles

Todo elemento presionable debe tener:

- `transform: scale(0.97)`
- `opacity: 0.8`
- `transition: all 0.1s ease-out`

Objetivo:

- simular respuesta fisica inmediata
- reducir duda tactil
- confirmar accion sin depender solo del color

### Entrada de modales y bottom sheets

Animacion obligatoria:

- desde `transform: translateY(100%)`
- hacia `transform: translateY(0)`
- duracion: `0.3s`
- easing: `cubic-bezier(0.16, 1, 0.3, 1)`

El overlay puede aparecer con un fade corto sincronizado, pero sin adelantarse visualmente al panel.

### Reglas de animacion

- no usar animaciones continuas decorativas salvo que aporten estado real
- evitar `transition: all` excepto en controles tactiles muy simples
- no introducir delays largos en acciones de uso frecuente
- preferir transform y opacity para rendimiento
- las animaciones deben sentirse nativas en movil

## 9. Anti-patrones

No hacer lo siguiente:

- usar sombras grandes para separar cards
- expandir layouts a ancho completo en desktop rompiendo la sensacion de app movil
- meter componentes con gutters inconsistentes
- usar rojos puros y saturados como fondo dominante de tarjetas financieras
- mezclar estilos de botones sin semantica clara
- inventar una nueva curva de easing por componente sin razon
- introducir animaciones lentas o teatrales en acciones operativas

## 10. Criterio de implementacion futura

Cuando se implemente esta linea visual en codigo:

- centralizar tokens de color, radios, spacing y motion en variables reutilizables
- mantener una unica fuente de verdad para componentes base
- preferir wrappers y componentes compartidos antes que estilos aislados por pagina
- si una pantalla necesita desviarse del sistema, documentar la razon y mantener la desviacion minima
