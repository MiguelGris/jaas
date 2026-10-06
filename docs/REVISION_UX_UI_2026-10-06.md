# Revisión UX/UI · 6 de octubre de 2026

La revisión se realizó sobre `correcciones`, la copia más reciente de la aplicación en este repositorio. Las mejoras se concentran en los componentes compartidos y en las pantallas de consulta pública y acceso administrativo.

## Hallazgos y cambios

- Los controles dependían de clases de color de borde sin declarar su grosor y, en varios formularios, sin espaciado interior. Se añadieron bordes, fondo, espaciado y una altura mínima de 44 px para inputs y selects.
- Se reforzó el contraste de textos secundarios, encabezados de tabla y navegación lateral.
- Se añadió foco visible para controles, enlaces y elementos desplegables, un enlace para saltar al contenido y respeto por la preferencia de movimiento reducido.
- El menú lateral permanece visible al desplazar la página en escritorio. Los enlaces activos tienen `aria-current` e indicador visual. En móvil, Escape cierra el menú y devuelve el foco al botón.
- Se eliminó el segundo botón de cierre de sesión, cuyo icono podía confundirse con un enlace externo. La acción sigue disponible con su nombre en el menú de usuario.
- La tabla compartida admite foco y desplazamiento horizontal por teclado, con una etiqueta accesible.
- La consulta pública usa una cabecera más compacta en móvil y explica el formato del DNI/RUC junto al campo. Los errores se vinculan al control mediante `aria-describedby` y `aria-invalid`.
- El acceso permite mostrar u ocultar la contraseña y comunica el estado del botón. Los errores tienen identificación accesible y el correo admite autocompletado de credenciales.
- El pie público permanece al final de la pantalla cuando hay poco contenido.

## Implementación

La capa común está en `public/css/ux.css` y se carga después del bundle existente de Tailwind en ambos layouts. No requiere regenerar los assets con Vite. Para instalar estos cambios deben copiarse las cinco vistas modificadas y el nuevo archivo CSS; el CSS forma parte del cambio y no debe omitirse.

## Verificación

- Suite existente: 81 pruebas aprobadas y 1.371 aserciones, usando SQLite en memoria y el bootstrap de QA existente.
- Consulta pública inspeccionada visualmente en escritorio y móvil.
- Acceso inspeccionado en móvil; verificado el cambio de tipo del campo de contraseña al pulsar Mostrar.
- Sin desbordamiento horizontal en las pantallas públicas comprobadas.
- Verificado que Saltar al contenido mueve el foco al elemento principal.
- `git diff --check` sin errores de espacios.

La vista previa utilizó sesiones temporales y una base en memoria. No se instaló esta revisión en la aplicación externa de `Desktop/Jass`. El panel administrativo se cubrió mediante la revisión de sus vistas y la suite existente; queda pendiente una inspección visual de todos sus flujos con sesión autenticada.

## Integración posterior en Desktop/Jass

La capa visual descrita arriba se integró en Desktop/Jass junto con las mejoras de funcionamiento del informe de QA. El estado anterior de vista previa ya no describe la instalación actual. Consulta MEJORAS_APLICADAS_2026-10-06.md para las pruebas y límites de esta integración; la verificación móvil anterior no certifica los nuevos flujos administrativos.
