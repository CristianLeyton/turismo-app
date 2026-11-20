# Spec Delta

## Purpose

Define qué ediciones muestra el historial de un boleto, incluidas las del pasajero hechas desde su otro boleto de ida o vuelta, y cómo se buscan ediciones en la Auditoría para detectar correcciones de datos.

## ADDED Requirements

### Requirement: Ediciones del pasajero compartidas entre boletos
El historial de ediciones de un boleto, en "Ver boleto" y en el detalle de la Auditoría, SHALL mostrar además de sus ediciones propias los cambios de datos del pasajero (nombre, apellido, DNI, teléfono y datos del menor) hechos desde otro boleto del mismo pasajero en la misma venta (su ida o su vuelta), aunque ese boleto esté eliminado. Los cambios propios de ese otro boleto (precio, método de pago, mascotas, observación) y las ediciones de boletos de otras ventas MUST NOT mostrarse.

#### Scenario: Nombre corregido desde la ida
- **GIVEN** un pasajero con boleto de ida N° 38919 y de vuelta N° 38923
- **WHEN** un usuario corrige el nombre y el DNI desde el boleto 38919 y después alguien abre el historial del boleto 38923
- **THEN** el historial del 38923 muestra esa edición con el nombre y el DNI anteriores y nuevos

#### Scenario: Precio de la ida no aparece en la vuelta
- **WHEN** un usuario cambia el precio y el apellido del boleto de ida en una misma edición
- **THEN** el historial de la vuelta muestra solo el cambio de apellido

#### Scenario: Otra venta del mismo DNI
- **GIVEN** dos ventas distintas a una persona con el mismo DNI
- **WHEN** un usuario corrige el nombre en un boleto de la primera venta
- **THEN** el historial de los boletos de la segunda venta no muestra esa edición

### Requirement: Origen de una edición compartida
Cada edición que se muestra en el historial de un boleto pero se hizo desde otro boleto SHALL indicar desde qué boleto se hizo ("Editado desde el boleto N° 38919"). Las ediciones propias MUST NOT llevar esa indicación.

#### Scenario: Edición hecha desde otro boleto
- **WHEN** alguien abre el historial de la vuelta de un pasajero editado desde la ida
- **THEN** la edición dice "Editado desde el boleto N°" seguido del número de la ida

### Requirement: Boleto con pasajero editado en la Auditoría
Un boleto cuyo pasajero fue editado desde otro boleto SHALL contar como editado en la Auditoría: MUST aparecer en la lista y en el filtro de estado "Editados", aunque no tenga ediciones propias.

#### Scenario: Vuelta en la Auditoría
- **GIVEN** un pasajero editado solo desde su boleto de ida
- **WHEN** un usuario busca a ese pasajero en la Auditoría
- **THEN** aparecen el boleto de ida y el de vuelta

### Requirement: Filtro por campo cambiado
La Auditoría SHALL ofrecer el filtro "Campo cambiado", de selección múltiple, con los campos que registra el historial (DNI, Nombre, Apellido, Teléfono, Precio, Método de pago y los demás). Con campos elegidos, la lista MUST mostrar solo los boletos con al menos una edición, propia o compartida, de alguno de esos campos.

#### Scenario: Cambios de DNI
- **GIVEN** un boleto con un cambio de DNI y otro con solo un cambio de precio
- **WHEN** el usuario elige "DNI" en "Campo cambiado"
- **THEN** la lista muestra el primero y no el segundo

### Requirement: Filtro por usuario que editó
La Auditoría SHALL ofrecer el filtro "Editado por" con los usuarios del panel. Con un usuario elegido, la lista MUST mostrar solo los boletos con al menos una edición, propia o compartida, hecha por ese usuario, sin importar quién vendió el boleto.

#### Scenario: Ediciones de un vendedor
- **GIVEN** un boleto vendido por Carolina y editado por Julian
- **WHEN** el usuario elige "Julian" en "Editado por"
- **THEN** la lista muestra ese boleto

### Requirement: Filtro por fecha de edición
La Auditoría SHALL ofrecer el filtro "Fecha de edición" con un rango desde/hasta, ambos opcionales e inclusivos, en la zona horaria de la aplicación. Con fechas elegidas, la lista MUST mostrar solo los boletos con al menos una edición, propia o compartida, dentro del rango.

#### Scenario: Ediciones de un día
- **WHEN** el usuario elige desde 04/11/2026 hasta 04/11/2026
- **THEN** la lista muestra los boletos editados ese día y no los editados el 03/11 o el 05/11

### Requirement: Filtros de edición combinados
Los filtros "Campo cambiado", "Editado por" y "Fecha de edición" SHALL aplicarse sobre la misma edición: un boleto MUST aparecer solo si alguna de sus ediciones cumple a la vez todos los filtros de edición elegidos. Se MUST poder combinar con los filtros existentes (estado, N° de boleto, pasajero), cada filtro activo MUST mostrar su indicador y MUST conservarse en la sesión como los demás.

#### Scenario: DNI cambiado por Julian el 4/11
- **GIVEN** un boleto con un cambio de DNI hecho por Carolina el 4/11 y un cambio de precio hecho por Julian el 4/11
- **WHEN** el usuario elige "DNI", "Julian" y el 04/11/2026
- **THEN** ese boleto no aparece, porque ninguna edición cumple las tres condiciones
