## ADDED Requirements

### Requirement: Ver boleto desde el panel de clientes
"Ver boleto" en "Mis boletos" y en la notificación de "Elegir fecha" SHALL abrir en una pestaña nueva lo que corresponde al parámetro "Descargar PDF automáticamente al vender". Con el parámetro desactivado, MUST abrir la página del link del boleto, la misma que recibe el pasajero. Con el parámetro activado o sin configurar, MUST abrir el PDF del boleto, como hasta ahora. En los dos casos, el cliente MUST tener sesión y el boleto MUST ser de su DNI.

#### Scenario: Parámetro desactivado
- **WHEN** el parámetro de descarga automática está desactivado y un cliente con sesión toca "Ver boleto" en uno de sus boletos
- **THEN** se abre la página del link de ese boleto

#### Scenario: Parámetro activado
- **WHEN** el parámetro de descarga automática está activado y un cliente con sesión toca "Ver boleto" en uno de sus boletos
- **THEN** se abre el PDF del boleto

#### Scenario: Parámetro sin configurar
- **WHEN** nadie cambió el parámetro y un cliente con sesión toca "Ver boleto"
- **THEN** se abre el PDF del boleto, como con el parámetro activado

#### Scenario: Después de elegir fecha
- **WHEN** el parámetro está desactivado y el cliente toca "Ver boleto" en la notificación de "¡Boleto asignado!"
- **THEN** se abre la página del link de ese boleto, que ya muestra la fecha, el horario y el asiento asignados

#### Scenario: Boleto de otro cliente
- **WHEN** el parámetro está desactivado y un cliente con sesión pide ver un boleto de otro DNI a través del panel de clientes
- **THEN** no se le muestra el boleto ni el link

#### Scenario: Sin sesión
- **WHEN** alguien sin sesión pide ver un boleto a través del panel de clientes
- **THEN** no se le muestra el boleto ni el link
