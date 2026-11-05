# trip-export Specification

## Purpose

Define cómo se obtienen el PDF y el Excel con el detalle y los pasajeros de un viaje, y quién puede obtenerlos, para que los datos personales de los pasajeros nunca salgan sin una sesión del panel.

## Requirements

### Requirement: Sin exportaciones de viajes por dirección pública
Ninguna dirección del sitio SHALL entregar el PDF ni el Excel de un viaje a quien no tenga una sesión iniciada en el panel admin con permiso para ver viajes. En particular, `/trips/{id}/pdf` y `/trips/{id}/excel` MUST NOT entregar el archivo a un visitante sin sesión ni a un usuario sin ese permiso.

#### Scenario: Visitante sin sesión pide el PDF
- **GIVEN** un viaje con pasajeros
- **WHEN** alguien sin sesión abre `/trips/{id}/pdf` de ese viaje
- **THEN** no recibe el PDF ni ningún dato de los pasajeros

#### Scenario: Visitante sin sesión pide el Excel
- **GIVEN** un viaje con pasajeros
- **WHEN** alguien sin sesión abre `/trips/{id}/excel` de ese viaje
- **THEN** no recibe el Excel ni ningún dato de los pasajeros

#### Scenario: Recorrer números de viaje
- **WHEN** alguien sin sesión prueba números de viaje consecutivos en esas direcciones
- **THEN** ninguna respuesta incluye un archivo de exportación

#### Scenario: Usuario sin permiso de viajes
- **GIVEN** un usuario logueado en el panel sin permiso para ver viajes
- **WHEN** abre `/trips/{id}/pdf` o `/trips/{id}/excel` de un viaje
- **THEN** no recibe el archivo

### Requirement: Exportaciones desde el panel de viajes
El panel admin SHALL seguir ofreciendo las acciones "PDF" y "Excel" en la lista de viajes y en el detalle de un viaje, que descargan el archivo del viaje con su nombre actual (`Viaje_N°{id}_{colectivo}_{dd-mm-aaaa}`). Las acciones MUST seguir deshabilitadas en viajes sin boletos.

#### Scenario: Descargar el PDF desde la lista
- **GIVEN** un usuario logueado con acceso a la lista de viajes y un viaje con pasajeros
- **WHEN** toca "PDF" en la fila del viaje
- **THEN** descarga el PDF del viaje con el nombre `Viaje_N°{id}_{colectivo}_{fecha}.pdf`

#### Scenario: Descargar el Excel desde la lista
- **GIVEN** un usuario logueado con acceso a la lista de viajes y un viaje con pasajeros
- **WHEN** toca "Excel" en la fila del viaje
- **THEN** descarga el Excel del viaje con el nombre `Viaje_N°{id}_{colectivo}_{fecha}.xlsx`

#### Scenario: Viaje sin boletos
- **GIVEN** un viaje sin boletos
- **WHEN** el usuario mira sus acciones en la lista
- **THEN** "PDF" y "Excel" están deshabilitadas
