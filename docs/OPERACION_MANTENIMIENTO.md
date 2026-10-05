# Operación y mantenimiento

## 1. Rutina diaria

- Verifica los indicadores del dashboard.
- Revisa que el saldo en caja coincida con los movimientos del día.
- Registra otros ingresos y egresos con su sustento.
- Usa el flujo de cobranza para pagos; no crees recibos manualmente.
- Imprime o visualiza el recibo después de cada cobro.
- Corrige un cobro equivocado con **Anular** y un motivo claro.
- Revisa las próximas asambleas y el estado de las asistencias.

## 2. Rutina mensual

### Antes del día de emisión

1. Confirma que las conexiones, clientes y predios activos estén correctamente configurados.
2. Verifica asignaciones de uso vigentes.
3. Verifica la tarifa del año para cada uso.
4. Comprueba el ciclo trimestral o semestral.
5. Comprueba la configuración de mora.
6. Ejecuta `php artisan schedule:list` y valida la tarea mensual.

### Día de emisión

Con `billing_issue_day=28`, la tarea intenta ejecutarse aproximadamente a:

```text
00:05, 03:05, 06:05, 09:05, 12:05, 15:05, 18:05 y 21:05
```

La zona horaria es `America/Lima`. Laravel solo puede hacer esos intentos si el sistema operativo está llamando a `php artisan schedule:run` cada minuto o si `schedule:work` permanece activo.

La operación es idempotente: los reintentos no duplican cuotas.

### Comprobar la generación

```bash
php artisan billing:generate-monthly --month=2026-09
```

El comando informa cuántas cuotas nuevas creó. Un resultado de cero puede significar que ya estaban generadas o que faltan conexiones elegibles, usos, tarifas o ciclo configurado.

Para diagnosticarlo sin crear cuotas, abre **Cuotas → Revisar y generar cuotas**, selecciona el mes y revisa cada causa. También permite recuperar el mes con motivo y confirmación. Usa un titular específico para acotar la recuperación; dejarlo vacío revisa todas las conexiones. `billing_last_manual_run` muestra la última emisión manual que creó cuotas.

Las claves vigentes son `billing_period_months` (3 o 6) y `billing_issue_day` (1–28). Las claves históricas con otros nombres no sustituyen estas configuraciones. Gracia e importe de mora tienen su propio catálogo y vigencias. La interfaz valida los valores y no permite renombrar la clave de un registro existente.

### Cierre de caja

Cuando el mes haya concluido:

1. Descarga el flujo de caja del mes.
2. Revisa pagos anulados, ingresos y egresos.
3. Abre **Caja > Cierres de caja**.
4. Selecciona año y mes.
5. Verifica el cálculo automático.
6. Confirma el cierre.

Después del cierre no podrán alterarse movimientos de ese periodo.

## 3. Rutina anual

- Crea las tarifas del nuevo año antes de emitir enero.
- Revisa la edad de exoneración de faenas.
- Descarga el balance anual.
- Revisa usuarios activos y permisos.
- Archiva reportes y respaldos conforme a las reglas de la JASS.
- Confirma que los correlativos del nuevo año empiecen correctamente; las series anuales se separan automáticamente.

## 4. Respaldos

Realiza un respaldo antes de:

- Actualizar el código.
- Ejecutar migraciones.
- Cambiar catálogos con información vinculada.
- Realizar una corrección masiva.

Ejemplo MySQL:

```bash
mysqldump --single-transaction --routines --triggers -u USUARIO -p BASE_DATOS > jass-AAAA-MM-DD.sql
```

Guarda también, en un lugar seguro:

- `.env`.
- Archivos cargados dentro de `storage/app`, si se agregan en el futuro.
- Copia del código o referencia exacta del commit desplegado.

No almacenes respaldos dentro de `public` ni los confirmes en Git.

La restauración debe probarse primero en una base separada. No reemplaces la base operativa sin validar el archivo, la fecha y el destino exacto.

## 5. Despliegue de una actualización

1. Anuncia una ventana de mantenimiento.
2. Crea el respaldo.
3. Registra el commit actual: `git rev-parse --short HEAD`.
4. Descarga los cambios.
5. Instala dependencias.
6. Ejecuta migraciones.
7. Compila recursos.
8. Refresca cachés.
9. Ejecuta pruebas básicas.

