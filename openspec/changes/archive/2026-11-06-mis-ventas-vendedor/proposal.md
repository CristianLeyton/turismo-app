# Proposal

## Why

Hoy un vendedor no tiene forma de ver sus propias ventas, los pagos que realizó ni su saldo: esa información solo existe dentro del modal de detalle del recurso Ventas, que es exclusivo de administradores. Además, al revisar el clúster de Ventas aparecieron inconsistencias en los números que muestra (filtro de fechas, vendedores con solo pagos, filtro por método de pago) y un problema de seguridad en el componente de detalle que se vuelve explotable apenas un vendedor tenga acceso a él.

## What Changes

- Nueva página **"Mis ventas"** dentro del clúster Ventas: muestra en la página (no en un modal) el mismo detalle que hoy abre "Ver detalle" en el recurso Ventas (resumen, desglose por método, filtros, ventas con sus boletos, pagos recibidos, saldo y exportación PDF/Excel), siempre acotado al usuario logueado.
- Nuevo permiso `sales.view_own` ("Ver mis ventas") en el catálogo. Lo trae por defecto el rol Vendedor; el Administrador no. Solo el Super Administrador puede asignarlo a otros roles (se mantiene la regla de la matriz).
- **Seguridad:** el componente de detalle deja de aceptar que el navegador cambie el vendedor consultado y autoriza tanto al montarse como al exportar: con `sales.view` se puede ver a cualquier vendedor; con `sales.view_own`, solo a uno mismo.
- **Corrección del filtro de fechas** del recurso Ventas: un vendedor aparece solo si tiene ventas (o pagos) dentro del rango, no por tener una venta antes y otra después.
- **Vendedores con solo pagos** en el período aparecen en la tabla de Ventas y cuentan en el resumen.
- **Filtro por método de pago consistente**: filtra ventas y pagos por igual en las filas, el ordenamiento, la tarjeta de resumen y el modal; el modal hereda también el método elegido en la tabla.
- **Rendimiento** de la tabla de Ventas: los totales por fila se calculan en la base de datos en lugar de cargar e iterar todas las ventas y boletos.

## Capabilities

### New Capabilities
- `seller-sales`: página "Mis ventas" del vendedor (acceso, contenido, filtros por defecto y exportación de sus propios datos).
- `sales-summary`: cálculo y presentación de ventas, pagos y saldo por vendedor en el clúster Ventas (tabla de Ventas, tarjeta de resumen y detalle por vendedor), incluida la autorización del detalle.

### Modified Capabilities
- `role-permissions`: el rol Vendedor pasa a traer por defecto `sales.view_own`; el Administrador no lo trae.

## Impact

- `app/Support/Permissions.php` (catálogo y defaults); requiere correr `php artisan permissions:sync` en el deploy.
- `app/Filament/Resources/Sales/SalesResource.php` (filtros, columnas, totales, apertura del modal).
- `app/Livewire/SalesUserTickets.php` y `resources/views/livewire/sales-user-tickets.blade.php` (autorización, propiedad bloqueada, uso en página).
- Nueva página Filament en el clúster `SalesCluster` y su vista.
- `app/View/Components/TableSummaryCard.php` / resumen de Ventas.
- Tests: `RolesAndPermissionsTest` y nuevos tests de la página, del componente y de la tabla de Ventas.
- Fuera de alcance: la duplicación de acciones escritorio/mobile del recurso Ventas y la exclusión del usuario de pruebas id=1.
