<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ProveedorExclusionAnitaSupport;
use Tests\TestCase;

class ProveedorExclusionAnitaSupportTest extends TestCase
{
    public function test_tipo_retencion_erp_anita_codigo(): void
    {
        $this->assertSame('0', ProveedorExclusionAnitaSupport::tipoRetencionErpAnitaCodigo('G'));
        $this->assertSame('1', ProveedorExclusionAnitaSupport::tipoRetencionErpAnitaCodigo('I'));
        $this->assertSame('2', ProveedorExclusionAnitaSupport::tipoRetencionErpAnitaCodigo('B'));
        $this->assertNull(ProveedorExclusionAnitaSupport::tipoRetencionErpAnitaCodigo('S'));
    }

    public function test_lineas_desde_request_ignora_renglones_vacios(): void
    {
        $lineas = ProveedorExclusionAnitaSupport::lineasDesdeRequest([
            'desdefechas' => ['2026-02-01', '', '2026-04-01'],
            'hastafechas' => ['2026-06-30', '2027-01-01', '2027-03-31'],
            'porcentajeexclusiones' => [100, 50, 100],
            'tiporetenciones' => ['I', 'G', 'G'],
            'comentarios' => ['iva', 'ignorar', 'gan'],
        ]);

        $this->assertCount(2, $lineas);
        $this->assertSame('1', $lineas[0]['tipo_anita']);
        $this->assertSame('0', $lineas[1]['tipo_anita']);
    }

    public function test_campos_promae_desde_dos_exclusiones(): void
    {
        $lineas = ProveedorExclusionAnitaSupport::lineasDesdeRequest([
            'desdefechas' => ['2026-02-01', '2026-04-01'],
            'hastafechas' => ['2026-06-30', '2027-03-31'],
            'porcentajeexclusiones' => [100, 100],
            'tiporetenciones' => ['I', 'G'],
            'comentarios' => ['cert iva', 'cert gan'],
        ]);

        $campos = ProveedorExclusionAnitaSupport::camposPromaeDesdeLineas($lineas);

        $this->assertSame(100, $campos['exclusionretiva']);
        $this->assertSame('20260630', $campos['fechaexclusionretiva']);
        $this->assertSame('20260201', $campos['fechainicioexclusionretiva']);
        $this->assertSame(100, $campos['exclusionretgan']);
        $this->assertSame('20270331', $campos['fechaexclusionretgan']);
        $this->assertSame('20260401', $campos['fechainicioexclusionretgan']);
    }

    public function test_fecha_anita_a_iso(): void
    {
        $this->assertSame('2026-02-01', ProveedorExclusionAnitaSupport::fechaAnitaAIso('20260201'));
        $this->assertSame('2026-02-01', ProveedorExclusionAnitaSupport::fechaAnitaAIso('2026-02-01'));
        $this->assertNull(ProveedorExclusionAnitaSupport::fechaAnitaAIso(0));
    }

    public function test_lineas_erp_desde_anita_proexcl_y_promae(): void
    {
        $promae = (object) [
            'prom_excl_retiva' => 0,
            'prom_fecha_excl' => 0,
            'prom_fe_ini_excl' => 0,
            'prom_excl_retgan' => 100,
            'prom_fecha_exclrg' => '20270331',
            'prom_fe_ini_exclrg' => '20260401',
            'prom_excl_retib' => 0,
            'prom_fecha_exclib' => 0,
            'prom_fe_ini_exclib' => 0,
        ];
        $proexcl = [
            (object) [
                'proex_tipo_ret' => '1',
                'proex_desde_fecha' => '20260201',
                'proex_hasta_fecha' => '20260630',
                'proex_porc_excl' => '100.0',
                'proex_comentario' => 'cert iva',
            ],
        ];

        $lineas = ProveedorExclusionAnitaSupport::lineasErpDesdeAnita($proexcl, $promae);

        $this->assertCount(2, $lineas);
        $porTipo = collect($lineas)->keyBy('tiporetencion');
        $this->assertSame('2026-02-01', $porTipo['I']['desdefecha']);
        $this->assertSame('2026-04-01', $porTipo['G']['desdefecha']);
    }
}
