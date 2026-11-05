# Spec Delta

## ADDED Requirements

### Requirement: Destino del logo según el panel de clientes
El logo de la barra superior de la página pública del link SHALL ser un enlace. Con el panel de clientes activado, MUST llevar al ingreso de clientes (`/clientes/login`). Desactivado o sin configurar, MUST llevar al inicio del sitio.

#### Scenario: Panel activado
- **WHEN** el panel de clientes está activado y alguien toca el logo en el link de un boleto
- **THEN** va a `/clientes/login`

#### Scenario: Panel desactivado
- **WHEN** el panel de clientes está desactivado o sin configurar y alguien toca el logo en el link de un boleto
- **THEN** va al inicio del sitio
