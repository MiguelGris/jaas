# Correcciones de la prueba funcional del 5 de octubre de 2026

Se corrigen los errores detectados durante la simulación con diez clientes y dos ciclos de pago.

| Hallazgo | Comportamiento corregido |
| --- | --- |
| Asistencia manual duplicada causaba error 500 | Registrar asistencia actualiza el padrón preparado, conserva una sola fila y registra el cambio en la bitácora. No se permite trasladar una asistencia a otro cliente o asamblea. |
| Reabrir una asamblea dejaba multas cobrables | Reabrir o cancelar anula las multas pendientes. Si hay pagos activos, se exige resolver sus recibos antes. El cobro y la reapertura comparten bloqueo transaccional. Las multas antiguas de asambleas programadas o canceladas quedan fuera de la deuda y no pueden cobrarse. |
| Auditor veía acciones que no podía completar | Los formularios GET y las acciones visibles usan la misma matriz de permisos. Se ocultan Usuarios, botones de escritura y grupos vacíos para los roles sin acceso. Las tarjetas del panel respetan el acceso al módulo. |
| Uso residencial podía duplicarse | El detalle explica la asignación automática y enlaza los usos existentes. Web y API rechazan fechas cruzadas, duplicados y fechas finales anteriores al inicio. |
| Error técnico mostraba datos internos | Los errores del servidor presentan un mensaje sencillo y un identificador que también queda en los registros internos, incluso con depuración habilitada. |
| Etiquetas y presentación | Paginación en español, validación de observaciones traducida, identificadores de bitácora enteros, panel con tablas contenidas en móvil y acceso directo para buscar o reimprimir recibos. |

Los formularios incorporan instrucciones breves sobre el padrón, el cierre de asambleas y los cambios de uso. La corrección de asistencia después de un cobro conserva el recibo y su asignación histórica; cualquier devolución de dinero requiere la anulación explícita del pago según las reglas de caja.

## Validación

La suite pasó con **62 pruebas y 500 aserciones**. Incluye los casos existentes de diez clientes y dos ciclos, más regresiones específicas para los errores anteriores y la compatibilidad de los nombres de administrador en español e inglés. Las pruebas utilizan SQLite en memoria; no limpian ni reemplazan los datos del sistema. La compilación de Vite terminó correctamente y el panel se verificó en pantalla de 375 píxeles sin desbordamiento horizontal.

En la instalación local se consolidó un uso idéntico duplicado y se anularon ocho multas pendientes de una asamblea reabierta. Se compararon todos los pagos, sus asignaciones y el saldo de caja antes y después: se conservaron. Queda una asamblea programada con una multa histórica ya cobrada; revisar su recibo antes de decidir una anulación o el cierre de la asamblea.

Las inconsistencias históricas requieren revisión: no se cambia automáticamente el estado de una asamblea que tiene multas cobradas ni se anulan pagos. El comando `php artisan jass:repair-operational-records` muestra una vista previa. Con `--apply --user=ID_DEL_ADMINISTRADOR`, consolida solo los usos idénticos y anula multas pendientes sin pagos de asambleas abiertas o canceladas, conservando la bitácora. Los intervalos distintos que se superponen requieren una decisión sobre sus fechas.

Las propuestas de conciliación externa, devoluciones, ayuda interactiva y pruebas en dispositivos físicos son ampliaciones futuras; no forman parte de estas correcciones.
