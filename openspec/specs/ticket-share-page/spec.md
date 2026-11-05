# ticket-share-page Specification

## Purpose

Link público de un boleto (`/boletos/{token}`), el que recibe el pasajero cuando la venta se entrega por link: entrega directamente el PDF del boleto, en línea o como descarga, sin sesión. Define qué tokens resuelve y cuál es el link de cada pasajero.

## Requirements

### Requirement: Link de un pasajero en un diferido
Al cerrar una venta diferida, el link entregado para cada pasajero SHALL ser el de su boleto de ida. El PDF de ese link MUST incluir el boleto de ida y el de vuelta.

#### Scenario: Diferido de un pasajero sin fecha
- **GIVEN** el parámetro de descarga automática desactivado
- **WHEN** se cierra una venta diferida de un pasajero con ambos tramos sin fecha
- **THEN** el modal de entrega ofrece el link del boleto de ida, y su PDF contiene la ida y la vuelta

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
