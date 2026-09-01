<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecepcionProveedorReporteCacheSupport;
use Tests\TestCase;

class RecepcionProveedorReporteCacheSupportTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $filtrosBase = [
        'empresa_ids' => [1],
        'fecha_desde' => '2025-01-01',
        'fecha_hasta' => '2025-01-31',
        'modo' => 'detalle',
        'orden' => 'fecha',
        'facturacion' => 'todas',
        'tipo' => 'todas',
        'estado' => 'CONFIRMADA',
        'consolidar_empresas' => true,
        'solo_diferencias' => false,
        'solo_rechazadas' => false,
        'proveedor' => '',
        'sku' => '',
        'deposito' => '',
    ];

    protected function tearDown(): void
    {
        RecepcionProveedorReporteCacheSupport::limpiar($this->filtrosBase);
        parent::tearDown();
    }

    public function test_guarda_y_recupera_sin_releer_una_copia_extra_en_el_caller(): void
    {
        $filtros = $this->filtrosBase;

        RecepcionProveedorReporteCacheSupport::guardar($filtros, [
            'filas' => collect([
                ['tipo_fila' => 'dato', 'numerorecepcion' => '100'],
            ]),
            'totales' => ['cantidad_filas' => 1],
            'kpis' => [],
        ]);

        $recuperado = RecepcionProveedorReporteCacheSupport::recuperar($filtros);

        $this->assertNotNull($recuperado);
        $this->assertSame(1, $recuperado['filas']->count());
        $this->assertSame('100', $recuperado['filas']->first()['numerorecepcion']);
    }

    public function test_no_cachea_si_supera_el_tope_de_filas(): void
    {
        $this->assertTrue(RecepcionProveedorReporteCacheSupport::cabeEnCache(33933));
        $this->assertFalse(RecepcionProveedorReporteCacheSupport::cabeEnCache(
            RecepcionProveedorReporteCacheSupport::MAX_FILAS + 1
        ));
    }
}
