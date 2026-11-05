# Design

## Context

En `TicketForm`, Origen y Destino deciden `searchable()` con `count(opciones) > 10`. En estilo detallado, `destinationOptions()` devuelve un array agrupado (`['Hacia Salta' => [id => label], ...]`), por lo que `count()` devuelve la cantidad de grupos. El cambio se implementó antes de este documento; acá queda registrado.

## Goals / Non-Goals

**Goals:**
- Que Destino muestre el buscador con más de 10 paradas, igual que Origen.

**Non-Goals:**
- Cambiar el umbral de 10 o el estilo simple (select nativo, sin buscador).
- Cambiar cómo se arman o agrupan las opciones.

## Decisions

- **Helper `hasEnoughOptionsToSearch(array $options): bool`**: suma 1 por opción plana y `count()` por grupo. Lo usan Origen y Destino, así la regla queda en un solo lugar. Alternativas descartadas: `count($options, COUNT_RECURSIVE)`, que también cuenta los grupos y obliga a restarlos; y aplanar con `array_merge`, que pierde claves numéricas repetidas entre grupos.
- Una localidad que aparece en los dos sentidos cuenta dos veces: son dos opciones visibles en el select, que es lo que importa para decidir si hace falta buscar.

## Risks / Trade-offs

- [Se arman las opciones dos veces por render (para `searchable` y para `options`)] → Ya pasaba antes del cambio; no se agrega costo nuevo.
