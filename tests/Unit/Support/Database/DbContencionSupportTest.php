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
}
