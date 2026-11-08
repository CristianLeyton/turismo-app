# Spec Delta

## MODIFIED Requirements

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
