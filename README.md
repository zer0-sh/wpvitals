# WordPress Health & Operations Platform

![Banner](docs/assets/WPVitals.png)

## ¿Qué es?

Una herramienta Open Source para evaluar de forma integral el estado de una instalación WordPress.

## ¿Por qué existe?

Para ofrecer una plataforma unificada que responda: **¿Está mi WordPress actualizado, soportado, seguro, correctamente configurado, disponible y funcionando correctamente?** Priorizando la utilidad real, la privacidad y la simplicidad.

## ¿Qué comprueba?

Esta plataforma comprobará aspectos clave de tu instalación WordPress, incluyendo:

*   Versión de WordPress, plugins y temas, y sus actualizaciones disponibles.
*   Vulnerabilidades conocidas en componentes (WordPress, plugins, temas).
*   Versión de PHP y su estado de soporte.
*   Configuración básica de seguridad (HTTPS, XML-RPC, debug mode).
*   Aspectos de salud general y fiabilidad.

## ¿Cómo instalarlo?

### Entorno Local de Desarrollo (Docker)

Para iniciar el entorno local con Docker Compose:

```bash
cd docker
docker compose up -d
```

WordPress estará disponible en `http://localhost:8080`. El plugin se monta automáticamente en `wp-content/plugins/wpvitals`.

#### Procedimiento para detener el entorno local:
```bash
cd docker
docker compose down
```

#### Procedimiento para reiniciar el entorno local:
```bash
cd docker
docker compose restart
```

## Calidad y herramientas de desarrollo

Las dependencias de desarrollo (PHPUnit, PHPCS y WordPress Coding Standards) se gestionan con Composer a través del contenedor `composer`:

```bash
# Instalar/actualizar dependencias
make composer-install

# Verificar estándares de código (PHPCS)
make lint

# Corregir automáticamente errores de formato (PHPCBF)
make lint:fix

# Ejecutar tests unitarios (PHPUnit)
make test
```

Alternativamente, los mismos comandos se pueden ejecutar dentro de `docker/` con Docker Compose:

```bash
docker compose run --rm composer install
docker compose run --rm composer run lint
docker compose run --rm composer run test
```

El estándar de código está configurado en `phpcs.xml.dist` (WordPress Coding Standards, excluyendo `vendor/`, `docker/` y `tests/`) y la base de PHPUnit en `phpunit.xml.dist`.

## ¿Cómo contribuir?

*(Guía de contribución detallada se añadirá aquí en el futuro.)*

