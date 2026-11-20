# Spec Delta

## ADDED Requirements

### Requirement: El ingreso solo respeta URLs propias del panel de clientes
Tras un ingreso correcto en el panel de clientes, el destino SHALL ser la URL prevista guardada en la sesión ("intended") solo si esa URL pertenece al panel de clientes (ruta bajo `/clientes`), incluido el PDF de un boleto. Una URL almacenada por otra parte del sitio (la raíz, el panel de administración, el link compartible de un boleto) MUST NOT usarse como destino del ingreso: en ese caso el cliente va a "Mis boletos". La gestión del `intended` del panel de administración no cambia.

#### Scenario: Llegó al ingreso de clientes desde el login de administración
- **WHEN** alguien abre la raíz del sitio, es enviado a `/admin` y su sesión queda con la URL prevista `/admin`, pasa por el aviso de clientes de `/admin/login` e ingresa correctamente en `/clientes/login`
- **THEN** va a "Mis boletos" y no recae en el login de administración

#### Scenario: URL prevista dentro del panel de clientes
- **WHEN** alguien sin sesión es redirigido desde una página propia del panel de clientes al ingreso, y luego ingresa correctamente
- **THEN** la sesión lo lleva de vuelta a esa página del panel

#### Scenario: URL prevista de una zona ajena al panel
- **WHEN** la URL prevista guardada en la sesión no es una ruta de `/clientes` (por ejemplo, la raíz, una página de `/admin` o un link compartible en `/boletos/...`) y un cliente ingresa correctamente
- **THEN** el ingreso lo lleva a "Mis boletos" y descarta esa URL prevista

#### Scenario: Cliente con sesión que abre el ingreso
- **WHEN** un cliente ya autenticado abre `/clientes/login` con una URL prevista guardada de una zona ajena al panel
- **THEN** se lo lleva a "Mis boletos" y no a esa URL ajena
