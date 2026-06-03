# Restore de backup anitaERP

## ¿Dónde restaurar?

| Entorno | Servidor | Uso |
|---------|----------|-----|
| **Producción** | `.210` → BD `anitaERP` | Solo emergencia, con ventana de mantenimiento |
| **Prueba / verificación** | **`.211`** → BD `anitaERP_test` | **Recomendado** — no toca producción del .210 |

El `.211` es réplica del `.210`. Los backups diarios (06:00 y 18:00) se copian por rsync a:

`/var/www/html/anitaERP/backups/` en el **.211**.

La prueba mensual de restore debe hacerse en **`anitaERP_test` en el .211**, no en el .210.

---

## Restore de prueba en .211 (`anitaERP_test`)

Conectado al **10.20.30.211** como `sergio`:

```bash
DUMP="/var/www/html/anitaERP/backups/anitaERP_YYYYMMDD_HHMMSS.sql.gz"   # último dump
DB_TEST="anitaERP_test"

mysql -u sergio -p -e "DROP DATABASE IF EXISTS \`${DB_TEST}\`; CREATE DATABASE \`${DB_TEST}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"

gunzip -c "${DUMP}" | sed 's/`anitaERP`/`'"${DB_TEST}"'`/g' | mysql -u sergio -p

mysql -u sergio -p "${DB_TEST}" -e "
  SELECT COUNT(*) proveedores FROM proveedor;
  SELECT COUNT(*) ventas FROM venta;
  SELECT MAX(created_at) ultima_venta FROM venta;
  SELECT url FROM menu WHERE nombre LIKE '%Gastronom%' OR url LIKE '%gastronom%' LIMIT 5;
"
```

Si el dump ya trae `USE anitaERP` y `CREATE DATABASE`, el `sed` reemplaza nombres para no pisar la réplica en producción (`anitaERP` en .211).

**Validación mínima:** conteos similares al dump del .210 del mismo horario, última `venta.created_at` coherente, menús `ventas/*` en gastronomía.

```bash
# Al terminar (opcional)
mysql -u sergio -p -e "DROP DATABASE IF EXISTS \`${DB_TEST}\`;"
```

---

## Restore de emergencia en .210 (producción)

Solo si hay acuerdo de detener la aplicación:

```bash
DUMP="/var/www/html/anitaERP/backups/anitaERP_YYYYMMDD_HHMMSS.sql.gz"

# 1) Backup de seguridad del estado actual
mysqldump -u sergio -p --single-transaction anitaERP | gzip > /tmp/anitaERP_antes_restore_$(date +%Y%m%d_%H%M).sql.gz

# 2) Restaurar
mysql -u sergio -p -e "DROP DATABASE anitaERP; CREATE DATABASE anitaERP CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
gunzip -c "${DUMP}" | mysql -u sergio -p

# 3) Laravel
cd /var/www/html/anitaERP && php artisan cache:clear
```

---

## Recuperación entre backups (PITR con binlog)

Si hay dump de las 06:00 y fallo a las 15:00:

1. Restaurar el último dump **anterior** al incidente.
2. Usar snapshot en `backups/binlog/binlog_snapshot_*.txt` (columnas `File` / `Position` de `SHOW BINARY LOG STATUS`).
3. Aplicar binlogs copiados (`backups/binlog/binlog.0000xx`) con `mysqlbinlog`:

```bash
mysqlbinlog --start-position=POSICION binlog.0000XX binlog.0000YY ... | mysql -u sergio -p anitaERP
```

Requiere que `backup-binlog-copy-root.sh` esté activo (sudoers) o binlogs en el .211.

---

## Cron en .210

```
0 6,18 * * * /var/www/html/anitaERP/deploy/backup/backup-db.sh >> /var/www/html/anitaERP/storage/logs/backup-db.log 2>&1
```

## Checklist post-incidente

- [ ] `./setup-ssh-replica.sh` ejecutado (sync .211)
- [ ] sudoers binlog instalado (opcional, copia archivos binlog)
- [ ] Restore de prueba en `anitaERP_test` @ .211
- [ ] Revisar `storage/logs/backup-db.log` tras primera corrida 18:00
