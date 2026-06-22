# Logs rotados (AnitaERP)

## Archivos

| Log | Origen |
|-----|--------|
| `storage/logs/schedule.log` | Cron `php artisan schedule:run` (usuario `www-data`) |
| `storage/logs/queue-worker.log` | Supervisor `anitaERP-queue` |
| `storage/logs/queue-verificar-pico.log` | Schedule `queue:verificar-pico` (hora pico) |

## Instalación logrotate

```bash
chmod +x deploy/logrotate/aplicar-logrotate.sh
./deploy/logrotate/aplicar-logrotate.sh
```

Política: **diaria**, **14** archivos, **gzip** (`delaycompress`). Se usa `copytruncate` para no cortar al worker ni al cron.

## Cron (www-data)

El crontab debe seguir escribiendo al mismo path (logrotate rota el archivo):

```cron
* * * * * cd /var/www/html/anitaERP && /usr/bin/php artisan schedule:run >> /var/www/html/anitaERP/storage/logs/schedule.log 2>&1
```

## Verificación

```bash
sudo logrotate -f /etc/logrotate.d/anitaERP
ls -la /var/www/html/anitaERP/storage/logs/schedule.log*
ls -la /var/www/html/anitaERP/storage/logs/queue-worker.log*
```

## Forzar rotación de prueba (dry-run)

```bash
sudo logrotate -d /etc/logrotate.d/anitaERP
```
