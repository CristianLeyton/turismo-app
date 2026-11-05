# Design

## Context

La motivación está en proposal.md y los requisitos en `specs/ticket-contact-lines/spec.md`.

Estado actual relevante:

- **Parámetros:** `Setting` es una tabla clave/valor con caché por request. Las listas se guardan como JSON y se leen con un método que normaliza el contenido, como `Setting::serviceExceptions()` con `decodeJson()`. `TicketSettings` es la página de parámetros (permiso `settings.manage`): `getFormData()` carga el formulario, `save()` normaliza y guarda cada clave, y las excepciones de días de servicio usan un `Repeater`.
- **Boleto:** todos los PDF del boleto usan la plantilla `tickets.pdf.passenger-tickets`, ya sea por `TicketPdfService` (reprogramación, link y panel de clientes) o por `CreateTicket` (venta). Cada boleto mide 130 mm de alto fijo, la mitad de una hoja A4. La columna izquierda termina con la tabla de "Asiento" y "Planta", y debajo queda un espacio libre antes del número de boleto.
- **Tests de PDF:** `PendingTicketPdfTest` renderiza la plantilla a HTML con `view(...)->render()` y busca textos; no hace falta generar el PDF.

## Goals / Non-Goals

**Goals:**
- Que el máximo de líneas se cambie en un solo lugar.
- Que el bloque no cambie el alto del boleto ni empuje el número de boleto.
- Reutilizar el patrón de listas JSON de `Setting` y el `Repeater` de `TicketSettings`.

**Non-Goals:**
- Teléfonos distintos por colectivo, línea o ruta.
- Enlaces de WhatsApp en el PDF, o mostrar las líneas en la página del link o en el panel de clientes fuera del PDF.
- Convertir el número a un formato de marcado (agregar 0, 15 o +54): el enlace usa los dígitos tal como se cargaron.
- Validar que el número exista o normalizarlo a un formato único.

## Decisions

### 1. Clave JSON en `Setting` con el máximo como constante

`Setting` suma:

- `CONTACT_LINES = 'tickets.contact_lines'`.
- `CONTACT_LINES_MAX = 3`, el único lugar que define el máximo. La página usa `->maxItems(Setting::CONTACT_LINES_MAX)` y la lectura recorta a ese valor, así que subirlo alcanza para habilitar más líneas.
- `contactLines(): array`, con forma `list<array{name: ?string, phone: string, dial: string}>`. `dial` es el destino del enlace `tel:`: solo los dígitos, con el "+" inicial si lo tiene. Lee con `decodeJson()`, descarta entradas sin teléfono, recorta espacios y devuelve como máximo `CONTACT_LINES_MAX`. Sin clave guardada devuelve `[]`.

**Alternativa descartada: tres claves fijas (`contact_1`, `contact_2`, `contact_3`).** Subir el máximo obligaría a agregar claves, campos y lógica en la plantilla. Una lista JSON crece cambiando una constante.

### 2. Sección "Líneas de contacto" en `TicketSettings`

Es una `Section` nueva con un `Repeater` `contact_lines`:

- `->maxItems(Setting::CONTACT_LINES_MAX)`, `->defaultItems(0)`, `->addActionLabel('Agregar línea')` y `->columns(2)`. Se puede reordenar, porque el orden es el del boleto.
- Campo `name`, con etiqueta "Nombre (opcional)" y placeholder "Ej.: Orán", y `maxLength(30)`.
- Campo `phone`, con etiqueta "Teléfono", `required()`, `maxLength(20)` y una regla `regex:/^\+?[\d\s\-()]{6,20}$/` más una regla de al menos 6 dígitos. Tiene mensajes propios, como el resto de la página.
- La descripción de la sección explica que aparecen en todos los boletos debajo del asiento, en el orden cargado.

`getFormData()` carga `Setting::contactLines()`. `save()` recorta espacios, descarta las líneas sin teléfono, guarda `name` vacío como `null` y graba `json_encode()` de la lista.

### 3. Bloque compacto en la plantilla del PDF

Debajo de la tabla de "Asiento" y "Planta", en la columna izquierda, va un `div` con la clase `.contact-lines`, solo si `Setting::contactLines()` no está vacío:

```
Líneas de contacto: Orán: 3878-804506 · Tartagal: 3873-309893
```

- **Tamaño:** letra de 10 px, menor que la de los datos del pasajero (11-12 px) y mayor que la de las condiciones (8,5 px), que se leen con dificultad. El título va en gris y en negrita, el nombre en gris y el teléfono en negrita, para que el número sea lo que se ve primero.
- **Espacio:** una sola fila con `margin` de 1-2 mm. Con 3 líneas el ancho alcanza en la columna de 2/3. Si no entra, el texto pasa a una segunda fila sin cambiar el alto del boleto.
- **Lectura una vez por render:** la plantilla lee las líneas al inicio, no en cada boleto del `@foreach`.
- **Enlace para llamar:** cada teléfono va dentro de `<a href="tel:{{ $line['dial'] }}">`, que dompdf convierte en un enlace del PDF. Los visores de PDF del celular (el de Android, Chrome, Safari en iOS y el de WhatsApp) abren el marcador al tocarlo. El texto visible es el teléfono tal como se cargó, en negrita y subrayado, en el color del texto para no confundirlo con el número de asiento. El nombre queda fuera del enlace.
- **Marcado:** el enlace marca exactamente los dígitos cargados. La descripción de la sección de parámetros recomienda cargar el número como se marca desde un celular, por ejemplo con el prefijo de área, o con "+54 9" si hay clientes de otras provincias.

**Alternativa descartada: una línea por renglón, como en la captura.** Ocupa un renglón por número, y con 3 líneas se acerca al número de boleto. La fila con separadores ocupa uno solo.

## Risks / Trade-offs

- **[El bloque desborda los 130 mm del boleto]** → Es una sola fila en un espacio que hoy está libre. Se verifica en la prueba manual con 3 líneas largas y con un boleto con menor y mascota.
- **[Un teléfono muy largo corta la fila]** → El máximo de 20 caracteres por teléfono y de 30 por nombre acota la fila. En el peor caso pasa a dos renglones dentro del mismo espacio.
- **[Un visor de PDF no abre el enlace]** → El número sigue visible y se puede marcar a mano. Se verifica en la prueba manual con Android y iOS.
- **[El número se carga en un formato que no se puede marcar]** → La descripción de la sección explica cómo cargarlo, y la prueba manual incluye tocar cada número.
- **[Los boletos ya emitidos muestran el teléfono nuevo]** → Es lo esperado: el PDF se genera al vuelo y un número actualizado es lo que le sirve al cliente.

## Migration Plan

Sin migraciones. Se despliega el código y, mientras no se carguen líneas, los boletos quedan igual que hoy. Para volver atrás alcanza con revertir el commit; la clave guardada queda sin uso.
