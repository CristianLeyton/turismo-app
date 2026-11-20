# Spec Delta

## MODIFIED Requirements

### Requirement: Ver boleto desde el panel de clientes
"Ver boleto" en "Mis boletos" y en la notificación de "Elegir fecha" SHALL abrir en una pestaña nueva lo que corresponde al parámetro "Descargar PDF automáticamente al vender". Con el parámetro desactivado, MUST abrir el link del boleto, el mismo que recibe el pasajero, que muestra su PDF. Con el parámetro activado o sin configurar, MUST abrir el PDF del boleto, como hasta ahora. En los dos casos, el cliente MUST tener sesión y el boleto MUST ser de su DNI.

#### Scenario: Parámetro desactivado
- **WHEN** el parámetro de descarga automática está desactivado y un cliente con sesión toca "Ver boleto" en uno de sus boletos
- **THEN** se abre el link de ese boleto, que muestra su PDF

#### Scenario: Parámetro activado
- **WHEN** el parámetro de descarga automática está activado y un cliente con sesión toca "Ver boleto" en uno de sus boletos
- **THEN** se abre el PDF del boleto

#### Scenario: Parámetro sin configurar
- **WHEN** nadie cambió el parámetro y un cliente con sesión toca "Ver boleto"
- **THEN** se abre el PDF del boleto, como con el parámetro activado

#### Scenario: Después de elegir fecha
- **WHEN** el parámetro está desactivado y el cliente toca "Ver boleto" en la notificación de "¡Boleto asignado!"
- **THEN** se abre el link de ese boleto, cuyo PDF ya muestra la fecha, el horario y el asiento asignados

#### Scenario: Boleto de otro cliente
- **WHEN** el parámetro está desactivado y un cliente con sesión pide ver un boleto de otro DNI a través del panel de clientes
- **THEN** no se le muestra el boleto ni el link

#### Scenario: Sin sesión
- **WHEN** alguien sin sesión pide ver un boleto a través del panel de clientes
- **THEN** no se le muestra el boleto ni el link

### Requirement: Panel de clientes disponible solo si está activado
El panel de clientes SHALL existir solo cuando el parámetro "Panel de clientes activo" está activado. Desactivado o sin configurar, toda página de `/clientes`, incluido el ingreso, y las rutas `clientes/ingresar`, `clientes/salir`, `clientes/boletos/{boleto}` y `clientes/boletos/{boleto}/pdf` MUST responder 404, con o sin sesión de cliente. Los demás requisitos de esta capacidad aplican solo con el panel activado.

#### Scenario: Parámetro sin configurar
- **WHEN** nadie configuró el parámetro y alguien abre `/clientes/login`
- **THEN** recibe un 404

#### Scenario: Panel desactivado
- **WHEN** el panel está desactivado y alguien abre `/clientes`, `/clientes/ingresar` o pide el PDF de un boleto en `/clientes/boletos/{boleto}/pdf`
- **THEN** recibe un 404

#### Scenario: Cliente con sesión al desactivar
- **GIVEN** un cliente con sesión iniciada en el panel
- **WHEN** se desactiva el panel y el cliente abre "Mis boletos" o "Ver boleto"
- **THEN** recibe un 404

#### Scenario: Panel activado
- **WHEN** el panel está activado y alguien sin sesión abre `/clientes`
- **THEN** se lo redirige a `/clientes/login`, como hasta ahora

#### Scenario: El resto del sitio no cambia
- **WHEN** el panel está desactivado y alguien abre el link público de un boleto en `/boletos/{token}`
- **THEN** recibe el PDF del boleto normalmente
