# Tasks

## 1. Parámetro en `Setting`

- [x] 1.1 Agregar a `Setting` la clave `CONTACT_LINES`, el máximo `CONTACT_LINES_MAX = 3` y `contactLines()`, que lee el JSON, descarta entradas sin teléfono, recorta espacios, agrega `dial` (solo dígitos y el "+" inicial) y devuelve como máximo `CONTACT_LINES_MAX`, o `[]` sin clave guardada. Se verifica con tests en `TicketSettingsTest`: sin clave devuelve `[]`, descarta entradas inválidas, recorta al máximo y "+54 9 (3878) 804-506" da `dial` "+5493878804506".

## 2. Página de parámetros

- [x] 2.1 Agregar a `TicketSettings` la sección "Líneas de contacto" con el `Repeater` `contact_lines` (nombre opcional de hasta 30 caracteres y teléfono obligatorio con la regla de formato), con `maxItems(Setting::CONTACT_LINES_MAX)`. Cargarla en `getFormData()` y guardarla normalizada en `save()`. Se verifica con tests en `TicketSettingsTest`:
  - guardar dos líneas conserva el orden y se ven al recargar;
  - una línea sin nombre se guarda con nombre `null`;
  - un teléfono con letras muestra el error y no se guarda nada;
  - "+54 (3878) 804-506" se acepta;
  - el Repeater no admite más de 3 líneas.

## 3. Boleto

- [x] 3.1 En `tickets/pdf/passenger-tickets.blade.php`, leer las líneas una vez y, si hay, mostrar debajo de la tabla de asiento el bloque `.contact-lines` de una fila ("Líneas de contacto:" y "Nombre: teléfono" separados por " · ", solo el teléfono si no tiene nombre), con letra de 10 px. Cada teléfono va en `<a href="tel:{dial}">`, en negrita y subrayado, con el nombre fuera del enlace. Se verifica con tests nuevos en `TicketContactLinesPdfTest` que renderizan la plantilla: con dos líneas aparece el texto esperado, cada teléfono enlaza a su `tel:` sin guiones, una línea sin nombre muestra solo el teléfono, sin líneas no aparece el bloque y un cambio de teléfono se refleja en el siguiente render.
- [x] 3.2 Verificar que el bloque aparece en los PDF generados por `TicketPdfService` (link y reprogramación) y por la venta. Se verifica con un test que genera el HTML por `TicketPdfService` y encuentra el bloque.

## 4. Cierre

- [x] 4.1 Ejecutar `vendor/bin/pint --dirty --format agent` y los tests de `TicketSettingsTest`, `TicketContactLinesPdfTest`, `PendingTicketPdfTest` y `TicketShareLinkModeTest`. Todos tienen que pasar.
- [x] 4.2 Prueba manual: cargar 3 líneas con nombres y teléfonos largos y abrir el PDF de un boleto común y de uno con menor y mascota. El bloque se lee bien, entra en una o dos filas y no mueve el número de boleto ni corta el boleto.
- [x] 4.3 Prueba manual en celular: abrir el PDF de un boleto en Android y en iPhone (desde el link compartido y desde WhatsApp) y tocar cada teléfono. Se abre el marcador con el número correcto.

## Workflow follow-up

- Ofrecer ejecutar la suite completa antes de archivar.
- Archivar el cambio con `/opsx:archive` una vez revisado.
