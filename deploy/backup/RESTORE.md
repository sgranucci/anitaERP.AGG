# Restore de backup anitaERP

## Por servidor

| Instalación | Servidor | Sync remoto | Log backup |
|-------------|----------|-------------|------------|
| **AGG** | `.210` (prod) → `.211` (réplica) | Sí, rsync al `.211` | `storage/logs/backup-db.log` (ver `backup.local.conf.example`) |
| **INTERFORMING** | `server9` (u otro) | No | `backups/backup-db.log` |

La config base (`backup.conf`) apunta a INTERFORMING / servidores sin réplica. AGG sobreescribe con `backup.local.conf` (copiar desde `backup.local.conf.example`).

---

## Configuración inicial

El cron `backup-db.sh` corre como **`sergio`** y necesita:

1. **`~/.my.cnf`** — credenciales MySQL (`chmod 600`):

```bash
cd /var/www/html/anitaERP
chmod +x deploy/backup/setup-mycnf.sh deploy/backup/backup-db.sh
./deploy/backup/setup-mycnf.sh
```

2. **Directorio de backups** — el script lo crea solo; debe poder escribir en `/var/www/html/anitaERP/backups/`.

3. **Log** — por defecto va a `backups/backup-db.log` (no a `storage/logs/`, que es de `www-data` y sergio no puede escribir ahí).

4. **Cron** (sin redirección; el script escribe el log):

```cron
0 6,18 * * * /var/www/html/anitaERP/deploy/backup/backup-db.sh
```

Probar:

```bash
./deploy/backup/backup-db.sh
tail -20 backups/backup-db.log
ls -lh backups/anitaERP_*.sql.gz | tail -3
```

### Solo AGG (.210)

```bash
cp deploy/backup/backup.local.conf.example deploy/backup/backup.local.conf
# Editar: REMOTE_SYNC_ENABLED=1, REMOTE_HOST=10.20.30.211, MIN_BACKUP_BYTES=50000000
./deploy/backup/setup-ssh-replica.sh
```

---

## ¿Dónde restaurar? (AGG)

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
2. Usar snapshot en `backups/binlog/binlog_snapshot_*.txt` (columnas `File` / `Position` de `SHOW MASTER STATUS`).
3. Aplicar binlogs copiados (`backups/binlog/binlog.0000xx`) con `mysqlbinlog`:

```bash
mysqlbinlog --start-position=POSICION binlog.0000XX binlog.0000YY ... | mysql -u sergio -p anitaERP
```

Requiere binary log activo y `backup-binlog-copy-root.sh` con sudoers (opcional).

---

## Habilitar binary log (MariaDB / MySQL)

En **server9 (INTERFORMING)** hoy `log_bin = OFF`. Sin binary log no hay snapshots PITR ni copia de binlogs en el backup.

### 1. Archivo de configuración

En Debian/MariaDB conviene un drop-in (no editar `50-server.cnf` a mano):

```bash
cd /var/www/html/anitaERP
sudo cp deploy/backup/mariadb-binlog.cnf.example /etc/mysql/mariadb.conf.d/99-anitaERP-binlog.cnf
sudo mkdir -p /var/log/mysql
sudo chown mysql:mysql /var/log/mysql
sudo chmod 750 /var/log/mysql
```

Plantilla incluida: `deploy/backup/mariadb-binlog.cnf.example` (`server-id`, `log_bin`, retención 7 días).

### 2. Reiniciar MariaDB

**Ventana breve de corte** (segundos). Avisar si hay usuarios conectados.

```bash
sudo systemctl restart mariadb
sudo systemctl status mariadb
```

### 3. Verificar

```bash
mysql -e "SHOW VARIABLES LIKE 'log_bin';"
mysql -e "SHOW MASTER STATUS;"
mysql -e "SHOW BINARY LOGS;"
```

`ls /var/log/mysql/` da **permiso denegado** para `sergio`: es normal (`750`, dueño `mysql`). Verificá con los comandos SQL arriba, o como root: `sudo ls -la /var/log/mysql/`.

Si `SHOW MASTER STATUS` responde *Access denied* / *BINLOG MONITOR*:

```bash
sudo mysql < deploy/backup/grant-binlog-monitor.sql
```

Debe mostrar `log_bin = ON` y al menos un archivo en `SHOW BINARY LOGS` (p. ej. `mariadb-bin.000001`).

### 4. Probar con el backup

```bash
./deploy/backup/backup-db.sh
ls -la backups/binlog/
cat backups/binlog/binlog_snapshot_*.txt | tail -20
```

### 5. (Opcional) Copiar binlogs cerrados al backup

Los archivos `.00000N` los crea `mysql`; `sergio` no puede leerlos sin sudo. Instalar sudoers:

```bash
sudo cp deploy/backup/sudoers.anitaERP-backup-binlog.example /etc/sudoers.d/anitaERP-backup-binlog
sudo chmod 440 /etc/sudoers.d/anitaERP-backup-binlog
sudo visudo -c
```

### Notas

- Solo se registran **cambios posteriores** al activar el log; no reemplaza el dump `.sql.gz`.
- **Espacio en disco**: los binlogs crecen con cada INSERT/UPDATE/DELETE; la retención (7 días) limita el tamaño.
- **MySQL 8** (no MariaDB): misma idea; archivo en `/etc/mysql/mysql.conf.d/` y opción `log_bin = /var/log/mysql/mysql-bin`.
- Si el binlog ya estaba comentado en `50-server.cnf` (`#log_bin = ...`), el drop-in `99-anitaERP-binlog.cnf` es suficiente; no hace falta descomentar el otro.

---

## Cron en .210 (AGG)

```cron
0 6,18 * * * /var/www/html/anitaERP/deploy/backup/backup-db.sh
```

Log en AGG: `storage/logs/backup-db.log` si está definido en `backup.local.conf`; si no, `backups/backup-db.log`.

## Checklist post-incidente

- [ ] `./setup-ssh-replica.sh` ejecutado (sync .211)
- [ ] sudoers binlog instalado (opcional, copia archivos binlog)
- [ ] Restore de prueba en `anitaERP_test` @ .211
- [ ] Revisar `storage/logs/backup-db.log` tras primera corrida 18:00
