<?php

namespace Tests\Unit\Support\Compras\Retencion;

use App\Models\Compras\Proveedor;
use App\Models\Compras\RetencionIIBB;
use App\Models\Compras\RetencionIIBB_Condicion;
use App\Models\Configuracion\Condicioniva;
use App\Support\Compras\ProveedorRetencionIibbAnitaSupport;
use App\Support\Compras\Retencion\RetencionIibbCrownMatrizSupport;
use App\Support\Compras\Retencion\RetencionIibbResultado;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class RetencionIibbCrownMatrizSupportTest extends TestCase
{
    private function catalogo(): RetencionIIBB
    {
        $cab = new RetencionIIBB([
            'provincia_id' => RetencionIibbCrownMatrizSupport::PROVINCIA_LOCAL_ID,
            'porcentajeretencion_fuera' => 5,
        ]);
        $cab->setRelation('retencionIIBB_condiciones', new Collection([
            new RetencionIIBB_Condicion([
                'condicionIIBB_id' => ProveedorRetencionIibbAnitaSupport::CONDICION_CONVENIO,
                'porcentajeretencion' => 1,
                'minimoimponible' => 2000,
                'minimoretencion' => 0,
            ]),
            new RetencionIIBB_Condicion([
                'condicionIIBB_id' => ProveedorRetencionIibbAnitaSupport::CONDICION_LOCAL,
                'porcentajeretencion' => 1,
                'minimoimponible' => 2000,
                'minimoretencion' => 0,
            ]),
        ]));

        return $cab;
    }

    private function proveedor(int $condicionId, int $provinciaId, bool $mono = false, array $cm05ProvinciaIds = []): Proveedor
    {
        $p = new Proveedor([
            'condicionIIBB_id' => $condicionId,
            'provincia_id' => $provinciaId,
            'condicioniva_id' => $mono ? 4 : 1,
        ]);
        if ($mono) {
            $iva = new Condicioniva(['id' => 4, 'nombre' => 'Monotributo', 'letra' => 'A', 'letra_compras' => 'C']);
            $p->setRelation('condicionivas', $iva);
        } else {
            $iva = new Condicioniva(['id' => 1, 'nombre' => 'Responsable Inscripto', 'letra' => 'A', 'letra_compras' => 'A']);
            $p->setRelation('condicionivas', $iva);
        }

        $cm05 = collect($cm05ProvinciaIds)->map(
            static fn (int $id) => new \App\Models\Compras\Proveedor_Cm05(['provincia_id' => $id])
        );
        $p->setRelation('proveedor_cm05s', $cm05);

        return $p;
    }

    public function test_convenio_rn_uno_por_ciento(): void
    {
        $r = RetencionIibbCrownMatrizSupport::resolver(
            $this->proveedor(1, 3),
            $this->catalogo(),
        );
        $this->assertTrue($r['aplica']);
        $this->assertSame(1.0, $r['tasa']);
        $this->assertSame('convenio_rn', $r['regla']);
    }

    public function test_convenio_fuera_cinco_por_ciento(): void
    {
        $r = RetencionIibbCrownMatrizSupport::resolver(
            $this->proveedor(1, 11),
            $this->catalogo(),
        );
        $this->assertTrue($r['aplica']);
        $this->assertSame(5.0, $r['tasa']);
        $this->assertSame('convenio_fuera', $r['regla']);
    }

    public function test_local_rn_uno_por_ciento(): void
    {
        $r = RetencionIibbCrownMatrizSupport::resolver(
            $this->proveedor(2, 3),
            $this->catalogo(),
        );
        $this->assertTrue($r['aplica']);
        $this->assertSame(1.0, $r['tasa']);
        $this->assertSame('local_rn', $r['regla']);
    }

    public function test_local_fuera_cinco_por_ciento(): void
    {
        $r = RetencionIibbCrownMatrizSupport::resolver(
            $this->proveedor(2, 2),
            $this->catalogo(),
        );
        $this->assertTrue($r['aplica']);
        $this->assertSame(5.0, $r['tasa']);
        $this->assertSame('local_fuera', $r['regla']);
    }

    public function test_mono_rn_cero(): void
    {
        $r = RetencionIibbCrownMatrizSupport::resolver(
            $this->proveedor(2, 3, true),
            $this->catalogo(),
        );
        $this->assertFalse($r['aplica']);
        $this->assertSame(RetencionIibbResultado::MOTIVO_NO_RETIENE, $r['motivo']);
        $this->assertSame('monotributo_rn_0', $r['regla']);
    }

    public function test_mono_fuera_cinco(): void
    {
        $r = RetencionIibbCrownMatrizSupport::resolver(
            $this->proveedor(1, 11, true),
            $this->catalogo(),
        );
        $this->assertTrue($r['aplica']);
        $this->assertSame(5.0, $r['tasa']);
        $this->assertSame('monotributo_fuera', $r['regla']);
    }

    public function test_no_inscripto_legado_como_convenio_fuera(): void
    {
        $r = RetencionIibbCrownMatrizSupport::resolver(
            $this->proveedor(3, 11),
            $this->catalogo(),
        );
        $this->assertTrue($r['aplica']);
        $this->assertSame(5.0, $r['tasa']);
        $this->assertSame(1, $r['condicion_iibb_id']);
    }

    public function test_convenio_fuera_con_cm05_rn_uno_por_ciento(): void
    {
        $r = RetencionIibbCrownMatrizSupport::resolver(
            $this->proveedor(1, 11, false, [RetencionIibbCrownMatrizSupport::PROVINCIA_LOCAL_ID]),
            $this->catalogo(),
        );
        $this->assertTrue($r['aplica']);
        $this->assertSame(1.0, $r['tasa']);
        $this->assertTrue($r['en_jurisdiccion_local']);
        $this->assertTrue($r['inscrito_cm05_rn']);
        $this->assertSame('convenio_rn', $r['regla']);
    }

    public function test_convenio_fuera_sin_cm05_sigue_cinco(): void
    {
        $r = RetencionIibbCrownMatrizSupport::resolver(
            $this->proveedor(1, 1, false, []),
            $this->catalogo(),
        );
        $this->assertTrue($r['aplica']);
        $this->assertSame(5.0, $r['tasa']);
        $this->assertFalse($r['inscrito_cm05_rn']);
        $this->assertSame('convenio_fuera', $r['regla']);
    }
}
