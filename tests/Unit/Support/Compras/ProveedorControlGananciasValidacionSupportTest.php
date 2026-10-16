<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Compras;

use App\Models\Compras\Proveedor;
use App\Models\Compras\Retencionganancia;
use App\Models\Configuracion\Condicioniva;
use App\Support\Compras\ProveedorActividadRetencionGananciasSupport;
use App\Support\Compras\ProveedorControlGananciasValidacionSupport;
use Tests\TestCase;

class ProveedorControlGananciasValidacionSupportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ProveedorActividadRetencionGananciasSupport::resetCatalogoCache();
        config([
            'arca.padron_validacion_proveedor.condicioniva_responsable_inscripto_id' => 1,
            'arca.padron_validacion_proveedor.condicioniva_monotributo_id' => 4,
            'arca.padron_validacion_cliente.condicioniva_responsable_inscripto_id' => 1,
            'arca.padron_validacion_cliente.condicioniva_monotributo_id' => 4,
            'arca.padron_validacion_cliente.impuesto_iva_id' => 30,
            'arca.padron_validacion_cliente.impuesto_monotributo_id' => 20,
            'arca.padron_validacion_cliente.estado_impuesto_activo' => 'AC',
            'compras_control_ganancias.condicioniva_monotributo_nombre_esperado' => 'Monotributo C Proveedores',
            'compras_control_ganancias.actividad_retencion_codigo' => [],
        ]);
    }

    public function test_segmento_ri_y_mono(): void
    {
        $ri = $this->condicion(1, 'Responsable Inscripto');
        $mono = $this->condicion(8, 'Monotributo C Proveedores');
        $otro = $this->condicion(2, 'Consumidor Final');

        $this->assertSame('ri', ProveedorControlGananciasValidacionSupport::segmento($ri));
        $this->assertSame('mono', ProveedorControlGananciasValidacionSupport::segmento($mono));
        $this->assertSame('otro', ProveedorControlGananciasValidacionSupport::segmento($otro));
    }

    public function test_condicion_mono_esperada(): void
    {
        $ok = $this->condicion(8, 'Monotributo C Proveedores');
        $mal = $this->condicion(4, 'Monotributo A Clientes');

        $this->assertTrue(ProveedorControlGananciasValidacionSupport::condicionMonoEsperada($ok));
        $this->assertFalse(ProveedorControlGananciasValidacionSupport::condicionMonoEsperada($mal));
    }

    public function test_fila_ri_sin_mapeo_marca_discrepancia(): void
    {
        $prov = $this->proveedor(
            $this->condicion(1, 'Responsable Inscripto'),
            $this->retencion('78', 'Servicios')
        );

        $fila = ProveedorControlGananciasValidacionSupport::armarFila($prov, [
            'actividadPrincipal' => [
                'idActividad' => 620100,
                'descripcionActividad' => 'Servicios de software',
                'fuente' => 'regimen_general',
            ],
            'impuestos' => [
                ['idImpuesto' => 30, 'estadoImpuesto' => 'AC', 'fuente' => 'regimen_general'],
            ],
            'datosMonotributo' => null,
            'error' => null,
        ]);

        $this->assertSame('ri', $fila['segmento']);
        $this->assertSame(620100, $fila['actividad_id']);
        $this->assertSame('Servicios de software', $fila['actividad_descripcion']);
        $this->assertSame('78', $fila['retencion_actual_codigo']);
        $this->assertSame('', $fila['retencion_propuesta_codigo']);
        $this->assertTrue($fila['discrepancia']);
        $this->assertStringContainsString('mapeo', mb_strtolower($fila['observaciones']));
    }

    public function test_fila_sin_consulta_arca_no_propone_codigo(): void
    {
        $prov = $this->proveedor(
            $this->condicion(1, 'Responsable Inscripto'),
            $this->retencion('78', 'Servicios')
        );

        $fila = ProveedorControlGananciasValidacionSupport::armarFila($prov, null, null);

        $this->assertSame('sin_consulta_arca', $fila['retencion_propuesta_origen']);
        $this->assertSame('', $fila['retencion_propuesta_codigo']);
        $this->assertNull($fila['padron_ok']);
    }

    public function test_proponer_sin_actividad_y_sin_mapeo(): void
    {
        $sinAct = ProveedorActividadRetencionGananciasSupport::proponerDesdeActividad(null);
        $this->assertSame('sin_actividad', $sinAct['origen']);

        $sinMapa = ProveedorActividadRetencionGananciasSupport::proponerDesdeActividad(999);
        $this->assertSame('sin_mapeo', $sinMapa['origen']);

        config(['compras_control_ganancias.actividad_retencion_codigo' => [999 => '55']]);
        $sinCodigoEnMapa = ProveedorActividadRetencionGananciasSupport::proponerDesdeActividad(111);
        $this->assertSame('actividad_sin_mapeo', $sinCodigoEnMapa['origen']);
    }

    private function condicion(int $id, string $nombre): Condicioniva
    {
        $iva = new Condicioniva;
        $iva->forceFill(['nombre' => $nombre, 'letra' => 'A']);
        $iva->id = $id;

        return $iva;
    }

    private function retencion(string $codigo, string $nombre): Retencionganancia
    {
        $ret = new Retencionganancia;
        $ret->forceFill(['codigo' => $codigo, 'nombre' => $nombre]);
        $ret->id = 10;

        return $ret;
    }

    private function proveedor(Condicioniva $iva, ?Retencionganancia $ret): Proveedor
    {
        $prov = new Proveedor;
        $prov->forceFill([
            'codigo' => '100',
            'nombre' => 'Proveedor Test SA',
            'nroinscripcion' => '30-71234567-8',
            'estado' => 'Activo',
            'retieneganancia' => 'S',
            'condicionganancia' => 'I',
            'condicioniva_id' => $iva->id,
            'retencionganancia_id' => $ret?->id,
        ]);
        $prov->id = 50;
        $prov->setRelation('condicionivas', $iva);
        $prov->setRelation('retencionganancias', $ret);

        return $prov;
    }
}
