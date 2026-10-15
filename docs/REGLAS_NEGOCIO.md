# Reglas de negocio

## 1. Cuota, recibo e ingreso

Son conceptos distintos:

| Concepto | Momento de creación | Significado | Código |
| --- | --- | --- | --- |
| Cuota mensual | Al emitir el cargo del servicio | Deuda del titular por un mes de servicio | `FAC26-000001` |
| Recibo de pago | Al registrar un cobro | Comprobante de dinero recibido y aplicado a deudas | `RC26-000001` |
| Ingreso adicional | Al registrar otra entrada de caja | Dinero que no proviene de la cobranza de cuotas o multas | `ING26-000001` |

Una cuota pendiente no representa dinero en caja. Un recibo válido sí forma parte de la recaudación.

## 2. Códigos automáticos

Los códigos se generan mediante secuencias protegidas contra concurrencia. Se permiten saltos si una transacción se revierte, pero no duplicados.

| Registro | Formato | Ejemplo |
| --- | --- | --- |
| Usuario | `USR_` + 4 dígitos | `USR_0001` |
| Cliente | `CLI_` + 4 dígitos | `CLI_0001` |
| Predio | `PRD_` + 4 dígitos | `PRD_0001` |
| Suministro | `SUM_` + 4 dígitos | `SUM_0001` |
| Cuota | `FAC` + año corto + 6 dígitos | `FAC26-000001` |
| Recibo | `RC` + año corto + 6 dígitos | `RC26-000001` |
| Operación de pago | `OP` + año corto + 6 dígitos | `OP26-000001` |
| Multa | `MLT` + año corto + 6 dígitos | `MLT26-000001` |
| Asamblea | `ASM` + año corto + 4 dígitos | `ASM26-0001` |
| Ingreso | `ING` + año corto + 6 dígitos | `ING26-000001` |
| Egreso | `EGR` + año corto + 6 dígitos | `EGR26-000001` |

Las series que incluyen año reinician su correlativo para cada año.

## 3. Elegibilidad para generar cuotas

La generación mensual actual procesa únicamente conexiones que cumplan todo lo siguiente:

- Modalidad `FIXED` (pago fijo) o `METERED` (consumo medido).
- Estado de conexión activo (`ACTIVO` o `ACTIVE`).
- Predio activo.
- Cliente activo (`ACTIVO` o `ACTIVE`) o exonerado de multas (`EXONERADO` o `EXEMPT`); la exoneración no elimina el servicio de agua.
- Fecha de instalación anterior o dentro del mes facturado; no se cobra un mes anterior al mes de instalación.
- Asignación de tipo de uso vigente durante el mes.
- Tarifa vigente para ese tipo de uso y año.
- Existencia del ciclo de pago configurado.
- Para `METERED`: precio por m³ y lecturas del mes. Se suman los consumos registrados, incluidos cambios de medidor.

Existe una restricción única por conexión y mes. Por eso ejecutar dos veces la generación para el mismo periodo no duplica la cuota.

La pantalla de emisión informa el motivo de cada omisión. La emisión manual requiere permiso `rates.manage`, motivo y confirmación, y registra cada cuota creada con el operador en auditoría. Modificar la instalación no borra ni recalcula cuotas históricas automáticamente.

## 4. Emisión mensual

La clave `billing_issue_day` define el día de emisión. El sistema acepta operativamente del 1 al 28 y usa 28 si el dato falta o no es válido.

La tarea se evalúa cada tres horas en el minuto 05, con zona horaria `America/Lima`, pero solo genera cuando la fecha del servidor coincide con el día configurado.

La fecha de emisión registrada en la cuota es ese día del mes facturado. El comando manual acepta meses pasados, actuales o futuros:

```bash
php artisan billing:generate-monthly --month=AAAA-MM
```

El comando manual es idempotente. Resulta útil para recuperar un mes cuando el servidor no estuvo activo el día de emisión.

## 5. Ciclos de pago configurables

La clave `billing_period_months` acepta:

