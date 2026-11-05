# Spec Delta

## ADDED Requirements

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
- **THEN** la página se muestra normalmente

### Requirement: Aviso de acceso de clientes en el ingreso de administración
El ingreso de `/admin` SHALL mostrar, debajo del formulario, el aviso "¿Sos cliente? Tus boletos están en el panel de Clientes." con un enlace al ingreso de clientes (`/clientes/login`), solo cuando el panel de clientes está activado. Desactivado o sin configurar, el aviso MUST NOT mostrarse.

#### Scenario: Panel activado
- **WHEN** el panel de clientes está activado y alguien abre `/admin/login`
- **THEN** ve el aviso y el enlace lleva a `/clientes/login`

#### Scenario: Panel desactivado
- **WHEN** el panel de clientes está desactivado o sin configurar y alguien abre `/admin/login`
- **THEN** no ve el aviso
