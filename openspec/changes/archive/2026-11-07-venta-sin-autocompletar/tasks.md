# Tasks

## 1. Venta

- [x] 1.1 Crear `tests/Feature/TicketFormAutofillTest.php` (`php artisan make:test --phpunit TicketFormAutofillTest --no-interaction`) con un helper que recorre el schema del wizard de venta (incluidos los hijos del Repeater y los formularios de "Crear cliente" y "Nuevo método de pago") y lista los `TextInput`/`Textarea` sin `autocomplete="off"`. Verificar que falla y que nombra los campos.
- [x] 1.2 Agregar `TicketForm::withoutBrowserAutofill()` y aplicarlo a todos los campos de texto del wizard, de "Crear cliente", de "Nuevo método de pago" y a `observationField()`. Verificar que pasa 1.1.
- [x] 1.3 Test de regresión del padrón (ya cubierto por `TicketSaleDniValidationTest::test_el_dni_del_padron_autocompleta_nombre_apellido_y_telefono`): escribir el DNI de un cliente registrado en el paso "Pasajeros" sigue completando apellido, nombre y teléfono. Correr además los tests existentes de venta (`--filter=TicketSale|Padron|Client`).

## 2. Edición

- [x] 2.1 Extender `TicketFormAutofillTest` al schema de `EditTicketForm` (pasajero, menor, mascotas, precio, observación y motivo). Verificar que falla, aplicar `withoutBrowserAutofill()` y verificar que pasa. Correr los tests existentes de edición (`--filter=EditTicket`).

## 3. Navegador

- [x] 3.1 Revisión en Chrome con datos guardados del navegador: en la venta y en "Editar boleto", hacer foco en DNI, apellido, nombre, teléfono y observación y confirmar que no aparecen sugerencias. Si algún campo las muestra, aplicar la alternativa del design (punto 3), documentarla en el helper, ajustar el test y repetir.

## 4. Cierre

- [x] 4.1 `vendor/bin/pint --dirty --format agent` y `php artisan test --compact --filter="TicketFormAutofill|TicketSale|EditTicket"`; todo en verde.

## Workflow follow-up

- Preguntar si correr la suite completa.
- Archivar con `/opsx:archive`.