- Cualquier entero positivo dentro del calendario admitido. Por ejemplo `1`, `4` u `8`.
- `3`: ciclo trimestral.
- `6`: ciclo semestral.

Cada mes conserva su propia cuota, aunque la obligación de pago se agrupa por ciclo.

La fecha de vencimiento se calcula como:

1. Fin del ciclo configurado al que pertenece el mes.
2. Más los meses de gracia configurados en la regla de mora.
3. Último día del mes resultante.

### Ejemplo trimestral

Para enero, febrero y marzo, con un mes de gracia:

- Fin del ciclo: 31 de marzo.
- Periodo sin mora: todo abril.
- Vencimiento: 30 de abril.
- Morosidad: desde el 1 de mayo.

Las tres cuotas usan el mismo vencimiento. Esto evita considerar moroso al titular durante febrero, marzo o abril.

### Ejemplo semestral

Para enero a junio, con un mes de gracia:

- Fin del ciclo: 30 de junio.
- Periodo sin mora: todo julio.
- Vencimiento: 31 de julio.
- Morosidad: desde el 1 de agosto.

La misma regla se aplica al segundo semestre.

## 6. Mora

La configuración de mora incluye monto mensual, meses de gracia y vigencia.

- No hay mora en la fecha de vencimiento.
- La mora aparece cuando la fecha actual es posterior al vencimiento.
- Los meses de mora se calculan comparando el mes del vencimiento con el mes actual.
- Monto de mora: `meses vencidos × monto mensual vigente`.
- El sistema conserva el mayor valor entre la mora ya guardada y la mora calculada.

La configuración histórica `payment_due_days` se mantiene en el catálogo por compatibilidad, pero el vencimiento vigente se determina mediante ciclo y `grace_months`.

## 7. Deuda pendiente y morosidad

### Deuda pendiente

Incluye toda cuota o multa con saldo, esté o no vencida. Se usa en cobranza y en la consulta pública.

### Deuda morosa

Incluye:

- Cuotas pendientes cuya fecha de vencimiento sea anterior a la fecha de consulta.
- Todas las multas pendientes desde su generación.

Una cuota en periodo de gracia no aparece en morosidad, aunque sí aparece como pendiente.

## 8. Pagos

La cobranza permite seleccionar varias cuotas y multas del mismo cliente en una sola operación.

- Cada concepto seleccionado se cobra por su saldo completo.
- El pago total es la suma de sus distribuciones.
- Solo se aceptan conceptos pendientes que pertenezcan al cliente elegido.
- El recibo, la operación y el usuario se asignan automáticamente.
- Los pagos válidos actualizan la deuda y la caja.

### Pago adelantado

Solo puede cobrarse una cuota que ya exista. Si se desea cobrar por adelantado un periodo aún no emitido, primero debe generarse su cuota con el comando mensual correspondiente. Actualmente no existe una acción web para emitir el año completo de una sola vez.

### Anulación

La anulación sustituye a la eliminación física del pago:

- Requiere un motivo de 3 a 250 caracteres.
- Cambia el estado de `ACTIVE` a `VOIDED`.
- Conserva distribuciones y datos del recibo.
- Recalcula las cuotas y multas afectadas.
- Excluye el pago de recaudación, saldo y reportes.
- No está permitida después de cerrar el periodo de caja.

## 9. Multas de asamblea

Al crear una asamblea se preparan asistencias para clientes registrados antes de la fecha y cuyo estado sea activo o exonerado.

Cuando la asamblea cambia a `HELD` o realizada:

- Si el importe por inasistencia es cero, no se generan multas.
- Se crea una multa por cada inasistente activo.
- Un cliente exonerado no recibe multa por asamblea.
- La combinación cliente-asamblea evita duplicados.
- La multa se considera morosa desde su generación.

## 10. Exoneración de faenas

El reporte de exonerados incluye titulares activos que:

- Tienen fecha de nacimiento registrada.
- Poseen al menos un predio.
- Cumplen o superan `work_exemption_age`.

