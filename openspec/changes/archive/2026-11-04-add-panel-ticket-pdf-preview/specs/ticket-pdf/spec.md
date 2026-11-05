# Spec Delta

## Purpose

Define qué PDF le corresponde a un boleto individual y con qué nombre se entrega, para que sea el mismo desde cualquier lugar donde se lo pida.

## ADDED Requirements

### Requirement: Contenido del PDF de un boleto
El PDF de un boleto individual SHALL ser la hoja de ida y vuelta del pasajero cuando el boleto es de ida y vuelta: incluye los boletos de ese pasajero en la misma venta. En cualquier otro caso MUST ser la hoja individual de ese boleto.

#### Scenario: Boleto de ida y vuelta
- **GIVEN** una venta de ida y vuelta de un pasajero
- **WHEN** se pide el PDF del boleto de ida
- **THEN** el PDF es la hoja de ida y vuelta con los dos tramos de ese pasajero

#### Scenario: Boleto sólo de ida
- **GIVEN** una venta sólo de ida
- **WHEN** se pide el PDF de uno de sus boletos
- **THEN** el PDF es la hoja individual de ese boleto

#### Scenario: Venta de ida y vuelta de varios pasajeros
- **GIVEN** una venta de ida y vuelta de dos pasajeros
- **WHEN** se pide el PDF del boleto de ida de uno de ellos
- **THEN** el PDF sólo incluye los tramos de ese pasajero, no los del otro

### Requirement: Nombre del archivo del PDF de un boleto
El archivo del PDF de un boleto individual SHALL llamarse `Boleto_N°{id}_{colectivo}.pdf`, con los espacios del nombre del colectivo cambiados por guiones bajos. Cuando el boleto no tiene viaje o colectivo asignado, MUST usar `Sin_fecha` en lugar del colectivo.

#### Scenario: Boleto con colectivo
- **GIVEN** el boleto N°15 en un viaje del colectivo "Coche 3"
- **WHEN** se descarga su PDF
- **THEN** el archivo se llama `Boleto_N°15_Coche_3.pdf`

#### Scenario: Boleto sin fecha
- **GIVEN** el boleto N°16 vendido sin fecha, sin viaje asignado
- **WHEN** se descarga su PDF
- **THEN** el archivo se llama `Boleto_N°16_Sin_fecha.pdf`

### Requirement: Mismo PDF en todos los accesos
El PDF de un boleto individual y su nombre de archivo MUST ser los mismos sin importar desde dónde se lo pida:
- la descarga y la vista previa del "Ver boleto" del panel
- las descargas de la tabla de boletos
- el PDF que se entrega al reprogramar
- el PDF del link público de un boleto
- el PDF del panel de clientes

Cada acceso MUST conservar su forma de entrega actual.

#### Scenario: Panel y link público
- **GIVEN** un boleto de ida y vuelta con link público
- **WHEN** se descarga su PDF desde el "Ver boleto" del panel y desde su link público
- **THEN** los dos PDF son la misma hoja de ida y vuelta y tienen el mismo nombre de archivo

#### Scenario: Tabla de boletos
- **WHEN** se descarga el PDF de un boleto desde la tabla de boletos
- **THEN** se descarga directamente, con el mismo contenido y nombre que desde el "Ver boleto"

#### Scenario: Reprogramación con descarga automática
- **GIVEN** el parámetro "Descargar PDF automáticamente al vender" activado
- **WHEN** se reprograma un boleto
- **THEN** se entrega el PDF del boleto reprogramado como hasta ahora, con el contenido y nombre que define esta capacidad

#### Scenario: Panel de clientes
- **WHEN** un cliente abre el PDF de uno de sus boletos desde el panel de clientes
- **THEN** el PDF se muestra en línea con el contenido y nombre que define esta capacidad
