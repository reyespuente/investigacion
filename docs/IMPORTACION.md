# Formato de importación privada

Todos los archivos de esta sección pertenecen a la institución y se entregan por un canal privado. Nunca se incluyen en GitHub. El sistema base inicia vacío.

## Perfiles

`researchers.json` contiene una lista JSON. Cada elemento usa `legacy_id` entero estable, `name` y, opcionalmente, `orcid` y `origin`. `legacy_id` es una referencia del sistema de origen, no una credencial ni un RFC. Repetir la carga no modifica perfiles que ya existen. Conserva los identificadores entre importaciones.

## Publicaciones

Un objeto JSON por línea, UTF-8, en `.jsonl` o `.jsonl.gz`. Campos:

| Campo | Contenido |
|---|---|
| `publicacion_id` | Entero estable del origen. |
| `titulo`, `anio`, `fecha`, `tipo`, `revista`, `doi`, `enlace` | Bibliografía principal. Valores desconocidos: `null`. Fecha con la precisión real. |
| `autores` | Lista ordenada: `posicion`, `nombre`, `orcid`, `afiliaciones` (textos) y, si existe, `nombre_estructurado` con `given`/`family`. No deducir apellidos a partir del nombre. |
| `bibliografia` | Volumen, número, páginas, editorial, ISSN/ISBN, idioma, licencias, temas y campos complementarios disponibles. |
| `resumen_principal`, `resumenes` | Objetos con `texto`, `idioma`, fuente y URL. Usar `und` si no se declaró idioma. |
| `estado` | `lista` pasa al catálogo público; `revision` queda en revisión; `incompleta` permanece incompleta. Importa como `lista` únicamente datos previamente validados. |
| `procedencia` | Registro/URL/fuente por campo. Se conserva en la ficha. |
| `csl`, `referencia` | Cita estructurada CSL y referencia original, si existen. |
| `researcher_links` | Lista con `legacy_id` de perfil y `state`: `confirmed`, `pending` o `rejected`. |
| `personas_confirmadas` | Alternativa para catálogos previos: lista con `id` del perfil; se interpreta como autoría confirmada. |

Primero importa los perfiles. Una atribución pendiente nunca aparece públicamente como confirmada. Los bot recolectores futuros deben enviar candidatos para revisión, no marcar sus resultados como `lista` sin una validación previa.

Las importaciones se identifican por `legacy_id`; no mezcles dos catálogos distintos con identificadores superpuestos sin una transformación previa. DOI es único. El administrador debe resolver conflictos de DOI antes de cargar el archivo. Cada línea se aplica en una transacción; si una línea falla, las anteriores quedan disponibles y el comando puede repetirse. No se sobrescriben publicaciones con `user_modified_at`.

Los campos no esenciales y evidencias adicionales se preservan en `metadata`. No incluyas correos o RFC en esos metadatos. Conserva las respuestas originales completas en un archivo histórico privado independiente cuando requieran contener esos datos.

## Correo para reclamación

`claims.csv`, UTF-8, encabezado exacto `legacy_id,email`. No requiere nombres ni RFC. Se comprueba que cada perfil exista, que el correo sea válido y esté en `ALLOWED_EMAIL_DOMAINS`, y que no esté repetido. Las filas sin correo se omiten. El archivo se valida antes de modificar las asociaciones.

Se guarda HMAC del correo normalizado (espacios extremos fuera y minúsculas), con la clave de la aplicación. La reclamación exige una cuenta Google con ese correo exacto y verificado. Importar un correo no reclama el perfil ni crea una cuenta por adelantado.

Si una dirección pertenece a un dominio no permitido, conserva la fila para revisión; no inventes una cuenta equivalente ni cambies el dominio automáticamente. La institución decide si amplía la lista permitida o corrige su padrón. Tras modificar `.env`: `php artisan config:cache`, y vuelve a importar las filas corregidas.

## Instalación ya utilizada

Cuando ya hay usuarios, reclamaciones, archivos o ediciones, el respaldo de la base de Laravel es la fuente principal de migración. Las importaciones bibliográficas no sustituyen ese respaldo ni contienen contraseñas, documentos o configuración visual guardada en el panel.
