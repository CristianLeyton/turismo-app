# Spec Delta

## ADDED Requirements

### Requirement: Bienvenida con resumen de lo que se puede hacer
La página de primer ingreso del cliente SHALL, además de pedir la contraseña, explicar en pocas líneas qué podrá hacer en el panel: ver sus boletos, elegir fecha y asiento de los boletos sin fecha, y ver o descargar el boleto en PDF. El resumen MUST aparecer antes del formulario, usar el vocabulario del negocio y no depender de datos del cliente para mostrarse.

#### Scenario: Resumen visible en el primer ingreso
- **WHEN** un cliente con la contraseña inicial llega a elegir su contraseña
- **THEN** ve el saludo por su nombre, el resumen de lo que podrá hacer en el panel y el formulario para elegir la contraseña

#### Scenario: El resumen no cambia el flujo obligatorio
- **WHEN** el cliente está en la bienvenida y todavía no eligió su contraseña
- **THEN** sigue sin poder usar otras páginas del panel y solo puede guardar la contraseña o salir

### Requirement: Bienvenida legible en celular y escritorio
La bienvenida del primer ingreso MUST poder leerse y completarse en celular sin scroll horizontal, con el formulario alcanzable sin recorrer un bloque de texto largo, y en escritorio MUST mantener un ancho de lectura acotado. MUST respetar el modo claro y oscuro y el color principal configurado por la empresa.

#### Scenario: Celular
- **WHEN** el cliente abre la bienvenida desde un celular
- **THEN** el resumen se muestra en una sola columna, sin desbordar el ancho de la pantalla, y el formulario queda debajo

#### Scenario: Escritorio
- **WHEN** el cliente abre la bienvenida desde una computadora
- **THEN** el contenido ocupa un ancho acotado y centrado, con el resumen distribuido en columnas

#### Scenario: Modo oscuro y color de la empresa
- **WHEN** el dispositivo del cliente usa modo oscuro, o la empresa eligió otro color principal
- **THEN** los textos e íconos del resumen se leen con buen contraste y toman el color principal configurado
