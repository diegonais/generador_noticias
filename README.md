# Portal Noticias ABI

Portal web en PHP 8.2 que consume el RSS oficial de la Agencia Boliviana de Informacion (ABI), normaliza y deduplica noticias, las almacena en JSON local o en Supabase y las expone mediante una API liviana y una interfaz responsive.

## Estructura

```text
api/
  news.php
bin/
  update-news.php
  migrate-news-to-supabase.php
bootstrap/
  app.php
  autoload.php
public/
  index.php
  news.php
  assets/
scripts/
  update_news.php
  migrate_news_to_supabase.php
src/
  News/
    Application/
    Domain/
    Infrastructure/
  Shared/
    Config/
    Http/
    Infrastructure/
    Support/
storage/
```

## Criterio de arquitectura

- `Application`: casos de uso orquestados, sin detalles de infraestructura.
- `Domain`: contratos y entidad `NewsItem`.
- `Infrastructure`: RSS ABI, persistencia JSON/Supabase, cache de paginas y logging.
- `Shared`: configuracion, HTTP y utilidades transversales.
- `bootstrap/`: composicion de dependencias y autoload.
- `bin/`: comandos principales del proyecto.
- `scripts/`: wrappers de compatibilidad para no romper flujos existentes.

## Configuracion

El archivo `.env` permite ajustar opciones basicas:

```env
APP_NAME="Portal Noticias ABI"
APP_PUBLIC_URL="https://tu-dominio.com"
TIMEZONE="America/La_Paz"
ABI_RSS_URL="https://abi.bo/feed/"
MAX_NEWS_ITEMS=60
FOOTER_AUTHOR="Diego"
SUPABASE_ENABLED=false
SUPABASE_URL="https://tu-proyecto.supabase.co"
SUPABASE_SERVICE_ROLE_KEY="tu-service-role-key"
SUPABASE_TABLE="news"
INGEST_TOKEN="token-largo-para-cron"
```

`APP_PUBLIC_URL` debe apuntar a la URL publica real del portal, preferiblemente con HTTPS. Es importante para que Facebook, WhatsApp, X y otros servicios puedan leer correctamente los metadatos Open Graph y la imagen de cada noticia al compartir enlaces.

Si `SUPABASE_ENABLED=true`, el sistema intentara leer desde Supabase y mantener `storage/news.json` como respaldo local sincronizado.

## Actualizacion manual de noticias

```bash
php bin/update-news.php
```

Tambien se conserva el wrapper anterior:

```bash
php scripts/update_news.php
```

## Ingesta automatica en Render

Para que la ingesta no dependa de que el proyecto este corriendo en tu maquina, crea un servicio separado de tipo **Cron Job** en Render apuntando a este mismo repositorio.

Configuracion recomendada:

- Runtime: Docker
- Schedule: `*/10 * * * *`
- Command / Docker Command: `php bin/update-news.php`
- Environment variables: las mismas que usa el Web Service, especialmente `SUPABASE_ENABLED=true`, `SUPABASE_URL`, `SUPABASE_SERVICE_ROLE_KEY`, `SUPABASE_TABLE`, `ABI_RSS_URL`, `MAX_NEWS_ITEMS` y `TIMEZONE`.

El Web Service puede seguir mostrando el portal, pero la responsabilidad de insertar noticias nuevas queda en el Cron Job de Render. Esto es mas confiable que depender del cron interno del contenedor web, porque los Web Services gratuitos de Render pueden apagarse cuando no reciben trafico.

## Ingesta automatica con Supabase Cron

El proyecto expone un endpoint protegido para ejecutar la ingesta desde un programador externo:

```text
POST /api/update-news.php
Authorization: Bearer <INGEST_TOKEN>
```

Configura `INGEST_TOKEN` en Render con un valor largo y secreto. El endpoint rechaza peticiones sin token, con token incorrecto o con un metodo distinto de `POST`.

En Supabase, habilita `pg_cron` y `pg_net`, y programa una llamada cada 10 minutos:

```sql
select cron.schedule(
  'ingest-news-every-10-minutes',
  '3,13,23,33,43,53 * * * *',
  $$
  select net.http_post(
    url := 'https://tu-app.onrender.com/api/update-news.php',
    headers := jsonb_build_object(
      'Authorization', 'Bearer TU_INGEST_TOKEN',
      'Content-Type', 'application/json'
    ),
    body := '{}'::jsonb
  );
  $$
);
```

Reemplaza `https://tu-app.onrender.com` por la URL publica real de Render y `TU_INGEST_TOKEN` por el mismo valor configurado en Render. Para produccion, guarda ese token en Supabase Vault y referencialo desde el job en lugar de dejarlo escrito en SQL.

## Ingesta manual con GitHub Actions

El proyecto tambien incluye un workflow en `.github/workflows/update-news.yml` como respaldo manual para ejecutar la ingesta desde GitHub Actions.

Configura estos secrets en GitHub, dentro de **Settings > Secrets and variables > Actions**:

- `SUPABASE_URL`
- `SUPABASE_SERVICE_ROLE_KEY`
- `SUPABASE_TABLE` opcional; si no existe, se usa `news`

El workflow tambien se puede ejecutar manualmente desde la pestana **Actions** con **Run workflow**.

## Monitoreo de logs

Cada sincronizacion escribe en `storage/logs/update.log` el estado del RSS de ABI, la lectura/guardado en Supabase y el respaldo JSON local.

Para ver el log en vivo en Windows/PowerShell:

```powershell
Get-Content .\storage\logs\update.log -Tail 80 -Wait
```

Si la aplicacion esta corriendo en Docker:

```bash
docker exec -it portal-noticias-abi sh -lc "tail -f storage/logs/update.log"
```

Para probar una sincronizacion manual dentro del contenedor:

```bash
docker exec -it portal-noticias-abi php bin/update-news.php
```

Una ejecucion correcta deberia mostrar pasos similares a:

```text
[11/05/2026, 10:15:00 AM] INFO: Solicitando feed RSS de ABI.
[11/05/2026, 10:15:01 AM] INFO: Feed RSS de ABI recibido correctamente. {"context":{"obtenidas":10}}
[11/05/2026, 10:15:02 AM] INFO: Guardado en Supabase completado. {"context":{"items":60}}
[11/05/2026, 10:15:02 AM] INFO: Guardado en JSON local completado. {"context":{"items":60}}
[11/05/2026, 10:15:02 AM] INFO: Resumen de sincronizacion ABI {"context":{"status":"SUCCEEDED","obtained":10,"new":0}}
```

## Migracion a Supabase

```bash
php bin/migrate-news-to-supabase.php
```

Wrapper compatible:

```bash
php scripts/migrate_news_to_supabase.php
```

## SOLID aplicado

- Responsabilidad unica: la extraccion RSS, la persistencia, el logging y los casos de uso quedaron separados.
- Abierto/cerrado: se pueden agregar nuevas fuentes o repositorios implementando contratos del dominio.
- Inversion de dependencias: los casos de uso dependen de interfaces, no de implementaciones concretas.
- Presentacion limpia: los entrypoints (`public`, `api`, `bin`) solo coordinan, no contienen logica de negocio.

