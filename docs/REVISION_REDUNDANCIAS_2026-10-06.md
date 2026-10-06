# Revisión de redundancias

Se revisaron las rutas, los controladores web y API, servicios, modelos, vistas, configuración, tareas programadas, migraciones, datos iniciales y pruebas del proyecto. Se contrastaron las referencias de las plantillas antes de retirar archivos.

## Cambios aplicados

- Eliminadas las plantillas sin referencias `resources/views/app.blade.php`, `resources/views/form.blade.php` y `resources/views/welcome.blade.php`. El diseño y formulario vigentes son `layouts.app` y `resources.form`; la portada usa `public.home`.
- Eliminados los bloques de configuración y mora del listado genérico. El controlador ya utiliza la pantalla específica de configuraciones o redirige a ella, por lo que esos bloques no se ejecutaban.
- Unificadas tres validaciones idénticas de los controladores web y API en `ResourceValidationService`: fechas de movimientos de caja, cambio de modalidad de conexión y asignación de medidores.
- Unificada la preparación del menú, antes repetida en ocho controladores, mediante un compositor de la plantilla administrativa. Se conservan los permisos de cada opción.
- Retirada la creación inicial de `payment_due_days`, una configuración obsoleta que duplicaba la gracia de la mora. Actualizado el texto inicial del ciclo de pago.

## Elementos conservados tras la revisión

- Las rutas antiguas redirigen enlaces existentes a las direcciones en español; cumplen una función de compatibilidad.
- Web y API tienen contratos de entrada y salida diferentes. Se comparten las reglas idénticas sin combinar sus formularios, respuestas ni permisos.
- Las migraciones y objetos SQL históricos se conservan: forman parte de la instalación y podrían ser utilizados fuera de la aplicación. No se ejecutaron cambios en datos ni se eliminaron registros históricos.
- Los informes de pagos, caja y deuda tienen finalidades distintas. Su apariencia similar no supone que dupliquen la misma información.

## Verificación

Suite completa: 81 pruebas aprobadas, 1 371 comprobaciones, utilizando exclusivamente SQLite en memoria. Incluye permisos, clientes, medidores, caja, cobros, anulaciones, configuración, ciclos, mora y reportes.

Se compilan las vistas y los recursos de la aplicación después de instalar la limpieza.
