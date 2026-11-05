# ticket-detail Specification

## Purpose

Define qué muestra el "Ver boleto" del panel admin al personal y cómo accede desde ahí al PDF del boleto, sin depender de los links públicos que se entregan al pasajero.

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
La vista previa del detalle SHALL mostrar el PDF embebido cuando el navegador puede mostrar PDFs en línea en pantalla de escritorio. Cuando no puede, MUST mostrar en lugar del visor una tarjeta con el nombre del archivo y las acciones "Abrir PDF" (en una pestaña nueva) y "Descargar PDF".

#### Scenario: Escritorio
- **WHEN** el usuario abre el detalle en un navegador de escritorio con visor de PDF
- **THEN** ve el PDF embebido en la sección "Vista previa"

#### Scenario: Celular
- **WHEN** el usuario abre el detalle en un celular sin visor de PDF integrado
- **THEN** no ve un visor en blanco: ve una tarjeta con el nombre del archivo, "Abrir PDF" y "Descargar PDF"

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

### Requirement: La vista previa no crea links públicos
Abrir el detalle de un boleto o su vista previa MUST NOT generar ni guardar el token del link público del boleto ni el de su venta.

#### Scenario: Boleto sin link
- **GIVEN** un boleto que nunca tuvo link público
- **WHEN** un usuario abre su detalle en el panel y se carga la vista previa
- **THEN** el boleto y su venta siguen sin token de link público

#### Scenario: Boleto con link existente
- **GIVEN** un boleto que ya tiene link público
- **WHEN** un usuario abre su detalle en el panel
- **THEN** el link público del boleto no cambia y sigue funcionando
