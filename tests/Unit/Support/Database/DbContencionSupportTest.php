<?php

namespace Tests\Unit\Support\Database;

use App\Support\Database\DbContencionSupport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test puro (sin BD ni RefreshDatabase).
 * Solo cubre detección de errores; el path con Log::warning se smokea en CI con Tests\TestCase si hace falta.
 */
class DbContencionSupportTest extends TestCase
{
    public function test_detecta_deadlock_mysql_por_mensaje(): void
    {
        $e = new RuntimeException('Deadlock found when trying to get lock; try restarting transaction');
        $this->assertTrue(DbContencionSupport::esErrorReintentable($e));
    }

    public function test_detecta_codigos_mysql_en_mensaje(): void
    {
        $this->assertTrue(DbContencionSupport::esErrorReintentable(new RuntimeException('SQLSTATE[40001]: 1213')));
        $this->assertTrue(DbContencionSupport::esErrorReintentable(new RuntimeException('Lock wait timeout exceeded; try restarting transaction')));
        $this->assertTrue(DbContencionSupport::esErrorReintentable(new RuntimeException('Error 1205')));
    }

    public function test_detecta_deadlock_y_lock_postgres(): void
    {
        $this->assertTrue(DbContencionSupport::esErrorReintentable(new RuntimeException('ERROR: deadlock detected')));
        $this->assertTrue(DbContencionSupport::esErrorReintentable(new RuntimeException('SQLSTATE[40P01]')));
        $this->assertTrue(DbContencionSupport::esErrorReintentable(new RuntimeException('canceling statement due to lock timeout')));
        $this->assertTrue(DbContencionSupport::esErrorReintentable(new RuntimeException('lock_not_available')));
    }

    public function test_no_reintenta_errores_comunes(): void
    {
        $this->assertFalse(DbContencionSupport::esErrorReintentable(
            new RuntimeException('SQLSTATE[23000]: Integrity constraint violation')
        ));
        $this->assertFalse(DbContencionSupport::esErrorReintentable(
            new RuntimeException('syntax error at or near')
        ));
    }

    public function test_detecta_unicidad_mysql_y_postgres(): void
    {
        $mysql = new RuntimeException(
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1-2-67' for key 'cobranza.empresa_tipo_numero_unique'"
        );
        $pg = new RuntimeException(
            'ERROR: duplicate key value violates unique constraint "cobranza_empresa_tipo_numero_unique"'
        );

        $this->assertTrue(DbContencionSupport::esViolacionUnicidad($mysql));
        $this->assertTrue(DbContencionSupport::esViolacionUnicidad($mysql, 'empresa_tipo_numero_unique', 'otra'));
        $this->assertTrue(DbContencionSupport::esViolacionUnicidad($pg, 'cobranza'));
        $this->assertFalse(DbContencionSupport::esViolacionUnicidad($mysql, 'venta_puntoventa_numerocomprobante_unique'));
        $this->assertFalse(DbContencionSupport::esViolacionUnicidad(
            new RuntimeException('syntax error at or near')
        ));
    }

    public function test_detecta_fk_mysql_y_postgres(): void
    {
        $mysql = new RuntimeException(
            'Cannot add or update a child row: a foreign key constraint fails (`anitaERP`.`jornada`, CONSTRAINT `jornada_empresa_id_foreign` FOREIGN KEY (`empresa_id`))'
        );
        $pg = new RuntimeException(
            'insert or update on table "jornada_estacionamiento" violates foreign key constraint "jornada_estacionamiento_empresa_id_foreign"'
        );

        $this->assertTrue(DbContencionSupport::esViolacionClaveForanea($mysql));
        $this->assertTrue(DbContencionSupport::esViolacionClaveForanea($mysql, 'empresa_id'));
        $this->assertTrue(DbContencionSupport::esViolacionClaveForanea($pg, 'empresa_id'));
        $this->assertFalse(DbContencionSupport::esViolacionClaveForanea($pg, 'usuario_apertura_id'));
        $this->assertFalse(DbContencionSupport::esViolacionClaveForanea(
            new RuntimeException('Duplicate entry \'1\' for key \'primary\'')
        ));
    }
}
