# linea-colectivo Specification

## Purpose
Agrupar colectivos que comparten recorrido en una línea, para que la búsqueda de horarios y destinos al vender cubra los colectivos regulares y sus refuerzos sin cambiar los datos existentes.

## Requirements

### Requirement: Agrupación de colectivos en líneas
El sistema SHALL permitir asignar cada colectivo a una línea, y varios colectivos MAY pertenecer a la misma línea. Un colectivo sin línea MUST comportarse como una línea propia que contiene solo a sí mismo.

#### Scenario: Colectivo de refuerzo en una línea
- **WHEN** el colectivo "Oran - Salta (Refuerzo)" se asigna a la línea "Oran - Salta" que ya contiene al colectivo "Oran - Salta"
- **THEN** ambos colectivos forman parte de la línea "Oran - Salta" y el refuerzo conserva su nombre

#### Scenario: Colectivo sin línea
- **WHEN** un colectivo no tiene línea asignada
- **THEN** su búsqueda de horarios y destinos considera solo ese colectivo

### Requirement: Colectivos regulares de una línea
El sistema SHALL identificar un colectivo regular por línea como el que se ofrece en el selector de colectivo de la venta. Los colectivos de refuerzo de la línea MUST NOT aparecer en ese selector.

#### Scenario: Selector de colectivo con línea
- **WHEN** el vendedor abre el selector de colectivo y la línea tiene un regular y un refuerzo
- **THEN** el selector lista solo el regular

#### Scenario: Colectivo sin línea en el selector
- **WHEN** el vendedor abre el selector de colectivo y existe un colectivo sin línea
- **THEN** el selector lista ese colectivo como antes

### Requirement: Línea sin cambios en ventas existentes
La asignación de líneas MUST NOT modificar ventas, boletos, asientos, viajes ni nombres de colectivos ya existentes. Un colectivo sin línea MUST seguir ofreciendo los mismos horarios, paradas y destinos que antes de la introducción de líneas.

#### Scenario: Migración de datos existentes
- **WHEN** se aplica la migración que crea las líneas y el campo de línea de colectivos
- **THEN** todos los colectivos existentes quedan sin línea y ningún boleto, asiento o viaje cambia

### Requirement: Reprogramación de tramos con colectivo de la línea
La reprogramación de un tramo que tiene colectivo guardado MUST ofrecer como horarios solo los de los colectivos de la misma línea, o del propio colectivo si no tiene línea, igual que la venta. Cada opción MUST mostrar el nombre del colectivo al que pertenece. La validación del horario elegido MUST aceptar un horario de cualquier colectivo de la línea y rechazar uno de otra línea.

#### Scenario: Tramo del regular con horario de refuerzo
- **WHEN** se reprograma un tramo vendido con el colectivo "Oran - Salta" y la línea tiene un horario activo del refuerzo que cubre el tramo
- **THEN** ese horario aparece entre las opciones con el nombre "Oran - Salta (Refuerzo)" y puede elegirse

#### Scenario: Horario de otra línea
- **WHEN** se intenta reprogramar un tramo vendido con el colectivo "Oran - Salta" a un horario de un colectivo de otra línea
- **THEN** el sistema rechaza el horario con "El horario elegido no corresponde al colectivo del boleto."

#### Scenario: Tramo sin colectivo guardado
- **WHEN** el boleto es anterior a las líneas y no tiene colectivo guardado
- **THEN** la reprogramación no restringe horarios por colectivo, como antes

### Requirement: Gestión de líneas restringida por permisos
El acceso a la vista de líneas y a cada una de sus acciones MUST requerir el permiso correspondiente del módulo "Líneas" del catálogo de roles: ver listado, crear, editar y eliminar. Un usuario sin el permiso de ver listado MUST NOT ver la vista en la navegación ni poder abrirla. El Super Administrador MUST conservar acceso completo.

#### Scenario: Vendedor sin permisos de líneas
- **WHEN** un usuario con rol Vendedor intenta abrir la vista de líneas
- **THEN** el sistema le niega el acceso y la vista no aparece en su navegación

#### Scenario: Rol con permiso solo de ver listado
- **WHEN** un usuario cuyo rol tiene solo el permiso de ver listado de líneas abre la vista
- **THEN** ve el listado de líneas pero no puede crear, editar ni eliminar líneas

#### Scenario: Rol con permisos completos de líneas
- **WHEN** un usuario cuyo rol tiene los permisos de ver listado, crear, editar y eliminar líneas usa la vista
- **THEN** puede crear, editar y eliminar líneas como hoy

#### Scenario: Super Administrador
- **WHEN** el Super Administrador abre la vista de líneas
- **THEN** tiene acceso completo sin necesidad de asignación explícita

### Requirement: Permisos de líneas en la matriz de roles
El catálogo de roles MUST incluir el módulo "Líneas" con las acciones estándar, visible en la matriz de permisos. Esos permisos MUST NOT asignarse por defecto a los roles Administrador ni Vendedor, y la sincronización de permisos MUST NOT revocarlos una vez habilitados desde la matriz.

#### Scenario: Sincronización de permisos
- **WHEN** se ejecuta la sincronización de permisos
- **THEN** los permisos del módulo "Líneas" existen y los roles Administrador y Vendedor no los tienen asignados

#### Scenario: Habilitar desde la matriz
- **WHEN** se habilita "Líneas — Ver listado" a un rol desde la matriz y luego se vuelve a sincronizar
- **THEN** el rol conserva el permiso y sus usuarios pueden abrir la vista de líneas
