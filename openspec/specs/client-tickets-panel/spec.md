# client-tickets-panel Specification

## Purpose
Define la pantalla "Mis boletos" del panel de clientes: qué boletos ve el cliente y cómo los organiza, cómo se entera de lo pendiente y de su próximo viaje, cómo pide ayuda y cómo se le informan los errores al elegir fecha.

## Requirements

### Requirement: Pestañas de boletos con conteo
"Mis boletos" SHALL organizar los boletos del cliente en tres pestañas, cada una con la cantidad de boletos que contiene: "Pendientes" (boletos sin fecha asignada), "Próximos" (boletos con fecha de hoy o posterior) e "Historial" (boletos con fecha anterior a hoy). La pestaña inicial MUST ser "Pendientes" si el cliente tiene algún boleto sin fecha, y "Próximos" si no. Un boleto eliminado MUST NOT aparecer en ninguna pestaña ni en los conteos. Cada cliente MUST ver solo los boletos de su DNI.

#### Scenario: Conteos por pestaña
- **GIVEN** un cliente con 2 boletos sin fecha, 3 con fecha futura y 1 con fecha pasada
- **WHEN** abre "Mis boletos"
- **THEN** ve las pestañas "Pendientes" con 2, "Próximos" con 3 e "Historial" con 1

#### Scenario: Pestaña inicial con pendientes
- **GIVEN** un cliente con al menos un boleto sin fecha
- **WHEN** abre "Mis boletos"
- **THEN** la pestaña "Pendientes" está seleccionada

#### Scenario: Pestaña inicial sin pendientes
- **GIVEN** un cliente sin boletos sin fecha
- **WHEN** abre "Mis boletos"
- **THEN** la pestaña "Próximos" está seleccionada

#### Scenario: Viaje de hoy
- **WHEN** un boleto tiene fecha de hoy
- **THEN** aparece en "Próximos" y no en "Historial"

#### Scenario: Boleto eliminado
- **GIVEN** un boleto del cliente que el personal eliminó
- **WHEN** el cliente abre cualquier pestaña
- **THEN** el boleto no aparece y no suma en ningún conteo

#### Scenario: Boletos de otro cliente
- **WHEN** otro cliente tiene boletos con fechas pasadas y futuras
- **THEN** ninguno aparece en las pestañas ni en los conteos del cliente con sesión

### Requirement: Estado vacío por pestaña
Cuando una pestaña no tiene boletos, "Mis boletos" SHALL mostrar un mensaje propio de esa pestaña, en lugar de una tabla en blanco, que explique qué se vería ahí. El mensaje de "Pendientes" MUST indicar que no hay boletos esperando fecha.

#### Scenario: Sin pendientes
- **GIVEN** un cliente sin boletos sin fecha
- **WHEN** abre la pestaña "Pendientes"
- **THEN** ve que no tiene boletos pendientes de fecha, sin tabla en blanco

#### Scenario: Cliente sin ningún boleto
- **GIVEN** un cliente que nunca compró un boleto
- **WHEN** abre cualquier pestaña
- **THEN** ve el mensaje vacío de esa pestaña

### Requirement: Aviso de boletos pendientes de fecha
"Mis boletos" SHALL mostrar, encima de las pestañas y en cualquiera de ellas, un aviso de los boletos sin fecha, solo si el cliente tiene alguno. Con uno solo, el aviso MUST nombrar su tramo y su número y ofrecer "Elegir fecha", que abre el formulario de ese boleto. Con dos o más, MUST decir cuántos son y ofrecer "Ver pendientes", que lleva a la pestaña "Pendientes" sin abrir ningún formulario, salvo que ya esté en ella. Sin boletos sin fecha no se muestra.

#### Scenario: Con boletos pendientes
- **GIVEN** un cliente con 2 boletos sin fecha
- **WHEN** abre "Mis boletos"
- **THEN** el aviso dice "Tenés 2 boletos sin fecha" y ofrece "Ver pendientes", no "Elegir fecha"

#### Scenario: Un solo pendiente
- **GIVEN** un cliente con el boleto N°39114 sin fecha, de Yrigoyen a Salta
- **WHEN** abre "Mis boletos"
- **THEN** el aviso lo dice en singular, con su tramo y su número, y ofrece "Elegir fecha"

