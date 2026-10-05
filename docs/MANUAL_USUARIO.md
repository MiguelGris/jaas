# Manual de usuario

## 1. Acceso y navegación

La página principal permite consultar deudas sin iniciar sesión. El titular ingresa un DNI de exactamente 8 dígitos y ve sus cuotas pendientes, moras y multas.

El personal autorizado entra por **Ingresar**. El menú lateral conserva su posición al navegar y se adapta a pantallas pequeñas. Las opciones visibles dependen del rol y de sus permisos.

En el nombre del usuario, arriba a la derecha, se encuentran:

- **Cambiar contraseña**.
- **Cerrar sesión**.

El icono de cierre rápido también se mantiene disponible.

## 2. Flujo inicial recomendado

Antes de iniciar la cobranza:

1. Revisa los catálogos de sectores, estados, tipos de conexión, usos y medios de pago.
2. Configura el ciclo de pago, la mora, el día de emisión y la edad de exoneración.
3. Registra las tarifas del año.
4. Registra clientes.
5. Asigna uno o más predios a cada cliente.
6. Crea las conexiones de cada predio.
7. Verifica la asignación del tipo de uso de cada conexión.

## 3. Clientes y predios

### Clientes

Registra DNI, nombres, apellidos, fecha de nacimiento, contacto, fecha de alta y estado. El código se asigna automáticamente, por ejemplo `CLI_0001`.

El DNI se usa para la consulta pública, para localizar al titular durante la cobranza y para registrar asistencia mediante lector de código de barras.

### Predios

Cada predio pertenece a un cliente e incluye dirección, sector y estado. El selector permite buscar al titular para evitar recorrer una lista extensa. El código se genera como `PRD_0001`.

Los predios nuevos empiezan activos. Desmarca **Activo** solamente cuando corresponda suspender la emisión. En el detalle del cliente puedes elegir **Registrar predio de este cliente**; en el del predio, **Registrar conexión en este predio**. El titular o predio queda seleccionado. En la conexión, **Asignar uso** conserva el suministro seleccionado. Revisa antes las asignaciones existentes para evitar duplicar una vigencia.

En **Clientes**, busca por nombre, apellido, DNI, RUC o código. En teléfono, la lista se presenta en tarjetas con acciones grandes.

## 4. Servicios y conexiones

Cada conexión pertenece a un predio y recibe un suministro automático como `SUM_0001`. Debe indicarse:

- Tipo de servicio: agua, desagüe o ambos.
- Estado operativo.
- Fecha de instalación.
- Modalidad de cobro: **Pago fijo** o **Con medidor**.

La modalidad predeterminada es pago fijo. Al crear una conexión se asigna inicialmente el uso residencial, que luego puede cambiarse mediante **Asignaciones de uso** indicando sus fechas de vigencia.

No se puede cambiar una conexión con medidor activo a pago fijo hasta desactivar o retirar ese medidor.

## 5. Tarifas, cuotas y mora

### Tarifas

La tarifa se registra por tipo de uso y año. Contiene:

- Monto mensual fijo.
- Precio por metro cúbico para conexiones con medidor y lecturas registradas.
- Fecha de inicio y fin de vigencia.
- Indicación de aprobación por asamblea.

### Generar cuotas

Las cuotas de conexiones activas se generan automáticamente en el día configurado. Las de medidor requieren precio por m³ y lecturas del mes. Si el servidor estuvo apagado, un usuario con permiso de tarifas puede abrir **Revisar cuotas** en el panel o **Revisar y generar cuotas** en Cuotas.

Selecciona un mes y, si corresponde, un titular. **Revisar emisión** no crea deuda: muestra cuotas listas, existentes y omitidas con su causa (tarifa, uso, estados, instalación o lecturas). Escribe el motivo y usa **Confirmar generación** solamente después de revisar. Se emite un mes por ejecución; repite para recuperar otros meses. Una cuota existente no se duplica ni se modifica.

Un cliente EXONERADO sigue pagando agua; la exoneración corresponde a las multas aplicables. No se emiten cuotas de meses anteriores al mes de instalación. Al corregir la fecha, revisa la asignación de uso; las cuotas históricas existentes requieren revisión administrativa, no se eliminan automáticamente.

Una cuota es una obligación mensual (`FAC26-000001`), no un comprobante de pago. Puede estar pendiente, pagada o anulada.

### Cuotas pendientes y morosidad

- **Cuotas pendientes** muestra toda obligación con saldo por pagar, aunque todavía esté dentro del plazo.
- **Morosidad** muestra solamente cuotas cuyo vencimiento ya pasó y todas las multas pendientes.

La fecha de vencimiento depende del ciclo trimestral o semestral y de los meses de gracia. La mora empieza al día siguiente del vencimiento.

## 6. Cobranza y recibos

En **Caja > Pagos**:

1. Busca por DNI, nombres o apellidos.
2. Selecciona al cliente correcto.
3. Marca las cuotas y multas que pagará.
4. Elige el medio de pago.
5. Agrega una observación si corresponde.
6. Pulsa **Registrar pago y emitir recibo**, revisa el titular, los conceptos, el medio y el total; elige **Confirmar pago y emitir recibo** o **Volver y corregir**.

Puedes seleccionar todas las cuotas y multas, limpiar la selección o elegir un trimestre/semestre disponible. El botón de ciclo selecciona solo las cuotas de ese ciclo y reemplaza la selección anterior de cuotas; revisa las multas aparte. Las observaciones admiten 250 caracteres, con contador. Si el servidor rechaza el pago, se muestra el motivo y se conservan tus entradas; corrige el problema antes de reintentar.

