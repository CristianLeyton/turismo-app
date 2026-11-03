# Guía de uso de OpenSpec

Setup del proyecto: CLI 1.16.0, esquema `spec-driven`, artefactos en español (`openspec/config.yaml`) y comandos `/opsx:*` disponibles en Claude Code.

## Cómo funciona

- **`openspec/specs/`**: describe cómo funciona hoy el sistema. Es la referencia de lo que ya existe.
- **`openspec/changes/<nombre>/`**: cada cambio en curso tiene su carpeta con:
  - `proposal.md`: qué se hace y por qué.
  - `design.md`: cómo se hace.
  - `tasks.md`: la lista de tareas.
  - `specs/`: los requisitos que se agregan, modifican o eliminan respecto de las specs principales.
- Cuando el cambio está terminado se **archiva**: sus requisitos se integran en `openspec/specs/` y la carpeta pasa a `changes/archive/`.

## Flujo recomendado, paso a paso (dentro de Claude Code)

### 1. Explorar (opcional, para ideas que aún no están claras)

```
/opsx:explore quiero que el cliente pueda cancelar un pasaje desde su panel
```

Sirve para conversar la idea, investigar el código y aclarar requisitos. No crea archivos de código. Úsalo cuando todavía no sabes bien qué quieres.

### 2. Proponer el cambio

```
/opsx:propose cancelación de pasajes desde el panel cliente
```

Crea `openspec/changes/<nombre>/` con `proposal.md`, `design.md`, `tasks.md` y los requisitos en `specs/`, todo en un paso.

### 3. Revisar la propuesta (lo haces tú, es el paso más importante)

Lee los archivos generados y corrige lo que no te convenza. Si quieres cambiar algo:

```
/opsx:update la cancelación solo se permite hasta 24 h antes del viaje
```

Esto mantiene coherentes entre sí la propuesta, el diseño, los requisitos y las tareas. No toca código.

### 4. Validar desde la terminal

```
openspec validate <nombre> --strict
```

### 5. Implementar

```
/opsx:apply <nombre>
```

Va resolviendo las tareas de `tasks.md` y marcándolas con `[x]`. Si se corta, vuelve a ejecutar `/opsx:apply` y continúa donde quedó.

### 6. Probar

Corre los tests (`php artisan test --compact ...`) y revisa la app.

### 7. Archivar

```
/opsx:archive <nombre>
```

Integra los requisitos del cambio en `openspec/specs/` y mueve la carpeta a `changes/archive/`. Hazlo recién cuando el cambio esté terminado y probado.

> `/opsx:sync` integra los requisitos en las specs principales **sin** archivar. Úsalo solo si necesitas las specs actualizadas antes de cerrar el cambio. Normalmente basta con `archive`.

## Comandos útiles de la CLI (en la terminal)

| Comando | Para qué |
|---|---|
| `openspec list` | Cambios en curso |
| `openspec list --specs` | Specs principales |
| `openspec show <nombre>` | Ver un cambio o una spec |
| `openspec view` | Panel interactivo con todo |
| `openspec validate <nombre> --strict` | Validar el formato de un cambio |
| `openspec archive <nombre>` | Archivar sin pasar por Claude |
| `openspec update` | Regenerar los comandos y skills tras actualizar OpenSpec |
| `openspec doctor` | Diagnosticar problemas de la configuración |

## Buenas prácticas

- **Un cambio por funcionalidad.** Cambios chicos y enfocados son más fáciles de revisar y archivar.
- **Revisa antes de `apply`.** Corregir la propuesta es barato; corregir código ya escrito no.
- **Archiva siempre al terminar.** Si no, `openspec/specs/` deja de reflejar el sistema real.
- **Las correcciones de una línea no necesitan OpenSpec.** Resérvalo para cambios de comportamiento.
- **Usa `openspec/config.yaml`** (`context:` y `rules:`) para restricciones globales. Por ejemplo, reglas para `tasks` que exijan incluir tests con PHPUnit.
- **Haz commit del cambio junto con el código.** Así queda el porqué de cada funcionalidad en el historial.

## Resumen

```
/opsx:propose → revisar o /opsx:update → /opsx:apply → tests → /opsx:archive
```