#### Scenario: Botón del aviso
- **GIVEN** un cliente con un solo boleto sin fecha
- **WHEN** toca "Elegir fecha" en el aviso
- **THEN** se abre el formulario de "Elegir fecha" de ese boleto

#### Scenario: Ver pendientes
- **GIVEN** un cliente con 2 boletos sin fecha que está en la pestaña "Próximos"
- **WHEN** toca "Ver pendientes"
- **THEN** pasa a la pestaña "Pendientes" y no se abre ningún formulario

#### Scenario: Ya en Pendientes
- **GIVEN** un cliente con 2 boletos sin fecha que está en la pestaña "Pendientes"
- **WHEN** mira el aviso
- **THEN** ve la cantidad pero no el botón "Ver pendientes"

#### Scenario: Aviso en cualquier pestaña
- **GIVEN** un cliente con boletos sin fecha
- **WHEN** está en la pestaña "Pendientes", "Próximos" o "Historial"
- **THEN** ve el aviso en las tres

#### Scenario: Sin pendientes
- **GIVEN** un cliente sin boletos sin fecha
- **WHEN** abre "Mis boletos"
- **THEN** no ve el aviso

### Requirement: Tarjeta de próximo viaje
"Mis boletos" SHALL mostrar, encima de las pestañas, una tarjeta "Próximo viaje" con el boleto del cliente cuyo viaje tiene la fecha y el horario más cercanos desde hoy: recorrido "Origen → Destino", fecha, horario y asiento, y un acceso a "Ver boleto". Si hay varios boletos para el mismo día y horario, MUST mostrarse el de menor número. Sin viajes de hoy en adelante, la tarjeta MUST NOT mostrarse. Los boletos sin fecha MUST NOT contar como próximo viaje.

#### Scenario: Varios viajes futuros
- **GIVEN** un cliente con viajes el 15/11 a las 08:30 y el 20/11 a las 21:00
- **WHEN** abre "Mis boletos"
- **THEN** la tarjeta muestra el viaje del 15/11 a las 08:30 con su recorrido y asiento

#### Scenario: Sin viajes futuros
- **GIVEN** un cliente con solo viajes pasados y boletos sin fecha
- **WHEN** abre "Mis boletos"
- **THEN** no ve la tarjeta de próximo viaje

#### Scenario: Ver boleto desde la tarjeta
- **WHEN** el cliente toca "Ver boleto" en la tarjeta
- **THEN** se abre en una pestaña nueva lo mismo que abre "Ver boleto" de la tabla para ese boleto

#### Scenario: Boleto eliminado
- **GIVEN** que el boleto más cercano del cliente fue eliminado por el personal
- **WHEN** el cliente abre "Mis boletos"
- **THEN** la tarjeta muestra el siguiente viaje vigente

### Requirement: Acciones de cada boleto
Cada fila de boletos SHALL ofrecer "Ver boleto" y, solo para un boleto sin fecha que el cliente puede operar, "Elegir fecha". En celular las acciones MUST estar en un menú de tres puntos con sus nombres visibles, y en escritorio MUST estar disponibles como botones, con "Elegir fecha" siempre a la vista. Tocar la fila MUST NOT abrir nada. El cliente MUST NOT poder elegir fecha de un boleto que ya tiene fecha, ni de uno que no es suyo.

#### Scenario: Boleto sin fecha
- **WHEN** el cliente mira un boleto sin fecha en "Pendientes"
- **THEN** puede usar "Elegir fecha" y "Ver boleto"

#### Scenario: Boleto con fecha
- **WHEN** el cliente mira un boleto con fecha en "Próximos" o "Historial"
- **THEN** solo puede usar "Ver boleto" y no ve "Elegir fecha"

#### Scenario: Acciones en celular
- **WHEN** el cliente abre "Mis boletos" desde un celular
- **THEN** cada fila tiene un menú de tres puntos con "Elegir fecha" (si corresponde) y "Ver boleto"

#### Scenario: Tocar la fila
- **WHEN** el cliente toca una fila fuera de sus acciones
- **THEN** no se abre ningún formulario ni enlace

