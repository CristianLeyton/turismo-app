# Spec Delta

## Purpose

Permitir que una persona sin conocimientos técnicos configure una ruta completa (sus datos, el recorrido de paradas y los horarios) desde un solo lugar del panel, con validaciones y avisos en lenguaje simple.

## ADDED Requirements

### Requirement: Edición de una ruta en un solo lugar
El sistema SHALL ofrecer, por cada ruta, una pantalla con las secciones Datos, Recorrido y Horarios. Al crear una ruta MUST llevar al usuario a esa pantalla. Las paradas y los horarios MUST NOT aparecer como menús sueltos en la navegación del cluster Destinos.

#### Scenario: Abrir una ruta
- **WHEN** el usuario elige editar la ruta "Oran - Salta" desde el listado de rutas
- **THEN** ve en una sola pantalla los datos de la ruta, su recorrido y sus horarios

#### Scenario: Crear una ruta
- **WHEN** el usuario crea la ruta "Salta - Jujuy"
- **THEN** queda en la pantalla de esa ruta, lista para cargar horarios y recorrido

#### Scenario: Navegación del cluster
- **WHEN** el usuario abre el cluster Destinos
- **THEN** ve los menús Destinos y Rutas, y no ve menús sueltos de Paradas ni de Horarios

### Requirement: Permisos por sección de la ruta
Cada sección de la pantalla de ruta MUST respetar los permisos existentes de su módulo: datos de la ruta con los de rutas, recorrido con los de paradas y horarios con los de horarios. Una sección sin permiso de ver listado MUST NOT mostrarse.

#### Scenario: Rol sin permisos de horarios
- **WHEN** un usuario con permisos de rutas y paradas, pero no de horarios, abre una ruta
- **THEN** ve las secciones Datos y Recorrido y no ve la sección Horarios

### Requirement: Recorrido ordenado sin números de orden
El recorrido SHALL listar las paradas de la ruta en su orden de paso. Una parada nueva MUST agregarse al final del recorrido sin que el usuario escriba un número de orden. El usuario SHALL poder cambiar el orden arrastrando paradas, y las horas MUST quedar pegadas a la posición como hoy. El nuevo orden MUST guardarse recién al tocar "Confirmar cambios" y MUST poder descartarse.

#### Scenario: Agregar una parada
- **WHEN** la ruta tiene Orán y Pichanal y el usuario agrega Salta
- **THEN** Salta queda tercera en el recorrido sin haber cargado un número de orden

#### Scenario: Arrastrar una parada
- **WHEN** el usuario arrastra Pichanal a la posición de Güemes y toca "Confirmar cambios"
- **THEN** el orden cambia y cada posición conserva la hora que tenía

#### Scenario: Arrastrar sin confirmar
- **WHEN** el usuario arrastra paradas y todavía no tocó "Confirmar cambios"
- **THEN** el recorrido muestra el orden nuevo con el aviso "Hay cambios sin guardar." y no se guarda nada

#### Scenario: Descartar el orden nuevo
- **WHEN** el usuario arrastra paradas y toca "Descartar cambios"
- **THEN** el recorrido vuelve al orden guardado sin modificar nada

### Requirement: Localidad de una parada
Al cargar una parada el usuario SHALL elegir la localidad (destino) con un buscador y MUST poder crear un destino nuevo sin salir de la ruta, si tiene permiso para crear destinos. Una localidad que ya está en el recorrido MUST rechazarse con el mensaje "Esta localidad ya está en el recorrido."

#### Scenario: Localidad nueva
- **WHEN** el usuario busca "Tartagal", no existe y lo crea desde la parada
- **THEN** el destino se crea y queda elegido en la parada

#### Scenario: Localidad repetida
- **WHEN** el usuario agrega una parada con Pichanal y Pichanal ya está en el recorrido
- **THEN** el sistema rechaza la parada con "Esta localidad ya está en el recorrido."

