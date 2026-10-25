# qTranslate-X (fork)

Fork de [qTranslate-X](https://github.com/qTranslate-Team/qtranslate-x) 3.4.6.8, mantenido
por **Raul Fernandez** por motivos técnicos.

Da soporte multiidioma a contenido dinámico de WordPress guardando todas las traducciones
en el mismo campo del post, en lugar de duplicar entradas o sitios por idioma.

## No recomendado para proyectos nuevos

**Si estás empezando un proyecto, no uses este fork.**

Existe con un objetivo acotado: mantener en funcionamiento una instalación heredada sobre
versiones modernas de PHP, sin reescribir su capa multiidioma. No es un plugin en desarrollo
activo, no añade funcionalidad, no sigue la evolución de WordPress y arrastra un problema de
seguridad sin parchear (ver más abajo).

Para un proyecto nuevo, o para cualquier instalación que pueda migrar, la opción sensata es
[**qTranslate-XT**](https://github.com/qtranslate/qtranslate-xt): es el sucesor mantenido por
la comunidad, integra como módulos los plugins acompañantes, declara compatibilidad con
versiones recientes de WordPress y requiere PHP 7.4 o superior.

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

- **Absorber el plugin acompañante `qtranslate-slug` como módulo**, para que un solo plugin
  sea dueño de todo el comportamiento de URLs multiidioma. Como referencia de diseño conviene
  mirar cómo lo resolvió qTranslate-XT, que completó esa integración usando metadatos, y no
  el intento inacabado de upstream con una tabla propia.
- **Evaluar la migración a qTranslate-XT.** Ya incluye los slugs como módulo integrado y un
  migrador desde los metadatos `_qts_slug_*` del plugin qTranslate Slug.

## Licencia

GPL v2 o posterior, igual que el original. Autoría original de Qian Qin; desarrollo posterior
del qTranslate Team. Este fork lo mantiene Raul Fernandez.
