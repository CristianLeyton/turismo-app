# Spec Delta

## MODIFIED Requirements

### Requirement: Enviar por WhatsApp desde el detalle del boleto
El "Ver boleto" del panel admin SHALL ofrecer la acción "Enviar", con el ícono de WhatsApp de la aplicación, que abre WhatsApp en una pestaña nueva hacia el teléfono del boleto con el mensaje de WhatsApp de Parámetros y `{link}` reemplazado por el link del boleto. El teléfono es el del pasajero del boleto y, si no tiene, el que el padrón tenga por su DNI. La acción MUST verse solo con el parámetro "Descargar PDF automáticamente al vender" desactivado, el boleto vigente y algún teléfono.

#### Scenario: Pasajero con teléfono
- **GIVEN** el parámetro de descarga automática desactivado y un boleto vigente cuyo pasajero tiene el teléfono 3881234567
- **WHEN** un usuario abre el detalle del boleto y toca "Enviar"
- **THEN** se abre en una pestaña nueva WhatsApp hacia 543881234567 con el mensaje y el link del boleto

#### Scenario: Teléfono del padrón
- **GIVEN** el parámetro desactivado y un pasajero sin teléfono cuyo DNI tiene el teléfono 3882222222 en el padrón
- **WHEN** un usuario abre el detalle del boleto
- **THEN** ve "Enviar", dirigido a 543882222222

#### Scenario: Sin teléfono
- **GIVEN** el parámetro desactivado y un pasajero sin teléfono ni en la venta ni en el padrón
- **WHEN** un usuario abre el detalle del boleto
- **THEN** no ve "Enviar"

#### Scenario: Descarga automática activada o boleto eliminado
- **WHEN** el parámetro está activado o sin configurar, o el boleto está eliminado
- **THEN** el detalle no muestra "Enviar"

#### Scenario: Ícono y nombre accesible
- **GIVEN** un boleto vigente con teléfono y el parámetro desactivado
- **WHEN** un usuario abre el detalle del boleto
- **THEN** la acción muestra el ícono de WhatsApp de la aplicación y el texto "Enviar", ambos en blanco, y los lectores de pantalla la anuncian como "Enviar por WhatsApp"