### Requirement: Horas de las paradas según el primer horario
Las horas de llegada y salida de cada parada SHALL cargarse y mostrarse como hora del reloj del primer horario activo de la ruta (el de salida más temprana), y la pantalla MUST indicar a qué horario corresponden. Para cada parada MUST mostrarse la hora resultante en los demás horarios activos.

#### Scenario: Ruta con dos horarios
- **WHEN** la ruta tiene horarios activos de las 08:00 y las 14:00 y el usuario carga Pichanal saliendo a las 08:45
- **THEN** el recorrido indica que las horas son del horario de las 08:00 y muestra que en el horario de las 14:00 Pichanal sale a las 14:45

#### Scenario: Viaje que pasa la medianoche
- **WHEN** el primer horario sale a las 22:00 y el usuario carga una parada a las 01:30
- **THEN** la parada queda 3 h 30 min después de la salida y en un horario de las 23:00 se muestra a las 02:30

### Requirement: Recorrido sin horarios
Si la ruta no tiene horarios activos, el recorrido MUST explicar que primero hay que cargar un horario y MUST NOT permitir cargar horas de paradas.

#### Scenario: Ruta recién creada
- **WHEN** el usuario abre el recorrido de una ruta sin horarios activos
- **THEN** ve el mensaje "Primero cargá al menos un horario para poder indicar a qué hora pasa por cada parada." y no puede agregar paradas

### Requirement: Validación de horas de paradas
Una parada MUST rechazarse con un mensaje en lenguaje simple cuando su hora de llegada es posterior a su hora de salida, o cuando sale antes de la salida de la parada anterior del recorrido, medido desde la salida del horario.

#### Scenario: Llegada después de la salida
- **WHEN** el usuario carga Pichanal llegando 08:50 y saliendo 08:45
- **THEN** el sistema lo rechaza con "La hora de llegada no puede ser posterior a la hora de salida."

#### Scenario: Parada que sale antes que la anterior
- **WHEN** Pichanal sale 08:45 y el usuario carga la parada siguiente saliendo 08:30
- **THEN** el sistema lo rechaza con "Esta parada no puede salir antes que la parada anterior (Pichanal, 08:45)."

#### Scenario: Hora anterior a la salida del horario
- **WHEN** el primer horario sale a las 08:00, Orán sale a las 08:00 y el usuario carga la parada siguiente saliendo 07:30
- **THEN** el sistema lo rechaza con "Esta parada no puede salir antes que la parada anterior (Orán, 08:00)." en lugar de tomarla como 23 h 30 min después

### Requirement: Aviso de boletos vendidos afectados
Antes de guardar un cambio que modifica horas que ven los pasajeros (crear, editar o eliminar un horario, cambiar la hora de una parada, reordenar o eliminar una parada), si existen boletos en viajes que todavía no partieron afectados por el cambio (un viaje parte en su fecha a la hora de salida de su horario), el sistema MUST mostrar cuántos son y pedir confirmación. Sin boletos afectados MUST guardar sin aviso extra.

#### Scenario: Cambio con boletos vendidos
- **WHEN** el usuario cambia la salida del horario de las 08:00 a las 08:30 y hay 12 boletos en viajes que todavía no partieron de ese horario
- **THEN** el sistema avisa "Hay 12 boletos vendidos en viajes próximos que van a ver la hora nueva." y solo guarda si el usuario confirma

#### Scenario: Viaje de hoy que ya partió
- **WHEN** son las 10:00 y el usuario cambia una parada de una ruta cuyo único boleto vendido es del viaje de hoy de las 08:00
- **THEN** el cambio se guarda sin aviso de boletos, porque ese viaje ya partió

#### Scenario: Cambio sin boletos vendidos
- **WHEN** el usuario cambia la hora de una parada de una ruta sin boletos en viajes que todavía no partieron
- **THEN** el cambio se guarda sin aviso de boletos

### Requirement: Horario con boletos no se elimina
Un horario con boletos en viajes que todavía no partieron MUST NOT poder eliminarse; el sistema MUST explicar el motivo y ofrecer desactivarlo. Un horario sin esos boletos SHALL poder eliminarse.

