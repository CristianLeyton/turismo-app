# ticket-pdf Specification

## Purpose

Define qué PDF le corresponde a un boleto individual y con qué nombre se entrega, para que sea el mismo desde cualquier lugar donde se lo pida.

## Requirements

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

### Requirement: Aviso de elegir fecha en el PDF de un boleto sin fecha
El PDF de un boleto sin fecha SHALL mostrar el aviso de que, al adquirir el pasaje, queda en responsabilidad del pasajero elegir fecha y horario con al menos las horas de anticipación configuradas en "Anticipación mínima para elegir fecha" (por ejemplo, "con al menos 48hs de anticipación"), y que la asignación se realiza solo si la unidad cuenta con asientos disponibles. Sin configurar, el aviso MUST decir 24hs.

#### Scenario: Valor por defecto
- **GIVEN** que nadie configuró la anticipación
- **WHEN** se genera el PDF de un boleto sin fecha
- **THEN** el aviso dice "con al menos 24hs de anticipación"

#### Scenario: Valor configurado
- **GIVEN** la anticipación configurada en 48
- **WHEN** se genera el PDF de un boleto sin fecha
- **THEN** el aviso dice "con al menos 48hs de anticipación"

#### Scenario: Boleto con fecha
- **WHEN** se genera el PDF de un boleto que ya tiene fecha
- **THEN** no aparece el aviso de elegir fecha

### Requirement: El aviso refleja el valor vigente
El número de horas del aviso MUST ser el mismo en todos los PDF del boleto y MUST reflejar el valor vigente al generarlo, sin importar si el panel de clientes está activo. Con 0 horas, el aviso MUST omitir la condición de anticipación y mantener la aclaración sobre los asientos disponibles. Las condiciones de cambios y devoluciones del PDF MUST NOT cambiar con este valor.

#### Scenario: Cambio de configuración
- **GIVEN** un boleto sin fecha cuyo PDF decía 24hs
- **WHEN** la empresa cambia la anticipación a 72 y se vuelve a generar el PDF
- **THEN** el aviso dice 72hs

#### Scenario: Sin anticipación
- **GIVEN** la anticipación configurada en 0
- **WHEN** se genera el PDF de un boleto sin fecha
- **THEN** el aviso no menciona una anticipación mínima y conserva la aclaración sobre los asientos disponibles

#### Scenario: Panel de clientes apagado
- **GIVEN** el panel de clientes apagado y la anticipación guardada en 48
- **WHEN** se genera el PDF de un boleto sin fecha
- **THEN** el aviso dice 48hs

#### Scenario: Política de cambios y devoluciones
- **WHEN** se genera cualquier PDF de boleto con la anticipación configurada en un valor distinto de 24
- **THEN** las condiciones de cambios y devoluciones del PDF no cambian