La edad válida se limita de 18 a 120 años; si la configuración no es válida, se usa 65.

## 11. Caja

### Definiciones

- **Recaudación:** suma de pagos con estado válido.
- **Otros ingresos:** suma de registros de ingresos.
- **Egresos:** suma de gastos registrados.
- **Saldo:** saldo anterior + recaudación + otros ingresos − egresos.

El dashboard presenta el saldo en una fila completa y debajo separa recaudación, ingresos y gastos del mes.

### Cierre

- Solo se puede cerrar un mes ya concluido.
- El nuevo periodo debe ser posterior al último cierre.
- Los importes son calculados por el sistema; no se confía en valores enviados por el formulario.
- El cierre queda asociado al usuario autenticado.
- Un cierre confirmado es inmutable.
- No se pueden modificar o eliminar movimientos de un periodo ya cerrado.

## 12. Medidores

- Una conexión nueva usa pago fijo por defecto.
- Solo una conexión `METERED` puede recibir un medidor utilizable para lecturas.
- La lectura actual no puede ser menor que la anterior.
- No se admite más de una lectura por medidor y fecha.
- Si se modifica o elimina una lectura, se recalcula toda la secuencia posterior.
- El consumo es `lectura actual − lectura anterior`.

La cuota de una conexión `METERED` es la suma de consumos registrados en el mes multiplicada por el precio por m³ de la tarifa vigente, redondeada a dos decimales. Si faltan lecturas o precio, la conexión se omite con diagnóstico en la revisión web; no se cobra la tarifa fija como sustituto.

## 13. Auditoría y permisos

Las altas, modificaciones y eliminaciones realizadas por usuarios autenticados se registran con valores anteriores y nuevos. Se excluyen contraseñas, tokens y marcas técnicas de tiempo.

Los cambios de contraseña se registran con valores protegidos, nunca con el contenido de la contraseña.

El administrador tiene acceso total. Los demás roles dependen de permisos explícitos; además, una cuenta inactiva pierde acceso incluso si conserva rol y permisos.


## Cobro separado y condonación de moras

En Registrar pago, el servicio y la mora son cargos seleccionables por separado. Seleccionar un ciclo selecciona sus cuotas de servicio; las moras se marcan en su propia sección. Seleccionar todos incluye servicio, moras y multas.

Pagar todo el servicio emitido de un ciclo detiene la acumulación de su mora en la fecha del último pago de servicio. La mora pendiente permanece como deuda independiente. Los otros ciclos con servicio impago siguen acumulando mora. Anular un pago de servicio puede reabrir ese ciclo y reactivar el cálculo; anular un pago de mora restaura únicamente la mora cobrada.

La condonación está disponible en la cobranza del cliente para administradores y usuarios autorizados a gestionar las reglas de mora (`rates.manage`). Se pueden seleccionar una o varias deudas y condonar todo el saldo, un porcentaje de cada deuda o un importe total aplicado a las más antiguas. Cada operación exige un motivo o campaña y guarda el usuario, fecha, importe y cuota de origen en un historial y en la bitácora.

Una condonación no es un cobro ni suma a caja o a las moras recaudadas de los reportes. No modifica recibos históricos ni permite condonar importes ya cobrados. Si el servicio sigue impago, una campaña condona la deuda existente a esa fecha, sin exonerar la mora futura.

Los pagos históricos combinados mantienen su distribución. Los nuevos pagos separados identifican explícitamente el concepto de cada asignación para que recibos, reportes y anulaciones distingan servicio y mora.

## Campañas de condonación masiva de servicio, moras y multas

La opción Administración → Condonación masiva está disponible únicamente para el administrador. La restricción también se valida en el servidor para listar, revisar y aplicar campañas, aunque otro usuario tenga todos los permisos del catálogo.

Se pueden filtrar deudas por cliente, sector, tipo y fecha límite. Las listas permiten 10, 20, 50 o 100 deudas por página. Se puede condonar solo lo marcado en la página o todas las deudas que coincidan con los filtros, incluidas las demás páginas.

