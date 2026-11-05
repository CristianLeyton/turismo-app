# Spec Delta

## Purpose

Cómo un cliente del padrón entra a su panel (`/clientes`), mantiene y cierra su sesión, y qué puede ver sin ella. La identidad del cliente es la que usan sus boletos, la asignación de fecha y el PDF.

## ADDED Requirements

### Requirement: Ingreso con DNI y contraseña
El panel de clientes SHALL tener una página de ingreso en `/clientes/login` que pide DNI y contraseña. El DNI MUST normalizarse a solo dígitos antes de buscarlo, y solo entran clientes vigentes del padrón. Ante datos incorrectos, el mensaje MUST ser el mismo para un DNI desconocido y para una contraseña incorrecta.

#### Scenario: Ingreso correcto
- **WHEN** un cliente del padrón ingresa "11.223.344" y su contraseña
- **THEN** queda con sesión iniciada y va a "Mis boletos"

#### Scenario: Contraseña incorrecta
- **WHEN** un cliente ingresa su DNI con una contraseña que no es la suya
- **THEN** no inicia sesión y ve el mensaje genérico de credenciales incorrectas

#### Scenario: DNI desconocido
- **WHEN** alguien ingresa un DNI que no está en el padrón
- **THEN** no inicia sesión y ve el mismo mensaje genérico

#### Scenario: Cliente dado de baja
- **WHEN** un cliente eliminado del padrón ingresa su DNI y su contraseña
- **THEN** no inicia sesión

#### Scenario: Dirección anterior
- **WHEN** alguien abre `/clientes/ingresar`
- **THEN** se lo redirige a `/clientes/login`

### Requirement: Límite de intentos de ingreso
El ingreso SHALL limitar los intentos fallidos con el límite estándar del login del panel: superado, rechaza nuevos intentos durante un tiempo, aunque los datos sean correctos, e informa cuánto esperar.

#### Scenario: Demasiados intentos
- **WHEN** desde el mismo origen se superan los intentos permitidos en un minuto
- **THEN** el siguiente intento se rechaza, aun con la contraseña correcta, con el aviso de esperar

### Requirement: Sesión de cliente separada de la de administración
La sesión de un cliente MUST ser independiente de la de un usuario del panel de administración. Un cliente autenticado MUST NOT acceder al panel de administración, y un usuario de administración MUST NOT quedar autenticado como cliente.

#### Scenario: Cliente en el panel de administración
- **WHEN** un cliente con sesión iniciada abre `/admin`
- **THEN** se le pide el ingreso del panel de administración

#### Scenario: Las dos sesiones a la vez
- **WHEN** en el mismo navegador hay un vendedor con sesión en `/admin` y un cliente entra en `/clientes`
- **THEN** los dos siguen con sesión, cada uno en su panel

### Requirement: Contraseña inicial y cambio obligatorio
La contraseña inicial de todo cliente SHALL ser su DNI. Un cliente creado sin contraseña MUST recibir esa contraseña inicial con la obligación de cambiarla. Mientras la obligación siga vigente, después de ingresar el cliente solo puede usar la página para elegir su contraseña o salir.

#### Scenario: Alta sin contraseña
- **WHEN** se da de alta un cliente sin indicar contraseña, por ejemplo con el alta rápida desde la venta
- **THEN** puede ingresar con su DNI como contraseña y se le exige elegir una propia

#### Scenario: Primer ingreso
- **WHEN** un cliente con la contraseña inicial ingresa y abre "Mis boletos"
- **THEN** se lo redirige a elegir su contraseña

#### Scenario: Contraseña elegida
- **WHEN** el cliente elige su contraseña
- **THEN** la obligación se levanta, puede usar el panel y los ingresos siguientes requieren la contraseña nueva

### Requirement: Panel solo con sesión
Todas las páginas del panel de clientes, salvo el ingreso, y la descarga del PDF de un boleto MUST exigir un cliente con sesión iniciada. Cada cliente MUST ver y operar solo los boletos de su DNI.

#### Scenario: Sin sesión
- **WHEN** alguien sin sesión abre `/clientes`
- **THEN** se lo redirige a `/clientes/login`

#### Scenario: PDF sin sesión
- **WHEN** alguien sin sesión pide el PDF de un boleto en `/clientes/boletos/{boleto}/pdf`
- **THEN** no recibe el PDF

#### Scenario: Boleto de otro cliente
- **WHEN** un cliente con sesión pide el PDF de un boleto de otro DNI
- **THEN** no recibe el PDF

### Requirement: Recordarme y salida
El ingreso SHALL ofrecer "Recordarme", que mantiene la sesión del cliente después de cerrar el navegador. El panel muestra el menú de usuario con el nombre del cliente, el acceso a su perfil y "Salir". "Salir" MUST cerrar la sesión del cliente y llevarlo al ingreso, sin afectar una sesión de administración del mismo navegador.

#### Scenario: Salir
- **WHEN** un cliente con sesión toca "Salir" en el menú de usuario
- **THEN** queda sin sesión de cliente y va a `/clientes/login`

#### Scenario: Salir no cierra la sesión de administración
- **WHEN** en el mismo navegador hay un vendedor con sesión en `/admin` y el cliente toca "Salir"
- **THEN** el vendedor sigue con su sesión

#### Scenario: Recordarme
- **WHEN** un cliente ingresa con "Recordarme" marcado y su sesión de navegador termina
- **THEN** al volver a `/clientes` sigue autenticado

### Requirement: Perfil del cliente
El panel SHALL ofrecer, desde el menú de usuario, el perfil de Filament en `/clientes/perfil`. Ahí el cliente puede editar nombre, apellido y teléfono, y cambiar su contraseña. El DNI se muestra pero MUST NOT poder cambiarse. Para cambiar la contraseña MUST indicar la actual, y la nueva tiene al menos 6 caracteres y se repite para confirmarla.

#### Scenario: Editar datos personales
- **WHEN** el cliente cambia su nombre, apellido o teléfono y guarda
- **THEN** quedan guardados y el DNI no cambia

#### Scenario: Cambiar la contraseña
- **WHEN** el cliente escribe una contraseña nueva de 6 caracteres o más, la repite y escribe la actual correcta
- **THEN** la contraseña nueva queda guardada y los ingresos siguientes la requieren

#### Scenario: Contraseña actual incorrecta
- **WHEN** el cliente intenta cambiar la contraseña con una actual incorrecta
- **THEN** no se guarda y se avisa que la contraseña actual es incorrecta

#### Scenario: Teléfono inválido
- **WHEN** el cliente guarda un teléfono que no tiene entre 7 y 12 números
- **THEN** no se guarda y se avisa debajo del campo
