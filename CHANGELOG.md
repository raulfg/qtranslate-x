# Registro de cambios

Este plugin es un fork de [qTranslate-X](https://github.com/qTranslate-Team/qtranslate-x)
3.4.6.8, cuyo desarrollo original se detuvo el 23 de junio de 2016 y cuya ficha de
WordPress.org se cerró el 31 de agosto de 2021.

Lo mantiene Raul Fernandez por motivos técnicos: conservar en funcionamiento una instalación
heredada sobre versiones modernas de PHP. **No es recomendable usarlo en producción en
proyectos nuevos**; ver las advertencias en `readme.md`.

Las versiones a partir de la 4.0.0 son de este fork; todo número inferior pertenece a
upstream y está documentado en `changelog-qtranslate-x.md`.

## 4.2.3

Completa la 4.2.2, que corrigió **una** de las siete llamadas afectadas. El patrón
`preg_split(..., null, PREG_SPLIT_*)` estaba repetido por todo el plugin; ahora las siete
pasan `-1`:

- `qtranslate_core.php:552` — valor por defecto de una opción que aún no está guardada.
- `qtranslate_core.php:1352` — `qtranxf_join_byseparator()`, que recompone un valor
  multiidioma a partir de sus partes. Esta es la más relevante: sólo se ejecuta al **escribir**
  contenido, no al leerlo, así que el día que PHP 9 la convierta en `TypeError` el fallo
  aparecería guardando, no mostrando.
- `admin/qtx_admin_options_update.php:329, 499, 503, 581, 685` — guardado de opciones y
  lectura de los ficheros de configuración JSON.

Igual que en la 4.2.2, **el comportamiento no cambia**: comprobado en PHP 8.4.27 que `null` y
`-1` devuelven el mismo array tanto con `PREG_SPLIT_NO_EMPTY` como con
`PREG_SPLIT_DELIM_CAPTURE`, y la página servida es byte a byte idéntica.

## 4.2.2

Mantenimiento: `qtranslate_core.php` pasaba `null` como tercer argumento de `preg_split()`
al leer la opción `qtranslate_ignore_file_types`. Ese parámetro es `$limit` y su valor por
defecto es `-1`; pasar `null` a un parámetro no nulable de una función interna está deprecado
desde PHP 8.1, y el RFC que introdujo el aviso declara la intención de convertirlo en
`TypeError` en PHP 9.

**El comportamiento no cambia.** Para `preg_split()`, `null`, `0` y `-1` significan todos "sin
límite": comprobado en PHP 8.4.27, los tres devuelven el mismo array. Sólo desaparece el aviso,
uno por petición del front end cuando esa opción no está vacía.

## 4.2.1

Arregla un error fatal en el escritorio con WordPress 7.0 y posteriores.

`qtranxf_get_terms_args()`, el filtro de `get_terms_args` que escapa el `&` de los nombres
de término, daba por hecho que `$args['name']` siempre era una cadena. `WP_Term_Query`
acepta ahí una cadena **o un array** desde WordPress 4.6, y cuando llega un array
`strpos()` lanza un `TypeError` en PHP 8. El filtro ahora recorre ambas formas y devuelve
la que recibió.

Hasta WordPress 6.9 el fallo no se notaba. `WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles()`
salía antes de tiempo cuando el tema activo no traía `theme.json`:

```php
if ( $theme->get_stylesheet() === get_stylesheet() && ! wp_theme_has_theme_json() ) {
    return array();
}
```

WordPress 7.0 elimina ese retorno temprano, así que la consulta de `wp_global_styles`
—que resuelve su `tax_query` por nombre de término y por tanto pasa un array— se ejecuta
también con temas clásicos. A partir de ahí, cualquier página del escritorio moría al
imprimir las fuentes (`wp_print_font_faces()` → `wp_get_global_settings()`).

Sólo afecta al escritorio: `admin/qtx_admin.php` no se carga en el front end.

## 4.2.0

Integra el plugin acompañante **ACF qTranslate** como módulo, y de paso le arregla la puerta
de versión que lo apagaba en silencio con ACF 6. Mismo movimiento que la 4.1.0 con el slug:
un solo plugin pasa a ser dueño de toda la capa multiidioma.

### Qué se añade

- `acf/` con `src/`, `assets/` y `compatibility/` del acompañante, copiados **sin modificar**
  desde la versión 1.7.25 de funkjedi (GPLv2 o posterior) para que se puedan seguir
  comparando con el original. Toda la adaptación vive en el cargador, no en el código
  copiado. Este repositorio normaliza los finales de línea a LF vía `.gitattributes`, igual
  que hizo con `slug/`, así que la comparación con el original es byte a byte salvo en eso.
- `acf/loader.php`, que sustituye a su arranque. Conserva las constantes
  `ACF_QTRANSLATE_PLUGIN` y `ACF_QTRANSLATE_PLUGIN_DIR`, de las que dependen tanto los
  `require_once` internos como las URLs de assets vía `plugins_url()`, y que ahora apuntan a
  `plugins/qtranslate-x/acf/`.
- La descripción del plugin pasa a mencionar los campos multiidioma para ACF.

### Admitir ACF 6 y posteriores

El acompañante solo se activaba con ACF 4 o 5 (`acf_major_version() === 4 || === 5`), así que
con ACF 6 se desactivaba **sin error fatal y sin aviso**. Se añade `acf_branch_version()`, que
mapea cualquier ACF >= 5 a la rama `acf_5` porque ACF 6 conserva la API de campos de ACF 5, y
se usa en los tres puntos que decidían por versión. El tercero era el menos evidente: la ruta
del JS de admin se construía con el número de versión y pedía `/assets/acf_6/qtranslatex.js`,
que no existe, porque solo hay `assets/acf_4` y `assets/acf_5`.

Las versiones futuras cargan a propósito la rama más reciente en lugar de no cargar nada: una
rama que quizá necesite ajustes se detecta y se corrige, mientras que el apagado silencioso no
se nota hasta que alguien ve un dato mal.

**Lo que esto no promete.** Que el puente *cargue* con ACF 6 no garantiza que su interfaz de
admin funcione allí. Las 348 líneas de JS de `assets/acf_5/` se escribieron contra el marcado
de ACF 5, y ACF 6 rediseñó el editor de grupos de campos. Queda pendiente probarlo contra una
instalación real de ACF Pro 6.

### Los datos no se tocan

No hay migración. Los siete tipos de campo (`qtranslate_text`, `qtranslate_textarea`,
`qtranslate_wysiwyg`, `qtranslate_url`, `qtranslate_image`, `qtranslate_file`,
`qtranslate_post_object`) conservan sus nombres, así que las definiciones de campo guardadas
siguen resolviendo igual, y los valores siguen en el formato `[:en]...[:]` de siempre.

### Lo único que se pierde al empaquetarlo

`acf_qtranslate_plugin::__construct()` registra `plugin_action_links_<basename>` para poner un
atajo de Ajustes en la fila del propio plugin. Empaquetado, ese basename resuelve a
`qtranslate-x/acf/loader.php`, una fila que no existe, y el filtro nunca se dispara. La página
de ajustes no se ve afectada: se registra por `admin_menu` y sigue accesible desde Ajustes. El
filtro muerto se deja a propósito en lugar de parchearlo, para que `src/` siga idéntico a
upstream.

### Cómo cambiar desde el plugin suelto

El despliegue es inerte: la carga va guardada por `class_exists('acf_qtranslate_plugin')`, así
que mientras el plugin suelto siga activo este módulo no se carga y nada cambia. Al
desactivarlo, el módulo toma el relevo en la siguiente petición; reactivarlo lo revierte.

**Aquí la guarda hace falta en los dos lados.** WordPress añade los plugins recién activados al
final de `active_plugins`, de modo que el suelto puede cargar antes o después de este fichero
según cuándo se activase por última vez. Si carga después, el módulo ya declaró la clase y su
`require_once` apunta a otra ruta absoluta, que `require_once` no deduplica: error fatal por
redeclaración. Por eso el plugin suelto lleva también una retirada temprana. A diferencia del
caso del slug, aquí basta con un `return`, porque su fichero principal solo ejecuta sentencias
y no declara nada en el nivel superior.

## 4.1.0

Integra el plugin acompañante **qTranslate Slug** como módulo. Hasta ahora era un plugin
aparte que dependía de que este se cargase antes; ahora un solo plugin es dueño de todo el
comportamiento de URLs multiidioma.

### Qué se añade

- `slug/` con el código, los assets y las traducciones del acompañante.
- `slug/loader.php`, que sustituye a su arranque. Conserva los mismos includes, las mismas
  constantes, el mismo global `$qtranslate_slug` y las mismas funciones de plantilla
  (`qts_get_url`, `qts_get_canonical_url`, `qts_get_slug`, `qts_language_menu`), de modo que
  el código y los datos no cambian.
- La descripción del plugin pasa a mencionar la definición de slugs por idioma.

### Los datos no se tocan

La meta `_qts_slug_*` y la opción `qts_options` siguen igual. `install()` solo se ejecuta si
la versión almacenada difiere de la empaquetada, así que al cambiar desde el plugin suelto no
hace nada y se heredan los datos tal cual.

### Qué descarta el cargador, y por qué

- `register_activation_hook()` y `register_deactivation_hook()`: solo se disparan para un
  fichero de plugin real. `install()` se ejecuta desde una comprobación de versión.
- `register_uninstall_hook()` y `qts_uninstall()`: desinstalar es del ciclo de vida del plugin
  padre, y borrar los slugs al desinstalar este plugin sería una sorpresa destructiva.
- `qts_add_settings_link()`: apuntaba a la fila del acompañante en la página de plugins, que
  ya no existe. La página de ajustes se registra sola y sigue en Ajustes -> Slug options.

### Cómo cambiar desde el plugin suelto

El despliegue es inerte. La carga va guardada por `class_exists('QtranslateSlug')`, de modo
que mientras el plugin suelto siga activo este módulo no se carga y nada cambia. Al
desactivarlo, el módulo toma el relevo en la siguiente petición; reactivarlo lo revierte.
Conviene refrescar las reglas de rewrite una vez desde Ajustes -> Enlaces permanentes.

**Aviso para quien conserve el plugin suelto instalado:** no basta con un `return` temprano en
su fichero principal para evitar el conflicto. PHP enlaza las declaraciones de funciones de
nivel superior al **compilar** el fichero, antes de ejecutar nada, así que reactivarlo con
este módulo ya cargado provoca un error fatal por redeclarar `qts_language_menu`. El cuerpo
del plugin suelto debe moverse a un fichero que solo se incluya tras comprobar la guarda.

## 4.0.0

Primera versión bajo mantenimiento propio. Funcionalmente equivalente a la 3.4.6.8 más las
correcciones siguientes, que llevaban años aplicadas sin quedar registradas.

### Compatibilidad con PHP

- `qtranslate_frontend.php` — sustituir `continue` por `break` dentro de un `switch`.
  PHP 7.3 deprecó `continue` apuntando a un `switch`, donde siempre se había comportado
  como `break`.
- `qtranslate_frontend.php` — eliminar el paso por referencia en
  `qtranxf_postsFilter($posts, &$query)` y
  `qtranxf_excludeUntranslatedPosts($where, &$query)`. WordPress pasa los argumentos de los
  filtros por valor, y declararlos por referencia provoca un error fatal en PHP 8.

### Hooks

- `qtranslate_hooks.php` — dejar de registrar `qtranxf_widget_init` en `widgets_init`.
  El widget propio del plugin no se utiliza en la instalación que mantiene este fork.
- `qtranslate_hooks.php` — añadir un `qtranxf_modifyTermFormFor()` vacío. La función no
  existe en ninguna parte de la 3.4.6.8 y, sin embargo, hay temas que la registran en el
  formulario de alta y edición de taxonomías públicas, lo que sin el stub sería un error
  fatal de función no definida.

### Metadatos

- Renombrado a `qTranslate-X (fork)` para que WordPress no lo case nunca con la ficha
  cerrada de wordpress.org.
- `GitHub Plugin URI` y `GitHub Branch` apuntan a este fork y a la rama `main`, no a upstream.
- Se conservan `Original Author` y `Upstream Author`.
- `readme.md` deja de ser un enlace simbólico a `readme.txt` y pasa a ser la portada del
  fork, en castellano, con las advertencias de uso. `readme.txt` se mantiene como la
  documentación de upstream, que es la que WordPress muestra en el administrador.

### Problema de seguridad conocido, sin parche

XSS almacenado en las versiones hasta la 3.4.6.8 incluida, publicado el 31 de agosto de 2021,
sin CVE asignado y sin corrección oficial. **Este fork no lo corrige.**

Requiere una cuenta con rol administrador y, al mismo tiempo, una instalación multisite o que
la capacidad `unfiltered_html` esté deshabilitada. En una instalación de un solo sitio cuyos
administradores conservan `unfiltered_html` el problema es inerte, porque un administrador ya
puede inyectar marcado arbitrario desde el editor. Si no se cumplen esas condiciones, este
fork no es seguro y procede migrar a qTranslate-XT.

### Previsto

- Absorber el plugin acompañante `qtranslate-slug` como módulo, para que un solo plugin sea
  dueño de todo el comportamiento de URLs multiidioma.
- Evaluar la migración a [qTranslate-XT](https://github.com/qtranslate/qtranslate-xt), el
  sucesor mantenido por la comunidad, que ya incluye los slugs como módulo integrado y un
  migrador desde los metadatos `_qts_slug_*`.
