## ADDED Requirements

### Requirement: Switch para ver solo los viajes que parten hoy
La lista de viajes SHALL ofrecer, al lado del switch "Ver también viajes sin pasajeros", el switch "Mostrar solo los viajes que parten hoy", apagado por defecto. Encendido, la lista MUST mostrar solo los viajes cuya fecha de salida es la fecha de hoy, según la zona horaria de la aplicación, sin importar si el horario de salida ya pasó. El switch MUST combinarse con los demás filtros (incluido "Ver también viajes sin pasajeros") y MUST conservarse en la sesión, como el resto de los filtros de la lista. Buscar por "Viaje Nº" MUST mostrar ese viaje aunque el switch esté encendido y el viaje no sea de hoy, igual que ya pasa con los viajes sin pasajeros.

#### Scenario: Lista por defecto
- **WHEN** existen viajes con pasajeros de hoy y de mañana, y el usuario abre la lista de viajes
- **THEN** el switch está apagado y la lista muestra los dos viajes

#### Scenario: Encender el switch
- **WHEN** el usuario enciende "Mostrar solo los viajes que parten hoy"
- **THEN** la lista muestra solo los viajes de hoy, incluidos los que ya salieron más temprano

#### Scenario: Combinado con los viajes sin pasajeros
- **WHEN** los dos switches están encendidos
- **THEN** la lista muestra todos los viajes de hoy, con y sin pasajeros

#### Scenario: Solo hoy con los viajes vacíos ocultos
- **WHEN** solo el switch de hoy está encendido
- **THEN** la lista muestra los viajes de hoy con pasajeros y oculta los de hoy sin pasajeros

#### Scenario: Búsqueda por número con el switch encendido
- **GIVEN** el switch de hoy encendido
- **WHEN** el usuario busca por "Viaje Nº" un viaje de mañana
- **THEN** la lista muestra ese viaje

#### Scenario: Apagar el switch
- **WHEN** el usuario apaga el switch
- **THEN** la lista vuelve a mostrar los viajes de todas las fechas
