-- Privilegios para aplicar binlog (PITR) en BD de prueba del .211.
-- Ejecutar una sola vez en 10.20.30.211 como root:
--   sudo mysql < deploy/backup/grant-binlog-applier-211.sql
--
-- Permite SET @@SESSION.* y eventos ROW del mysqlbinlog sin dar SUPER global.

GRANT REPLICATION_APPLIER, SESSION_VARIABLES_ADMIN ON *.* TO 'anitaERP'@'localhost';
FLUSH PRIVILEGES;
