# Mejoras aplicadas en Desktop/Jass · 6 de octubre de 2026

## Resultado

Los cambios están instalados en `C:/Users/IGP/Desktop/Jass`. Se conserva la base local de QA: diez clientes, sesenta cuotas, veinte recibos válidos y uno anulado, ocho multas, ingreso, egreso y cierre de septiembre. Se tomó un respaldo antes de la migración en `tmp/respaldo-antes-mejoras-2026-10-06.json`; no se incluye en Git.

## Cambios visibles

- Alta guiada: cada paso se guarda y abre su ficha con la continuación hacia casa/local, conexión y revisión de uso. Las fechas se rellenan con el día actual y pueden corregirse.
- Ficha del titular: deuda, servicios y últimos recibos, con acceso a cobrar cuando el rol lo permite.
- Conexiones medidas: requisitos y enlaces para medidor, lectura, tarifa y revisión de cuotas.
- Corrección de fechas: guía de tres pasos con vigencias existentes y cantidad de cuotas históricas en la edición. La corrección no reescribe las deudas ni cambia automáticamente las vigencias; el usuario revisa ambas antes de generar cuotas.
- Panel con tareas según permisos, recordatorios de facturación y lecturas faltantes, y centro de reportes separado. Favoritos por usuario y navegador.
- Cobranza: selección acumulativa de ciclos y referencia externa opcional en confirmación, detalle y recibo térmico. Se mantiene el **cobro completo**, según la decisión del usuario; no se implementan abonos parciales.
- Ayudas que distinguen cuota, recibo y otros ingresos y previenen registrar dos veces una cobranza.
- Bitácora con módulos en español, resumen del evento y filtros de usuario, fechas y módulo/registro.
- Listados con tarjetas para pantallas estrechas, controles y foco más visibles, menú accesible y opción de mostrar contraseña.
- Aviso del navegador al salir de formularios con cambios sin guardar; incluye selecciones hechas con botones de ciclo.
- Cierres con nombre del mes e importes en soles. Resúmenes monetarios PDF uniformes; los ceros de Excel se conservan como valores numéricos.
- Emisión de ciclo completo con vista previa mensual, idempotencia y auditoría dentro de una transacción. Hasta 24 meses por operación; ciclos mayores pueden emitirse mes a mes. Cambiar los filtros oculta la confirmación hasta revisar otra vez.
- Indicador de capacitación configurable, desactivado por defecto, disponible en entorno local/testing. Requiere que quien prepare la instalación configure una base separada; el indicador no crea ni separa datos automáticamente.

## Reparaciones técnicas

- La instalación MySQL tolera las vistas y el procedimiento retenidos por `migrate:fresh`.
- Se retiran las vistas antiguas de deuda y el procedimiento antiguo de multas, que no aplicaban todas las reglas actuales; la aplicación utiliza sus servicios Laravel.
- Los usuarios sembrados reciben códigos; la migración repara códigos ausentes conservando cuentas, contraseñas y códigos existentes.
- Se documenta la extensión PHP `intl` para el diagnóstico de base. No se modificó el PHP de otras aplicaciones.
- `tmp/` queda excluido de Git para proteger respaldos y artefactos de QA.

## Validación

- 84 pruebas y 1.391 aserciones aprobadas en SQLite en memoria, incluyendo generación de ciclo completo sin duplicados, vista previa sin escrituras, ficha del titular, filtros de auditoría, reparación repetible de códigos y Excel con ceros.
- Dos instalaciones consecutivas `migrate:fresh --seed` en una base MySQL temporal exclusiva de la prueba, ambas correctas y con cinco códigos de usuario. La base temporal se eliminó al terminar; la base `jass` no se reinstaló.
- Compilación Vite, caché de vistas Blade y revisión de formato PHP correctas.
- Comprobación visual en el servidor de Desktop/Jass: panel, favorito persistente, ficha del titular, requisitos del medidor, guía de fechas y vista previa de ciclo.

No se certifican teléfono real, impresora física, lector físico, dos cajeros simultáneos ni separación automática del entorno de capacitación. No se envían recordatorios por correo o WhatsApp; los recordatorios quedan dentro del sistema. Los cambios visuales que ya estaban pendientes en esta copia se integran y conservan.
