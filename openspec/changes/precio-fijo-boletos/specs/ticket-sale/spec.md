# Spec Delta

## MODIFIED Requirements

### Requirement: Precio y método de pago por pasajero
Cada pasajero MUST tener precio (entre 0 y 999999) y un método de pago activo. Con el precio fijo apagado, el vendedor carga el precio a mano; con el precio fijo prendido, el precio lo calcula el sistema según la capacidad `ticket-fixed-price` y no se puede editar. Al emitir, el sistema rechaza métodos inexistentes o inactivos. Quien tiene el permiso `payment_methods.create` puede crear un método de pago nuevo desde el mismo selector, que queda activo y seleccionado.

#### Scenario: Método inactivo
- **WHEN** se emite una venta con un método de pago inactivo
- **THEN** se rechaza con "Hay un método de pago inválido o inactivo en la venta. Recargá el formulario e intentá de nuevo."

#### Scenario: Precio a mano con el precio fijo apagado
- **GIVEN** el precio fijo apagado
- **WHEN** el vendedor carga 1500 como precio de un pasajero y emite
- **THEN** el boleto se guarda con precio 1500

#### Scenario: Método de pago con el precio fijo prendido
- **GIVEN** el precio fijo prendido
- **WHEN** el vendedor emite sin elegir el método de pago de un pasajero
- **THEN** la venta no se emite y el campo pide el método de pago

## ADDED Requirements

### Requirement: Total de la venta al editar un precio
Cuando la edición de un boleto cambia su precio, ya sea a mano o por el ajuste del seguro del precio fijo, el total de la venta SHALL recalcularse como la suma de los precios de sus boletos.

#### Scenario: Precio editado a mano
- **GIVEN** el precio fijo apagado y una venta de dos boletos de 1000 y 1500, con total 2500
- **WHEN** se edita el primer boleto a 1200
- **THEN** el total de la venta pasa a 2700

#### Scenario: Ajuste del seguro
- **GIVEN** el precio fijo prendido con seguro 800 y una venta de un boleto de 5000, con total 5000
- **WHEN** se edita el boleto y se agrega el menor
- **THEN** el total de la venta pasa a 5800
