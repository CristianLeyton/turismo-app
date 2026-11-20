# Tasks

## 1. Paleta y asignación automática

- [x] 1.1 Agregar `PaymentMethod::PALETTE` (9 colores en orden de asignación), resolver `color()` y `badgeClasses()` por la paleta con gris neutro para claves desconocidas, y agregar `suggestedColor()`; verificar con tests en `PaymentMethodColorTest` que cada clave devuelve su color de Filament y sus clases, que una clave desconocida da gris y que `suggestedColor()` devuelve el primer libre, el menos usado cuando la paleta se agota e ignora los eliminados
- [x] 1.2 Asignar `suggestedColor()` en `creating` cuando `color` está vacío o fuera de la paleta; verificar con un test que crear "Posnet" con Efectivo y Transferencia existentes le asigna Ámbar y que un color válido elegido se respeta

## 2. "Métodos de pago" en el panel

- [x] 2.1 Agregar el campo "Color" al formulario de `PaymentMethodResource` (opciones de la paleta mostradas como badge, valor por defecto `suggestedColor()`, requerido, solo valores de la paleta, mensajes en voseo) y mostrar el nombre como badge de su color en la tabla, siguiendo la skill `filament-ui`; verificar con tests de `ManagePaymentMethods` que crear trae el color sugerido, editar cambia el color, un color fuera de la paleta da error de validación y la tabla muestra el badge

## 3. Mismo color en todas las pantallas

- [x] 3.1 Verificar que la tabla de Pagos usa el color de la paleta (array de Filament para los tonos nuevos) con un test de `ManagePayments` sobre el color de la columna de método para un método personalizado
- [x] 3.2 Reemplazar la deducción por `str_contains` de `tickets/summary.blade.php` por `PaymentMethod::badgeClasses()`; verificar con un test de render que un método personalizado índigo aparece con sus clases y revisión visual en el navegador
- [x] 3.3 Verificar con un test de `SalesUserTickets` que el badge de un método personalizado en las filas y en el desglose usa sus clases de la paleta

## 4. Métodos existentes

- [x] 4.1 Crear la migración de datos que colorea, en orden de `sort_order`, los métodos con color fuera de la paleta (incluidos los eliminados) con el mismo criterio de asignación; verificar con un test que ejecuta la migración sobre Efectivo, Transferencia y tres métodos grises y comprueba tres colores distintos de la paleta sin tocar los dos primeros

## 5. Integración

- [x] 5.1 Correr `vendor/bin/pint --dirty --format agent`, `npm run build` y los tests afectados (`PaymentMethodColorTest`, tests de Métodos de pago, Pagos, `SalesUserTicketsTest`, `BrandColorUsageTest`); revisar en el navegador "Métodos de pago", Pagos, el detalle de ventas y el resumen de un boleto en modo claro y oscuro

## Workflow follow-up

- En el deploy: `php artisan migrate` y `npm run build`.
- Archivar el cambio con `/opsx:archive` cuando esté implementado y revisado.
