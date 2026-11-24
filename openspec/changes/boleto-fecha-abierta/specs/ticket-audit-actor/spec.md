## ADDED Requirements

### Requirement: Tipo de cada cambio de fecha
Cada cambio de fecha de un boleto SHALL guardar su tipo como un dato propio: "reprogramación" cuando el boleto pasa a un viaje, o "fecha abierta" cuando el personal le quita el viaje y el asiento. El tipo MUST NOT deducirse de que falte el viaje de destino, porque ese dato también puede faltar si el viaje se borra. Los cambios registrados antes de este cambio MUST quedar como reprogramación.

#### Scenario: Fecha abierta
- **WHEN** el personal deja un boleto con fecha abierta
- **THEN** el cambio queda registrado como "fecha abierta", con el viaje, el horario y el asiento de origen y el autor

#### Scenario: Viaje de destino borrado
- **GIVEN** una reprogramación a un viaje que después se borra
- **WHEN** el personal consulta ese cambio
- **THEN** sigue registrado como reprogramación, no como fecha abierta

#### Scenario: Cambios anteriores
- **WHEN** se aplica el cambio
- **THEN** todas las reprogramaciones ya registradas quedan como "reprogramación"

#### Scenario: Elegir fecha después de abrirla
- **GIVEN** un boleto que el personal dejó con fecha abierta
- **WHEN** el cliente elige la fecha nueva desde su panel
- **THEN** se registra una reprogramación hecha por ese cliente, aparte del registro de fecha abierta

### Requirement: Motivo de un cambio de fecha
Cada cambio de fecha SHALL poder guardar un motivo opcional en texto libre. Las reprogramaciones existentes y las que no informan motivo MUST quedar sin motivo.

#### Scenario: Fecha abierta con motivo
- **WHEN** el personal deja un boleto con fecha abierta con el motivo "Perdió el colectivo"
- **THEN** el cambio guarda ese motivo

### Requirement: Fecha abierta en el detalle del Historial
El detalle de un boleto en el Historial SHALL mostrar cada cambio de tipo "fecha abierta" como "Dejó el boleto con fecha abierta", con el viaje, el horario y el asiento de origen, el autor y la fecha del cambio, y con el motivo solo si existe. MUST NOT mostrarlo como una reprogramación a un viaje vacío.

#### Scenario: Detalle con motivo
- **GIVEN** un boleto que Carolina dejó con fecha abierta con el motivo "No sabe cuándo vuelve"
- **WHEN** el personal abre su detalle en el Historial
- **THEN** ve "Dejó el boleto con fecha abierta", el viaje y asiento de origen, "Realizado por" Carolina y el motivo

#### Scenario: Detalle sin motivo
- **GIVEN** un boleto dejado con fecha abierta sin motivo
- **WHEN** el personal abre su detalle en el Historial
- **THEN** no ve ningún renglón de motivo

### Requirement: Fecha abierta en los filtros del Historial
Un cambio de tipo "fecha abierta" SHALL contar como cambio de fecha en los filtros del Historial: el boleto MUST aparecer entre los reprogramados y, como lo hace siempre un usuario del panel, en "Vendedores" del filtro "Realizado por".

#### Scenario: Filtro de reprogramados
- **GIVEN** un boleto cuyo único cambio fue quedar con fecha abierta
- **WHEN** el usuario filtra el Historial por boletos reprogramados
- **THEN** el boleto aparece

#### Scenario: Realizado por vendedores
- **GIVEN** un boleto que un vendedor dejó con fecha abierta
- **WHEN** el usuario elige "Vendedores" en "Realizado por"
- **THEN** el boleto aparece, y con "Clientes" no
