# Spec Delta

## Purpose

Permite que cada vendedor consulte en el panel sus propias ventas, los boletos de cada venta, los pagos que realizó y su saldo, y que exporte esos datos, sin acceder a la información de otros vendedores.

## ADDED Requirements

### Requirement: Acceso a "Mis ventas"
El clúster Ventas SHALL ofrecer una página "Mis ventas" visible y accesible solo para usuarios con el permiso "Ver mis ventas". Un usuario sin ese permiso MUST NOT verla en la navegación ni poder abrirla por URL.

#### Scenario: Vendedor ve la página
- **GIVEN** un usuario con el rol Vendedor y los permisos por defecto
- **WHEN** abre el panel
- **THEN** ve el clúster Ventas con la página "Mis ventas" y puede abrirla

#### Scenario: Administrador no la ve
- **GIVEN** un usuario con el rol Administrador y los permisos por defecto
- **WHEN** abre el clúster Ventas
- **THEN** no ve "Mis ventas" en la navegación

#### Scenario: Acceso directo sin permiso
- **GIVEN** un usuario sin el permiso "Ver mis ventas"
- **WHEN** abre la URL de "Mis ventas"
- **THEN** recibe un error de acceso denegado

#### Scenario: Vendedor sin otros permisos de Ventas
- **GIVEN** un Vendedor que solo tiene "Ver mis ventas" dentro del módulo Ventas
- **WHEN** entra al clúster Ventas
- **THEN** llega a "Mis ventas" y no ve las páginas Ventas ni Pagos

### Requirement: Contenido de "Mis ventas"
La página SHALL mostrar en la propia página, sin abrir un modal, el mismo detalle que el administrador ve al abrir "Ver detalle" de un vendedor en el recurso Ventas: resumen de totales, desglose por método de pago, saldo, filtros, ventas con sus boletos y pagos realizados.

#### Scenario: Detalle completo en la página
- **GIVEN** un vendedor con ventas y pagos en el mes actual
- **WHEN** abre "Mis ventas"
- **THEN** ve los boletos vendidos, el total de ventas, los pagos realizados, el total de pagos y el saldo (ventas menos pagos)
- **AND** ve sus ventas con el detalle de boletos de cada una y sus pagos

#### Scenario: Mismos números que ve el administrador
- **GIVEN** un vendedor y un administrador con el mismo rango de fechas y método de pago
- **WHEN** el vendedor abre "Mis ventas" y el administrador abre el detalle de ese vendedor
- **THEN** ambos ven los mismos totales, el mismo saldo y los mismos registros

### Requirement: Solo los datos propios
"Mis ventas" SHALL mostrar únicamente las ventas, boletos y pagos del usuario logueado. El usuario MUST NOT poder consultar ni exportar datos de otro vendedor desde esta página, aunque manipule la petición desde el navegador.

#### Scenario: No ve ventas ajenas
- **GIVEN** dos vendedores con ventas en el mismo período
- **WHEN** uno de ellos abre "Mis ventas"
- **THEN** solo ve sus propias ventas y pagos

#### Scenario: Intento de cambiar el vendedor consultado
- **GIVEN** un vendedor en "Mis ventas"
- **WHEN** intenta cambiar desde el navegador el vendedor que consulta la página
- **THEN** la petición es rechazada y no se muestran datos de otro vendedor

### Requirement: Filtros de "Mis ventas"
La página SHALL abrir con el rango de fechas del mes actual, todos los métodos de pago y todos los tipos de registro, y SHALL permitir cambiar el rango, elegir uno o más métodos de pago, el tipo (todos, ventas, pagos) y el orden por fecha, y restablecer los filtros.

#### Scenario: Valores iniciales
- **WHEN** un vendedor abre "Mis ventas"
- **THEN** el rango va del primer al último día del mes actual y se muestran ventas y pagos de todos los métodos

#### Scenario: Varios métodos de pago
- **GIVEN** un vendedor con ventas y pagos en efectivo, por transferencia y con tarjeta
- **WHEN** elige "Efectivo" y "Transferencia" en el filtro de método
- **THEN** ve y suma solo las ventas y pagos de esos dos métodos

#### Scenario: Restablecer
- **GIVEN** un vendedor que cambió el rango y el método de pago
- **WHEN** presiona "Restablecer"
- **THEN** los filtros vuelven a los valores iniciales

### Requirement: Exportación de los datos propios
El vendedor SHALL poder exportar a PDF y a Excel lo que muestra "Mis ventas" con los filtros vigentes, y la exportación MUST contener solo sus propios datos.

#### Scenario: Exportar PDF
- **GIVEN** un vendedor con ventas en el período filtrado
- **WHEN** presiona "PDF"
- **THEN** descarga un PDF con sus ventas, pagos y totales del período

#### Scenario: Exportar Excel
- **GIVEN** un vendedor con ventas en el período filtrado
- **WHEN** presiona "Excel"
- **THEN** descarga un Excel con sus ventas, pagos y totales del período
