# Spec Delta

## ADDED Requirements

### Requirement: Enviar por WhatsApp desde el detalle del boleto
El "Ver boleto" del panel admin SHALL ofrecer la acción "Enviar por WhatsApp", que abre WhatsApp en una pestaña nueva hacia el teléfono del boleto con el mensaje de WhatsApp de Parámetros y `{link}` reemplazado por el link del boleto. El teléfono es el del pasajero del boleto y, si no tiene, el que el padrón tenga por su DNI. La acción MUST verse solo con el parámetro "Descargar PDF automáticamente al vender" desactivado, el boleto vigente y algún teléfono.

#### Scenario: Pasajero con teléfono
- **GIVEN** el parámetro de descarga automática desactivado y un boleto vigente cuyo pasajero tiene el teléfono 3881234567
- **WHEN** un usuario abre el detalle del boleto y toca "Enviar por WhatsApp"
- **THEN** se abre en una pestaña nueva WhatsApp hacia 543881234567 con el mensaje y el link del boleto

#### Scenario: Teléfono del padrón
- **GIVEN** el parámetro desactivado y un pasajero sin teléfono cuyo DNI tiene el teléfono 3882222222 en el padrón
- **WHEN** un usuario abre el detalle del boleto
- **THEN** ve "Enviar por WhatsApp", dirigido a 543882222222

#### Scenario: Sin teléfono
- **GIVEN** el parámetro desactivado y un pasajero sin teléfono ni en la venta ni en el padrón
- **WHEN** un usuario abre el detalle del boleto
- **THEN** no ve "Enviar por WhatsApp"

#### Scenario: Descarga automática activada o boleto eliminado
- **WHEN** el parámetro está activado o sin configurar, o el boleto está eliminado
- **THEN** el detalle no muestra "Enviar por WhatsApp"

### Requirement: Copiar el link desde la vista previa
Con el parámetro "Descargar PDF automáticamente al vender" desactivado y el boleto vigente, la sección "Vista previa" SHALL ofrecer "Copiar link", que copia al portapapeles el link público del boleto, el mismo que se envía por WhatsApp, y confirma "¡Copiado!" durante unos segundos. Con el visor embebido, el botón va en el encabezado de la sección, en la esquina opuesta al título. Con la tarjeta, va en la tarjeta.

#### Scenario: Escritorio
- **GIVEN** el parámetro desactivado y un boleto vigente
- **WHEN** el usuario abre el detalle en un navegador de escritorio con visor de PDF
- **THEN** ve "Copiar link" a la altura del título "Vista previa", en la otra esquina, y al tocarlo el link del boleto queda en el portapapeles

#### Scenario: Celular
- **GIVEN** el parámetro desactivado y un boleto vigente
- **WHEN** el usuario abre el detalle en un celular
- **THEN** ve la tarjeta con "Abrir PDF" y "Copiar link"

#### Scenario: Descarga automática activada
- **WHEN** el parámetro está activado o sin configurar
- **THEN** la vista previa no muestra "Copiar link"

## MODIFIED Requirements

### Requirement: Vista previa según el dispositivo
La vista previa del detalle SHALL mostrar el PDF embebido cuando el navegador puede mostrar PDFs en línea en pantalla de escritorio. Cuando no puede, MUST mostrar en lugar del visor una tarjeta con el nombre del archivo y las acciones "Abrir PDF" (en una pestaña nueva) y, al lado, "Copiar link" si el boleto tiene link para compartir (parámetro "Descargar PDF automáticamente al vender" desactivado y boleto vigente) o "Descargar PDF" si no lo tiene.

#### Scenario: Escritorio
- **WHEN** el usuario abre el detalle en un navegador de escritorio con visor de PDF
- **THEN** ve el PDF embebido en la sección "Vista previa"

#### Scenario: Celular
- **WHEN** el usuario abre el detalle en un celular sin visor de PDF integrado
- **THEN** no ve un visor en blanco: ve una tarjeta con el nombre del archivo, "Abrir PDF" y "Descargar PDF", o "Copiar link" si el boleto tiene link para compartir

#### Scenario: Navegación dentro del panel
- **WHEN** el usuario llega al detalle de un boleto navegando dentro del panel, sin recargar la página
- **THEN** la vista previa elige visor o tarjeta igual que al cargar la página desde cero

## RENAMED Requirements

- FROM: `### Requirement: La vista previa no crea links públicos`
- TO: `### Requirement: Links públicos al abrir el detalle`

## MODIFIED Requirements

### Requirement: Links públicos al abrir el detalle
Con el parámetro "Descargar PDF automáticamente al vender" desactivado, abrir el detalle de un boleto vigente SHALL generar y guardar el token de su link público si todavía no lo tiene. Con el parámetro activado o sin configurar, o con el boleto eliminado, abrir el detalle o su vista previa MUST NOT generar el token. Nunca se genera el token de la venta.

#### Scenario: Boleto sin link en modo link
- **GIVEN** el parámetro desactivado y un boleto vigente que nunca tuvo link público
- **WHEN** un usuario abre su detalle en el panel
- **THEN** el boleto queda con su token de link público y la venta sigue sin token

#### Scenario: Boleto sin link
- **GIVEN** el parámetro activado y un boleto que nunca tuvo link público
- **WHEN** un usuario abre su detalle en el panel y se carga la vista previa
- **THEN** el boleto y su venta siguen sin token de link público

#### Scenario: Boleto con link existente
- **GIVEN** un boleto que ya tiene link público
- **WHEN** un usuario abre su detalle en el panel
- **THEN** el link público del boleto no cambia y sigue funcionando
