<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\IngresoEgresoListadoMontoSupport;
use PHPUnit\Framework\TestCase;

class IngresoEgresoListadoMontoSupportTest extends TestCase
{
    public function test_canje_muestra_monto_de_una_pata(): void
    {
        $anulado = (object) ['numerocheque' => '79030174'];
        $reemplazo = (object) [
            'cheque_reemplaza_id' => 34016,
            'origen' => 'E',
            'moneda_id' => 1,
            'cotizacion' => 1,
            'monto' => 729138.10,
            'numerocheque' => '79030503',
            'cuentacajas' => (object) ['nombre' => 'BCO.MACRO GERLI'],
            'chequeReemplazado' => $anulado,
        ];
        $mov = (object) [
            'caja_movimiento_cuentacajas' => [],
            'cheques' => [$reemplazo],
        ];

        $res = IngresoEgresoListadoMontoSupport::resumen($mov);

        $this->assertSame(729138.10, $res['monto']);
        $this->assertNotEmpty($res['lineas']);
        $this->assertStringContainsString('79030174', $res['lineas'][0]);
        $this->assertStringContainsString('79030503', $res['lineas'][0]);
    }

    public function test_cheque_emitido_normal_sigue_contando(): void
    {
        $cheque = (object) [
            'cheque_reemplaza_id' => null,
            'origen' => 'E',
            'moneda_id' => 1,
            'cotizacion' => 1,
            'monto' => 1000.0,
            'numerocheque' => '1',
            'cuentacajas' => (object) ['nombre' => 'Banco'],
        ];
        $mov = (object) [
            'caja_movimiento_cuentacajas' => [],
            'cheques' => [$cheque],
        ];

        $res = IngresoEgresoListadoMontoSupport::resumen($mov);
        $this->assertSame(1000.0, $res['monto']);
    }
}
