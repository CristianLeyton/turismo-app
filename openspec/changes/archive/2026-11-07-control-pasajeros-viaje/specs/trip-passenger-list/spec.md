# Spec Delta

## ADDED Requirements

### Requirement: Orden por parada de subida
La lista de pasajeros del viaje (modal "Ver detalles", PDF y Excel) SHALL ordenarse por la posición de la parada de origen del boleto dentro del recorrido de la ruta y, dentro de una misma parada, por número de asiento de menor a mayor como número, no como texto. Los menores y mascotas de un boleto MUST ir inmediatamente debajo de la fila de su titular.

#### Scenario: Paradas en orden del recorrido
- **GIVEN** una ruta Orán (1) → Pichanal (2) → Salta (3) y boletos con origen Salta, Orán y Pichanal
- **WHEN** el usuario abre "Ver detalles" del viaje
- **THEN** primero aparecen los pasajeros que suben en Orán, después los de Pichanal y por último los de Salta

#### Scenario: Asientos ordenados como número
- **GIVEN** dos boletos con origen Orán en los asientos 10 y 2
- **WHEN** el usuario abre "Ver detalles" o descarga el PDF o el Excel
- **THEN** el pasajero del asiento 2 aparece antes que el del asiento 10

#### Scenario: Acompañantes junto al titular
- **GIVEN** un boleto con origen Orán cuyo pasajero viaja con un menor y una mascota, y otro boleto con origen Orán en un asiento mayor
- **WHEN** el usuario abre "Ver detalles"
- **THEN** las filas del menor y de la mascota aparecen inmediatamente después del titular y antes del otro boleto

### Requirement: Boletos con origen fuera del recorrido
Un boleto del viaje cuya parada de origen ya no forma parte del recorrido vigente de la ruta (parada eliminada o cambiada) SHALL seguir apareciendo en el modal, el PDF y el Excel, después de todos los pasajeros cuyo origen sí está en el recorrido. Cada boleto MUST aparecer una sola vez.

#### Scenario: Parada de origen eliminada
- **GIVEN** un boleto con origen Pichanal y la parada Pichanal eliminada del recorrido de la ruta
- **WHEN** el usuario abre "Ver detalles" del viaje
- **THEN** el pasajero aparece al final de la lista, con su origen Pichanal, y el contador lo incluye

#### Scenario: Parada eliminada y vuelta a agregar
- **GIVEN** la parada Orán eliminada y vuelta a agregar al recorrido, y un boleto con origen Orán
- **WHEN** el usuario abre "Ver detalles" del viaje
- **THEN** el pasajero aparece una sola vez, en la posición de la parada Orán vigente

### Requirement: Agrupado por parada de subida en el modal
El modal "Ver detalles" SHALL ofrecer el switch "Agrupar por parada". Encendido, la lista MUST mostrar un encabezado por cada parada de origen, numerada según su posición en el recorrido ("1. Orán"), con la cantidad de pasajeros que suben en ella; los de origen fuera del recorrido van al final bajo un encabezado sin número. Apagado, la lista MUST verse sin encabezados, en el mismo orden.

#### Scenario: Encabezados numerados
- **GIVEN** el switch encendido y un viaje con pasajeros que suben en la parada 1 Orán y en la parada 3 Salta
- **WHEN** el usuario abre "Ver detalles"
- **THEN** la lista tiene un encabezado "1. Orán" con sus pasajeros y otro "3. Salta" con los suyos, y no tiene encabezado para Pichanal

#### Scenario: Agrupado apagado
- **WHEN** el usuario apaga "Agrupar por parada"
- **THEN** los encabezados de parada desaparecen y los pasajeros siguen en el mismo orden

### Requirement: Preferencia de agrupado recordada
El estado del switch "Agrupar por parada" SHALL recordarse en el navegador del usuario y MUST aplicarse la próxima vez que abra "Ver detalles" de cualquier viaje, incluso después de recargar la página. La primera vez, el switch MUST estar encendido. El PDF del viaje MUST seguir separado por parada sin importar el switch.

#### Scenario: Recordar el agrupado apagado
- **GIVEN** el usuario apagó "Agrupar por parada" en un viaje
- **WHEN** recarga la página y abre "Ver detalles" de otro viaje
- **THEN** el switch aparece apagado y la lista se ve sin encabezados

### Requirement: Filtros por origen y destino en el modal
El modal "Ver detalles" SHALL ofrecer un filtro por origen y otro por destino. Sus opciones MUST ser las paradas del recorrido del viaje, numeradas y en el orden del recorrido ("1. Orán", "2. Pichanal"), más "Todas" como opción por defecto. Los filtros MUST combinarse entre sí y con el buscador, y el contador de pasajeros MUST mostrar cuántos quedan visibles sobre el total.

