<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\GastronomiaMovimientoStockSupport as S;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class GastronomiaMovimientoStockSupportMensajeErrorEmisionTest extends TestCase
{
    public function test_invalid_argument_se_devuelve_tal_cual(): void
    {
        $e = new InvalidArgumentException('Configure el tipo de transacción (factura) en la configuración del PV gastronomía.');
        self::assertSame(
            'Configure el tipo de transacción (factura) en la configuración del PV gastronomía.',
            S::mensajeErrorEmision($e)
        );
    }

    public function test_invalid_argument_vacio_devuelve_fallback(): void
    {
        $e = new InvalidArgumentException('');
        self::assertStringContainsString('validación interna falló', S::mensajeErrorEmision($e));
    }

    public function test_excepcion_de_datos_se_formatea_para_operador(): void
    {
        $e = new RuntimeException('WSFE — FECAESolicitar: 10015 Falta dato obligatorio: DocTipo');
        $msg = S::mensajeErrorEmision($e);

        self::assertStringContainsString('ARCA rechazó el comprobante por datos inválidos', $msg);
        self::assertStringContainsString('10015', $msg);
        self::assertStringContainsString('Reintentar con CAEA no corrige', $msg);
    }

    public function test_excepcion_de_sistema_se_formatea_para_operador(): void
    {
        $e = new RuntimeException("SOAP-ERROR: Encoding: object has no 'Id' property");
        $msg = S::mensajeErrorEmision($e);

        self::assertStringContainsString('Error interno del sistema', $msg);
        self::assertStringContainsString('soporte técnico', $msg);
    }

    public function test_lock_wait_timeout_se_formatea_como_contencion(): void
    {
        $e = new RuntimeException(
            'SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction'
        );
        $msg = S::mensajeErrorEmision($e);

        self::assertStringContainsString('bloqueo en base de datos', $msg);
        self::assertStringContainsString('Espere unos segundos', $msg);
        self::assertStringNotContainsString('Error interno del sistema al generar el comprobante', $msg);
    }

    public function test_excepcion_de_transporte_se_formatea_con_contexto(): void
    {
        $e = new RuntimeException('Could not connect to host');
        $msg = S::mensajeErrorEmision($e, [
            'intento_caea' => false,
            'reintento_caea_habilitado' => true,
        ]);

        self::assertStringContainsString('no hubo respuesta clara de ARCA', $msg);
        self::assertStringContainsString('reintentó automáticamente con CAEA', $msg);
    }

    public function test_excepcion_durante_reintento_caea_marca_contexto(): void
    {
        $e = new RuntimeException('Could not connect to host');
        $msg = S::mensajeErrorEmision($e, ['intento_caea' => true]);

        self::assertStringContainsString('tampoco hubo respuesta clara al reintento con CAEA', $msg);
    }
}
