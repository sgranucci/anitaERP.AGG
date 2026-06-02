<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ProveedorImpuestosRetencionRules;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProveedorImpuestosRetencionRulesTest extends TestCase
{
    public function test_normalizar_no_inscripto_y_no_retiene(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('retencionganancia')) {
            $this->markTestSkipped('Tablas de retención no disponibles.');
        }

        $ids = ProveedorImpuestosRetencionRules::idsSinCodigo();
        if ($ids['retencionganancia_id'] === null) {
            $this->markTestSkipped('Catálogo sin código de retención no cargado (ejecutar migración 2026_06_02_120000).');
        }

        $data = ProveedorImpuestosRetencionRules::normalizar([
            'condicionganancia' => 'N',
            'retieneganancia' => 'S',
            'retieneiva' => 'N',
            'retienesuss' => 'N',
            'retencionganancia_id' => 1,
            'retencioniva_id' => 2,
            'retencionsuss_id' => 3,
        ]);

        $this->assertSame('N', $data['retieneganancia']);
        $this->assertSame($ids['retencionganancia_id'], (int) $data['retencionganancia_id']);
        $this->assertSame($ids['retencioniva_id'], (int) $data['retencioniva_id']);
        $this->assertSame($ids['retencionsuss_id'], (int) $data['retencionsuss_id']);
    }

    public function test_catalogo_sin_codigo_usa_codigo_cero(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('retencionganancia')) {
            $this->markTestSkipped('Tablas de retención no disponibles.');
        }

        if (! ProveedorImpuestosRetencionRules::idsSinCodigo()['retencionganancia_id']) {
            $this->markTestSkipped('Catálogo sin código de retención no cargado (ejecutar migración 2026_06_02_120000).');
        }

        $this->assertTrue(
            DB::table('retencionganancia')
                ->where('codigo', ProveedorImpuestosRetencionRules::CODIGO_SIN_RETENCION)
                ->where('nombre', ProveedorImpuestosRetencionRules::NOMBRE_SIN_CODIGO_GANANCIA)
                ->exists()
        );
    }
}
