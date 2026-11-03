# Spec Delta

## MODIFIED Requirements

### Requirement: Colectivo obligatorio
Toda venta MUST tener un colectivo seleccionado, también cuando algún tramo se vende sin fecha. El selector de colectivo MUST listar los colectivos regulares, es decir, sin incluir los de refuerzo de una línea, y los colectivos sin línea. Por defecto se preselecciona el primer colectivo por nombre. Cambiar el colectivo reinicia origen, destino, horario, viaje e ida y vuelta. El colectivo del boleto MUST ser el del horario elegido, que puede ser un colectivo de refuerzo de la línea del colectivo seleccionado.

#### Scenario: Emisión sin colectivo
- **WHEN** llega una emisión sin colectivo, aunque los tramos vayan sin fecha
- **THEN** la venta se rechaza con "Seleccione el colectivo antes de completar la venta." y no se crea nada

#### Scenario: Selector sin refuerzos
- **WHEN** el vendedor abre el selector de colectivo y la línea tiene un regular y un refuerzo
- **THEN** el selector no muestra el refuerzo

### Requirement: Horarios ofrecidos para el tramo de ida
El sistema SHALL ofrecer como horarios de ida solo los activos de rutas activas de todos los colectivos de la línea del colectivo elegido (incluido el propio colectivo) en los que origen y destino forman un tramo válido de la ruta. Cada opción MUST mostrar el nombre del colectivo al que pertenece el horario. MUST excluir los bloqueados por el corte de venta y ordenarlos por nombre en orden natural. Si hay un único horario posible, queda preseleccionado.

#### Scenario: Un único horario disponible
- **WHEN** para la línea, origen, destino y fecha elegidos existe un solo horario ofrecible
- **THEN** ese horario queda seleccionado automáticamente

#### Scenario: Ningún horario disponible
- **WHEN** no hay horarios ofrecibles para la combinación elegida
- **THEN** el selector muestra "No hay horarios disponibles para esta ruta"

#### Scenario: Horario de un refuerzo de la línea
- **WHEN** el colectivo elegido es "Oran - Salta" y el refuerzo "Oran - Salta (Refuerzo)" tiene un horario ofrecible para el tramo
- **THEN** ese horario aparece entre las opciones con el texto que incluye "Oran - Salta (Refuerzo)"

#### Scenario: Colectivo sin línea
- **WHEN** el colectivo elegido no tiene línea
- **THEN** los horarios ofrecidos son solo los de ese colectivo, como antes
