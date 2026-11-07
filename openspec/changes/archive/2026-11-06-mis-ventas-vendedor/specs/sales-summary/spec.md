# Spec Delta

## Purpose

Define cómo el clúster Ventas calcula y muestra las ventas, los pagos y el saldo de cada vendedor (tabla de Ventas, tarjeta de resumen y detalle por vendedor), para que los números sean consistentes entre pantallas y el detalle solo se muestre a quien corresponde.

## ADDED Requirements

### Requirement: Vendedores listados según el período
La tabla de Ventas SHALL listar a un vendedor solo si tiene al menos una venta o un pago dentro del rango de fechas filtrado. MUST NOT listarlo por tener ventas antes y después del rango sin ninguna dentro de él.

#### Scenario: Ventas fuera del rango por ambos lados
- **GIVEN** un vendedor con una venta en septiembre y otra en noviembre, y ninguna en octubre
- **WHEN** se filtra la tabla de Ventas por octubre
- **THEN** el vendedor no aparece

#### Scenario: Venta dentro del rango
- **GIVEN** un vendedor con una venta en octubre
- **WHEN** se filtra la tabla de Ventas por octubre
- **THEN** el vendedor aparece con esa venta

#### Scenario: Solo pagos en el período
- **GIVEN** un vendedor sin ventas en octubre que realizó un pago en octubre
- **WHEN** se filtra la tabla de Ventas por octubre
- **THEN** el vendedor aparece con 0 boletos, el pago en sus totales y saldo negativo
- **AND** el pago se suma en la tarjeta de resumen

### Requirement: Filtro por método de pago consistente
Cuando se filtra por uno o más métodos de pago, la tabla de Ventas, su ordenamiento, la tarjeta de resumen y el detalle por vendedor SHALL considerar solo las ventas y los pagos de esos métodos. Con el mismo filtro, el saldo de un vendedor MUST ser igual en la tabla, el resumen y el detalle.

#### Scenario: Pagos filtrados por método
- **GIVEN** un vendedor con un pago en efectivo y otro por transferencia en el período
- **WHEN** se filtra la tabla de Ventas por "Efectivo"
- **THEN** la fila del vendedor y la tarjeta de resumen cuentan solo el pago en efectivo

#### Scenario: Mismo saldo en tabla y detalle
- **GIVEN** la tabla de Ventas filtrada por un rango y un método de pago
- **WHEN** se abre el detalle de un vendedor
- **THEN** el detalle abre con el mismo rango y el mismo método
- **AND** muestra el mismo total de ventas, total de pagos y saldo que la fila

#### Scenario: Detalle hereda varios métodos
- **GIVEN** la tabla de Ventas filtrada por "Efectivo" y "Transferencia"
- **WHEN** se abre el detalle de un vendedor
- **THEN** el filtro de método del detalle tiene elegidos "Efectivo" y "Transferencia"
- **AND** los totales coinciden con los de la fila

#### Scenario: Ordenar respeta el método
- **GIVEN** la tabla de Ventas filtrada por "Efectivo"
- **WHEN** se ordena por total de ventas, total de pagos o saldo
- **THEN** el orden coincide con los montos que muestra cada fila

### Requirement: Rendimiento de la tabla de Ventas
Los totales por vendedor de la tabla de Ventas SHALL calcularse sin cargar cada venta y boleto del período en memoria, de modo que la cantidad de consultas no crezca con la cantidad de ventas.

#### Scenario: Muchas ventas en el período
- **GIVEN** vendedores con cientos de ventas en el rango filtrado
- **WHEN** se abre la tabla de Ventas
- **THEN** la cantidad de consultas no depende de la cantidad de ventas ni de boletos

### Requirement: Autorización del detalle por vendedor
El detalle de ventas y pagos de un vendedor SHALL mostrarse y exportarse solo a usuarios con el permiso "Ver detalle" de Ventas (cualquier vendedor) o con "Ver mis ventas" cuando el vendedor consultado es el propio usuario. El vendedor consultado MUST NOT poder cambiarse desde el navegador.

#### Scenario: Administrador ve cualquier vendedor
- **GIVEN** un usuario con "Ver detalle" de Ventas
- **WHEN** abre el detalle de cualquier vendedor
- **THEN** ve sus ventas, pagos y saldo

#### Scenario: Vendedor intenta ver a otro
- **GIVEN** un usuario con "Ver mis ventas" y sin "Ver detalle" de Ventas
- **WHEN** se intenta cargar el detalle de otro vendedor
- **THEN** se rechaza con acceso denegado

#### Scenario: Cambio del vendedor desde el navegador
- **GIVEN** un detalle de vendedor ya cargado
- **WHEN** el navegador intenta cambiar el vendedor consultado
- **THEN** la petición es rechazada

#### Scenario: Exportación sin permiso
- **GIVEN** un usuario que perdió los permisos de Ventas con el detalle abierto
- **WHEN** presiona "PDF" o "Excel"
- **THEN** la exportación se rechaza con acceso denegado
