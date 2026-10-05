# Informe de funcionamiento y facilidad de uso de JASS

Fecha: 5 de octubre de 2026. Entorno: http://localhost:8000. Perfil simulado: persona con poca experiencia en computadoras, que necesita registrar usuarios del agua, consultar deudas, cobrar y corregir equivocaciones.

La cobranza básica funcionó para diez clientes y dos ciclos trimestrales completos. Se encontraron tres errores reproducibles y varias dificultades que pueden provocar equivocaciones o dependencia de un técnico. Los tres errores se detallan con pasos y evidencia; las propuestas de facilidad de uso se distinguen de los fallos funcionales.

## 1. Alcance y metodología

Se registraron desde la interfaz diez clientes ficticios, sus diez predios activos, sus diez conexiones y las asignaciones de uso. Se utilizó el tipo de uso exclusivo **QA OCT2026** y una tarifa ficticia de S/ 15 por mes, vigente desde el 1 de enero de 2026. Los nombres son **Prueba 01 QA Octubre** a **Prueba 10 QA Octubre**, con documentos ficticios **99000001** a **99000010**.

Se probaron dos ciclos completos, enero–marzo y abril–junio de 2026. Cada trimestre contiene tres cuotas mensuales. Se generaron 60 cuotas y se realizaron los cobros en la fecha real de las pruebas, 5 de octubre; por eso corresponden las moras acumuladas. No se adelantó el reloj del servidor ni se esperaron seis meses reales.

La emisión de cuotas necesitó preparación técnica porque no existe una acción web para recuperar periodos. El script de preparación ejecutó el servicio original, restringido a las diez conexiones ficticias. Inicialmente no produjo cuotas porque la tarifa residencial existente empezaba el 23 de septiembre. Se creó la tarifa QA para probar los meses anteriores sin cambiar las tarifas de los clientes existentes.

Los registros y los pagos se efectuaron por formularios visibles. Los recorridos repetidos se automatizaron después de comprobar sus campos y resultados. Se simularon errores habituales: documento incorrecto, documento repetido, intento de cobrar sin seleccionar cuotas, observación demasiado larga y anulación de un recibo equivocado.

Dos diagnósticos adicionales de facturación ejecutaron el código instalado dentro de transacciones revertidas. Se comprobó que no dejaron cuotas, pagos ni cambios de caja. También se ejecutaron las 45 pruebas automatizadas existentes, protegidas para utilizar SQLite en memoria.

La aplicación completa instalada está en `C:/Users/IGP/Desktop/Jass`. La carpeta `jass-schema-staging` del espacio de trabajo es una copia anterior y parcial; los hallazgos finales se basan en la aplicación instalada y en las pantallas del servidor local.

Esta evaluación simula el comportamiento de un principiante; no sustituye una sesión observada con personas reales de ese perfil.

## 2. Resultados de los dos ciclos

| Cliente ficticio | Código | Documento ficticio | Recibo ciclo 1 | Importe ciclo 1 | Recibo ciclo 2 válido al terminar | Importe ciclo 2 | Deuda final |
|---|---|---|---|---:|---|---:|---:|
| Prueba 01 | CLI_0004 | 99000001 | RC26-000009 | S/ 81 | RC26-000010 | S/ 63 | S/ 0 |
| Prueba 02 | CLI_0005 | 99000002 | RC26-000011 | S/ 81 | RC26-000012 | S/ 63 | S/ 0 |
| Prueba 03 | CLI_0006 | 99000003 | RC26-000013 | S/ 81 | RC26-000014 | S/ 63 | S/ 0 |
| Prueba 04 | CLI_0007 | 99000004 | RC26-000015 | S/ 81 | RC26-000016 | S/ 63 | S/ 0 |
| Prueba 05 | CLI_0008 | 99000005 | RC26-000017 | S/ 81 | RC26-000018 | S/ 63 | S/ 0 |
| Prueba 06 | CLI_0009 | 99000006 | RC26-000019 | S/ 81 | RC26-000020 | S/ 63 | S/ 0 |
| Prueba 07 | CLI_0010 | 99000007 | RC26-000021 | S/ 81 | RC26-000022 | S/ 63 | S/ 0 |
| Prueba 08 | CLI_0011 | 99000008 | RC26-000023 | S/ 81 | RC26-000024 | S/ 63 | S/ 0 |
| Prueba 09 | CLI_0012 | 99000009 | RC26-000025 | S/ 81 | RC26-000026 | S/ 63 | S/ 0 |
| Prueba 10 | CLI_0013 | 99000010 | RC26-000027 | S/ 81 | RC26-000029 | S/ 63 | S/ 0 |

