# Proposal

## Why

En el link público que se entrega al cerrar una venta, el vendedor ve el PDF del boleto sin descargarlo. En el "Ver boleto" del panel admin no: para revisar el PDF tiene que descargarlo, o abrir "Ver link" si está activa la emisión por link. Ese botón crea un link público como efecto secundario y no anda con boletos eliminados.

Además, la regla "qué PDF le corresponde a un boleto y cómo se llama el archivo" está copiada en seis lugares. Sumar la vista previa haría una séptima copia. Es el momento de unificarla.

## What Changes

- Nueva ruta autenticada del panel admin que devuelve el PDF de un boleto en línea (`inline`). Exige sesión del panel y el permiso de ver boletos, acepta boletos eliminados y no genera ni persiste tokens compartibles.
- Nueva sección "Vista previa" en el "Ver boleto" del panel, entre "Detalles de venta" y "Historial de ediciones". Repite el comportamiento del link público: visor embebido en escritorio con visor de PDF, y en el resto una tarjeta con el nombre del archivo y las acciones "Abrir PDF" y "Descargar PDF". El resto del diseño de la pantalla no cambia.
- Unificación de la regla del PDF de un boleto en un único punto del servicio de PDFs: variante ida y vuelta según `is_round_trip`, si no la individual, y nombre `Boleto_N°{id}_{colectivo}.pdf`. Pasan a usarlo las seis copias actuales:
  - la descarga en "Ver boleto"
  - las dos descargas de la tabla de boletos
  - el PDF tras reprogramar
  - el PDF del link público de un boleto
  - el PDF del panel de clientes

  Cada uno conserva su forma de entrega: descarga directa, sesión hacia `tickets.download` o respuesta inline.
- Sin cambios para el pasajero ni para el cliente: el link público, "Ver link", la descarga automática y el panel de clientes funcionan igual que hoy.

## Capabilities

### New Capabilities
- `ticket-detail`: el "Ver boleto" del panel admin, es decir, qué muestra el detalle de un boleto para el personal (por ahora, la vista previa de su PDF y el acceso autenticado a ese PDF).
- `ticket-pdf`: qué PDF le corresponde a un boleto individual y cómo se llama el archivo. Tiene que ser lo mismo en todos los accesos: panel admin, tabla, reprogramación, link público y panel de clientes.

### Modified Capabilities
<!-- Ninguna: el link público, "Ver link" y el panel de clientes no cambian de comportamiento. -->

## Impact

- **Rutas**: nueva ruta GET autenticada para el PDF inline de un boleto en el panel admin, en `routes/web.php`.
- **Controladores**: uno nuevo (o una acción nueva) para esa ruta. `TicketShareController` y `ClientTicketPdfController` delegan la generación en el servicio.
- **Servicio**: `App\Services\TicketPdfService` gana un método para "PDF + nombre de archivo de un boleto".
- **Filament**: `TicketInfolist`, que suma la sección de vista previa, más `ViewTicket`, `TicketsTable` y `RescheduleTicket`, que delegan en el servicio.
- **Vistas**: un blade nuevo para la vista previa del panel, con la lógica escritorio/celular en Alpine.
- **Tests**: nuevos tests de la ruta (acceso, permisos, eliminados, sin token) y de la vista previa, más tests que fijan la variante y el nombre del PDF en cada acceso.
- **Dependencias**: ninguna nueva.
