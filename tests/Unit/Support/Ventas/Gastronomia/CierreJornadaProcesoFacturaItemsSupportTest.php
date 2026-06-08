<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAsientosPreviewSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoClasificacionSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoFacturaItemsSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoMedioSupport;
use ReflectionMethod;
use Tests\TestCase;

final class CierreJornadaProcesoFacturaItemsSupportTest extends TestCase
{
    public function test_escalar_precios_cuadra_con_total_facturable_post_redistribucion(): void
    {
        $method = new ReflectionMethod(CierreJornadaProcesoFacturaItemsSupport::class, 'escalarPreciosAlTotal');
        $method->setAccessible(true);

        /** @var array{0:list<float>,1:list<float>} $result */
        $result = $method->invoke(null, [1, 2], [2., 1.], [100., 100.], 600.);
        [$precios, $cantidades] = $result;

        $suma = 0.;
        for ($i = 0; $i < count($precios); $i++) {
            $suma += round($cantidades[$i] * $precios[$i], 2);
        }

        $this->assertSame(600., round($suma, 2));
    }

    public function test_total_facturable_suma_total_completo_comandas_atomicas(): void
    {
        $movimientos = [[
            'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
            'total' => 1000.,
            'medios_pago_planificados' => [
                ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR, 'monto' => 400.],
                ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_MP, 'monto' => 200.],
                ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => 400.],
            ],
        ]];

        $this->assertSame(
            1000.,
            CierreJornadaProcesoAsientosPreviewSupport::totalQrFacturaProceso($movimientos),
        );
    }
}