El recibo RC26-000028 del cliente 10 se anuló intencionalmente y se sustituyó por RC26-000029 para probar la recuperación. Quedan **20 recibos válidos y 1 anulado** de las pruebas.

La mora observada coincide con la regla mensual vigente de S/ 2 por cuota: enero–marzo vence el 30 de abril y acumula seis meses de mora hasta octubre, mientras abril–junio vence el 31 de julio y acumula tres meses. Por cliente: primer ciclo, 3 × (15 + 12) = S/ 81; segundo ciclo, 3 × (15 + 6) = S/ 63. Total por cliente: S/ 144.

| Comprobación | Resultado |
|---|---|
| Clientes, predios y conexiones ficticios | 10 de cada uno |
| Cuotas mensuales de prueba | 60; todas pagadas al terminar |
| Cuotas duplicadas al repetir emisión | 0 |
| Clientes con deuda cero al terminar | 10 de 10 |
| Recaudación válida de prueba | S/ 1,440 |
| Servicio incluido en los dos ciclos | S/ 900 |
| Mora incluida en los dos ciclos | S/ 540 |
| Caja antes de las pruebas | S/ 35 |
| Caja después de las pruebas | S/ 1,475 |
| Reporte de flujo de octubre: ingresos / egresos / saldo del periodo | S/ 1,440 / S/ 0 / S/ 1,440 |
| Pruebas automatizadas existentes | 45 aprobadas; 360 verificaciones |

El saldo del periodo y el saldo acumulado de caja son conceptos diferentes: S/ 1,440 corresponde a octubre; S/ 1,475 incluye los S/ 35 anteriores.

## 3. Errores reproducibles

### E01. Cobranza: un error de observaciones queda invisible y borra la selección

**Prioridad alta. Comprobado desde la pantalla.**

Pasos: consultar el cliente 02; seleccionar enero, febrero y marzo; elegir EFECTIVO; escribir más de 250 caracteres en Observaciones; pulsar Registrar pago y emitir recibo.

Resultado: el pago se rechaza, pero no aparece una explicación. Se desmarcan las cuotas, se borra la observación, el medio de pago vuelve a Selecciona una opción y el total vuelve a S/ 0.00. El usuario puede pensar que el sistema se trabó o que realizó el pago, y debe repetir el trabajo.

Esperado: conservar las cuotas, medio de pago y texto; mostrar junto al campo «La observación debe tener hasta 250 caracteres» y llevar el foco al error. La vista solo muestra el error `charges`, aunque el servidor también valida otros campos.

Mejora: añadir límite y contador de caracteres, mensajes de todos los campos y conservación de entradas. Aplicar el mismo tratamiento a errores por cuotas que otro operador ya haya pagado.

Evidencia: [pantalla después del rechazo](C:/Users/IGP/Documents/jass-app/qa/evidencias/01-error-silencioso-cobranza.png). La ausencia de un nuevo recibo y el posterior cobro correcto también se comprobaron.

### E02. El estado EXONERADO excluye al cliente de la cuota de agua

**Prioridad alta. Comprobado ejecutando el servicio instalado, con cambios revertidos.**

Preparación: cliente QA con conexión y predio activos, uso QA y tarifa vigente. Cambiar temporalmente su estado a EXONERADO y generar julio de 2026.

Resultado: **0 cuotas generadas**. Con el resto de condiciones cumplidas, la consulta de elegibilidad solo admite ACTIVO/ACTIVE. El estado EXONERADO se interpreta además como exención de multas en la lógica de clientes y asambleas.

Impacto: si administración usa EXONERADO para liberar de multas, puede detener involuntariamente la facturación del agua. Debe confirmarse y explicitarse que la exoneración se refiere a multas, conforme al catálogo y a la lógica de asambleas; si se pretendiera exoneración total de agua, haría falta una regla distinta y visible.

Mejora: separar «servicio activo» de «exoneración de multas/faenas». Mientras se mantenga el estado actual, incluir al exonerado en facturación cuando la exención no sea del agua. Mostrar qué conceptos quedan exonerados y desde cuándo.

Evidencia: [diagnósticos del servicio](C:/Users/IGP/Documents/jass-app/qa/billing-diagnostics.json).

### E03. Corregir la instalación puede permitir cargos anteriores al servicio

**Prioridad alta. Comprobado ejecutando el servicio instalado, con cambios revertidos.**

Pasos del diagnóstico: una conexión QA tenía instalación y asignación de uso desde enero. Corregir temporalmente su instalación a septiembre, conservando la asignación existente, y generar julio.

