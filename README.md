# Sistema de gestión JASS

Aplicación web para administrar una Junta Administradora de Servicios de Saneamiento (JASS). Centraliza clientes, predios, conexiones, cuotas mensuales, cobranzas, caja, asambleas, multas, medidores, reportes y auditoría.

La interfaz está en español, es adaptable a computadoras y teléfonos, y está construida con Laravel, Blade y Tailwind CSS.

## Funcionalidades implementadas

- Consulta pública de deuda mediante DNI de 8 dígitos.
- Gestión de clientes, predios, conexiones y tipos de uso.
- Modalidad de pago fijo o con medidor por conexión; pago fijo es el valor predeterminado.
- Registro y validación de medidores y lecturas de consumo.
- Tarifas anuales para cuota fija y precio por metro cúbico.
- Generación mensual automática e idempotente de cuotas fijas y por consumo medido; revisión y emisión manual desde la web con diagnóstico por conexión.
- Ciclos obligatorios trimestrales o semestrales, con periodo de gracia configurable.
- Cálculo de mora mensual después del vencimiento.
- Cobranza por DNI, nombres o apellidos, seleccionando cuotas y multas.
- Recibos de pago de 58 mm, número de operación automático y anulación controlada.
- Ingresos, egresos, saldo en caja y cierres mensuales automáticos.
- Asambleas, lector de código de barras del DNI, asistencias y multas por inasistencia.
- Dashboard con saldo, recaudación, ingresos, gastos e indicadores operativos.
- Reportes en Excel y PDF, con visualización del PDF en el navegador.
- Usuarios, roles, permisos, cambio de contraseña y bitácora de auditoría.
- Códigos correlativos automáticos para los documentos operativos.

## Tecnologías

- PHP 8.3 o superior.
- Laravel 13.
- MySQL/MariaDB para producción; SQLite en memoria para pruebas.
- Blade y Tailwind CSS 4.
- Vite 8.
- PhpSpreadsheet para Excel.
- Dompdf para PDF.
- PHPUnit 12.

## Puesta en marcha rápida

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Antes de ejecutar los seeders en un entorno real, define contraseñas seguras en `.env`:

```dotenv
JASS_ADMIN_PASSWORD="una-contraseña-segura"
JASS_DEFAULT_USER_PASSWORD="otra-contraseña-segura"
```

La configuración completa de MySQL, Windows/XAMPP, producción y el programador de tareas está en la [guía de instalación](docs/INSTALACION.md).

## Documentación

- [Índice de documentación](docs/README.md)
- [Instalación y despliegue](docs/INSTALACION.md)
- [Manual de usuario](docs/MANUAL_USUARIO.md)
- [Reglas de negocio](docs/REGLAS_NEGOCIO.md)
- [Reportes e indicadores](docs/REPORTES.md)
- [Arquitectura técnica y API interna](docs/ARQUITECTURA.md)
- [Operación y mantenimiento](docs/OPERACION_MANTENIMIENTO.md)
- [Correcciones de funcionamiento y facilidad de uso, 05/10/2026](docs/CORRECCIONES_2026-10-05.md)

## Generación automática de cuotas

El día se toma de la configuración `billing_issue_day` y se limita al intervalo del 1 al 28. El valor inicial es 28. Durante ese día, Laravel intenta ejecutar la generación a las `00:05`, `03:05`, `06:05` y así sucesivamente, en la zona horaria `America/Lima`.

El servidor debe ejecutar el scheduler de Laravel cada minuto. Una generación repetida no duplica cuotas.

```bash
php artisan schedule:work
```

Para una recuperación manual:

```bash
php artisan billing:generate-monthly --month=2026-09
```

## Pruebas

Las pruebas están protegidas para usar exclusivamente SQLite en memoria.

```bash
php artisan test
```

En XAMPP, cuando SQLite no está habilitado globalmente:

```powershell
C:\xampp\php\php.exe -d extension_dir=C:\xampp\php\ext -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll vendor\bin\phpunit --do-not-cache-result
```

## Estado conocido

La gestión de medidores y lecturas y su facturación mensual están implementadas. Para cobrar consumo medido se requieren lecturas del mes y precio por m³ en la tarifa vigente. La revisión web explica cuando falta alguno de estos datos.

## Seguridad operativa

- Cambia las contraseñas iniciales inmediatamente después de instalar.
- Usa `APP_DEBUG=false` y HTTPS en producción.
- No publiques el archivo `.env` ni respaldos de la base de datos.
- Programa respaldos antes de migraciones y actualizaciones.
- No elimines pagos: utiliza la opción **Anular**, que conserva la trazabilidad y reabre las deudas relacionadas.
