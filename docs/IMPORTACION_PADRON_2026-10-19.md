# Importación del padrón corregido — 19/10/2026

Se reemplazaron los datos operativos de la base `jass` por las 918 filas del padrón actualizado. Se conservaron usuarios y contraseñas, roles, permisos, catálogos, tarifas y configuración. No se generaron cuotas, pagos ni deudas.

## Resultado

- 813 clientes, incluidas 14 instituciones sin apellidos.
- 918 predios, 918 conexiones y 918 asignaciones de uso.
- Los nombres completos coincidentes comparten cliente, ignorando tildes, puntuación y espacios. Cada fila conserva un predio y una conexión propios.
- 40 filas sin documento reutilizan el DNI de otra fila con el mismo nombre completo.
- Se asignaron 236 DNI y 14 RUC provisionales a los clientes restantes sin documento, con 8 y 11 dígitos respectivamente, desde `00000001` y `00000000001`, evitando colisiones. Se identifican en las observaciones para sustituirlos por documentos reales.

## Identificadores y fechas

Los identificadores y códigos de clientes, predios, conexiones y asignaciones se cargaron desde 1. Después de la transacción se ajustaron los autoincrementos al máximo identificador más uno; en las tablas vacías el siguiente es 1. Los identificadores existentes de usuarios y catálogos no se renumeraron. Los correlativos operativos se reiniciaron para seguir después del último registro importado.

El alta y la vigencia de uso comienzan el 19/10/2026, como fecha de carga. La instalación queda sin fecha porque el Excel no aporta ese dato. Se conserva la dirección del Excel y su criterio referencial. Las conexiones se cargan activas con pago fijo.

## Respaldo y controles

Los archivos privados de esta operación están en `tmp/reimportacion_padron_2026-10-19/`, fuera de Git y de la carpeta pública:

- `jass_antes_de_importar.sql`: respaldo completo anterior a la limpieza, cuya restauración se comprobó antes de aplicar cambios.
- `jass_con_padron.sql`: respaldo final con el padrón corregido, destinado a importarse en una base vacía.
- `padron_fuente.xlsx`: copia intacta del archivo recibido.
- `REGISTROS_IMPORTADOS.csv`: relación entre filas del Excel e identificadores de la base.
- `DOCUMENTOS_PROVISIONALES.csv`: clientes pendientes de regularizar. Abrir los documentos como texto para conservar ceros iniciales.
- `RESULTADO_IMPORTACION.json`: conteos, hashes y resultado de las verificaciones.

La limpieza y carga se ensayaron en una base aislada y se aplicaron en una transacción con claves foráneas activas. Se comprobaron las 918 filas contra cliente, dirección, barrio, servicio y uso. Se verificó la restauración del respaldo final y se retiró la base de ensayo. La operación queda registrada en la bitácora.

## Configuración pendiente

Las 2 conexiones industriales conservan el tipo de uso INDUSTRIAL, sin inventar una tarifa. Debe configurarse antes de emitir sus cuotas. Las tarifas existentes se conservaron y llevan observaciones de simulación QA; revisar importes y aprobación antes de operar.

## Tamaño de listas

Las listas admiten 25, 50, 100 y 200 registros, con 25 por defecto. Se conservaron búsquedas y filtros al cambiar de página, incluidas las listas y recibos de campañas. La API usa 25 por defecto y admite hasta 200 registros por página.
