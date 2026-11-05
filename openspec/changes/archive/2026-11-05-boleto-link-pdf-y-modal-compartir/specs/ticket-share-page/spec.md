# Spec Delta

## ADDED Requirements

### Requirement: El link del boleto muestra su PDF
El link público de un boleto (`/boletos/{token}`) SHALL responder directamente con el PDF del boleto para verlo en línea, sin una página intermedia. El nombre del archivo MUST ser el del PDF del boleto. Si el navegador no muestra PDFs en línea, por ejemplo en Android, puede descargarlo en su lugar. El link MUST funcionar sin sesión.

#### Scenario: Abrir el link de un boleto
- **WHEN** alguien sin sesión abre el link de un boleto
- **THEN** recibe el PDF de ese boleto en línea, con el nombre de archivo del boleto, y no una página HTML

#### Scenario: Diferido con vuelta asignada
- **WHEN** alguien abre el link del boleto de ida de un diferido
- **THEN** el PDF contiene la ida y la vuelta

#### Scenario: Link inválido o boleto borrado
- **WHEN** alguien abre un link cuyo token no existe o cuyo boleto fue borrado
- **THEN** recibe un 404

### Requirement: Descarga del PDF del link
Además del link, cada boleto SHALL tener una URL pública de descarga que entrega el mismo PDF como archivo adjunto, con el mismo nombre de archivo. Es la que usa "Descargar PDF" en el modal de entrega.

#### Scenario: Descargar desde la URL de descarga
- **WHEN** alguien abre la URL de descarga de un boleto
- **THEN** el navegador descarga el PDF del boleto con su nombre de archivo

### Requirement: Sin links de venta
El sistema SHALL entregar solo links de boletos. Un token de link de venta MUST responder 404, tanto en el link como en la descarga.

#### Scenario: Token de una venta
- **GIVEN** una venta que tiene un token de link de venta generado antes de este cambio
- **WHEN** alguien abre `/boletos/{token de la venta}`
- **THEN** recibe un 404

## MODIFIED Requirements

### Requirement: Link de un pasajero en un diferido
Al cerrar una venta diferida, el link entregado para cada pasajero SHALL ser el de su boleto de ida. El PDF de ese link MUST incluir el boleto de ida y el de vuelta.

#### Scenario: Diferido de un pasajero sin fecha
- **GIVEN** el parámetro de descarga automática desactivado
- **WHEN** se cierra una venta diferida de un pasajero con ambos tramos sin fecha
- **THEN** el modal de entrega ofrece el link del boleto de ida, y su PDF contiene la ida y la vuelta

## REMOVED Requirements

### Requirement: Acceso desde el detalle del boleto en el panel
**Reason**: Con el link convertido en el PDF, "Ver link" repetía la vista previa del detalle. Se reemplaza por "Enviar por WhatsApp" y "Copiar link" en el detalle del boleto (capacidad `ticket-detail`).
**Migration**: Para mandar el link desde el detalle se usa "Enviar por WhatsApp"; para obtenerlo, "Copiar link" en la vista previa.

### Requirement: Presentación igual al "Ver boleto" del panel
**Reason**: El link ya no muestra una página HTML con las tarjetas del boleto. Responde directamente con el PDF.
**Migration**: El contenido del boleto se ve en el PDF. El 404 para tokens inválidos o boletos borrados pasa al requisito "El link del boleto muestra su PDF".

### Requirement: Acciones visibles al abrir la página
**Reason**: Las acciones de compartir, descargar y copiar pasan al modal de entrega del vendedor (capacidad `ticket-delivery-modal`). El pasajero recibe solo el PDF.
**Migration**: Usar el modal de entrega al cerrar la venta o la reprogramación.

### Requirement: Tema claro y oscuro
**Reason**: No hay página HTML a la que aplicarle tema.
**Migration**: Ninguna.

### Requirement: Vista previa del PDF según el dispositivo
**Reason**: El link es el PDF. El navegador decide si lo muestra o lo descarga, y en Android la descarga está aceptada.
**Migration**: Ninguna.

### Requirement: Sólo las idas en pantalla en el link de una venta
**Reason**: Ya no se entregan links de venta. Cada pasajero recibe el link de su boleto de ida.
**Migration**: Para mandar todos los boletos juntos se usa "Enviar todos a un número" del modal de entrega.

### Requirement: Destino del logo según el panel de clientes
**Reason**: No hay página HTML ni logo en el link.
**Migration**: Ninguna.