#### Scenario: Eliminar horario con boletos
- **WHEN** el usuario intenta eliminar el horario de las 08:00 que tiene boletos en viajes que todavía no partieron
- **THEN** el sistema no lo elimina, explica que tiene boletos vendidos y ofrece desactivarlo para que no se use en nuevas ventas

### Requirement: Ruta con viajes pendientes no se elimina
Una ruta con viajes pendientes (viajes que todavía no partieron y tienen al menos un boleto vendido) MUST NOT poder eliminarse, ni desde el listado, ni desde su pantalla, ni en lote. Al intentarlo, el sistema MUST avisar cuántos boletos tiene y por qué no se puede, y MUST ofrecer desactivarla. Una ruta sin viajes pendientes SHALL poder eliminarse.

#### Scenario: Eliminar ruta con viajes pendientes
- **WHEN** el usuario toca eliminar en la ruta "Oran - Salta", que tiene 2 boletos en viajes que todavía no partieron
- **THEN** el sistema avisa "Esta ruta tiene 2 boletos vendidos en viajes que todavía no partieron, por eso no se puede eliminar." y no ofrece confirmar la eliminación

#### Scenario: Desactivar desde el aviso
- **WHEN** el usuario toca "Desactivar ruta" en ese aviso
- **THEN** la ruta queda inactiva y no eliminada, y los boletos vendidos siguen siendo válidos

#### Scenario: Ruta con viajes ya partidos o vacíos
- **WHEN** la ruta solo tiene viajes que ya partieron o viajes sin boletos
- **THEN** el usuario puede eliminarla

#### Scenario: Eliminar en lote
- **WHEN** el usuario elimina varias rutas a la vez y alguna tiene viajes pendientes
- **THEN** el sistema no elimina ninguna y nombra las rutas que tienen viajes pendientes

### Requirement: Destino usado en rutas no se elimina
Un destino que está en el recorrido de una ruta no eliminada MUST NOT poder eliminarse, ni normal ni definitivamente ni en lote; el sistema MUST avisar en qué rutas está. Al editar un destino usado en rutas, el sistema MUST avisar en cuáles se va a ver el cambio. El listado de destinos SHALL mostrar en cuántas rutas se usa cada uno.

#### Scenario: Eliminar destino usado
- **WHEN** el usuario toca eliminar en "Pichanal", que está en las rutas "Oran - Salta" y "Tartagal - Salta"
- **THEN** el sistema avisa "Este destino está en el recorrido de 2 rutas: Oran - Salta y Tartagal - Salta." y no ofrece confirmar la eliminación

#### Scenario: Destino solo en rutas eliminadas
- **WHEN** el destino solo aparece en rutas eliminadas o en paradas eliminadas
- **THEN** el usuario puede eliminarlo

#### Scenario: Editar destino usado
- **WHEN** el usuario edita "Pichanal", que está en la ruta "Oran - Salta"
- **THEN** el formulario avisa que el cambio de nombre se va a ver en esa ruta y en los boletos que lo tienen

### Requirement: Listado de rutas legible
El listado de rutas SHALL mostrar, por ruta, el recorrido resumido como "origen → destino", la cantidad de paradas y la cantidad de horarios activos. Una ruta activa sin paradas o sin horarios activos MUST mostrarse con una alerta visible que indique qué le falta. Las columnas técnicas de fechas de creación, modificación y eliminación MUST NOT mostrarse en las pantallas del cluster Destinos.

#### Scenario: Ruta completa
- **WHEN** el usuario ve la ruta "Oran - Salta" con 5 paradas y 2 horarios activos
- **THEN** el listado muestra "Orán → Salta", 5 paradas y 2 horarios

#### Scenario: Ruta activa incompleta
- **WHEN** una ruta activa no tiene horarios activos
- **THEN** el listado la marca con la alerta "Sin horarios"
