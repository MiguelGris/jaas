# Instalación y despliegue

## 1. Requisitos

- PHP 8.3 o superior.
- Composer 2.
- MySQL 8 o MariaDB equivalente para el entorno operativo.
- Node.js y npm compatibles con Vite 8.
- Extensiones PHP requeridas por Laravel, PDO y las dependencias de PDF/Excel.
- Extensión `pdo_mysql` habilitada cuando se usa MySQL.
- Extensiones `pdo_sqlite` y `sqlite3` para ejecutar las pruebas.

Comprueba el entorno con:

```bash
php -v
composer --version
node --version
npm --version
php -m
```

En XAMPP, las extensiones se habilitan en `C:\xampp\php\php.ini`. Después de modificarlo, reinicia Apache y cualquier terminal o proceso PHP abierto.

## 2. Obtener el proyecto

```bash
git clone https://github.com/MiguelGris/jaas.git
cd jaas
composer install
npm install
```

En Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

En Linux o macOS:

```bash
cp .env.example .env
```

Genera la clave de la aplicación:

```bash
php artisan key:generate
```

## 3. Configurar MySQL

Crea una base de datos vacía y un usuario con acceso únicamente a esa base. Ejemplo de configuración en `.env`:

```dotenv
APP_NAME=JASS
APP_ENV=production
APP_DEBUG=false
APP_URL=https://jass.ejemplo.pe
APP_TIMEZONE=America/Lima
APP_LOCALE=es

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=jass
DB_USERNAME=jass_app
DB_PASSWORD="contraseña-de-base-de-datos"

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

JASS_ADMIN_PASSWORD="contraseña-inicial-del-administrador"
JASS_DEFAULT_USER_PASSWORD="contraseña-inicial-para-los-demás-roles"
```

No uses las contraseñas de ejemplo en producción. Los valores `JASS_ADMIN_PASSWORD` y `JASS_DEFAULT_USER_PASSWORD` solo intervienen al crear las cuentas iniciales mediante los seeders.

El error `could not find driver` indica que la extensión PDO correspondiente no está cargada por el PHP que ejecuta Laravel. Para MySQL debe aparecer `pdo_mysql` en `php -m`.

## 4. Crear la estructura y datos iniciales

```bash
php artisan migrate --seed
```

El seeder crea catálogos iniciales, roles, permisos, tarifas de referencia y estas cuentas:

| Rol | Correo inicial | Contraseña de origen |
| --- | --- | --- |
| Administrador | `admin@jass.local` | `JASS_ADMIN_PASSWORD` |
| Cajero | `cajero@jass.local` | `JASS_DEFAULT_USER_PASSWORD` |
| Contabilidad | `contabilidad@jass.local` | `JASS_DEFAULT_USER_PASSWORD` |
| Auditor | `auditor@jass.local` | `JASS_DEFAULT_USER_PASSWORD` |
| Operador | `operador@jass.local` | `JASS_DEFAULT_USER_PASSWORD` |

Cada persona debe cambiar su contraseña desde el menú de usuario después del primer acceso. El administrador puede crear, desactivar y asignar roles a otras cuentas desde **Administración > Usuarios del sistema**.

## 5. Compilar la interfaz

Para producción:

```bash
npm run build
php artisan optimize
```

Para desarrollo:

```bash
composer run dev
```

También se puede ejecutar únicamente el servidor web:

```bash
php artisan serve
```

## 6. Configurar el scheduler

El scheduler es indispensable para generar las cuotas mensuales. Laravel debe recibir una llamada a `schedule:run` cada minuto.

### Linux

Agrega al `crontab` del usuario del servidor, reemplazando la ruta:

```cron
* * * * * cd /var/www/jaas && php artisan schedule:run >> /dev/null 2>&1
```

### Windows

Crea una tarea en el Programador de tareas:

- Programa: `C:\xampp\php\php.exe`
- Argumentos: `C:\ruta\al\proyecto\artisan schedule:run`
- Iniciar en: `C:\ruta\al\proyecto`
- Repetición: cada minuto, indefinidamente.

Para desarrollo puede mantenerse una consola activa con:

```bash
php artisan schedule:work
```

La tarea de cuotas se evalúa cada tres horas, en el minuto 05, y solo trabaja cuando la fecha coincide con `billing_issue_day`. La zona horaria es `America/Lima`.

## 7. Servidor web

El directorio público del sitio debe ser `public`, no la raíz del repositorio. Configura Apache o Nginx para redirigir las solicitudes a `public/index.php` y habilita HTTPS.

El usuario del servidor web necesita escritura en:

- `storage`
- `bootstrap/cache`

Después de cambiar permisos, configuración o rutas:

```bash
php artisan optimize:clear
php artisan optimize
```

## 8. Verificación

```bash
php artisan about
php artisan migrate:status
php artisan route:list
php artisan schedule:list
php artisan test
```

Comprueba manualmente:

1. La página pública `/`.
2. El inicio de sesión `/ingresar`.
3. El panel `/panel`.
4. La creación de un cliente, predio y conexión de prueba.
5. La descarga de un reporte PDF y Excel.

## 9. Actualización del sistema

Realiza primero un respaldo de la base de datos y del archivo `.env`. Luego:

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci
npm run build
php artisan optimize
```

No ejecutes `migrate:fresh` en una base con información real: elimina todas las tablas y sus datos.
