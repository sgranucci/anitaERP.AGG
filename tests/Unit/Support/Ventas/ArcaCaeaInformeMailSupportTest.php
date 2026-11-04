<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\ArcaCaeaInformeMailSupport;
use PHPUnit\Framework\TestCase;

final class ArcaCaeaInformeMailSupportTest extends TestCase
{
    public function test_timeout_del_worker_no_se_presenta_como_error_arca(): void
    {
        $freno = ArcaCaeaInformeMailSupport::resolverFreno(
            [
                'fallo_worker' => true,
                'errores_lote' => 0,
                'informados' => 0,
            ],
            [],
            'El proceso superó el tiempo máximo del worker y se cortó. No es un rechazo de ARCA.',
        );

        self::assertNull($freno);
    }

    public function test_error_de_lote_conserva_punto_de_venta_y_numero(): void
    {
        $freno = ArcaCaeaInformeMailSupport::resolverFreno(
            [
                'errores_lote' => 1,
                'errores_muestra' => [[
                    'pto_vta' => 34,
                    'numero' => 606573,
                    'codigo' => '10016',
                    'mensaje' => 'Comprobante rechazado',
                ]],
            ],
        );

        self::assertNotNull($freno);
        self::assertSame('PV 00034 #606573', $freno['etiqueta']);
        self::assertSame('10016', $freno['codigo_error']);
    }
}
