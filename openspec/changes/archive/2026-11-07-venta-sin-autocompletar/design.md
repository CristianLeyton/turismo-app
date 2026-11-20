# Design

## Context

- El wizard de venta está en `TicketForm::configure()` (pasos Buscar viaje, Asientos ida/vuelta, Pasajeros, Resumen). Los campos de texto del paso "Pasajeros" están en un `Repeater` (`passengers`): `dni`, `phone_number`, `last_name`, `first_name`, `child_data.*`, `pet_data.pet_names`, `price`. La observación sale de `TicketForm::observationField()` (compartido con "Editar boleto").
- Desde el wizard se abren `createOptionForm` de método de pago (`label`) y la acción "Crear cliente" (`apellido`, `nombre`, `dni`, `telefono`).
- "Editar boleto" usa `EditTicketForm` con `last_name`, `first_name`, `dni`, `phone_number`, `child_data.*`, `pet_data.pet_names`, `price`, `edit_reason` y la observación.
- Ningún campo define hoy `autocomplete`. Los `id`/`name` que arma Filament (`data.passengers.<uuid>.first_name`) contienen palabras que Chrome reconoce para su autocompletado de direcciones ("first_name", "phone").
- El padrón se aplica del lado del servidor (`afterStateUpdated` del DNI), no depende del navegador.

## Goals / Non-Goals

**Goals:**
- Ningún campo de texto de los dos formularios ofrece valores guardados del navegador.
- Una sola forma de marcarlo, fácil de mantener cuando se agreguen campos.

**Non-Goals:**
- Cambiar el resto del panel (login, parámetros, ABMs).
- Cambiar el autocompletado desde el padrón.
- Selects y date pickers: no usan el autocompletado de texto del navegador.

## Decisions

### 1. `->autocomplete(false)` en cada campo, con un helper
Filament genera `autocomplete="off"` con `TextInput::autocomplete(false)` (y `Textarea` acepta el atributo por `extraInputAttributes`). Se agrega un helper estático `TicketForm::withoutBrowserAutofill(Field $field): Field` y se aplica a cada campo de texto. Alternativa descartada: `TextInput::configureUsing()` global en un service provider, que cambiaría también el login y el resto del panel, fuera del pedido.

### 2. Test que recorre el schema
En vez de un test por campo, un test que arma el schema de cada formulario, recorre todos los componentes (incluidos los hijos del `Repeater` y los formularios de las acciones "Crear cliente" y "Nuevo método de pago") y falla si un `TextInput` o `Textarea` no tiene `autocomplete="off"`. Así, un campo nuevo sin la marca rompe el test.

### 3. Chrome
Chrome respeta `autocomplete="off"` para las sugerencias de valores escritos antes, pero su autocompletado de direcciones a veces lo ignora en campos que reconoce como nombre o teléfono. Se verifica en Chrome con datos guardados. Si en algún campo sigue ofreciendo valores, alternativa en orden de preferencia:
1. Un valor de `autocomplete` que Chrome no asocia con datos de perfil (por ejemplo `autocomplete="off"` + `data-form-type="other"`), y repetir la prueba.
2. Cambiar el atributo `name` del input por uno neutro vía `extraInputAttributes` (Livewire usa `wire:model`, no el `name`), para que la heurística no reconozca "first_name"/"phone".

**Resultado de la verificación (2026-11-07):** en el Chrome del usuario, el autocompletado de direcciones siguió sugiriendo en apellido, nombre y teléfono con todas las variantes probadas: `off`, un valor inventado, `new-password`, `one-time-code`, `id` neutro, sin `<label>`, campo bloqueado hasta el foco, `type="search"` y etiqueta con un carácter invisible. Chrome reconoce los campos igual. Decisión del usuario: quedarse con `autocomplete="off"`, que corta el historial de valores escritos y los navegadores que respetan el estándar, y aceptar la limitación de Chrome.

## Risks / Trade-offs

- [Navegadores que ignoran la indicación] → verificación en Chrome (el que usa la empresa) y alternativa del punto 3. En otros navegadores se acepta el comportamiento estándar.
- [Gestores de contraseñas] → no aplican a estos campos (no hay contraseña), salvo la confirmación de contraseña de "Editar boleto", que queda fuera.
- [Campos nuevos sin la marca] → el test del schema lo detecta.

## Migration Plan

Sin migraciones. Deploy: `git pull` y `php artisan view:clear`.
