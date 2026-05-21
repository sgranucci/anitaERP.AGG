# Colas (queues) en AnitaERP

## Configuración (.env)

```env
QUEUE_CONNECTION=database
```

Con `sync`, los jobs se ejecutan en el mismo request (no hay cola real). Con `database`, los jobs quedan en la tabla `jobs` hasta que un worker los procese.

Tablas requeridas (migraciones ya incluidas): `jobs`, `failed_jobs`.

## Producción — Supervisor (recomendado)

Ejecutar como usuario con sudo:

```bash
chmod +x deploy/queue/aplicar-supervisor.sh
./deploy/queue/aplicar-supervisor.sh
```

Comandos útiles:

```bash
sudo supervisorctl status anitaERP-queue
sudo supervisorctl restart anitaERP-queue
tail -f storage/logs/queue-worker.log
```

El worker corre como `www-data` (mismo usuario que Apache).

## Desarrollo — script manual

```bash
chmod +x deploy/queue/queue-worker.sh
./deploy/queue/queue-worker.sh start
./deploy/queue/queue-worker.sh status
./deploy/queue/queue-worker.sh stop
```

## Logs rotados

`schedule.log` y `queue-worker.log` rotan con logrotate (ver [logs.md](logs.md)):

```bash
chmod +x deploy/logrotate/aplicar-logrotate.sh
./deploy/logrotate/aplicar-logrotate.sh
```

## Verificación

```bash
php artisan config:show queue.default
# debe mostrar: database

php artisan queue:monitor database:default
```

Jobs fallidos: tabla `failed_jobs` o `php artisan queue:failed`.

## Waitry

Los reintentos de comanda usan la cola `default` (`WAITRY_COLA` en config). Sin worker activo, el **primer envío al facturar sigue siendo síncrono**; solo los reintentos quedan pendientes en `jobs`.
