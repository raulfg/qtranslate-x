# qTranslate-X (fork)

> ## Antes de nada: mira qTranslate-XT
>
> **Si estás valorando usar un plugin de este tipo, empieza por
> [qTranslate-XT](https://github.com/qtranslate/qtranslate-xt). No por aquí.**
>
> Es el sucesor mantenido por la comunidad del mismo plugin: desarrollo activo, integra como
> módulos los plugins acompañantes que antes había que instalar aparte, declara compatibilidad
> con versiones recientes de WordPress y requiere PHP 7.4 o superior.
>
> Este repositorio **no es una alternativa a XT**. Es un fork de una versión de 2016 con un
> único propósito: mantener en pie una instalación heredada. Si tienes la opción de elegir,
> no elijas esto.

Fork de [qTranslate-X](https://github.com/qTranslate-Team/qtranslate-x) 3.4.6.8, mantenido
por **Raul Fernandez** por motivos técnicos.

Da soporte multiidioma a contenido dinámico de WordPress guardando todas las traducciones
en el mismo campo del post, en lugar de duplicar entradas o sitios por idioma.

## Por qué no deberías usar este fork

Si has llegado hasta aquí a pesar del aviso de arriba, estas son las razones concretas:

- **No está en desarrollo activo.** No se añade funcionalidad ni se sigue la evolución de
  WordPress. Solo se corrige lo que impide funcionar a la instalación que lo usa.
- **Arrastra un XSS sin parchear**, heredado de upstream y sin corrección oficial. Está
  documentado más abajo, con las condiciones en las que resulta explotable.
- **La base es de 2016.** Nueve años de correcciones de WordPress y de PHP que XT sí ha
  incorporado aquí no están.
- **No hay comunidad detrás.** Si aparece un fallo que no afecta a la instalación que lo
  mantiene, nadie lo va a arreglar.

Para un proyecto nuevo, o para cualquier instalación que pueda migrar, la opción es
[**qTranslate-XT**](https://github.com/qtranslate/qtranslate-xt).

## Por qué existe este fork

El desarrollo original se detuvo el **23 de junio de 2016** y la ficha de WordPress.org se
cerró el **31 de agosto de 2021**. A pesar de ello el plugin sigue en producción en
instalaciones heredadas, con correcciones aplicadas directamente sobre los ficheros del
servidor y sin registro en ningún sitio.

Este repositorio existe para que esas correcciones dejen de ser conocimiento tácito: cada
cambio es un commit con su motivo, y `git diff 3.4.6.8..main` muestra en todo momento la
desviación completa respecto al original.

La numeración propia arranca en **4.0.0**. Todo número inferior pertenece a upstream y está
documentado en `changelog-qtranslate-x.md`.

## Novedades de la 4.3.1

Se van el 70% de los avisos de deprecación de PHP que emitía el plugin, y con ellos un fallo que
llevaba una década descartando en silencio el atributo `title` de los enlaces del menú.

El módulo `slug/` llamaba a `getSortedLanguages($text)` creyendo que parseaba texto multiidioma,
como hacía en qTranslate 2. En qTranslate-X esa función recibe un booleano y devuelve la lista de
idiomas activos, de modo que la comprobación nunca acertaba y el método devolvía `null` siempre.
Ese `null` llegaba al `strip_tags` que core aplica a `nav_menu_attr_title` — un aviso por item de
menú y por página — y además borraba el valor del atributo.

Arreglado usando `qtranxf_use_language()`, la API correcta, y desregistrando el filtro, que era
redundante: el plugin ya traduce `attr_title` por otra vía. qTranslate-XT tampoco lo engancha.

Medido sobre 100 páginas en cuatro idiomas: de 7.889 deprecaciones a 2.301.

## Novedades de la 4.3.0

Desaparece la sección **Import/Export** de los ajustes, que servía para migrar opciones desde
mqTranslate, qTranslate Plus y zTranslate.

No se quita por limpieza, se quita por seguridad. Su handler recorría `$_POST` buscando claves
terminadas en `-migration` y **no comprobaba si el plugin de origen estaba instalado**: con que
existiese la función correspondiente —y todas existen en el propio archivo— un envío manipulado
desde la página de ajustes, por alguien con permisos de administración, podía sobrescribir las
opciones de qTranslate. La interfaz aparecía vacía porque sí hacía esa comprobación, de modo que
el riesgo no se veía.

Ninguna instalación que use este fork migra desde otro plugin multiidioma. El código queda en el
repositorio, comentado, con el motivo escrito al lado.

## Novedades de la 4.2.1

Corrige un error fatal que tumbaba **cualquier página del escritorio** a partir de WordPress
7.0. El filtro `get_terms_args` del plugin escapaba el `&` de los nombres de término dando por
hecho que recibía una cadena, pero `WP_Term_Query` acepta ahí una cadena **o un array** desde
WordPress 4.6, y con un array `strpos()` lanza un `TypeError` en PHP 8.

El fallo llevaba años latente y nadie lo veía porque el núcleo lo tapaba: hasta WordPress 6.9,
la consulta de estilos globales salía antes de tiempo cuando el tema activo no traía
`theme.json`, de modo que el filtro nunca recibía un array. WordPress 7.0 elimina ese retorno
temprano y el fallo aparece con cualquier tema clásico.

Solo afecta al escritorio: el archivo que define el filtro no se carga en el front end. Los
datos no cambian y no hay migración. El detalle está en `CHANGELOG.md`.

## Novedades de la 4.2.0

Integra el plugin acompañante **ACF qTranslate** como módulo (`acf/`) y corrige la puerta de
versión que lo apagaba en silencio con ACF 6: solo se activaba con ACF 4 o 5, de modo que con
ACF 6 se desactivaba sin error fatal y sin aviso. Ahora cualquier ACF a partir de la 5 usa la
rama `acf_5`, porque ACF 6 conserva la API de campos de ACF 5.

Los datos no cambian: los siete tipos de campo conservan sus nombres y los valores siguen en
el formato `[:en]...[:]`, así que no hay migración.

Que el puente cargue con ACF 6 no garantiza que su interfaz de admin funcione allí; ese JS se
escribió contra el marcado de ACF 5 y queda pendiente probarlo contra una instalación real de
ACF Pro 6. El detalle está en `CHANGELOG.md`.

## Novedades de la 4.1.0

Integra el plugin acompañante **qTranslate Slug** como módulo (`slug/`). Hasta ahora era un
plugin aparte que dependía de que este se cargase antes; ahora un solo plugin es dueño de todo
el comportamiento de URLs multiidioma.

Los datos no cambian: la meta `_qts_slug_*` y la opción `qts_options` se heredan tal cual.

El cambio desde el plugin suelto es inerte y reversible: la carga va guardada por
`class_exists('QtranslateSlug')`, así que mientras ese plugin siga activo el módulo no se
carga. Al desactivarlo, el módulo toma el relevo. El detalle está en `CHANGELOG.md`, incluido
un aviso importante sobre por qué un `return` temprano no basta para guardar el plugin suelto.

## Novedades de la 4.0.0

Funcionalmente equivalente a 3.4.6.8 más las correcciones siguientes, que llevaban años
aplicadas sin estar registradas.

### Compatibilidad con PHP

| Cambio | Motivo |
|---|---|
| `continue` sustituido por `break` dentro de un `switch`, en `qtranslate_frontend.php` | PHP 7.3 deprecó `continue` apuntando a un `switch`, donde siempre se había comportado como `break`. Emitía un aviso en cada carga. |
| Eliminado el paso por referencia en `qtranxf_postsFilter($posts, &$query)` y `qtranxf_excludeUntranslatedPosts($where, &$query)` | WordPress pasa los argumentos de los filtros por valor. Declararlos por referencia provoca un **error fatal** en PHP 8. |

### Hooks

| Cambio | Motivo |
|---|---|
| Se deja de registrar `qtranxf_widget_init` en `widgets_init` | El widget propio del plugin no se utiliza en la instalación que mantiene este fork. |
| Añadido un `qtranxf_modifyTermFormFor()` vacío | La función **no existe** en ninguna parte de 3.4.6.8, y sin embargo hay temas que la registran en el formulario de alta y edición de taxonomías públicas. Sin el stub, es un error fatal de función no definida. |

### Metadatos

- Renombrado a `qTranslate-X (fork)`, para que WordPress no lo case nunca con la ficha
  cerrada de wordpress.org.
- `GitHub Plugin URI` y `GitHub Branch` apuntan a este fork y a la rama `main`. Si apuntaran
  a upstream y alguien instalase un actualizador desde GitHub, revertiría estos cambios.
- Se conservan `Original Author` y `Upstream Author`: la autoría del trabajo original es de
  Qian Qin y del qTranslate Team.

## Problema de seguridad conocido, sin parche en upstream

Existe un **XSS almacenado** en las versiones hasta la 3.4.6.8 incluida, publicado el
31 de agosto de 2021, sin CVE asignado y **sin corrección oficial**. Este fork **no lo
corrige**.

Para explotarlo hacen falta dos cosas a la vez:

1. una cuenta con rol **administrador**, y
2. que la instalación sea **multisite** o que la capacidad `unfiltered_html` esté
   deshabilitada.

En una instalación de un solo sitio cuyos administradores conservan `unfiltered_html`, el
problema es **inerte**: un administrador ya puede inyectar HTML y JavaScript desde el editor,
de modo que el XSS no le concede nada nuevo. Conviene comprobar ambas condiciones antes de
usar este plugin, y reevaluarlas si alguna cambia.

Si tu instalación es multisite o restringe `unfiltered_html`, **este fork no es seguro para
ti**: migra a qTranslate-XT.

## Despliegue

El plugin se despliega versionado junto al resto del código. **No se debe usar ningún
actualizador automático sobre él**: sobrescribiría los cambios de este fork.

## Relación con upstream

```bash
git remote add upstream https://github.com/qTranslate-Team/qtranslate-x.git
git fetch --tags upstream

git diff 3.4.6.8..main             # la desviación completa de este fork
git log 3.4.6.8..upstream/master   # trabajo de upstream posterior a esta base
```

Upstream dejó 59 commits y un tag `3.4.6.9` posteriores a esta base que nunca llegaron a
WordPress.org. Entre ellos hay un módulo `slugs/` a medio construir, con marcas `//todo` en
el importador y un esquema de base de datos sin decidir. **No conviene partir de ahí.**

## Hoja de ruta

- ~~Absorber el plugin acompañante `qtranslate-slug` como módulo~~ — **hecho en la 4.1.0**.
  Es un empaquetado, no una reescritura: conserva el almacenamiento en la meta `_qts_slug_*`.
  Una integración de verdad usaría el enfoque de qTranslate-XT, que la completó con metadatos
  propios, y no el intento inacabado de upstream con tablas aparte.
- ~~Absorber el plugin acompañante `acf-qtranslate` como módulo~~ — **hecho en la 4.2.0**.
  Copia byte a byte de la 1.7.25 con la puerta de versión corregida para admitir ACF 6. Queda
  pendiente comprobar su interfaz de admin contra una instalación real de ACF Pro 6.
- **Evaluar la migración a qTranslate-XT.** Ya incluye los slugs como módulo integrado y un
  migrador desde los metadatos `_qts_slug_*` del plugin qTranslate Slug. Sigue siendo la
  opción sensata a medio plazo; este fork solo mantiene en pie lo que ya existe.

## Licencia

GPL v2 o posterior, igual que el original. Autoría original de Qian Qin; desarrollo posterior
del qTranslate Team. Este fork lo mantiene Raul Fernandez.
