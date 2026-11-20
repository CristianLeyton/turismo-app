# Proposal

## Why

Los métodos de pago que crea la empresa (Posnet, Orden de Compra Municipal, Paciente Oncológico…) salen todos en gris: solo Efectivo y Transferencia traen color. En el detalle de ventas, "Mis ventas", Pagos y el boleto no se distinguen de un vistazo, y no hay forma de cambiarles el color desde el panel.

## What Changes

- Paleta fija de colores para los métodos de pago, con el mismo estilo de los badges actuales (fondo suave y texto del tono, con su variante oscura). Sin rojo (en el panel significa error), gris (parece inactivo) ni el color de la empresa (cambia según la instalación).
- Al crear un método se le asigna automáticamente el primer color de la paleta que no use otro método; si están todos usados, el menos repetido.
- En "Métodos de pago", campo "Color" en el formulario (con vista previa del badge) y badge con el color en la tabla.
- Todos los lugares que pintan el método de pago usan ese color: tabla de Pagos, detalle de ventas / "Mis ventas", detalle del boleto y resumen de la venta (hoy decide el color comparando texto de clases).
- Migración de datos: los métodos existentes en gris reciben un color de la paleta.

## Capabilities

### New Capabilities
- `payment-method-colors`: color de cada método de pago (paleta, asignación automática al crear, edición desde "Métodos de pago" y uso consistente en todas las pantallas).

### Modified Capabilities

## Impact

- `app/Models/PaymentMethod.php` (paleta, asignación al crear, `color()` y `badgeClasses()`).
- `app/Filament/Resources/PaymentMethods/PaymentMethodResource.php` (campo y columna de color).
- `app/Filament/Resources/Payments/PaymentResource.php` (badge con paletas que Filament no trae registradas).
- `resources/views/tickets/summary.blade.php` (deja de deducir el color por texto).
- Migración de datos para los métodos en gris; requiere `php artisan migrate` y `npm run build` (clases Tailwind nuevas) en el deploy.
- Tests nuevos de `PaymentMethod` y del recurso Métodos de pago.
- Fuera de alcance: los PDF y Excel, que hoy muestran el método sin color.