Resultado: **se creó una cuota de julio**, anterior a la instalación corregida. El generador verifica vigencia de uso y tarifa, pero no usa la fecha de instalación para excluir ese mes.

Impacto: una corrección habitual de fechas puede dejar deuda por un periodo en el que el servicio aún no existía. La prueba cubre específicamente el desajuste entre instalación corregida y asignación antigua; no demuestra que toda conexión recién instalada genere deuda anterior.

Mejora: validar que el mes facturado no sea anterior a la instalación; detectar inconsistencias con la asignación de uso al editar; explicar qué fechas deben corregirse y no alterar cuotas históricas pagadas sin un procedimiento explícito.

Evidencia: [diagnósticos y comprobación de reversión](C:/Users/IGP/Documents/jass-app/qa/billing-diagnostics.json). Los conteos y la caja quedaron iguales antes y después del diagnóstico.

## 4. Dificultades que pueden provocar errores de operación

| ID | Prioridad | Observación comprobada | Consecuencia para un principiante | Cambio recomendado |
|---|---|---|---|---|
| U01 | Alta | Nuevo predio muestra Activo desmarcado por defecto. | Puede registrar correctamente todos los campos y dejar el inmueble sin facturación. | Activar por defecto en un alta ordinaria, o exigir una decisión explícita y explicar que Inactivo impide emitir cuotas. |
| U02 | Alta | Cuotas mensuales ofrece filtros y consultas, pero no emisión o recuperación desde la web. | Si falla el programador o se necesita cobrar un periodo adelantado, el operador depende de comandos. | Acción «Generar cuotas» con periodo, vista previa, cantidades y motivo; acceso por permisos y protección contra duplicados. |
| U03 | Alta | Primera preparación devolvió 0 cuotas por falta de tarifa residencial vigente en enero–junio. | Es difícil distinguir ausencia de deuda de una facturación incompleta. | Resumen «generadas / omitidas» y causas: tarifa, uso, medidor, predio inactivo o cliente excluido. |
| U04 | Media | Configuraciones muestra varias entradas de significado parecido: Periodo de Pago de agua, Periodo de facturación y Meses del ciclo de pago; también dos nombres de día de emisión. | Puede editar una clave antigua creyendo cambiar la regla vigente. | Pantalla única con ciclo 3/6 meses, día de emisión y gracia; marcar ajustes históricos como no operativos. |
| U05 | Media | El listado de 13 clientes no muestra buscador propio; cobranza sí tiene uno. | Obliga a recorrer filas para encontrar o corregir al titular. | Búsqueda por DNI/RUC, nombre y código, con los mismos controles en todos los módulos. |
| U06 | Media | Las seis cuotas se seleccionan individualmente y el formulario no agrupa visualmente por trimestre. | Puede olvidar un mes o interpretar que el total es el ciclo completo. | «Pagar enero–marzo», «Pagar abril–junio», «Seleccionar todo» y resumen de meses incluidos. |
| U07 | Media | Recibo térmico muestra S/ 21 por cuota, sin separar los S/ 15 de servicio y S/ 6 de mora; el detalle administrativo sí los separa. | El cliente puede cuestionar el monto y el cajero necesita otra pantalla para explicarlo. | Desglose en recibo: servicio, mora, multas y total; explicación breve del vencimiento. |
| U08 | Media | Registrar pago envía directamente la selección; no se observó un resumen de confirmación previo. | Puede cobrar al cliente o trimestre equivocado. | Confirmación breve con titular, documento, meses y total; botones «Volver» y «Confirmar pago». |
| U09 | Baja | Aparecen textos como «Nuevo conexión», «Tarifa creado» y año «2,026». | Reduce claridad y da impresión de inconsistencia. | Revisar concordancia de mensajes y mostrar años sin separador de miles. |
| U10 | Media | En emulación móvil de 390 × 844, la tabla de clientes mide aproximadamente 854 px dentro de 341 px visibles. Al entrar predominan código y tipo; nombre y acciones quedan hacia la derecha. | El usuario puede no descubrir cómo ver el nombre o editar; debe desplazar horizontalmente. | Tarjetas de cliente con nombre, documento, estado y acciones visibles; si se conserva la tabla, indicar «Desliza para ver más». |

Estas observaciones son problemas de claridad o riesgos de uso; no todas representan un cálculo incorrecto. No se cambiaron preferencias generales ni se implementaron las soluciones en esta evaluación.

## 5. Lo que funcionó

