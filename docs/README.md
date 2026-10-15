# Documentación del sistema JASS

Documentación actualizada al 15 de octubre de 2026. Incluye DNI/RUC obligatorio, cobro separado de mora, condonaciones individuales y masivas, recibos y reportes de condonación, y revisión de campos vacíos de la base de datos.

## Guías disponibles

| Documento | Dirigido a | Contenido |
| --- | --- | --- |
| [Instalación y despliegue](INSTALACION.md) | Administrador técnico | Requisitos, MySQL, instalación, scheduler, producción y actualización. |
| [Manual de usuario](MANUAL_USUARIO.md) | Administrador y operadores | Uso de cada módulo y flujos diarios. |
| [Reglas de negocio](REGLAS_NEGOCIO.md) | JASS, analistas y desarrollo | Cuotas, vencimientos, mora, pagos, multas, caja, códigos y medidores. |
| [Reportes e indicadores](REPORTES.md) | Tesorería, contabilidad y auditoría | Fórmulas del dashboard, filtros y contenido de los reportes. |
| [Arquitectura técnica](ARQUITECTURA.md) | Desarrollo y soporte | Componentes, modelo de datos, seguridad, API interna y pruebas. |
| [Revisión de base de datos](REVISION_BASE_DATOS_2026-10-14.md) | Soporte y desarrollo | Campos vacíos, usos comprobados y decisión de conservar el esquema. |
| [Operación y mantenimiento](OPERACION_MANTENIMIENTO.md) | Soporte y administrador técnico | Rutinas, respaldos, tareas automáticas y solución de errores frecuentes. |

## Alcance actual

Consulta la [relación de observaciones corregidas y verificaciones](CORRECCIONES_2026-10-05.md).

El sistema cubre el flujo operativo desde el registro de un cliente hasta la emisión de cuotas, cobranza, impresión o anulación del recibo, cierre de caja, reportes y auditoría. Incluye cobro fijo y por consumo medido con lecturas del mes.

La documentación describe el comportamiento real implementado. Las limitaciones pendientes se marcan expresamente para evitar que una función preparada se confunda con una función ya automatizada.

## Convenciones

- **Cuota:** obligación mensual por el servicio. Su código comienza con `FAC`.
- **Recibo de pago:** comprobante de un pago efectivamente registrado. Su código comienza con `RC`.
- **Recibo de condonación de servicio:** comprobante de deuda condonada, con código `CD` y dinero recibido S/ 0.00; no suma a caja.
- **Recaudación:** suma de pagos válidos, sin incluir ingresos adicionales.
- **Ingreso adicional:** entrada de dinero distinta de un pago de cuota o multa.
- **Moroso:** titular con una cuota cuyo vencimiento ya pasó o con una multa pendiente.
- **Saldo en caja:** saldo del último cierre más cobros e ingresos posteriores, menos egresos posteriores.
