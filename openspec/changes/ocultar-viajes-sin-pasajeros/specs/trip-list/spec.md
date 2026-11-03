# Spec Delta

## Purpose

Definir qué viajes muestra la lista de viajes del panel de administración, para que por defecto se vean solo los que tienen pasajeros y los vacíos queden a un switch de distancia.

## ADDED Requirements

### Requirement: Viajes sin pasajeros ocultos por defecto
La lista de viajes SHALL mostrar por defecto solo los viajes con al menos un boleto activo. Un viaje cuyos boletos están todos borrados, o que nunca tuvo boletos, cuenta como sin pasajeros y MUST NOT aparecer mientras el switch "Ver también viajes sin pasajeros" esté apagado. Ocultarlos MUST NOT borrar ni modificar viajes.

#### Scenario: Lista por defecto
- **WHEN** existen un viaje con un boleto y otro sin boletos, y el usuario abre la lista de viajes
- **THEN** la lista muestra solo el viaje con el boleto

#### Scenario: Viaje con todos sus boletos borrados
- **WHEN** el único boleto de un viaje fue borrado
- **THEN** el viaje no aparece en la lista por defecto

#### Scenario: Los viajes ocultos siguen existiendo
- **WHEN** un viaje sin pasajeros está oculto en la lista
- **THEN** el viaje sigue en la base y se puede volver a usar al vender ese horario y fecha

### Requirement: Switch para ver los viajes sin pasajeros
La lista de viajes SHALL ofrecer, junto a los demás filtros, el switch "Ver también viajes sin pasajeros", apagado por defecto. Encendido, la lista MUST mostrar también los viajes sin pasajeros, que siguen viéndose en gris. El switch MUST combinarse con los demás filtros y MUST conservarse en la sesión, como el resto de los filtros de la lista.

#### Scenario: Encender el switch
- **WHEN** el usuario enciende "Ver también viajes sin pasajeros"
- **THEN** la lista muestra los viajes con pasajeros y los viajes sin pasajeros, estos últimos en gris

#### Scenario: Switch combinado con la fecha
- **WHEN** el switch está encendido y el usuario filtra por una fecha
- **THEN** la lista muestra todos los viajes de esa fecha, con y sin pasajeros

#### Scenario: Apagar el switch
- **WHEN** el usuario apaga el switch
- **THEN** la lista vuelve a ocultar los viajes sin pasajeros

### Requirement: Búsqueda por número de viaje
Cuando el usuario filtra por "Viaje Nº", la lista SHALL mostrar el viaje con ese número aunque no tenga pasajeros y el switch esté apagado.

#### Scenario: Buscar un viaje vacío por número
- **WHEN** el switch está apagado y el usuario busca el número de un viaje sin pasajeros
- **THEN** la lista muestra ese viaje
