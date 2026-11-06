# Spec Delta

## Purpose

Define la observación interna de un boleto: cómo se carga al vender, cómo se edita, cuánto puede medir y en qué pantallas y archivos se muestra o nunca se muestra.

## ADDED Requirements

### Requirement: Observaciones apagadas por parámetro
Con el parámetro "Observaciones en la venta" apagado, que es su valor inicial, el sistema MUST NOT ofrecer el campo de observación en la venta ni en "Editar boleto", y MUST NOT mostrar observaciones en la lista de pasajeros del viaje (modal, PDF y Excel), en el detalle del boleto ni en la auditoría. Apagarlo MUST NOT borrar las observaciones guardadas: vuelven a verse al prenderlo. Los requisitos de este capability sobre carga y visualización aplican con el parámetro prendido.

#### Scenario: Venta con el parámetro apagado
- **GIVEN** el parámetro apagado
- **WHEN** el vendedor llega al paso "Resumen"
- **THEN** no ve el campo "Observaciones"

#### Scenario: Observación enviada salteando la interfaz
- **GIVEN** el parámetro apagado
- **WHEN** llega una venta con una observación forzada en el pedido
- **THEN** la venta se emite y sus boletos quedan sin observación

#### Scenario: Editar un boleto con el parámetro apagado
- **GIVEN** el parámetro apagado y un boleto con la observación "Baja en Metán"
- **WHEN** un usuario edita otro dato del boleto y guarda
- **THEN** no ve el campo de observación, el boleto conserva "Baja en Metán" y el historial no registra un cambio de observación

#### Scenario: Volver a prenderlo
- **GIVEN** un boleto con observación y el parámetro apagado
- **WHEN** un usuario con el permiso prende el parámetro
- **THEN** la observación vuelve a verse en el detalle del boleto y en la lista de pasajeros del viaje

### Requirement: Observaciones en el resumen de la venta
El paso "Resumen" del asistente de venta SHALL ofrecer el campo opcional "Observaciones", ubicado entre el resumen del viaje y la lista de pasajeros, con un contador de caracteres y una ayuda que indique que se guarda en todos los boletos de la venta. El campo MUST NOT ser obligatorio para finalizar la venta.

#### Scenario: Venta sin observación
- **WHEN** el vendedor llega al paso "Resumen", deja "Observaciones" vacío y toca "Finalizar"
- **THEN** la venta se emite normalmente y sus boletos quedan sin observación

#### Scenario: Ubicación del campo
- **WHEN** el vendedor abre el paso "Resumen"
- **THEN** ve "Observaciones" debajo del resumen del viaje y arriba de la lista de pasajeros

### Requirement: Largo máximo de la observación
La observación SHALL admitir hasta 280 caracteres, tanto al vender como al editar un boleto. Un texto más largo MUST rechazarse con un mensaje que indique el máximo, también si llega salteando la interfaz. Los espacios al principio y al final se descartan, y un texto vacío o con solo espacios MUST guardarse como sin observación.

#### Scenario: Observación de 280 caracteres
- **WHEN** el vendedor escribe una observación de exactamente 280 caracteres y finaliza la venta
- **THEN** la venta se emite y los boletos guardan la observación completa

#### Scenario: Observación demasiado larga
- **WHEN** el vendedor escribe una observación de 281 caracteres y toca "Finalizar"
- **THEN** la venta no se emite y ve un mensaje que indica que la observación no puede superar los 280 caracteres

#### Scenario: Solo espacios
- **WHEN** el vendedor escribe solo espacios en "Observaciones" y finaliza la venta
- **THEN** los boletos quedan sin observación

### Requirement: La observación de la venta se copia a todos sus boletos
Al emitir una venta con observación, el sistema SHALL guardar esa misma observación en cada boleto que crea la venta: los de ida y los de vuelta, para todos los pasajeros, incluidos los boletos sin fecha.

#### Scenario: Ida y vuelta con dos pasajeros
- **GIVEN** una venta de ida y vuelta con dos pasajeros y la observación "Paga el saldo al subir"
- **WHEN** el vendedor finaliza la venta
- **THEN** los cuatro boletos creados tienen la observación "Paga el saldo al subir"

#### Scenario: Tramo sin fecha
- **GIVEN** una venta de ida y vuelta con la vuelta sin fecha y una observación
- **WHEN** el vendedor finaliza la venta
- **THEN** el boleto de ida y el boleto pendiente de vuelta tienen la observación

### Requirement: Editar la observación de un boleto
"Editar boleto" SHALL permitir cargar, cambiar o borrar la observación del boleto, con el mismo límite de 280 caracteres. El cambio MUST afectar solo al boleto editado: los demás boletos de la misma venta conservan su observación. El cambio MUST quedar en el "Historial de ediciones" del boleto con la etiqueta "Observación", el valor anterior y el nuevo.

#### Scenario: Cambiar la observación de un boleto
- **GIVEN** una venta con dos boletos que tienen la observación "Baja en Metán"
- **WHEN** un usuario edita el primer boleto y cambia la observación a "Baja en Rosario de la Frontera"
- **THEN** el primer boleto tiene la observación nueva y el segundo sigue con "Baja en Metán"

#### Scenario: Borrar la observación
- **WHEN** un usuario edita un boleto con observación y vacía el campo
- **THEN** el boleto queda sin observación

#### Scenario: Historial de ediciones
- **WHEN** un usuario cambia la observación de un boleto
- **THEN** el "Historial de ediciones" del boleto muestra el cambio de "Observación" con el valor anterior y el nuevo

### Requirement: La reprogramación conserva la observación
Reprogramar un boleto, o asignarle fecha a un boleto sin fecha, MUST conservar su observación sin cambios.

#### Scenario: Asignar fecha a un boleto pendiente
- **GIVEN** un boleto sin fecha con la observación "Lleva silla de ruedas"
- **WHEN** un usuario le asigna fecha y horario
- **THEN** el boleto sigue con la observación "Lleva silla de ruedas" y aparece con ella en la lista de pasajeros del viaje asignado

### Requirement: Observación en la auditoría de boletos
El detalle de un boleto en la auditoría SHALL mostrar su observación cuando la tiene. Cuando no la tiene, MUST NOT mostrar un recuadro vacío.

#### Scenario: Boleto auditado con observación
- **WHEN** un usuario con acceso a la auditoría abre el detalle de un boleto con observación
- **THEN** ve la observación del boleto

#### Scenario: Boleto auditado sin observación
- **WHEN** abre el detalle de un boleto sin observación
- **THEN** no ve la sección de observación

### Requirement: La observación es solo interna
La observación MUST NOT mostrarse a los pasajeros: no aparece en el PDF del boleto, en la vista previa de ese PDF dentro del panel, en la página pública del boleto ni en el panel de clientes.

#### Scenario: PDF del pasajero
- **GIVEN** un boleto con la observación "Paga el saldo al subir"
- **WHEN** se genera el PDF del boleto
- **THEN** el PDF no contiene ese texto

#### Scenario: Página pública del boleto
- **GIVEN** un boleto con observación y link público
- **WHEN** alguien abre el link público del boleto
- **THEN** la página no muestra la observación

#### Scenario: Panel de clientes
- **GIVEN** un cliente con un boleto que tiene observación
- **WHEN** el cliente abre "Mis boletos"
- **THEN** no ve la observación
