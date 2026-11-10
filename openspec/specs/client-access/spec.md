# client-access Specification

## Purpose

Cómo un cliente del padrón entra a su panel (`/clientes`), mantiene y cierra su sesión, y qué puede ver sin ella. La identidad del cliente es la que usan sus boletos, la asignación de fecha y el PDF.

## Requirements

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
La contraseña inicial de todo cliente SHALL ser su DNI. Un cliente creado sin contraseña MUST recibir esa contraseña inicial con la obligación de cambiarla. Mientras la obligación siga vigente, después de ingresar el cliente solo puede usar la página para elegir su contraseña o salir. Esa página MUST recibir al cliente por su nombre, explicar con tono cercano que elegir una contraseña propia protege sus boletos, pedir la contraseña nueva y su repetición con opción de verla mientras escribe, y mostrar los errores debajo de cada campo. Si guardar falla por una causa inesperada, MUST avisar que no se pudo guardar y cómo seguir, sin mostrar el mensaje de la excepción. El texto MUST NOT decir que la contraseña inicial es conocida por la boletería.

#### Scenario: Alta sin contraseña
- **WHEN** se da de alta un cliente sin indicar contraseña, por ejemplo con el alta rápida desde la venta
- **THEN** puede ingresar con su DNI como contraseña y se le exige elegir una propia

#### Scenario: Primer ingreso
- **WHEN** un cliente con la contraseña inicial ingresa y abre "Mis boletos"
- **THEN** se lo redirige a elegir su contraseña

#### Scenario: Contraseña elegida
- **WHEN** el cliente elige su contraseña
- **THEN** la obligación se levanta, puede usar el panel y los ingresos siguientes requieren la contraseña nueva

#### Scenario: Bienvenida por nombre
- **WHEN** un cliente llamado "Ana" llega a elegir su contraseña
- **THEN** la página lo saluda por su nombre y no dice que la contraseña inicial la conoce la boletería

#### Scenario: Contraseña corta
- **WHEN** el cliente escribe una contraseña de menos de 6 caracteres y guarda
- **THEN** no se guarda y ve el error debajo del campo

#### Scenario: Las contraseñas no coinciden
- **WHEN** el cliente escribe una repetición distinta de la contraseña nueva y guarda
- **THEN** no se guarda y ve el aviso de que no coinciden

#### Scenario: Ver la contraseña mientras se escribe
- **WHEN** el cliente toca el ojo de un campo de contraseña
- **THEN** ve el texto escrito, y al tocarlo de nuevo vuelve a ocultarlo

#### Scenario: Falla inesperada al guardar
- **WHEN** ocurre una excepción al guardar la contraseña
- **THEN** el cliente ve que no se pudo guardar y cómo seguir, sin el texto de la excepción, y el error queda registrado

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

### Requirement: Aviso de acceso de clientes en el ingreso de administración
El ingreso de `/admin` SHALL mostrar, debajo del formulario, el aviso "¿Sos cliente? Tus boletos están en el panel de Clientes." con un enlace al ingreso de clientes (`/clientes/login`), solo cuando el panel de clientes está activado. Desactivado o sin configurar, el aviso MUST NOT mostrarse.

#### Scenario: Panel activado
- **WHEN** el panel de clientes está activado y alguien abre `/admin/login`
- **THEN** ve el aviso y el enlace lleva a `/clientes/login`

#### Scenario: Panel desactivado
- **WHEN** el panel de clientes está desactivado o sin configurar y alguien abre `/admin/login`
- **THEN** no ve el aviso