### Requirement: Errores al elegir fecha sin detalles técnicos
Si elegir la fecha de un boleto falla por una causa inesperada, "Mis boletos" SHALL avisar con una notificación de error que explique que no se pudo asignar la fecha y que se vuelva a intentar o se contacte a boletería, sin mostrar el mensaje de la excepción. El error MUST registrarse para el personal. Los errores de validación esperados, como una fecha sin servicio o un asiento ocupado, MUST seguir mostrándose con su motivo, y el formulario MUST quedar abierto con lo que el cliente ya eligió.

#### Scenario: Falla inesperada
- **WHEN** ocurre una excepción al asignar la fecha
- **THEN** el cliente ve que no se pudo asignar la fecha y cómo seguir, sin el texto de la excepción, y el error queda registrado

#### Scenario: Fecha sin servicio
- **WHEN** el cliente confirma una fecha sin servicio
- **THEN** ve el motivo de la validación y el formulario sigue abierto

### Requirement: Reconfirmación al elegir fecha
"Elegir fecha" SHALL ser un asistente de dos pasos: en el primero el cliente elige fecha, horario y asiento, y en el segundo ve el resumen y la pregunta "¿Estás seguro de confirmar esta fecha?", con el aviso de que no podrá cambiarla de nuevo y de que, para modificarla, debe comunicarse con boletería. La fecha MUST asignarse solo al confirmar en el segundo paso, con el botón "Sí, confirmar fecha".

#### Scenario: Dos pasos
- **WHEN** el cliente abre "Elegir fecha" de un boleto sin fecha
- **THEN** ve el primer paso, "Elegí tu viaje", con fecha, horario y asiento

#### Scenario: Resumen y aviso
- **GIVEN** que el cliente eligió el 05/10/2026, un horario y el asiento 1
- **WHEN** pasa al segundo paso
- **THEN** ve el tramo, la fecha, el horario y el asiento elegidos, la pregunta "¿Estás seguro de confirmar esta fecha?" y el aviso de que no podrá cambiarla de nuevo

#### Scenario: Volver y corregir
- **WHEN** el cliente toca "Volver" en el segundo paso
- **THEN** vuelve al primer paso con lo que había elegido y puede cambiarlo

#### Scenario: Confirmar
- **WHEN** el cliente toca "Sí, confirmar fecha" en el segundo paso
- **THEN** la fecha, el horario y el asiento quedan asignados y "Elegir fecha" ya no se ofrece para ese boleto

#### Scenario: Cerrar sin confirmar
- **WHEN** el cliente cierra el modal en el primer o en el segundo paso
- **THEN** el boleto sigue sin fecha

#### Scenario: Sin asiento
- **GIVEN** que el cliente eligió fecha y horario pero no el asiento
- **WHEN** intenta pasar al segundo paso
- **THEN** no avanza y ve "Falta elegir el asiento"

#### Scenario: Ida y vuelta
- **WHEN** el cliente elige la fecha del tramo de ida o del tramo de vuelta
- **THEN** cada tramo se reconfirma por separado

#### Scenario: Todas las entradas
- **WHEN** el cliente abre "Elegir fecha" desde el botón de escritorio, el menú del celular o el aviso de boletos sin fecha
- **THEN** en los tres casos pasa por la reconfirmación

### Requirement: Ayuda con las líneas de contacto
"Mis boletos" SHALL mostrar un bloque "¿Necesitás ayuda?" con las líneas de contacto configuradas en Parámetros, en el orden cargado. Cada línea MUST mostrar su nombre, si lo tiene, y su teléfono, con un botón "Llamar" que abre el marcador con ese número y un botón "WhatsApp" que abre una conversación de WhatsApp con ese número. Sin líneas configuradas, el bloque MUST NOT mostrarse.

#### Scenario: Dos líneas
- **GIVEN** las líneas "Orán: 3878-804506" y "Tartagal: 3873-309893"
- **WHEN** el cliente abre "Mis boletos"
- **THEN** ve las dos líneas, cada una con "Llamar" y "WhatsApp"

#### Scenario: Sin líneas
- **GIVEN** que no hay líneas de contacto configuradas
- **WHEN** el cliente abre "Mis boletos"
- **THEN** no ve el bloque de ayuda

#### Scenario: Línea sin nombre
- **GIVEN** una línea con solo el teléfono
- **WHEN** el cliente abre "Mis boletos"
- **THEN** ve el teléfono sin nombre ni dos puntos, con sus dos botones