Una campaña condona todo el saldo, un porcentaje de cada deuda o un importe por cliente aplicado desde sus deudas seleccionadas más antiguas. En la modalidad por importe, cada cliente recibe hasta el importe indicado, limitado a su saldo seleccionado. Si debe menos, se condona solo su deuda; la diferencia no crea un saldo a favor ni aumenta el importe asignado a otro cliente. Antes de aplicar se muestra una revisión paginada con clientes, deudas, saldo actual, importe condonado y saldo restante. El motivo es obligatorio. La confirmación conserva la selección revisada y vuelve a comprobar los saldos; si algún cobro o condonación los cambió, se rechaza toda la campaña para evitar resultados parciales.

La bitácora registra cada condonación de mora o multa y el resumen de la campaña: administrador, fecha, motivo, clientes, cantidad de deudas y totales separados. Las condonaciones de mora y multa no crean recibos de pago ni ingresos de caja. Las de servicio generan su propio recibo de condonación, sin dinero recibido. Los pagos anteriores y el importe original de las multas se conservan; solo se reduce el saldo pendiente. Anular un cobro posterior restaura el importe cobrado, conservando la condonación.


## Condonación del servicio y recibos mensuales

Las campañas del administrador permiten seleccionar servicio, moras y multas juntos o filtrar cada concepto. Desde Registrar pago, un usuario autorizado para cobranza (`payments.create`) también puede condonar el servicio de las cuotas seleccionadas del cliente. La condonación masiva sigue reservada al administrador.

Cada cuota afectada genera un recibo mensual de condonación con código propio, periodo, saldo anterior, importe condonado, saldo restante, fecha, motivo y usuario. Indica DEUDA CONDONADA o DEUDA PARCIALMENTE CONDONADA, con dinero recibido S/ 0.00. Puede imprimirse en papel de 58 u 80 mm desde el historial del cliente o los recibos de la campaña.

En condonaciones individuales se rechazan importes superiores al saldo pendiente. En campañas masivas, el importe por cliente se limita automáticamente a la deuda seleccionada de ese cliente, sin rechazar a quienes deben menos. Los porcentajes superiores al 100% se rechazan en ambas modalidades. Los saldos no pueden ser negativos ni convertirse en créditos a favor del cliente. Una condonación no crea un pago ni aumenta caja o recaudación. Los pagos posteriores solo cobran el saldo restante y las anulaciones conservan las condonaciones previas.

Un ciclo cuyo servicio queda totalmente cubierto por pagos, condonaciones o ambos deja de acumular mora en la fecha en que se cubre la última cuota de servicio. Su mora existente permanece pendiente salvo que también se condone o pague. Todas las condonaciones del servicio y sus campañas se registran en la bitácora.

## Cuotas históricas con multas incluidas

La condonación del servicio cubre únicamente la tarifa de servicio pendiente. Una multa incluida en una cuota antigua conserva su saldo; no se condona al seleccionar servicio.

La mora del ciclo depende del servicio impago. Cuando el servicio está totalmente cubierto, una multa antigua pendiente no sigue aumentando la mora del ciclo. Las cuotas de tarifa cero sin servicio impago no generan mora nueva.

Las asambleas con multas condonadas no pueden reabrirse, cancelarse ni cambiar el importe histórico de la multa. Así se conserva la deuda que respaldó la campaña y se evita reescribir las condonaciones registradas.

## Documento obligatorio del cliente

Al registrar o editar un cliente se exige DNI de exactamente 8 dígitos para una persona natural o RUC de exactamente 11 dígitos para una empresa o negocio. El documento debe ser único. No se aceptan valores vacíos ni espacios; las validaciones se aplican en el formulario web y en el servidor, incluida la API.

Una actualización parcial por API puede omitir el documento si el cliente ya tiene uno válido, pero no puede borrarlo. Los registros históricos sin documento no se completan con números ficticios: deben regularizarse al editarse. No se cambió la columna a NOT NULL.
