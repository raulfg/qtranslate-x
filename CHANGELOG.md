# Registro de cambios

Este plugin es un fork de [qTranslate-X](https://github.com/qTranslate-Team/qtranslate-x)
3.4.6.8, cuyo desarrollo original se detuvo el 23 de junio de 2016 y cuya ficha de
WordPress.org se cerró el 31 de agosto de 2021.

Lo mantiene Raul Fernandez por motivos técnicos: conservar en funcionamiento una instalación
heredada sobre versiones modernas de PHP. **No es recomendable usarlo en producción en
proyectos nuevos**; ver las advertencias en `readme.md`.

Las versiones a partir de la 4.0.0 son de este fork; todo número inferior pertenece a
upstream y está documentado en `changelog-qtranslate-x.md`.

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