#### Scenario: Filtrar por origen
- **GIVEN** un viaje con pasajeros que suben en Orán y en Pichanal
- **WHEN** el usuario elige el origen "2. Pichanal"
- **THEN** solo se ven los pasajeros que suben en Pichanal, con sus acompañantes, y el contador muestra los visibles sobre el total

#### Scenario: Filtrar por origen y destino
- **GIVEN** pasajeros Orán→Salta, Orán→Pichanal y Pichanal→Salta
- **WHEN** el usuario elige origen "1. Orán" y destino "3. Salta"
- **THEN** solo se ve el pasajero Orán→Salta

#### Scenario: Opciones en orden del recorrido
- **WHEN** el usuario abre el filtro de destino
- **THEN** las paradas aparecen numeradas y en el orden del recorrido, no en orden alfabético

#### Scenario: Sin resultados
- **WHEN** los filtros elegidos no coinciden con ningún pasajero
- **THEN** el modal muestra el mensaje "Ningún pasajero coincide con los filtros." y una forma de limpiar los filtros

### Requirement: Buscador de pasajeros en el modal
El modal "Ver detalles" SHALL ofrecer un buscador que filtra la lista por nombre, DNI, número de asiento o número de boleto, sin distinguir mayúsculas ni tildes. Cuando la búsqueda coincide con un menor o una mascota, MUST mostrarse también la fila de su titular, y cuando coincide con un titular, también sus acompañantes.

#### Scenario: Buscar por DNI
- **WHEN** el usuario escribe el DNI de un pasajero en el buscador
- **THEN** solo se ve ese pasajero y sus acompañantes

#### Scenario: Buscar sin tildes
- **GIVEN** una pasajera llamada "Ana Gómez"
- **WHEN** el usuario escribe "gomez"
- **THEN** la lista muestra a Ana Gómez

#### Scenario: Buscar a un menor
- **WHEN** el usuario escribe el nombre de un menor acompañante
- **THEN** se ven el menor y la fila de su titular

### Requirement: "Viaja con" en la fila del titular
En el modal y en el PDF del viaje, la fila del adulto titular de un boleto con menores o mascotas SHALL indicar con quién viaja, nombrando a cada menor y a las mascotas (por ejemplo "Viaja con: Tomás Gómez (menor), Firulais (mascota)"). Las filas de menores y mascotas MUST NOT repetir el nombre del titular y MUST identificarse como acompañantes. El Excel MUST mantener su columna "Viaja con" sin cambios.

#### Scenario: Titular con menor
- **GIVEN** un boleto de Ana Gómez que viaja con el menor Tomás Gómez
- **WHEN** el usuario abre "Ver detalles" o descarga el PDF
- **THEN** la fila de Ana dice "Viaja con: Tomás Gómez (menor)" y la fila de Tomás no muestra "Viaja con: Ana Gómez"

#### Scenario: Titular con varias mascotas
- **GIVEN** un boleto cuyo titular viaja con 2 mascotas
- **WHEN** el usuario abre "Ver detalles"
- **THEN** la fila del titular indica que viaja con sus mascotas, incluida la cantidad

#### Scenario: Excel sin cambios
- **WHEN** el usuario descarga el Excel de un viaje con un menor acompañante
- **THEN** la fila del menor sigue mostrando en "Viaja con" el nombre del titular seguido de "(Menor)"

### Requirement: Teléfono para llamar y escribir
Cuando el modal "Ver detalles" muestra la columna Teléfono, el teléfono de cada pasajero SHALL ser un link que inicia una llamada a ese número y MUST tener al lado un acceso que abre un chat de WhatsApp con el pasajero, sin mensaje precargado. Los números sin código de país MUST tomarse como argentinos. Un pasajero sin teléfono MUST mostrar "—" sin links. Esta regla MUST NOT cambiar cuándo se muestra la columna.

#### Scenario: Llamar al pasajero
- **GIVEN** el parámetro "Observaciones en la venta" apagado y un pasajero con teléfono 3815550001
- **WHEN** el usuario toca el teléfono en "Ver detalles"
- **THEN** el dispositivo inicia una llamada a ese número

#### Scenario: Escribir por WhatsApp
- **GIVEN** el parámetro apagado y un pasajero con teléfono 3815550001
- **WHEN** el usuario toca el acceso de WhatsApp de ese pasajero
- **THEN** se abre un chat de WhatsApp con el número 543815550001

#### Scenario: Pasajero sin teléfono
- **GIVEN** el parámetro apagado y un pasajero sin teléfono
- **WHEN** el usuario abre "Ver detalles"
- **THEN** la celda del teléfono muestra "—" y no tiene link de llamada ni de WhatsApp

#### Scenario: Observaciones prendidas
- **GIVEN** el parámetro "Observaciones en la venta" prendido
- **WHEN** el usuario abre "Ver detalles"
- **THEN** la columna Teléfono sigue sin mostrarse