| Recorrido | Evidencia observada |
|---|---|
| Alta de diez clientes y sus servicios | Códigos correlativos, confirmaciones y registros visibles. |
| Documento incorrecto | ABC no pudo enviarse como DNI; el navegador señaló formato inválido. El mensaje genérico puede mejorarse. |
| Documento repetido | Rechazó 99000001 con «El valor de DNI ya está registrado» y conservó los demás datos. |
| Buscar titular y predio | Resultados incluyen código, documento, nombre o dirección; ayudaron a confirmar la selección. |
| Cobro sin seleccionar cuotas | Mostró «Selecciona al menos una cuota o multa» y no creó recibo. |
| Suma de cuotas y mora | S/ 81 y S/ 63 por ciclo; todos los clientes cerraron con deuda cero. |
| Medios de pago | Se probaron EFECTIVO, Yape, Plin y TRANSFERENCIA. Son registros simulados: no se realizaron transferencias reales. |
| Repetir emisión | Segunda ejecución creó 0 cuotas para cada mes. |
| Repetir cobro de cuota pagada | El servicio lo rechazó; se comprobó técnicamente, sin forzar el formulario del usuario. |
| Anulación | Conservó RC26-000028, estado Anulado, operador y motivo; reabrió tres cuotas. |
| Caja después de anular | Bajó de S/ 1,475 a S/ 1,412; al reponer el cobro volvió a S/ 1,475. |
| Consulta pública | Cliente 10 mostró S/ 63 tras anular y S/ 0 después del pago de reposición. |
| Reportes calculados | Flujo de caja y conceptos de octubre coinciden en S/ 1,440 de ingresos, sin incluir el recibo anulado. |
| Recibo imprimible | Abrió el recibo correcto con cliente, conceptos, medio, operación y total; ofrece instrucciones para papel de 58 mm. |
| Consulta y navegación móvil | En 390 × 844, consulta pública de 99000010 con saldo cero, menú móvil que abre y navegación a Clientes. |
| Regresión automatizada | 45 pruebas existentes aprobadas y 360 verificaciones. Esto no cubre todos los hallazgos nuevos de esta evaluación. |

## 6. Implementaciones para facilitar el uso

1. **Alta guiada de servicio:** Cliente → Predio → Conexión → Tarifa → Verificar. Mostrar «Listo para facturar» o indicar exactamente qué falta. Permitir continuar desde la ficha del cliente sin buscarlo nuevamente.
2. **Inicio según función:** para el cajero, botones grandes «Cobrar», «Buscar cliente», «Reimprimir» y «Corregir pago». Mantener catálogos y ajustes avanzados en el área del administrador.
3. **Cobranza en tres pasos:** buscar titular, elegir ciclo, revisar y confirmar. Explicar con palabras sencillas cuánto corresponde a agua y cuánto a mora.
4. **Errores que ayuden a corregir:** conservar entradas, marcar el campo, explicar el problema y ofrecer una acción concreta. Ejemplo: «La observación es demasiado larga: elimina 35 caracteres».
5. **Estado de facturación visible:** aviso de última emisión exitosa y conexiones omitidas. Evitar que «No tiene deuda» se interprete como «Está al día» cuando faltan cuotas por emitir.
6. **Recuperación fácil:** acceso «Registré un pago por error» que muestre anulación con motivo y resultado; explicar las restricciones por cierre de caja sin eliminar el historial.
7. **Recibos accesibles:** reimprimir desde la ficha del titular, vista legible para pantalla y formato térmico independiente; destacar agua, mora y total.
8. **Ayuda breve en el lugar necesario:** explicar Predio como «Casa o local que recibe el servicio», Asignación de uso como «Uso de la conexión y tarifa aplicable», y exoneración como «No paga estas multas».
9. **Captura de pagos parciales, si la JASS los admite:** definir saldo restante y comprobante antes de implementarlo. Actualmente el cobro normal toma el saldo completo de cada concepto elegido; no se comprobó como error porque la regla documentada lo exige.
10. **Uso en teléfono:** tarjetas por cliente, buscador fijo, botones amplios y total accesible durante la selección. La consulta pública y el menú funcionaron en emulación; las tarjetas reducirían el desplazamiento horizontal observado en Clientes. Falta probar cobros completos en un dispositivo físico.

## 7. Orden de atención y aceptación

