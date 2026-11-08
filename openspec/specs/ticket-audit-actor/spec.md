# ticket-audit-actor Specification

## Purpose
Define cómo el Historial de boletos del panel de administración registra y muestra quién hizo cada reprogramación (un usuario del panel o un cliente desde su panel) y cómo se filtra la lista por "Realizado por".

## Requirements

### Requirement: Autor de cada reprogramación
Cada reprogramación de un boleto SHALL guardar quién la hizo como un dato propio, sin depender de que el usuario exista: un usuario del panel de administración o un cliente. La hecha desde el panel de clientes MUST quedar como de ese cliente, y la hecha desde administración MUST quedar como de ese usuario. El autor MUST conservarse aunque después se borre del todo al usuario o al cliente.

#### Scenario: Reprogramación desde el panel de clientes
- **WHEN** un cliente elige la fecha de su boleto sin fecha desde su panel
- **THEN** la reprogramación queda registrada como hecha por ese cliente, sin usuario del panel

#### Scenario: Reprogramación desde administración
- **WHEN** un vendedor reprograma un boleto desde el panel de administración
- **THEN** la reprogramación queda registrada como hecha por ese usuario

#### Scenario: Usuario borrado del todo
- **GIVEN** una reprogramación hecha por un usuario
- **WHEN** ese usuario se borra definitivamente del sistema
- **THEN** la reprogramación sigue figurando como hecha por un usuario y no pasa a ser de un cliente

### Requirement: Autor de las reprogramaciones anteriores
Al aplicarse el cambio, las reprogramaciones ya registradas SHALL quedar con su autor: las que tienen usuario MUST quedar como de usuario, y las que no tienen usuario MUST quedar como de cliente. A las de cliente se les MUST enlazar el cliente cuando coincide el DNI del pasajero del boleto con el de un cliente del padrón; si no hay coincidencia quedan como de cliente sin enlace.

#### Scenario: Reprogramación vieja con usuario
- **GIVEN** una reprogramación anterior al cambio con usuario
- **WHEN** se aplica el cambio
- **THEN** queda como hecha por un usuario

#### Scenario: Reprogramación vieja sin usuario
- **GIVEN** una reprogramación anterior al cambio sin usuario, de un pasajero cuyo DNI es el de un cliente del padrón
- **WHEN** se aplica el cambio
- **THEN** queda como hecha por un cliente, enlazada a ese cliente

#### Scenario: Sin cliente que coincida
- **GIVEN** una reprogramación anterior sin usuario cuyo pasajero no está en el padrón
- **WHEN** se aplica el cambio
- **THEN** queda como hecha por un cliente, sin enlace

### Requirement: Autor en el detalle del Historial
El detalle de cada reprogramación en el Historial SHALL mostrar "Realizado por" con el autor: el nombre y el usuario de acceso del usuario del panel; "Cliente" seguido del nombre del cliente cuando se lo conoce, o solo "Cliente" cuando no; y "Usuario eliminado del sistema" únicamente cuando la hizo un usuario que ya no existe. MUST NOT decir "Usuario eliminado del sistema" para un cambio de un cliente.

#### Scenario: Cambio de un cliente
- **GIVEN** una reprogramación hecha por la clienta Ana García desde su panel
- **WHEN** el personal abre el detalle del boleto en el Historial
- **THEN** ve "Realizado por: Cliente Ana García"

#### Scenario: Cliente no identificable
- **GIVEN** una reprogramación de cliente sin enlace a un cliente del padrón
- **WHEN** el personal abre el detalle
- **THEN** ve "Realizado por: Cliente"

#### Scenario: Cambio de un usuario
- **WHEN** el personal abre el detalle de una reprogramación hecha por Carolina
- **THEN** ve su nombre y su usuario de acceso

#### Scenario: Usuario que ya no existe
- **GIVEN** una reprogramación hecha por un usuario borrado del todo
- **WHEN** el personal abre el detalle
- **THEN** ve "Realizado por: Usuario eliminado del sistema"

### Requirement: Filtro "Realizado por" en el Historial
La tabla del Historial SHALL ofrecer el filtro "Realizado por" con las opciones "Clientes" y "Vendedores". Sin elegir nada MUST mostrar todo el historial. El filtro activo MUST mostrar su indicador y conservarse en la sesión como los demás filtros.

#### Scenario: Sin elegir
- **GIVEN** boletos con cambios de clientes y de usuarios
- **WHEN** el usuario no elige nada en "Realizado por"
- **THEN** la lista muestra todos

#### Scenario: Indicador
- **WHEN** el usuario elige "Vendedores"
- **THEN** el filtro activo muestra "Realizado por: Vendedores"

### Requirement: Qué cuenta como Clientes y como Vendedores
"Clientes" MUST mostrar los boletos con al menos una reprogramación hecha por un cliente. "Vendedores" MUST mostrar los boletos con al menos una reprogramación hecha por un usuario, una edición de datos o una eliminación, sin importar el rol de ese usuario. Un boleto con cambios de ambos MUST aparecer en los dos filtros.

#### Scenario: Clientes
- **GIVEN** un boleto reprogramado por un cliente y otro reprogramado por un vendedor
- **WHEN** el usuario elige "Clientes"
- **THEN** la lista muestra solo el primero

#### Scenario: Vendedores de cualquier rol
- **GIVEN** un boleto reprogramado por un administrador, otro por un vendedor, uno editado y uno eliminado por usuarios, y uno reprogramado por un cliente
- **WHEN** el usuario elige "Vendedores"
- **THEN** la lista muestra los cuatro primeros y no el del cliente

#### Scenario: Cambios de los dos
- **GIVEN** un boleto reprogramado una vez por un cliente y otra por un vendedor
- **WHEN** el usuario elige "Clientes" y después "Vendedores"
- **THEN** el boleto aparece en los dos casos

#### Scenario: Usuario borrado no cuenta como cliente
- **GIVEN** una reprogramación hecha por un usuario que se borró del todo
- **WHEN** el usuario elige "Clientes"
- **THEN** ese boleto no aparece, y con "Vendedores" sí

### Requirement: El filtro "Realizado por" depende del panel de clientes
El filtro "Realizado por" SHALL mostrarse y aplicarse solo cuando el parámetro "Panel de clientes activo" está encendido. Con el panel apagado o sin configurar, MUST NOT mostrarse en el Historial y MUST NOT filtrar la lista, aunque quede un valor elegido guardado en la sesión. Al encender de nuevo el panel el filtro MUST volver a estar disponible.

#### Scenario: Panel activado
- **GIVEN** el parámetro "Panel de clientes activo" encendido
- **WHEN** el usuario abre el Historial
- **THEN** ve el filtro "Realizado por"

#### Scenario: Panel apagado
- **GIVEN** el parámetro apagado o sin configurar
- **WHEN** el usuario abre el Historial
- **THEN** no ve el filtro "Realizado por"

#### Scenario: Valor guardado con el panel apagado
- **GIVEN** un usuario que había elegido "Clientes" y después se apagó el panel de clientes
- **WHEN** vuelve al Historial
- **THEN** la lista muestra todo el historial, sin ese filtro ni su indicador

#### Scenario: Encender de nuevo
- **WHEN** se vuelve a encender el panel de clientes
- **THEN** el filtro "Realizado por" vuelve a mostrarse y a filtrar
