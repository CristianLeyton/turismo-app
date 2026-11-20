# Spec Delta

## ADDED Requirements

### Requirement: Anticipación mínima para elegir fecha
La pestaña "Clientes" SHALL ofrecer el campo "Anticipación mínima para elegir fecha", en horas, que muestra el valor guardado y, sin configurar, 24. El campo MUST aceptar solo números enteros de 0 a 720 y MUST ser obligatorio cuando se muestra, con los errores en español. Con 0 no se exige anticipación. El mismo permiso que gobierna la pestaña Clientes MUST gobernar el campo.

#### Scenario: Valor inicial
- **GIVEN** que nadie configuró la anticipación y el panel de clientes está activo
- **WHEN** un usuario con el permiso abre la pestaña Clientes
- **THEN** el campo muestra 24

#### Scenario: Cambiar las horas
- **WHEN** un usuario con el permiso escribe 48 y guarda
- **THEN** el parámetro queda en 48 y el panel de clientes y el PDF usan ese valor

#### Scenario: Valor inválido
- **WHEN** el usuario escribe un texto, un decimal, un número negativo o un número mayor que 720
- **THEN** no se guarda nada y el campo muestra el error debajo, en voseo

#### Scenario: Campo vacío
- **WHEN** el usuario deja el campo vacío con el panel de clientes activo y guarda
- **THEN** no se guarda nada y el campo pide ingresar las horas

#### Scenario: Cero horas
- **WHEN** el usuario escribe 0 y guarda
- **THEN** el parámetro queda en 0 y el panel de clientes no exige anticipación

### Requirement: La anticipación solo se ve con el panel de clientes activo
El campo "Anticipación mínima para elegir fecha" SHALL mostrarse solo mientras el interruptor "Panel de clientes activo" está encendido en el formulario, y MUST aparecer o desaparecer al cambiar el interruptor, sin guardar antes. Con el campo oculto, guardar la página MUST conservar el valor guardado y MUST NOT exigir ni validar el campo.

#### Scenario: Panel apagado
- **WHEN** el interruptor "Panel de clientes activo" está apagado
- **THEN** la pestaña Clientes no muestra la anticipación mínima

#### Scenario: Encender el interruptor
- **WHEN** el usuario enciende "Panel de clientes activo" sin guardar
- **THEN** el campo de anticipación aparece con el valor guardado

#### Scenario: Guardar con el panel apagado
- **GIVEN** la anticipación guardada en 48 y el panel de clientes apagado
- **WHEN** el usuario guarda la página sin mostrar el campo
- **THEN** la anticipación sigue en 48

#### Scenario: Pedido manipulado sin permiso
- **GIVEN** un usuario sin el permiso del panel de clientes
- **WHEN** guarda Parámetros enviando otra anticipación
- **THEN** la anticipación guardada no cambia