El sistema cobra el saldo completo de cada concepto seleccionado y crea:

- Un recibo automático `RC26-000001`.
- Un número de operación único `OP26-000001`.
- Las distribuciones del pago entre cuotas y multas.
- El registro del usuario que atendió la operación.

Después del cobro puede abrirse la impresión térmica de 58 mm. En el diálogo de impresión debe usarse escala 100 % y sin márgenes.

El recibo separa servicio, mora y multas. Primero se muestra en pantalla; el botón **Imprimir recibo** abre la impresión cuando lo necesitas.

### Anular un pago

Si el cobro fue registrado por error, abre el recibo y selecciona **Anular**. Debes escribir el motivo.

La anulación:

- Conserva el recibo y su trazabilidad.
- Marca el pago como anulado.
- Lo excluye de caja, recaudación y reportes.
- Devuelve las cuotas y multas relacionadas a pendientes.
- Registra quién anuló, cuándo y por qué.

No se puede anular un pago perteneciente a un periodo de caja ya cerrado.

## 7. Ingresos, egresos y caja

### Ingresos

Registra entradas diferentes de los cobros, como donaciones u otros conceptos. El usuario responsable se toma automáticamente de la sesión. El código tiene el formato `ING26-000001`.

### Egresos

Registra fecha, categoría, concepto, monto y comprobante. El usuario responsable también se toma de la sesión. El código tiene el formato `EGR26-000001`.

Los ingresos y egresos pueden eliminarse para corregir un error mientras su periodo permanezca abierto. Si existe un cierre que incluye esa fecha, el sistema impide la modificación o eliminación.

### Cierre de caja

El cierre solo puede hacerse para un mes concluido y posterior al último cierre. Los importes se calculan automáticamente:

`saldo anterior + pagos válidos + otros ingresos - egresos`

Una vez confirmado, el cierre no puede editarse ni eliminarse. Los campos de año y mes aceptan únicamente enteros dentro de los rangos permitidos.

## 8. Asambleas, asistencias y multas

Al crear una asamblea se define fecha, hora, tipo, lugar, importe por inasistencia y estado. El sistema prepara una fila de asistencia para cada titular elegible.

### Registro manual

En **Asistencias** busca la asamblea, elige **Editar** y marca o desmarca **Asistió**. Si la asamblea ya fue realizada, el sistema crea o retira automáticamente la multa correspondiente.

Si un inasistente se corrige posteriormente como asistente, la multa pendiente se elimina. Si la multa ya fue cobrada, queda anulada como historial y el pago, el recibo y el saldo de caja no se modifican.

### Lector de DNI

En **Lector de asistencia**:

1. Selecciona una asamblea programada.
2. Mantén el cursor en el campo de lectura.
3. Lee el código de barras del DNI.
4. El sistema extrae los 8 dígitos, registra al asistente y muestra su nombre.
5. El campo queda listo para la siguiente lectura.

El nombre confirmado se muestra en tamaño grande. Debajo del lector aparece la lista acumulada de asistentes, la hora exacta de cada registro y el contador de avance con el formato **n de N registran asistencia**. Si se lee el mismo DNI otra vez, el sistema informa que ya estaba registrado.

### Cierre de la asamblea

Al cambiar el estado a **Realizada**, se genera automáticamente una multa para cada inasistente activo. El proceso no duplica multas si se guarda más de una vez. Los titulares con estado exonerado no reciben esta multa.

## 9. Medidores y lecturas

Para usar lecturas:

1. Cambia la conexión a **Con medidor**.
2. Registra el medidor, su número, fecha, lectura inicial y estado activo.
3. Registra lecturas posteriores en orden cronológico.

El sistema calcula automáticamente:

- Lectura anterior.
- Lectura actual.
- Consumo como diferencia entre ambas.

No permite lecturas menores que la anterior, fechas duplicadas para el mismo medidor, medidores inactivos ni lecturas en conexiones de pago fijo.

La facturación mensual por consumo medido utiliza la suma de consumos registrados en el mes multiplicada por el precio por metro cúbico de la tarifa vigente. Si falta precio o lecturas, no se emite una cuota; revisa la causa desde **Revisar cuotas**.

## 10. Reportes

Los reportes están disponibles en el panel y en la sección **Reportes** del menú. Pueden descargarse en Excel o abrirse como PDF directamente en el navegador.

Incluyen flujo de caja mensual, balance anual, morosos, antigüedad de deuda, recaudación por medio, padrón de conexiones, asistencias e inasistencias y exonerados de faenas. El reporte de asistencias comienza con un resumen general y otro por barrio antes del detalle nominal.

Consulta las fórmulas y filtros en [Reportes e indicadores](REPORTES.md).

## 11. Usuarios, roles y auditoría

El administrador puede crear usuarios, asignar roles y activar o desactivar cuentas. No es posible desactivar o eliminar la propia cuenta durante la sesión.

Los roles iniciales son Administrador, Cajero, Contabilidad, Auditor y Operador. Los permisos controlan los módulos y acciones disponibles.

La **Bitácora de auditoría** registra altas, cambios y eliminaciones con usuario, tabla, registro, fecha y valores anteriores/nuevos. Las contraseñas nunca se guardan en texto dentro de la bitácora.

## 12. Eliminación de catálogos

Los catálogos muestran la opción **Eliminar**. Si un valor ya está relacionado con clientes, conexiones, tarifas u otros registros, la base de datos impide borrarlo y el sistema muestra una advertencia. En ese caso, corrige o reasigna primero la información dependiente.