| Orden | Trabajo | Cómo comprobar que quedó resuelto |
|---|---|---|
| 1 | E01, errores invisibles de cobranza | Repetir nota de más de 250 caracteres: mensaje visible, selección intacta, ningún pago creado. |
| 2 | E02, exoneración y facturación | Con la regla de exoneración de multas confirmada, emitir cuota normal de agua al cliente EXONERADO. |
| 3 | E03, coherencia de fechas | Corregir instalación a septiembre: julio no genera cuota y se identifica cualquier asignación incompatible. |
| 4 | U01–U03, preparación y emisión | Un principiante registra servicio activo y puede explicar las cuotas generadas y las omitidas sin usar una terminal. |
| 5 | U04–U08, operación diaria | Encontrar titular, pagar un trimestre y explicar el recibo usando controles consistentes. |
| 6 | Teléfono y ayuda | Sesión real en teléfono con personas de poca experiencia; comprobar lectura, desplazamiento, selección y recuperación de errores. |

## 8. Limitaciones y siguientes pruebas

La primera solicitud de tamaño móvil no afectó la pestaña administrativa existente. Se abrió una pestaña dedicada y se confirmó después **390 × 844 reales**: la consulta pública, el menú y la navegación a Clientes se comprobaron con ese tamaño. Se midió la tabla horizontal y se guardaron capturas. La prueba sigue siendo emulación de navegador, sin teléfono físico ni teclado táctil; los veinte cobros se realizaron en tamaño de computadora. El ajuste temporal se restableció.

Se inspeccionó el recibo HTML; no se imprimió físicamente. Se abrió la dirección de PDF, pero el visor del navegador de pruebas no permitió inspeccionar su contenido. Los totales de reportes se verificaron mediante el servicio instalado y la suite automatizada; no se certifica en este informe el diseño de cada Excel/PDF descargado.

No se simularon desde la pantalla todas las variantes de empresas/RUC, medidores, asambleas, multas, cierres o roles. La suite existente aporta comprobaciones sobre algunos de esos módulos, pero no equivale a sus recorridos completos como principiante. Tampoco se probó concurrencia real de varios cajeros, interrupción de red ni recuperación de una sesión vencida.

La primera ejecución de pruebas automatizadas tuvo errores de acceso a temporales y archivos de registro fuera del espacio autorizado. Se redirigieron esos archivos al espacio de trabajo y la suite aprobó; esos errores del entorno no se clasifican como fallas del producto.

## 9. Estado que queda en el sistema y evidencias

Quedan en la base local diez clientes, diez predios, diez conexiones, diez asignaciones QA, un tipo de uso QA, una tarifa QA, 60 cuotas pagadas, 20 recibos válidos y 1 recibo anulado. Los datos ficticios permanecen identificados para que los resultados puedan revisarse; aumentan los indicadores del entorno local. No se eliminaron registros anteriores ni se cambiaron sus tarifas. Los diagnósticos de exoneración e instalación se revirtieron. El código de la aplicación no fue modificado.

- [Preparación de los dos ciclos y ausencia de duplicados](C:/Users/IGP/Documents/jass-app/qa/prepared-cycles.json).
- [20 pagos iniciales verificados desde la pantalla](C:/Users/IGP/Documents/jass-app/qa/pagos-verificados-ui.json).
- [Verificación final, recibo de reposición y totales](C:/Users/IGP/Documents/jass-app/qa/final-verification.json).
- [Diagnósticos de facturación y cambios revertidos](C:/Users/IGP/Documents/jass-app/qa/billing-diagnostics.json).
- [Resultado de las 45 pruebas automatizadas](C:/Users/IGP/Documents/jass-app/qa/phpunit-results.xml).
- [Cliente 10 tras completar los dos ciclos](C:/Users/IGP/Documents/jass-app/qa/evidencias/02-dos-ciclos-cliente10.png).
- [Panel tras los 20 pagos](C:/Users/IGP/Documents/jass-app/qa/evidencias/03-panel-tras20pagos.png).
- [Recibo anulado con motivo](C:/Users/IGP/Documents/jass-app/qa/evidencias/04-recibo-anulado.png).
- [Recibo de reposición](C:/Users/IGP/Documents/jass-app/qa/evidencias/05-recibo-repuesto.png).
- [Predio inactivo por defecto](C:/Users/IGP/Documents/jass-app/qa/evidencias/06-predio-inactivo-por-defecto.png).
- [Consulta pública con saldo cero](C:/Users/IGP/Documents/jass-app/qa/evidencias/07-consulta-publica-saldo-cero.png).
- [Listado de clientes en tamaño de teléfono](C:/Users/IGP/Documents/jass-app/qa/evidencias/08-clientes-en-telefono.png).
- [Alcance y dimensiones de la comprobación móvil](C:/Users/IGP/Documents/jass-app/qa/mobile-verification.json).

![Consulta pública del cliente ficticio con saldo cero](C:/Users/IGP/Documents/jass-app/qa/evidencias/07-consulta-publica-saldo-cero.png)
