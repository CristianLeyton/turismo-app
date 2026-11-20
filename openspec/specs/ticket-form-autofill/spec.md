# ticket-form-autofill Specification

## Purpose

Evita que el navegador complete con datos guardados los formularios de venta y edición de boletos, para que los datos de cada pasajero se escriban a mano o vengan del padrón de clientes.

## Requirements

### Requirement: Venta sin autocompletado del navegador
Todos los campos de texto del wizard de venta SHALL indicarle al navegador que no los autocomplete ni sugiera valores guardados: datos del pasajero, del menor y de las mascotas, precio y observación. MUST incluir también los formularios que se abren desde el wizard ("Crear cliente" y "Nuevo método de pago").

#### Scenario: Datos del pasajero
- **WHEN** se muestra el paso "Pasajeros" de la venta
- **THEN** los campos DNI, apellido, nombre y teléfono de cada pasajero se entregan con `autocomplete="off"`

#### Scenario: Autocompletado de direcciones de Chrome
- **GIVEN** un Chrome con direcciones guardadas
- **WHEN** el vendedor hace foco en el apellido, el nombre o el teléfono
- **THEN** Chrome puede seguir ofreciendo sus direcciones guardadas, porque ignora `autocomplete="off"` en los campos que reconoce como datos personales; es una limitación conocida que este requisito no cubre

#### Scenario: Crear cliente desde la venta
- **WHEN** el vendedor abre "Crear cliente" desde el paso "Pasajeros"
- **THEN** todos sus campos de texto se entregan con `autocomplete="off"`

### Requirement: Edición sin autocompletado del navegador
Todos los campos de texto de "Editar boleto" SHALL indicarle al navegador que no los autocomplete: datos del pasajero, del menor y de las mascotas, precio, observación y motivo de la edición.

#### Scenario: Corregir el DNI
- **WHEN** se muestra "Editar boleto"
- **THEN** el campo DNI se entrega con `autocomplete="off"`

### Requirement: Padrón de clientes sin cambios
El autocompletado desde el padrón de clientes SHALL seguir funcionando: al escribir el DNI de un cliente registrado, el sistema MUST completar sus datos como hasta ahora. Bloquear el autocompletado del navegador MUST NOT afectar esta función.

#### Scenario: DNI del padrón
- **GIVEN** un cliente registrado en el padrón
- **WHEN** el vendedor escribe su DNI en el paso "Pasajeros"
- **THEN** el apellido, el nombre y el teléfono se completan con los datos del padrón
