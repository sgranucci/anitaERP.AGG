<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\CotFacturaEmitidaSupport;
use PHPUnit\Framework\TestCase;

final class CotFacturaEmitidaSupportTest extends TestCase
{
    public function test_clave_ignora_identidad_de_remito(): void
    {
        $this->assertSame('', CotFacturaEmitidaSupport::clave([
            'tipo' => 'REM',
            'letra' => 'R',
            'sucursal' => 1,
            'numero' => 905925,
        ]));
    }

    public function test_clave_desde_fila_anita_comprob(): void
    {
        $fila = [
            'origen' => 'anita_comprob',
            'factura_identidad' => [
                'tipo' => 'FAC',
                'letra' => 'A',
                'sucursal' => 10,
                'numero' => 7451,
            ],
        ];

        $this->assertSame('FAC|A|10|7451', CotFacturaEmitidaSupport::claveDesdeFila($fila));
    }

    public function test_prioridad_preferir_erp_sobre_anita(): void
    {
        $this->assertLessThan(
            CotFacturaEmitidaSupport::prioridadOrigen(['origen' => 'anita_comprob']),
            CotFacturaEmitidaSupport::prioridadOrigen(['origen' => 'erp'])
        );
        $this->assertLessThan(
            CotFacturaEmitidaSupport::prioridadOrigen(['origen' => 'anita_pendmae']),
            CotFacturaEmitidaSupport::prioridadOrigen(['origen' => 'anita_comprob'])
        );
    }

    public function test_etiqueta_factura(): void
    {
        $this->assertSame(
            'FAC A-0010-00007451',
            CotFacturaEmitidaSupport::etiqueta([
                'tipo' => 'FAC',
                'letra' => 'A',
                'sucursal' => 10,
                'numero' => 7451,
            ])
        );
    }
}
