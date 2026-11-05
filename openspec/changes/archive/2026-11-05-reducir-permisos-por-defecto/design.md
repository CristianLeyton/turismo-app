# Design

## Context

El catálogo de permisos (`App\Support\Permissions`) es la única fuente de verdad: `defaultsFor()` define los permisos de cada rol del sistema y `syncToDatabase()` (comando `permissions:sync`) los aplica con `givePermissionTo`, sin revocar. La matriz de roles ya filtra los permisos que el editor no posee. El cambio se implementó antes de este documento; acá queda registrado.

## Goals / Non-Goals

**Goals:**
- Que una instalación nueva arranque con Administrador y Vendedor sin borrado definitivo ni Clientes, y el Vendedor además sin Ventas.
- Mantener la sincronización aditiva para no perder lo habilitado a mano.

**Non-Goals:**
- Migración de datos que quite los permisos en instalaciones existentes (el usuario decidió no hacerla; se ajusta desde la matriz).
- Cambiar los permisos del Super Administrador o de roles custom.
- Cambiar la regla de la matriz que impide asignar permisos que el editor no tiene.

## Decisions

- **Helper `standardActionsWithoutForceDelete($module)`**: devuelve las acciones estándar de un módulo sin `force_delete`. Evita repetir el `array_diff` por cada módulo (antes solo se usaba en pagos). Alternativa descartada: listar los permisos a mano, más propenso a errores al agregar acciones estándar.
- **Clientes se quita por completo** en los dos roles, en vez de dejar solo lectura: es lo que se pidió y se puede habilitar desde la matriz.
- **Tests por barrido del catálogo**: los tests nuevos recorren `Permissions::all()` y verifican que ningún permiso `clients.*`, `sales.*` (Vendedor) ni `*.force_delete` esté asignado, así un módulo nuevo con acciones estándar queda cubierto sin tocar el test.
- `ClientsTableTest` pasa a usar un Super Administrador, porque prueba la tabla y no los permisos del Vendedor.

## Risks / Trade-offs

- [Instalaciones existentes no cambian] → Documentarlo; quitar a mano desde la matriz.
- [Un Administrador ya no puede habilitar Clientes a otros roles] → Lo hace el Super Administrador, o se le asigna Clientes al Administrador desde la matriz.
- [Vendedores sin acceso a Clientes ni Ventas] → Verificar que el flujo de venta no dependa de esos permisos; la suite completa pasa (salvo dos fallos previos ajenos: `PendingTicketPdfTest` y un test de `TicketAuditResourceTest`).
