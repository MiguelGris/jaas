# Documentación del sistema JASS

Documentación correspondiente al estado funcional del proyecto al 25 de septiembre de 2026.

## Guías disponibles

| Documento | Dirigido a | Contenido |
| --- | --- | --- |
| [Instalación y despliegue](INSTALACION.md) | Administrador técnico | Requisitos, MySQL, instalación, scheduler, producción y actualización. |
| [Manual de usuario](MANUAL_USUARIO.md) | Administrador y operadores | Uso de cada módulo y flujos diarios. |
| [Reglas de negocio](REGLAS_NEGOCIO.md) | JASS, analistas y desarrollo | Cuotas, vencimientos, mora, pagos, multas, caja, códigos y medidores. |
| [Reportes e indicadores](REPORTES.md) | Tesorería, contabilidad y auditoría | Fórmulas del dashboard, filtros y contenido de los reportes. |
| [Arquitectura técnica](ARQUITECTURA.md) | Desarrollo y soporte | Componentes, modelo de datos, seguridad, API interna y pruebas. |
| [Operación y mantenimiento](OPERACION_MANTENIMIENTO.md) | Soporte y administrador técnico | Rutinas, respaldos, tareas automáticas y solución de errores frecuentes. |

## Alcance actual

El sistema cubre el flujo operativo desde el registro de un cliente hasta la emisión de cuotas, cobranza, impresión o anulación del recibo, cierre de caja, reportes y auditoría. También prepara la transición futura a cobro por medidor.

La documentación describe el comportamiento real implementado. Las limitaciones pendientes se marcan expresamente para evitar que una función preparada se confunda con una función ya automatizada.

## Convenciones

- **Cuota:** obligación mensual por el servicio. Su código comienza con `FAC`.
- **Recibo:** comprobante de un pago efectivamente registrado. Su código comienza con `RC`.
- **Recaudación:** suma de pagos válidos, sin incluir ingresos adicionales.
- **Ingreso adicional:** entrada de dinero distinta de un pago de cuota o multa.
- **Moroso:** titular con una cuota cuyo vencimiento ya pasó o con una multa pendiente.
- **Saldo en caja:** saldo del último cierre más cobros e ingresos posteriores, menos egresos posteriores.
