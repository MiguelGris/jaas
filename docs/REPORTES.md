# Reportes e indicadores

## 1. Indicadores del dashboard

### Fila financiera

| Indicador | Cálculo | Destino al abrir |
| --- | --- | --- |
| Saldo en caja | Último saldo cerrado + pagos válidos + otros ingresos − egresos posteriores | Cierres de caja |
| Recaudación del mes | Suma de pagos válidos cuya fecha pertenece al mes actual | Recibos de pago |
| Ingresos del mes | Suma de ingresos adicionales del mes actual | Ingresos |
| Gastos del mes | Suma de egresos del mes actual | Egresos |

La recaudación no incluye otros ingresos. El saldo en caja sí integra ambos tipos de entrada.

### Indicadores operativos

- Clientes registrados.
- Conexiones registradas.
- Cuotas pendientes.
- Morosos y total vencido.
- Próximas asambleas.
- Pagos válidos del día y monto recaudado.

El dashboard limita **Últimos movimientos** y **Cuotas recientes** a cinco filas para conservar la legibilidad.

## 2. Formatos

Todos los reportes pueden generarse en:

- **Excel (`.xlsx`):** descarga con encabezados, formatos monetarios y panel inmovilizado.
- **PDF:** se abre en línea en el navegador; desde allí puede descargarse o imprimirse.

El PDF usa orientación horizontal cuando el reporte tiene más de cinco columnas.

## 3. Reportes disponibles

### Flujo de caja mensual

Filtro: mes en formato `AAAA-MM`.

Combina cronológicamente:

- Cobros válidos.
- Otros ingresos.
- Egresos.

Muestra fecha, tipo, código, concepto, ingreso, egreso y saldo acumulado dentro del periodo. El resumen presenta ingresos totales, egresos totales y saldo del mes.

### Balance anual

Filtro: año entre 2000 y 2100.

Presenta los 12 meses con:

- Cobros.
- Otros ingresos.
- Ingresos totales.
- Egresos.
- Saldo mensual.
- Saldo acumulado del año.

Los pagos anulados no se incluyen.

### Resumen mensual por concepto

Filtro: mes en formato `AAAA-MM`.

Separa los movimientos del mes en servicios cobrados, multas cobradas, moras cobradas, otros ingresos y egresos. El resumen presenta la recaudación por pagos, las entradas totales, los egresos y el saldo del periodo.

### Resumen anual por concepto

Filtro: año entre 2000 y 2100.

Muestra una fila por mes con columnas independientes para servicios, multas, moras, otros ingresos, egresos y saldo. Los conceptos de pago se obtienen de las distribuciones de recibos válidos; los pagos anulados no participan.

### Lista de morosos

No incluye todas las cuotas pendientes. Solo contiene:

- Cuotas vencidas con saldo.
- Multas pendientes.

Muestra código, DNI, titular, teléfono, cantidad de cuotas vencidas, cantidad de multas y deuda morosa total.

### Antigüedad de deuda

Detalla cada cuota vencida y multa pendiente, agrupando su antigüedad en:

- 0 a 30 días.
- 31 a 60 días.
- 61 a 90 días.
- Más de 90 días.

El resumen acumula el saldo de cada rango.

### Recaudación por medio de pago

Filtro: mes en formato `AAAA-MM`.

Agrupa pagos válidos por efectivo, Yape, Plin, transferencia u otros medios configurados. Muestra cantidad de operaciones, total recaudado y porcentaje de participación.

### Padrón de conexiones

Lista suministro, cliente, DNI, titular, predio, dirección, tipo de servicio, modalidad de cobro, estado y medidor activo. Resume conexiones totales, de pago fijo y con medidor.

### Asistentes e inasistentes

Filtro: asamblea. Si no se indica una, usa la más reciente.

Muestra código, DNI, titular, teléfono, estado de asistencia y observaciones. Resume asistentes e inasistentes.

### Exonerados de faenas

Usa la edad `work_exemption_age`. Incluye titulares activos con predios que alcanzan la edad mínima. Muestra datos del titular, edad, predio y dirección.

## 4. Rutas de descarga

La forma general es:

```text
/reportes/{reporte}/{formato}
```

| Reporte | Identificador | Filtros admitidos |
| --- | --- | --- |
| Flujo de caja | `cash-flow` | `month=AAAA-MM` |
| Balance anual | `annual-balance` | `year=AAAA` |
| Conceptos mensuales | `payment-concepts-monthly` | `month=AAAA-MM` |
| Conceptos anuales | `payment-concepts-annual` | `year=AAAA` |
| Morosos | `debtors` | Ninguno |
| Antigüedad | `debt-aging` | Ninguno |
| Medios de pago | `payment-methods` | `month=AAAA-MM` |
| Padrón | `service-register` | Ninguno |
| Asistencia | `attendance` | `assembly_id=N` |
| Exonerados | `work-exemptions` | Ninguno |

Los formatos válidos son `xlsx` y `pdf`. Ejemplos:

```text
/reportes/cash-flow/pdf?month=2026-09
/reportes/annual-balance/xlsx?year=2026
```

Se requiere una sesión activa y el permiso `reports.view`.

## 5. Consistencia de cifras

Para investigar una diferencia:

1. Comprueba si existen pagos anulados; no deben contarse.
2. Separa pagos de otros ingresos.
3. Verifica las fechas efectivas de pago, ingreso y egreso.
4. Revisa el último cierre de caja.
5. Confirma que el periodo consultado sea el mismo en dashboard y reporte.
6. Consulta la bitácora para identificar cambios o eliminaciones.

El flujo de caja mensual calcula un saldo propio del periodo. El saldo en caja del dashboard, en cambio, parte del último cierre confirmado y agrega movimientos posteriores; por ello pueden representar contextos distintos.
