# Tasks

## 1. Aviso según la cantidad de pendientes

- [x] 1.1 En `MyTickets`, pasar al aviso la cantidad y, con un solo pendiente, su boleto (con origen y destino cargados); agregar el método que lleva a la pestaña "Pendientes" sin abrir ningún formulario. Verificar con tests de que no hay cambios en lo que cuenta cada pestaña.
- [x] 1.2 En `pending-notice.blade.php`, mostrar con un pendiente el tramo y el número con "Elegir fecha", y con dos o más la cantidad con "Ver pendientes", manteniendo el estilo warning y el modo oscuro. Verificar en `ClientPanelTicketsTest` con: un pendiente nombra tramo y número y su botón abre su formulario; varios pendientes dicen la cantidad y muestran "Ver pendientes", no "Elegir fecha"; "Ver pendientes" desde "Próximos" pasa a "Pendientes" sin montar ninguna acción; ya en "Pendientes" no se ofrece "Ver pendientes"; el aviso aparece en las tres pestañas; sin pendientes no aparece. Actualizar los tests del aviso que esperaban abrir el boleto más antiguo.
- [x] 1.3 Correr `vendor/bin/pint --dirty --format agent` y `php artisan test --compact --filter="ClientPanel|BrandColorUsage"`.
