-- Privilegios para backup-binlog-snapshot.sh y backup-binlog-copy-root.sh (MariaDB 10.5+).
-- Ejecutar como root una sola vez:
--   sudo mysql < deploy/backup/grant-binlog-monitor.sql
--
-- Permite SHOW MASTER STATUS y SHOW BINARY LOGS (no acceso a datos de negocio extra).

GRANT BINLOG MONITOR ON *.* TO 'anitaERP'@'localhost';
FLUSH PRIVILEGES;
