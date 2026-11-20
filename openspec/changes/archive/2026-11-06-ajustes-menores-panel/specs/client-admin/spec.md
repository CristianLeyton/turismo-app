# Spec Delta

## Purpose

Define cómo el personal administra a los clientes desde el panel: banearlos con un motivo que ve el vendedor, habilitarlos de nuevo y resetear su contraseña del panel de clientes.

## ADDED Requirements

### Requirement: Banear a un cliente con motivo obligatorio
La acción "Banear" de la lista de clientes SHALL abrir un modal que pide el motivo del baneo en un campo de texto obligatorio, precargado con el motivo o comentario que el cliente ya tenga guardado. Sin motivo, el modal MUST NOT banear al cliente y MUST mostrar el error "Ingresá el motivo del baneo.". Al confirmar, el cliente MUST quedar sin poder comprar boletos y el motivo MUST guardarse en su campo "Motivo / Comentarios", que es el que se le muestra al vendedor cuando intenta venderle. La acción "Habilitar" MUST NOT pedir motivo y MUST NOT borrar el motivo guardado. Ambas acciones MUST estar disponibles solo para quien tiene el permiso de banear clientes, tanto en el menú del celular como en los botones de escritorio.

#### Scenario: Banear con motivo
- **WHEN** el usuario banea a un cliente habilitado e ingresa "No se presentó a tres viajes"
- **THEN** el cliente queda baneado y su "Motivo / Comentarios" dice "No se presentó a tres viajes"

#### Scenario: Banear sin motivo
- **WHEN** el usuario intenta banear a un cliente dejando el motivo vacío
- **THEN** el modal muestra "Ingresá el motivo del baneo." y el cliente sigue habilitado

#### Scenario: Motivo precargado
- **GIVEN** un cliente habilitado que ya tiene un comentario guardado
- **WHEN** el usuario abre el modal de "Banear"
- **THEN** el campo del motivo muestra ese comentario y se puede editar

#### Scenario: Habilitar no pide motivo
- **GIVEN** un cliente baneado con motivo
- **WHEN** el usuario lo habilita
- **THEN** el modal solo pide confirmación, el cliente vuelve a poder comprar y el motivo queda guardado

### Requirement: Resetear la contraseña de un cliente
La acción "Resetear contraseña" de la lista de clientes SHALL mostrarse solo si el parámetro "Panel de clientes activo" está encendido y el usuario tiene el permiso de resetear contraseñas de clientes, tanto en el menú del celular como en los botones de escritorio. Con el panel apagado, la acción MUST NOT mostrarse ni poder ejecutarse. El modal y la notificación MUST mostrar los textos con tildes y signos reales (nunca códigos como `ñ`), en español con voseo, explicando que el cliente va a entrar con su DNI como contraseña y que en ese ingreso va a tener que elegir una nueva. Si el cliente todavía no eligió su propia contraseña, el modal MUST avisar que ya entra con su DNI.

#### Scenario: Panel de clientes apagado
- **GIVEN** el parámetro "Panel de clientes activo" apagado
- **WHEN** un usuario con permiso abre la lista de clientes
- **THEN** no ve "Resetear contraseña" en ninguna fila

#### Scenario: Panel de clientes encendido
- **GIVEN** el parámetro encendido
- **WHEN** un usuario con permiso abre la lista de clientes
- **THEN** ve "Resetear contraseña" en cada fila

#### Scenario: Texto del modal con contraseña propia
- **GIVEN** el panel encendido y un cliente que ya eligió su propia contraseña
- **WHEN** el usuario abre "Resetear contraseña"
- **THEN** el modal pregunta si quiere resetear la contraseña del cliente, explica que va a entrar con su DNI y que va a tener que elegir una contraseña nueva, y no contiene códigos `\u`

#### Scenario: Resetear
- **WHEN** el usuario confirma el reseteo
- **THEN** el cliente puede entrar con su DNI, se le pide elegir una contraseña nueva, y la notificación lo explica sin códigos `\u`