Comandos habituales:

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci
npm run build
php artisan optimize
php artisan about
php artisan migrate:status
```

## 6. Diagnóstico del scheduler

### No se generaron cuotas

Comprueba:

```bash
php artisan schedule:list
php artisan billing:generate-monthly --month=AAAA-MM
```

Revisa además:

- Fecha y hora del servidor.
- Zona `America/Lima`.
- Valor de `billing_issue_day`.
- Ejecución de la tarea del sistema operativo.
- Estado activo de cliente, predio y conexión.
- Modalidad de pago fijo.
- Tipo de uso vigente.
- Tarifa del año.
- Ciclo de 3 o 6 meses.

El log principal se encuentra en `storage/logs/laravel.log`.

### El comando crea cero cuotas

Si las cuotas ya existen, es el resultado esperado. Para investigar elegibilidad, revisa la conexión, su tipo de uso y su tarifa. No elimines cuotas reales solo para volver a ejecutar el comando.

## 7. Errores frecuentes

### `could not find driver`

El PHP ejecutado no tiene el controlador PDO de la base. Comprueba qué binario y archivo de configuración usa:

```bash
php --ini
php -m
```

Para MySQL habilita `pdo_mysql`. Para las pruebas habilita `pdo_sqlite` y `sqlite3`.

### `The held at field must match the format H:i`

La interfaz normaliza horas `HH:mm` y también acepta un valor almacenado con segundos. Si reaparece después de una actualización, limpia vistas y configuración:

```bash
php artisan optimize:clear
php artisan optimize
```

### Error de escritura en `storage` o `bootstrap/cache`

Concede acceso de escritura al usuario que ejecuta PHP, limitado a esas carpetas. No otorgues permisos generales a todo el proyecto.

### Cambios de interfaz que no aparecen

```bash
npm run build
php artisan optimize:clear
php artisan optimize
```

Después fuerza la recarga del navegador.

### Un catálogo no se puede eliminar

El valor tiene información relacionada. Busca y reasigna esos registros antes de eliminarlo. Esta restricción protege la integridad histórica.

### Un ingreso, egreso o pago no puede corregirse

Comprueba si su mes ya fue cerrado. Los periodos cerrados son inmutables. Una corrección contable posterior debe seguir el procedimiento aprobado por la JASS, sin alterar silenciosamente el historial.

### El saldo no coincide con la recaudación

Es posible que sea correcto:

- Recaudación cuenta solo pagos válidos.
- Saldo también suma otros ingresos y resta egresos.
- Si existe un cierre, el saldo parte de ese valor y usa movimientos posteriores.

Usa el flujo de caja y la bitácora para reconciliar.

## 8. Salud y registros

- Endpoint de salud: `GET /up`.
- Log de Laravel: `storage/logs/laravel.log`.
- Estado de migraciones: `php artisan migrate:status`.
- Información del entorno: `php artisan about`.
- Lista de rutas: `php artisan route:list`.
- Tareas programadas: `php artisan schedule:list`.

En producción configura rotación y retención de logs para evitar llenar el disco.

## 9. Pruebas seguras

La suite usa SQLite en memoria. Ejecuta:

```bash
php artisan test
```

Con XAMPP y extensiones SQLite cargadas solo para el comando:

```powershell
C:\xampp\php\php.exe -d extension_dir=C:\xampp\php\ext -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll vendor\bin\phpunit --do-not-cache-result
```

Nunca cambies la protección de `tests/TestCase.php` para apuntar las pruebas a la base operativa.

## 10. Lista de verificación después de una incidencia

1. Conserva los logs y anota la hora exacta.
2. Identifica el usuario y la operación en la bitácora.
3. Verifica el último cierre de caja.
4. Comprueba si el pago está válido o anulado.
5. Compara la cuota, sus distribuciones y su saldo.
6. Crea un respaldo antes de cualquier corrección.
7. Corrige mediante la interfaz cuando exista un flujo soportado.
8. Documenta la causa, la corrección y el commit desplegado.
