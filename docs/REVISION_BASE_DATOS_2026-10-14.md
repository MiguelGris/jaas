# Revisión de campos vacíos de la base de datos

Revisión realizada el 14 de octubre de 2026 sobre la base MySQL `jass` de Desktop/Jass. Decisión documentada el 15 de octubre: conservar el esquema actual. No se eliminaron columnas, tablas ni datos.

## Alcance y resultados

Se consultó el esquema y se contaron valores no nulos por columna; en campos de texto también se descartaron cadenas vacías y espacios. Se contrastaron los resultados con modelos, controladores, servicios, vistas y migraciones. No se consideraron vacíos los números cero ni los valores booleanos falsos. Una tabla sin registros se clasificó por separado.

Resultado: 47 tablas, 19 columnas completamente vacías en tablas con registros y 6 tablas sin registros. Son cantidades de esa instalación y fecha, no una condición garantizada de otras bases.

| Tabla | Campos completamente vacíos | Uso o decisión |
| --- | --- | --- |
| `assembly_attendances` | `notes` | Observaciones de asistencia y reportes. |
| `connection_usage_types` | `ends_on` | Fin de vigencia; nulo permite vigencia abierta. |
| `customers` | `birth_date`, `phone`, `email` | Datos opcionales; nacimiento interviene en el reporte de exonerados por edad. |
| `expenses` | `receipt` | Comprobante opcional del egreso. |
| `incomes` | `reference` | Referencia opcional del ingreso. |
| `late_fee_settings` | `ends_on` | Fin de vigencia de la regla de mora. |
| `late_fee_waivers` | `campaign_id` | Vincula campañas masivas; nulo en condonaciones individuales. |
| `meter_readings` | `notes` | Observaciones de lecturas. |
| `payment_allocations` | `concept`, `service_component`, `fine_component` | Cobro separado de conceptos y compatibilidad histórica. |
| `payments` | `invoice_id`, `external_reference` | Relación heredada aún referenciada y referencia opcional de transferencias, respectivamente. |
| `rates` | `ends_on` | Fin de vigencia de tarifas. |
| `service_waivers` | `campaign_id` | Vincula campañas masivas; nulo en condonaciones individuales. |
| `users` | `email_verified_at`, `remember_token` | Verificación de correo preparada sin flujo activo; token utilizado por Recordar mi sesión. |

## Tablas sin registros

| Tabla | Función |
| --- | --- |
| `billing_cycle_schedules` | Historial y programación de cambios de ciclo; el servicio contempla configuración sin historial. |
| `cache_locks` | Bloqueos de caché de Laravel. |
| `jobs` | Cola de trabajos; la instalación configura cola en base de datos. |
| `job_batches` | Infraestructura de lotes de trabajos de Laravel. |
| `failed_jobs` | Registro de fallos de trabajos en cola. |
| `password_reset_tokens` | Estructura preparada para recuperación de contraseña; sin flujo activo identificado. |

## Decisión y criterios de depuración

Se conservan los campos funcionales y las tablas auxiliares. Los candidatos para una futura simplificación son `payments.invoice_id`, `users.email_verified_at` y `password_reset_tokens`, pero ninguno se elimina en esta revisión.

`payments.invoice_id` está vacío en los 27 pagos revisados; los pagos actuales usan `payment_allocations`. Aun así, conserva relaciones en `Payment` e `Invoice` y validación API. `email_verified_at` conserva su conversión en `User`. Retirarlos exige adaptar el código y decidir expresamente el alcance de autenticación y compatibilidad.

Antes de cualquier eliminación: respaldar la base, revisar también las demás instalaciones, adaptar referencias, preparar una migración y verificar los flujos afectados. El escaso espacio de estos campos no justifica por sí solo modificar el esquema.
