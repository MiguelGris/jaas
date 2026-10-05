# Correcciones posteriores a las pruebas de uso

Fecha: 5 de octubre de 2026. Referencia: pruebas con diez clientes ficticios y dos trimestres, documentadas en `INFORME_PRUEBAS_JASS_2026-10-05.md`. El informe original conserva los resultados observados antes de corregir.

| Observación | Corrección implementada |
| --- | --- |
| E01: error silencioso y pérdida del formulario de pago | Resumen visible de errores; se conservan cliente, cuotas, multas, medio de pago y observaciones. Límite y contador de 250 caracteres. |
| E02: EXONERADO omitido de la facturación del agua | ACTIVO/ACTIVE y EXONERADO/EXEMPT pueden recibir cuotas de agua. Se mantienen las reglas independientes de exoneración de multas. |
| E03: cobro de meses anteriores a la instalación corregida | La emisión verifica el mes contra la instalación antes de consultar la asignación de uso. Conserva las cuotas históricas existentes. |
| U01: predio nuevo inactivo | Activo marcado inicialmente, con ayuda sobre su efecto en la facturación. |
| U02: emisión solo por consola | Pantalla de revisión y emisión mensual; filtro opcional por titular, confirmación explícita, motivo y auditoría. Acceso con `rates.manage`. |
| U03: cero cuotas sin explicación | Detalle por conexión: existente, lista u omitida y causa concreta. Vista previa sin escrituras. |
| U04: claves antiguas de configuración confundidas | Ayuda para identificar claves vigentes; validación de ciclo 3/6 y día 1–28. Impide renombrar configuraciones y editar el registro automático de última emisión. Las claves antiguas se conservan. |
| U05: difícil localizar clientes | Búsqueda por documento, nombre o código, con paginación que conserva filtros. |
| U06: selección lenta de meses | Botones para seleccionar ciclo, todo o limpiar; total actualizado. Elegir ciclo reemplaza la selección de cuotas; las multas requieren revisión aparte. |
| U07: recibo térmico sin separación de mora | Usa el mismo detalle de conceptos que los reportes; separa servicio, mora y multas. Pantalla legible; formato de impresión conservado en 58 mm. |
| U08: cobro sin revisión final | Diálogo con titular, meses/multas, medio y total; permite volver a corregir antes de confirmar. |
| U09: textos y años confusos | Etiquetas «Registrar», avisos neutros y años sin separadores de miles. |
| U10: tabla de clientes demasiado ancha en teléfono | Tarjetas para clientes en pantallas pequeñas y botones amplios. |

## Ayudas incorporadas

El alta muestra los pasos cliente → predio activo → conexión activa → uso y tarifa. El detalle permite continuar al paso siguiente conservando la relación. El panel agrega búsqueda de clientes y revisión de cuotas según permisos. La impresión se abre al pulsar el botón, para permitir revisar el recibo antes.

## Verificación

Las pruebas de regresión comprueban los errores E01–E03, emisión protegida por permisos, vista previa sin escrituras, auditoría del motivo, emisión repetida sin duplicados, búsqueda y configuración válida. Además, generan 60 cuotas para diez titulares entre enero y junio, con vencimientos de ambos trimestres. Se ejecuta también la suite existente de cobros, anulaciones, caja, mora, medidores, reportes y autorización usando SQLite en memoria.

Los pagos y las cuotas reales existentes se conservan. La corrección de instalación evita nuevas emisiones incorrectas; no recalcula ni elimina obligaciones anteriores. Una cuota omitida por falta de tarifa sigue pendiente de configurar: la pantalla informa la causa, no inventa un precio.

Resultado: **52 pruebas y 436 comprobaciones correctas**, ejecutadas sobre la copia de trabajo y sobre los archivos instalados. La simulación de regresión incluye 20 pagos de diez titulares durante dos trimestres, con saldo pendiente final cero.

Verificación del servidor local: búsqueda por documento, continuidad al predio con titular seleccionado y activo marcado, revisión de cuota ya existente, selección por ciclo/todos/limpiar, diálogo de confirmación y vuelta al formulario, contador de observaciones y recibo con conceptos separados. En una pantalla de 390 × 844, las diez tarjetas son visibles en el flujo normal y la tabla ancha queda oculta; no hay desbordamiento horizontal del documento.

Diagnóstico transaccional sobre los datos ficticios instalados: EXONERADO genera una cuota de agua elegible; instalación en septiembre impide emitir julio; un nuevo cobro de cuota pagada se rechaza. Todos estos cambios se revierten. Los datos originales de QA permanecen: 60 cuotas pagadas, 20 pagos válidos por S/ 1,440, un recibo anulado, diez clientes sin deuda y caja global S/ 1,475. No se confirmaron nuevos cobros desde la interfaz durante esta corrección.

Los archivos originales modificados están respaldados en `C:/Users/IGP/Documents/jass-app/qa/respaldo-antes-correcciones`. Los cambios se aplicaron al proyecto del servidor local en `C:/Users/IGP/Desktop/Jass`; no requieren migraciones. Los estilos se recompilaron con Vite.

## Mejoras propuestas que requieren una decisión adicional

- Pago de una parte de una cuota: definir política, reparto de mora y saldo antes de habilitarlo.
- Asistente completo con guardado por etapas: actualmente hay instrucciones y continuidad entre formularios, no un asistente único.
- Adaptar también las demás tablas extensas del sistema a tarjetas; esta corrección cubre la lista de clientes observada.
- Certificar impresión física en la impresora de 58 mm y uso en teléfonos reales; la comprobación del navegador no sustituye esas pruebas.
