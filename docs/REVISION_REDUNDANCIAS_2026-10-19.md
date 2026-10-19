# Revisión de redundancias — 19/10/2026

## Alcance

Se revisaron controladores web y API, servicios de dominio, modelos, vistas compartidas, exportaciones y pruebas, con énfasis en la lógica duplicada incorporada durante los cambios de listas, padrones y nombres. Las correcciones no requieren migraciones ni modifican datos operativos.

## Correcciones

| Área | Problema | Solución |
| --- | --- | --- |
| Paginación | Tamaños y validaciones repetidos en tres controladores web, API y cuatro selectores. | `App\Support\ListPagination` concentra los tamaños 25, 50, 100 y 200, el valor predeterminado 25 y la validación web. La API conserva su rango flexible de 1 a 200 y reutiliza los mismos límites. |
| Opciones del selector | HTML de opciones repetido en clientes, recibos, campañas y páginas auxiliares. | `partials/page-size-options.blade.php` genera las opciones a partir de la configuración compartida. Se conservan los formularios y filtros de cada pantalla. |
| Presentación de recursos | Listas de computadora, tarjetas móviles y fichas resolvían por separado relaciones, usuarios, opciones, fechas e importes. La variante móvil descartaba la hora. | `App\Support\ResourcePresenter` aplica un criterio común y conserva la hora en campos `datetime-local`. Mantiene apellidos antes que nombres, ceros numéricos y etiquetas de catálogo. Las reglas específicas de cierres de caja permanecen en su ficha. |
| Padrones | Clientes y conexiones repetían la construcción de grupos por barrio y compartían una plantilla cuyo nombre sugería solo conexiones. | `ReportService::neighborhoodGroups` construye los grupos de ambos padrones. La plantilla compartida se llama `reports/register-pdf.blade.php`. |
| Exportación PDF | La detección del formato de padrón se evaluaba dos veces. | Un único indicador controla la reserva de memoria y la numeración de páginas. |

## Verificación

Se ejecutaron las pruebas del proyecto, incluidas regresiones de tamaños admitidos y rechazados, fechas con hora, usuarios con apellidos, relaciones ausentes, importes cero, catálogos, agrupación por barrio y descarga de padrones. Se verificaron compilación de recursos, vistas Blade y sintaxis PHP. Las exportaciones reales se comprobaron con las 918 conexiones y los 813 clientes del padrón actual.

Los datos siguen en MySQL y los respaldos privados permanecen fuera de Git. No se retiraron servicios utilizados por migraciones históricas: por ejemplo, `UserCodeService` sigue siendo necesario para reparar códigos en instalaciones anteriores. Tampoco se eliminaron campos vacíos o tablas auxiliares por ese único motivo.

Resultados finales: 135 pruebas aprobadas, 2078 aserciones, 157 archivos PHP sin errores de sintaxis, compilación Vite y vistas Blade correctas. Los PDF reales conservaron las 918 conexiones y los 813 clientes únicos (852 apariciones por barrio).
