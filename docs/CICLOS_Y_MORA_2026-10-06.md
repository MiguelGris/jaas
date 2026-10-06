# Ciclos de facturación y versiones de mora

## Uso

- En Administración → Configuraciones → Ciclo de pago se admite una cantidad entera positiva de meses, sin limitar las opciones a trimestre o semestre.
- El selector de mes es opcional. En blanco, el cambio comienza al terminar el ciclo activo. Si se selecciona un inicio posterior, debe coincidir con el inicio de un ciclo completo; los ciclos anteriores continúan hasta esa fecha. Antes de la primera emisión puede elegirse libremente el mes inicial.
- Los ciclos son continuos: ocho meses pueden abarcar enero–agosto, septiembre–abril y mayo–diciembre. No se reinician en enero.
- La pantalla muestra el ciclo activo y los cambios programados. Se rechazan solapamientos, modificaciones del pasado y cambios que afecten cuotas ya emitidas.
- En la fila Configuración de mora, Nueva versión conserva la versión anterior y cierra su vigencia. El historial se muestra en la misma pantalla, sin agregar un nivel de navegación.
- Cada ciclo conserva las fechas y la mora correspondientes a su inicio. Una nueva versión no recalcula ciclos emitidos. Cero meses de gracia permite el vencimiento al final del propio ciclo.

## Cobranza

La mora se acumula por mes calendario de atraso, una sola vez por ciclo y conexión. Para mantener la selección de cuotas y la distribución contable existente, se presenta en la cuota pendiente más antigua del ciclo. Los importes de mora registrados en otras cuotas del mismo ciclo se descuentan del cálculo; los cobros mensuales separados no la multiplican.

Ejemplo: tres cuotas de S/ 15 y mora de S/ 2, con un mes de atraso, totalizan S/ 47. Si se paga la primera por S/ 17, las otras dos quedan en S/ 30 ese mes. Si siguen pendientes al mes siguiente, el saldo es S/ 32. Cuando se paga todo, deja de generarse deuda. Anular un pago restaura su saldo y conserva la trazabilidad.

Los importes históricos de cuotas, pagos y distribuciones se preservan. No se reescriben cobros anteriores calculados con el criterio previo por cuota. La migración agrega únicamente las referencias de ciclo y la versión aplicable.

## Verificación

- Diez clientes y dos ciclos completos para duraciones de 1, 4, 5, 8 y 13 meses; emisión repetida sin duplicados.
- Continuidad entre años, cambios posteriores al ciclo activo, selector de inicio, rechazo de fechas que acortan ciclos, validación web y API.
- Versiones de mora con importes y gracia diferentes, conservación de ciclos anteriores y protección del historial.
- Cobros separados, incremento de mora en el siguiente mes, pago completo, anulación, nuevo cobro, recibos y totales por concepto.
- Suite completa: 81 pruebas y 1 366 comprobaciones, con SQLite en memoria.

Los respaldos de datos y las evidencias operativas se guardan fuera del repositorio.

La verificación en la instalación existente confirmó que las 70 cuotas recibieron su referencia de ciclo y que ocho conjuntos de datos contables y configuraciones mantuvieron todos sus valores anteriores, incluido el saldo de caja de S/ 1 525. Se revisaron también en el navegador los formularios de programación de ciclos y nueva versión de mora.

Una nueva versión de mora registrada durante la revisión posterior quedó conservada con su auditoría. La comparación final confirmó que cuotas, pagos, distribuciones, ingresos, egresos, cierres y demás configuraciones seguían intactos.
