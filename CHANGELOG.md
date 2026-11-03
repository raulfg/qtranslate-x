# Registro de cambios

Este plugin es un fork de [qTranslate-X](https://github.com/qTranslate-Team/qtranslate-x)
3.4.6.8, cuyo desarrollo original se detuvo el 23 de junio de 2016 y cuya ficha de
WordPress.org se cerró el 31 de agosto de 2021.

Lo mantiene Raul Fernandez por motivos técnicos: conservar en funcionamiento una instalación
heredada sobre versiones modernas de PHP. **No es recomendable usarlo en producción en
proyectos nuevos**; ver las advertencias en `readme.md`.

Las versiones a partir de la 4.0.0 son de este fork; todo número inferior pertenece a
upstream y está documentado en `changelog-qtranslate-x.md`.

## 5.0.0

Los campos `wysiwyg` nativos de ACF pasan a ser multiidioma. Hasta ahora el módulo `acf/` solo
cubría los tipos `qtranslate_*`; un `wysiwyg` normal guardaba en cada pantalla el contenido del
idioma activo y descartaba el resto. Compatible con Secure Custom Fields 6.9.7 además de ACF 5.

**Por qué sube de número mayor.** Tres cosas cambian para quien ya tenga el plugin instalado, y
ninguna se puede deshacer recargando:

1. Un `wysiwyg` nativo pasa a almacenarse como valor multiidioma con marcadores. Lo que antes
   era una cadena suelta ahora lleva los cuatro idiomas dentro, de modo que cualquier código
   que leyera ese campo sin pasar por qTranslate ve otra cosa.
2. El plugin autónomo `acf-qtranslate` se retira. Su módulo vive dentro del fork desde la 4.2.0
   y mantener los dos a la vez era un duplicado.
3. Guardar desde un `acf_form()` en el frontend deja de sustituir el valor entero: ahora funde
   el texto en el idioma de la URL. Es el arreglo de una pérdida de datos, pero es un cambio de
   comportamiento para quien dependiera del anterior.

`QTX_VERSION` sigue declarando `3.4.6.8` a propósito: es la versión de upstream de la que
desciende el código y hay comprobaciones de terceros que la leen. El número del fork vive en la
cabecera del plugin, que es lo que usa `qtranxf_fork_version()` para el cache-busting de assets.

### El diagnóstico

Dos mecanismos distintos, ambos medidos en un laboratorio (una página con un campo de cada
forma: de primer nivel, en `repeater`, en `group`) antes de tocar nada.

**A. El relevo entre el núcleo y el módulo perdía el valor crudo al desenganchar.** qTranslate
engancha cada `.wp-editor-area` al cargar el documento y sustituye el valor `[:en]A[:es]B[:]` por
el texto del idioma activo, repartiendo el resto en inputs ocultos. Al desenganchar, el núcleo
dejaba en el campo solo ese texto del idioma activo: el valor multiidioma original no se
reconstruía. Cualquier camino que desenganchara y volviera a enganchar (el barrido del módulo,
una fila nueva) convertía `[:en]A[:es]B[:]` en `A` y lo guardaba.

**B. El renombrado destructivo de ACF dejaba huérfano el registro de enganches.** ACF crea el
editor con un id temporal y lo sustituye con `acf.rename({destructive:true})` cuando inicializa
TinyMCE. El barrido anterior corría en `window.load`, antes de esa inicialización, de modo que
enganchaba el textarea temporal y el enganche quedaba apuntando a un elemento que ACF ya había
destruido. El editor definitivo no estaba enganchado: los botones de idioma no hacían nada sobre
él y el POST llevaba un único valor.

### Qué se cambió

- **`removeContentHookH` (`admin/js/common.js`)** reconstruye el valor multiidioma al desenganchar:
  primero sincroniza el slot del idioma activo desde el campo (y desde TinyMCE con `mce.save`
  cuando está visible) y después reensambla el valor con **todos** los slots de idioma, igual que
  hace `qtranxf_join_b` en PHP. No se pierde ni una edición ni un idioma. Los slots no vacíos
  idénticos colapsan, de modo que un valor sin marcar sigue sin marcar, y los vacíos votan en esa
  comparación (`[:en]A[:]` conserva sus marcadores). Es la corrección de A. Una primera versión
  restauraba el valor crudo capturado al enganchar; se sustituyó porque la revisión midió tres
  secuencias que perdían datos con ella (aplanado al cambiar de pestaña, edición de un idioma no
  activo y aplanado al teclear).
- **Enganche del `wysiwyg` (`acf/assets/acf_5/qtranslatex.js`)** a través del filtro
  `wysiwyg_tinymce_settings`, que ve el editor definitivo, y no desde `window.load`. Es la
  corrección de B. El barrido del módulo corre en la acción `load` de ACF con prioridad 1,
  deliberadamente **antes** del renombrado destructivo; el nuevo enganche del `wysiwyg` ocurre en
  `wysiwyg_tinymce_settings`, **después** de él. El desenganche genérico anterior se conserva porque limpia los enganches del
  núcleo sobre los textareas temporales que ACF va a destruir.
- **Endurecimiento de PHP en `qtranxf_collect_translations*` (`admin/qtx_admin.php`).** Con un
  campo anidado cuyo valor llegaba como cadena en lugar de array, el `[]` sobre una cadena era un
  `TypeError` en PHP 8. Además, indexar un campo ausente lo creaba con autovivificación, y la
  plantilla `acfcloneindex` de los `repeater` salía como una fila fantasma. Ahora se comprueba la
  forma de la petición, se omite `acfcloneindex` y un valor con contenido gana a uno vacío.
- **Guarda de pérdida (`acf/src/acf_5/acf.php`, filtro `acf/update_value`).** Si llega un valor
  sin marcadores y el guardado tenía marcadores, escribe una línea en `debug.log` con el post, el
  campo y las longitudes (nunca el contenido). **Avisa; no bloquea**: bloquear un guardado por una
  heurística convierte un falso positivo en pérdida de lo que el editor acaba de escribir, y un
  borrado legítimo es indistinguible desde el servidor. Desde la corrección de A la guarda no
  vuelve a dispararse: 15 líneas antes y 15 después de todas las pruebas.
- **Filas de `repeater`.** El desmontaje al borrar una fila estaba escrito pero nunca se asignaba
  (`repeaterRemove`), así que el registro goteaba desde siempre; ahora se instala. Al reordenar
  con arrastrar y soltar, el editor de la fila movida quedaba sin enlazar a su TinyMCE nuevo; el
  `init_instance_callback` lo reengancha. Los campos dentro de `group` pasaban ya en la línea base
  y **no requirieron cambio de código**.
- **Idiomas retirados (`de`, `ru`, `zh`): se conservan, no se descartan.** La especificación
  esperaba que un guardado los perdiera dejando la cadena bien formada. Medido: `qtranxj_split`
  devuelve los idiomas retirados que contienen datos y el núcleo ya documenta que se conservan y
  se vuelven a guardar hasta que alguien los borre a mano. Descartarlos exigiría código explícito
  y destruiría datos que nadie ha pedido borrar (1.602 valores en producción). La cadena sigue
  bien formada y cerrada con `[:]`. Es mejor de lo que prometía la especificación.
- **Divergencia deliberada `'0'` frente a `empty()`.** La unión de idiomas del lado JS trata el
  literal `'0'` como contenido; el `empty()` de PHP no. Se conserva porque colapsar `'0'` a vacío
  borraría un valor legítimo, y queda comentado en el código. Se añadió la guarda para campos de
  slug y de término, que comparten el camino de desenganchado pero no tienen ningún llamante real
  hoy.
- **`get_visible_acf_fields()` retirado.** Llevaba sin llamante desde que upstream eliminó el
  único que tenía. qTranslate-XT llegó a la misma conclusión. Se retira también del interfaz
  `acf_qtranslate_acf_interface` (si no, fatal por método abstracto sin implementar); el interfaz
  se conserva, ahora vacío, porque los constructores lo exigen en su tipado. La copia de
  `acf_4/acf.php`, también sin llamante, no se toca.
- **`jQuery(window).load` reemplazado.** `.load()` como atajo de evento se eliminó en jQuery 3 y
  solo funcionaba por el parche `jquery-migrate`; si ese se retira, el módulo dejaba de cargar.
  Ahora el módulo se engancha a la acción `load` de ACF con prioridad 1 (o se ejecuta de
  inmediato si ya se disparó), antes del renombrado destructivo de los editores.

### Artefacto minificado

`admin/js/common.min.js` se **regenera, nunca se edita a mano**. La orden exacta:

```
npx --yes terser@5.31.6 common.js -c -m -o common.min.js
```

Verificación funcional tras regenerar: `node --check common.min.js` y la comprobación de que las
seis funciones públicas (`addContentHook`, `addContentHookC`, `addContentHooks`,
`addContentHookById`, `removeContentHook`, y el constructor `qTranslateX`) siguen presentes en el
mismo número de asignaciones `this.X=` que antes (30 en ambos). Antes de esta versión no había
registro de cómo se generaba.

### Caché de assets

Los assets del fork se servían sin parámetro de versión útil: `qtranslatex.js`, el `common.js`
del módulo, `main.js` y `common.css` no llevaban ninguno, y el resto usaba `QTX_VERSION`. Sin
invalidación, cada despliegue de este arreglo servía JS viejo a los navegadores que ya habían
visitado el admin, y el síntoma era idéntico al defecto corregido.

Ahora llevan `?ver=` con la versión de la cabecera del plugin, que se lee una sola vez con
`qtranxf_fork_version()` (`get_file_data` sobre `qtranslate.php`). Hay una razón para no reutilizar
`QTX_VERSION`, que se **deja en `'3.4.6.8'`**: hay código que la compara con versiones de upstream.
Esa constante era además el único cache-buster de `options.min.js` y no se había movido en ninguna
release 4.x, así que los navegadores venían sirviendo ese fichero desde caché.

Hallazgo medido: el tema activo del sitio elimina `?ver=` de
**todos** los scripts y estilos con un filtro de prioridad 15, por seguridad. Con eso el argumento
de versión era un no-op y el cache-busting no habría tenido efecto alguno. Por eso
`qtranxf_restore_asset_version()` (prioridad 20, solo en el admin) vuelve a poner la versión del
fork únicamente en las URLs de este plugin. El `common.min.js` del núcleo se vuelca en línea en el
pie de página, salvo con `SCRIPT_DEBUG`, así que no depende de la caché del navegador.

### Duplicado `plugins/acf-qtranslate/` retirado

El plugin autónomo `acf-qtranslate` y el módulo `qtranslate-x/acf/` definen ambos la clase
`acf_qtranslate_plugin`. Eran idénticos byte a byte hasta este arreglo, que solo toca la copia
vendida, así que han divergido. Medido: el autónomo está inactivo y el vendido activo. Ambos
cargadores se protegen con `class_exists`, de modo que no hay fatal, pero el riesgo es silencioso:
si se reactivara el autónomo y cargara primero, ganaría la copia antigua, **sin el arreglo**, y el
módulo se apartaría sin avisar. Se retiran sus 29 ficheros del repositorio.

### `qtranslate_url` ya se puede guardar

El tipo `qtranslate_url` hacía fallar el guardado de **toda** la pantalla, y lo hacía desde
siempre: no es una regresión de esta versión.

El campo pinta un input por idioma, así que lo que llega a la validación es un **array** indexado
por código de idioma, no una cadena. `acf_field_url::validate_value()`, de la que hereda, espera
una cadena:

- En Secure Custom Fields 6.9.7 hay una guarda `if (!is_string($value))` que devuelve
  *«Value must be a valid URL»*. Como un array de cuatro cadenas vacías no es `empty()`, llegaba
  siempre a esa guarda: el guardado se bloqueaba **incluso con los cuatro idiomas vacíos**.
- En ACF Pro 5.8.14 y 6.2.0 no existe esa guarda, así que la ejecución alcanzaba
  `strpos($array, '://')`, que en PHP 8 es un `TypeError` fatal.

`acf_qtranslate_acf_5_url::validate_value()` valida ahora **cada idioma por separado** con el
validador original de ACF y devuelve el primer error prefijado con el nombre del idioma
(`(Français) Value must be a valid URL`). Un idioma vacío no es una URL malformada y se omite;
la obligatoriedad se sigue comprobando donde ya se comprobaba.

Es la misma forma que usa qTranslate-XT en
`QTX_Module_Acf_Admin::validate_language_values_standard()`, con una diferencia: XT recibe la
cadena multiidioma y la parte con `qtranxf_split()`, mientras que aquí el valor ya llega troceado
por idioma. XT no cubre este caso —no registra ningún filtro para `url` y trae `qtranslate_url`
desactivado por defecto—, así que el recorrido se alimenta del array.

Los otros seis tipos `qtranslate_*` no necesitaban nada: `text` y `textarea` heredan validadores
que se guardan con `is_scalar()`, `post_object` y `wysiwyg` no validan valor, e `image` y `file`
se comprobaron guardando con ellos presentes.

### El selector de idioma deja de perderse en pantallas largas

Una ficha de programa mide unas 45.000 pixeles de alto y reparte 38 metaboxes. Las barras de
idioma quedan a 95, 2.661 y 45.601 pixeles, de modo que entre la segunda y la ultima hay casi
43.000 pixeles —el 94% de la pantalla— sin un solo selector a la vista. Cambiar de idioma
obligaba a subir, pulsar y volver a buscar donde se estaba trabajando.

La primera barra se fija ahora bajo la barra de administracion en cuanto el scroll la alcanza, con
10 pixeles de separacion para que no se lea como parte de ella. Mientras estas arriba no se fija:
el titulo de la pantalla y el boton de anadir quedan libres. Las otras dos barras no se tocan.

No puede resolverse con `position: sticky`. La barra vive en `#post-body-content`, cuya caja
termina a los 2.721 pixeles, y un elemento pegajoso se suelta al final de su bloque contenedor:
justo donde empieza el problema. Se fija con `position: fixed`.

El cambio lo dispara un `IntersectionObserver` sobre un centinela colocado en el sitio de la
barra, nunca un manejador de scroll. Escribir estilos en cada evento de scroll fuerza recalculos
de maquetacion en una pantalla con decenas de metaboxes y editores TinyMCE; ese trabajo colapsa la
altura de la pagina, el navegador recorta el desplazamiento y el editor acaba de vuelta arriba.
Medido: con el manejador de scroll la pagina saltaba al principio; con el observador el
desplazamiento se mantiene sin deriva.

Tres detalles que el navegador obliga a tratar. El ancho y la posicion se toman del separador que
guarda el hueco de la barra, no del area de contenido completa, porque esta incluye la columna de
widgets y la barra se montaria encima de ella. El selector necesita el nombre de etiqueta
(`ul.qtranxs-lang-switch-wrap.qtranxs-lsb-pinned`): la regla propia de la barra tiene mas
especificidad que una clase suelta y conservaria su margen superior de 20 pixeles. Y el
desplazamiento vertical se mide en vez de fijarse por punto de ruptura: por debajo de 783 pixeles
WordPress pasa la barra de administracion a `position: absolute`, de modo que se va con el scroll;
reservarle sitio dejaria una franja vacia por la que asoma el contenido. Se consulta su posicion
calculada, y el hueco queda en 42 pixeles en escritorio y 10 en la disposicion estrecha.

Se carga solo en `post.php` y `post-new.php`. El resto del administrador no lo necesita.

### Un formulario en el frontend ya no borra los demas idiomas

Un `acf_form()` fuera del administrador no tiene nada de la maquinaria multiidioma: no se
carga `qTranslateConfig`, no hay barra de idiomas y el campo no lleva enganches. El idioma lo
pone la URL, el campo se pinta con ese unico idioma y el formulario devuelve esa unica cadena.
Guardada tal cual, sustituia al valor entero: **editar una pagina por `/es/` descartaba en
silencio el ingles, el frances y el arabe**. Medido: un campo con cuatro idiomas quedaba
reducido a texto plano sin marcadores.

Es un defecto anterior a esta version, no una regresion. El fork ya lo detectaba —la guarda
`log_translation_loss` lo registraba en `debug.log`— pero se limitaba a avisar.

Ahora el texto enviado se **funde** en el hueco del idioma que la URL pide, y los demas quedan
como estaban. Editar por `/en/` toca el ingles y nada mas, que es lo que promete una URL por
idioma. El administrador queda deliberadamente fuera: alli el valor llega ya ensamblado desde
los campos por idioma, y una cadena plana significa de verdad que se han vaciado los otros.

La lectura del valor almacenado se comparte ahora con la guarda, en `read_stored_value()`: pasa
por la API de metadatos en vez de `$wpdb->get_var()`, que convierte la cadena vacia en NULL y
ocultaria una fila presente pero vacia, y levanta el filtro `get_*_metadata` de qTranslate-X
durante esa unica lectura, porque si no devolveria solo el idioma en curso.

Verificado guardando el mismo campo desde `/es/`, `/en/`, `/fr/` y `/ar/`: cada URL escribe su
idioma, los cuatro conservan el texto propio y ninguno pisa a otro.

### Huecos conocidos

- **"Text Only" y "Delay initialization".** Un `wysiwyg` con `tabs => 'text'` o `delay => 1` no
  dispara el filtro `wysiwyg_tinymce_settings`, así que no se engancha. No hay ninguno hoy: los
  121 campos están en `tabs=all, delay=0`. La guarda de pérdida lo registraría en `debug.log` si
  apareciera uno.
- **Alcance de la auditoría.** Solo claves de meta sin guion bajo, solo marcadores con corchetes
  (`[:xx]`; los `{:xx}` de 100 filas de Yoast y los `<!--:xx-->` no se compararon), con clave
  `post_id|meta_key`. No se compararon termmeta, usermeta ni commentmeta.
- **ACF Blocks** no se ha probado. No se usa en este proyecto (0 grupos con ubicación `block`) y
  su guardado va por Gutenberg y REST, una via que no pasa por el enganche en carga de pagina, asi
  que activarlo obliga a verificar de nuevo.
- **Repeaters paginados: verificados.** Con paginacion de dos filas y cinco filas guardadas, las
  filas que llegan por AJAX al cambiar de pagina nacen enganchadas con sus cuatro idiomas, y
  guardar desde la primera pagina deja intactas las filas de las otras, que ni siquiera estan en
  el documento en ese momento.
- **`color_picker`: verificado.** Guarda su valor con normalidad; el fallo de una prueba anterior
  fue no escribir en su input oculto, no del plugin.
- **`max_input_vars`.** Medido en la pantalla más pesada (6.606 variables de 30.000, un 22 %) y
  con incremento 0 respecto a antes del arreglo: antes cada editor ya llevaba cinco inputs ocultos
  del enganche del núcleo, ahora los lleva una sola vez. Un truncamiento exigiría miles de filas
  más; no hay límite que documentar.

### Verificación

- Laboratorio: 11 campos (texto, textarea, `wysiwyg` de primer nivel, en `repeater`, en `group`,
  solo en inglés, y los tipos `qtranslate_*`) con SCF 6.9.7 y con ACF Pro 5.8.14: tras guardar sin
  tocar nada, todos intactos salvo dos reescrituras benignas de orden de idiomas con los mismos
  bytes (`lab_qtx_text`, `lab_qtx_wys`). Los campos solo-inglés no se duplican a los cuatro idiomas.
- Auditoría de 31.863 filas de meta con marcadores contra el volcado previo: 0 diferencias sin
  explicar, 0 filas ausentes, 0 idiomas perdidos.
- La guarda de pérdida, en silencio desde la corrección de A: 15 líneas antes y después.
- Tras el cambio de caché: recarga forzada del admin y guardado de la página de laboratorio con
  las nuevas URLs `?ver=5.0.0` presentes y el mismo resultado de 9 intactos y 2 benignos.

#### Matriz de contenedores y motores

Verificado en navegador sobre una copia de la base de datos de producción, con
**ACF Pro 5.8.14**, **ACF Pro 6.2.0** y **Secure Custom Fields 6.9.7**:

- `wysiwyg` de primer nivel, en `repeater`, en `repeater` anidado, en `group`, en `group` con
  `repeater` dentro, y en `group > repeater > flexible_content > layout`: en todos los casos los
  cuatro idiomas sobreviven al guardar, y editar uno deja los otros tres byte a byte intactos.
- Filas de `repeater`, subfilas y bloques de `flexible_content` creados en la misma sesión nacen
  enganchados con sus cuatro idiomas: el enganche no depende de que el contenedor existiera al
  cargar la página.
- `clone` en modo `seamless`, donde el nombre del input se reescribe a
  `acf[clone][clone_subcampo]`, y en modo `group`: los enganches siguen el nombre reescrito.
- `wysiwyg` después de un `tab` y dentro de un `accordion`.
- Página de opciones ACF (`wp_options`, fuera de `post.php`): de 142 opciones solo cambia la
  editada, 0 pierden idiomas.
- Los 44 tipos de campo que registra el motor, cubiertos por un grupo creado al efecto.
- Página más pesada del sitio (3.594 claves de meta, 10.602 campos en la POST, 227 `wysiwyg`):
  0 claves perdidas, 0 creadas, 0 idiomas perdidos, 0 filas `acfcloneindex`.
- `qtranslate_url`: guarda los cuatro idiomas en los tres motores, y una URL malformada en un solo
  idioma sigue rechazándose nombrando ese idioma.

Al guardar, TinyMCE reformatea el HTML del **idioma activo** de cada `wysiwyg` poblado de la
pantalla, añadiendo saltos de línea entre etiquetas de bloque. Medido idioma a idioma: el texto y
el marcado quedan idénticos tras normalizar espacios, y los idiomas no activos vuelven byte a byte
iguales porque viajan en los inputs ocultos sin pasar por el editor. Es comportamiento de
WordPress, no del plugin, pero conviene saberlo: un guardado deja ruido cosmético en esos campos.

## 4.3.1

Elimina el 70% de los avisos de deprecación de PHP que emitía el plugin, corrigiendo de paso
un fallo que llevaba una década borrando en silencio el atributo `title` de los enlaces del
menú.

### El diagnóstico

`qts_quickuse()`, en el módulo `slug/`, hacía esto:

```php
$parsed_text = call_user_func($this->get_plugin_prefix() . 'getSortedLanguages', $text);
if (!empty($parsed_text[$lang])) {
    return $parsed_text[$lang];
}
// sin return
```

Está escrito contra la API de qTranslate 2, donde `getSortedLanguages($text)` parseaba un texto
multiidioma y devolvía un array indexado por idioma. En qTranslate-X ese nombre pertenece a otra
función: recibe un booleano `$reverse` y devuelve la **lista de códigos de idioma activos**. Así
que `$text` viajaba como `$reverse`, el resultado era una lista 0-indexada, e indexarla por
código de idioma nunca acertaba. El método caía siempre al final **y devolvía `null`**.

qTranslate-XT lo confirma en su propio código: en `src/compatibility.php` declara
`qtrans_getSortedLanguages( bool $reverse = false ): array` — con los tipos explícitos, para que
el contrato no se pueda confundir.

### Las dos consecuencias

`qts_filter_attr_title()` estaba colgado de `nav_menu_attr_title` con **prioridad 0**, es decir
antes de los `wptexturize` y `strip_tags` que core engancha en la 10. Devolvía `null`, de modo
que:

- `strip_tags(null)` emitía un aviso de deprecación **por cada item de menú y por cada página**:
  5.488 de los 7.889 medidos en 100 cargas, el 69% del total.
- El atributo `title` del enlace se perdía. Lo que un editor escriba en "Atributo de título" de
  un item del menú se descartaba sin avisar.

### El arreglo

Dos cambios:

- `qts_quickuse()` usa ahora `qtranxf_use_language()`, que es la API correcta y la que
  `qtranslate_frontend.php` ya emplea para `post_excerpt` y `attr_title`. Devuelve `$text` sin
  tocar cuando el valor no lleva bloques de idioma, así que nunca más `null`.
- Se **desregistra** el filtro `nav_menu_attr_title`, siguiendo a qTranslate-XT, que no lo
  engancha en ningún sitio. Es redundante: core monta `attr_title` desde `post_excerpt` y acto
  seguido `qtranxf_wp_get_nav_menu_items()` lo traduce en `qtranslate_frontend.php:168`. Ese es
  el único camino por el que se traducen los items del menú, aquí y en XT.

### Medido

Sobre 100 páginas en cuatro idiomas: deprecaciones **7.889 → 2.301**, avisos de `strip_tags` con
`null` **5.488 → 0**, cero fatales. Verificado además con un experimento controlado que la salida
HTML es idéntica con y sin el filtro, descontando los identificadores que el sitio genera por
petición.

## 4.3.0

Desactiva la sección **Import/Export** de los ajustes, que migraba opciones desde
mqTranslate, qTranslate Plus y zTranslate. Se dejan de registrar sus dos hooks en
`admin/qtx_import_export.php`; el código permanece, comentado y documentado.

El motivo no es la interfaz, es el handler. `qtranxf_migrate_plugins()`, colgado de
`qtranslate_saveConfig`, recorría `$_POST` buscando claves terminadas en `-migration` y
componía el nombre de la función a ejecutar a partir del valor recibido. **No comprobaba si el
plugin de origen estaba instalado**: le bastaba con que la función existiera, y todas están
definidas en ese mismo archivo. La interfaz sí lo comprobaba (`qtranxf_add_row_migrate()`
devuelve sin pintar nada si el plugin no está presente), de modo que la sección aparecía vacía
y la operación parecía imposible — pero un POST con `<plugin>-migration=import` contra la
página de ajustes, hecho por alguien con `manage_options`, habría sobrescrito las opciones de
qTranslate igualmente.

De paso esto deja inalcanzable `qtranxf_migrate_options_copy()`, cuyo `continue` dentro de un
`switch` (línea 62) equivale a `break` y por tanto copia las 21 opciones que el código parece
querer saltarse. Ese defecto no se corrige aquí: la intención original es ambigua y cambiarlo
alteraría una ruta de migración que no se puede probar. Queda documentado y sin salida.

Ninguna instalación que use este fork migra desde otro plugin multiidioma.

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
