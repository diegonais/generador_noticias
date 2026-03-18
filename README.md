# Portal Noticias ABI

Portal web simple en PHP puro que consume el RSS oficial de la Agencia Boliviana de Informacion (ABI), procesa las noticias y las almacena localmente en `storage/news.json` o en Supabase para servirlas desde una API liviana y mostrarlas en una interfaz responsive.

## Objetivo

- Obtener noticias del RSS oficial de ABI.
- Normalizar y deduplicar la informacion.
- Guardar el resultado localmente en JSON o en Supabase.
- Exponer las noticias mediante `api/news.php`.
- Mostrar el contenido en `public/index.php`.
- Actualizar la informacion cada 10 minutos con un script ejecutable por cron.

## Configuracion

El archivo `.env` permite ajustar opciones basicas:

```env
APP_NAME="Portal Noticias ABI"
TIMEZONE="America/La_Paz"
ABI_RSS_URL="https://abi.bo/feed/"
MAX_NEWS_ITEMS=60
FOOTER_AUTHOR="Diego"
SUPABASE_ENABLED=false
SUPABASE_URL="https://tu-proyecto.supabase.co"
SUPABASE_SERVICE_ROLE_KEY="tu-service-role-key"
SUPABASE_TABLE="news"
```

Si `SUPABASE_ENABLED=true`, el sistema intentara leer y escribir noticias en Supabase. El archivo `storage/news.json` se mantiene como respaldo local.

## Actualizacion manual de noticias

Para obtener noticias nuevas y regenerar el almacenamiento configurado:

```bash
php scripts/update_news.php
```

## Migracion a Supabase

Con la tabla `news` ya creada y las variables de entorno configuradas, puedes migrar el contenido local actual con:

```bash
php scripts/migrate_news_to_supabase.php
```
