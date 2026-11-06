# ticket-detail Specification

## Purpose

Define qué muestra el "Ver boleto" del panel admin al personal, cómo accede desde ahí al PDF del boleto por una dirección autenticada del panel y, en modo link, cómo comparte el link público del boleto por WhatsApp o copiándolo.

## Requirements

### Requirement: Vista previa del PDF en el detalle del boleto
El "Ver boleto" del panel admin SHALL mostrar una sección "Vista previa" con el PDF del boleto, ubicada después de los detalles de venta y antes del historial de ediciones. El resto del contenido y del diseño del detalle MUST quedar igual.

#### Scenario: Boleto vigente
- **WHEN** un usuario con permiso para ver boletos abre el detalle de un boleto vigente
- **THEN** ve la sección "Vista previa" debajo de los detalles de venta y, si el boleto tiene ediciones, el historial de ediciones debajo de la vista previa

#### Scenario: Boleto eliminado
- **WHEN** un usuario con permiso para ver boletos abre el detalle de un boleto eliminado
- **THEN** ve la sección "Vista previa" con el PDF de ese boleto

#### Scenario: Independiente del modo de entrega
- **WHEN** un usuario abre el detalle de un boleto, con el parámetro "Descargar PDF automáticamente al vender" activado o desactivado
- **THEN** ve la sección "Vista previa" en los dos casos

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

### Requirement: Acceso autenticado al PDF del boleto
El PDF que usa la vista previa SHALL servirse por una dirección del panel que exige una sesión iniciada en el panel admin y el permiso para ver boletos. MUST aceptar boletos eliminados, y MUST ofrecer el PDF en línea para la vista previa y como descarga.

#### Scenario: Usuario con permiso
- **WHEN** un usuario logueado en el panel con permiso para ver boletos pide el PDF de un boleto
- **THEN** recibe el PDF del boleto

#### Scenario: Sin sesión
- **WHEN** alguien sin sesión en el panel pide el PDF de un boleto por esa dirección
- **THEN** no recibe el PDF

#### Scenario: Usuario sin permiso
- **WHEN** un usuario logueado sin permiso para ver boletos pide el PDF de un boleto
- **THEN** recibe un acceso denegado y no el PDF

#### Scenario: Boleto inexistente
- **WHEN** un usuario con permiso pide el PDF de un boleto que no existe
- **THEN** recibe un 404

#### Scenario: Descarga
- **WHEN** el usuario toca "Descargar PDF" en la vista previa
- **THEN** el navegador descarga el PDF con el nombre de archivo del boleto

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

### Requirement: Observación en los detalles de venta
Con el parámetro "Observaciones en la venta" prendido, el "Ver boleto" del panel admin SHALL mostrar la observación del boleto dentro de "Detalles de venta", con el texto completo, cuando el boleto la tiene. Con el parámetro apagado, MUST NOT mostrarla. Cuando no la tiene, MUST NOT mostrar un recuadro vacío. La sección "Vista previa" MUST NOT incluir la observación, porque muestra el PDF del pasajero.

#### Scenario: Boleto con observación
- **GIVEN** un boleto con la observación "Baja en el peaje de Metán"
- **WHEN** un usuario con permiso para ver boletos abre su detalle
- **THEN** ve "Baja en el peaje de Metán" en "Detalles de venta"

#### Scenario: Boleto sin observación
- **WHEN** un usuario abre el detalle de un boleto sin observación
- **THEN** "Detalles de venta" no muestra la observación ni un recuadro vacío

#### Scenario: Vista previa
- **GIVEN** un boleto con observación
- **WHEN** el usuario mira la sección "Vista previa" del detalle
- **THEN** el PDF mostrado no incluye la observación

#### Scenario: Parámetro apagado
- **GIVEN** el parámetro apagado y un boleto con observación
- **WHEN** un usuario abre su detalle
- **THEN** "Detalles de venta" no muestra la observación
