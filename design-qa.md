# Revisión de diseño

Resultado: **passed**. Revisión local del 28 de octubre de 2026.

Se compararon conjuntamente la referencia seleccionada y la portada implementada a 1487 × 1058, y se comprobó la adaptación a 390 × 844. Las capturas con datos de la institución se conservan fuera del repositorio público.

Se mantiene la estructura elegida: identidad institucional, búsqueda protagonista, filtros inmediatos, publicación reciente destacada, tres tarjetas y columna de investigadores. La petición posterior de simplificar el diseño se aplica con logotipos más contenidos, menos bordes y adornos, tipografía local, colores configurables y más espacio. El crédito de autor es discreto y GitHub se representa mediante un icono con etiqueta accesible.

## Comprobaciones

- Escritorio: jerarquía legible, enlaces de ficha y cita, columna de comunidad y banner de preferencias.
- Móvil: navegación en una segunda fila, filtros acomodados, tarjetas en una columna y sin desplazamiento horizontal.
- Búsqueda mientras se escribe: resultados reales, sin botón Buscar; el enlace de catálogo conserva la consulta.
- Ficha: autores y afiliaciones, resumen, bibliografía, fuente original y selector de citas. Chicago y copia se probaron en navegador; los seis formatos tienen una prueba reproducible adicional.
- Descarga de ficha PDF: preparación correcta desde la interfaz.
- Cookies: rechazo de opcionales y acceso permanente a preferencias.
- Teclado: etiquetas de campos, enlace para saltar contenido, foco visible y estados de carga accesibles.

La revisión detectó y corrigió una recuperación inválida de conexiones S3 desde caché persistente. Una prueba posterior entre varias solicitudes protege la portada y el panel frente a esa regresión.

Sin defectos P1/P2 abiertos en las pantallas examinadas. Esto no sustituye la configuración ni la validación real de OAuth, Turnstile y entrega de correo de la institución en producción.
