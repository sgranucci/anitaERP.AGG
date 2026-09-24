<?php

namespace Tests\Unit\Support\Compras\AnitaImport;

use App\Models\Compras\Pagoproveedor;
use App\Support\Compras\AnitaImport\PagoproveedorAnitaImpresionAuxpagMapper;
use App\Support\Compras\AnitaImport\PagoproveedorAnitaImpresionElegibleSupport;
use App\Support\Compras\AnitaImport\PagoproveedorAnitaImpresionRetencionMapper;
use App\Models\Compras\Pagoproveedor_Retencion;
use Tests\TestCase;

class PagoproveedorAnitaImpresionSupportTest extends TestCase
{
    public function test_elegible_solo_stub_anita_sin_asiento(): void
    {
        $stub = new Pagoproveedor([
            'detalle' => 'Importado desde Anita — OPP documento (sin cuenta corriente)',
            'asiento_id' => null,
            'caja_movimiento_id' => null,
        ]);
        $stub->setRelation('caja_movimientos', collect());
        $stub->setRelation('cheques', collect());
        $stub->setRelation('pagoproveedor_estados', collect());

        $this->assertTrue(PagoproveedorAnitaImpresionElegibleSupport::esElegible($stub));

        $erp = new Pagoproveedor([
            'detalle' => 'Pago proveedor confirmado',
            'asiento_id' => 99,
            'caja_movimiento_id' => null,
        ]);
        $erp->setRelation('caja_movimientos', collect());
        $erp->setRelation('cheques', collect());
        $erp->setRelation('pagoproveedor_estados', collect());

        $this->assertFalse(PagoproveedorAnitaImpresionElegibleSupport::esElegible($erp));
    }

    public function test_elegible_omite_si_tiene_caja_o_cheques(): void
    {
        $stub = new Pagoproveedor([
            'detalle' => 'Importado desde Anita — OPP documento (sin cuenta corriente)',
            'asiento_id' => null,
            'caja_movimiento_id' => null,
        ]);
        $stub->setRelation('caja_movimientos', collect([(object) ['id' => 1]]));
        $stub->setRelation('cheques', collect());
        $stub->setRelation('pagoproveedor_estados', collect());

        $this->assertFalse(PagoproveedorAnitaImpresionElegibleSupport::esElegible($stub));
    }

    public function test_auxpag_mapper_separa_factura_transferencia_y_cheque(): void
    {
        $lineas = [
            (object) [
                'axp_tipo_ap' => 'FIS',
                'axp_monto_ap' => 1000,
                'axp_nro' => '123',
                'axp_letra_comp' => 'A',
                'axp_sucursal' => 2,
                'axp_nro_interno' => 55,
                'axp_fecha_co' => '20260901',
                'axp_fecha' => '20260910',
                'axp_cod_mon_co' => 1,
            ],
            (object) [
                'axp_tipo_ap' => 'TMB',
                'axp_monto_ap' => 800,
                'axp_banco' => '00001234',
                'axp_nro' => '0',
                'axp_fecha' => '20260910',
                'axp_cod_mon_co' => 1,
            ],
            (object) [
                'axp_tipo_ap' => 'CHP',
                'axp_monto_ap' => 200,
                'axp_banco' => '00001234',
                'axp_nro' => '998877',
                'axp_fecha_co' => '20261001',
                'axp_fecha' => '20260910',
                'axp_cod_mon_co' => 1,
            ],
            (object) [
                'axp_tipo_ap' => 'RGP',
                'axp_monto_ap' => 50,
                'axp_nro' => '1',
                'axp_fecha' => '20260910',
            ],
        ];

        $snap = PagoproveedorAnitaImpresionAuxpagMapper::aSnapshot($lineas, [
            '00001234' => 'Banco Macro',
        ]);

        $this->assertCount(1, $snap['aplicaciones']);
        $this->assertSame('FIS', $snap['aplicaciones'][0]['tipo']);
        $this->assertSame(1000.0, $snap['aplicaciones'][0]['monto_aplicado']);

        $this->assertCount(1, $snap['medios_caja']);
        $this->assertStringContainsString('Banco Macro', $snap['medios_caja'][0]['cuenta']);
        $this->assertSame(800.0, $snap['medios_caja'][0]['monto_abs']);

        $this->assertCount(1, $snap['cheques']);
        $this->assertSame('998877', $snap['cheques'][0]['numerocheque']);
        $this->assertSame(200.0, $snap['cheques'][0]['monto']);
    }

    public function test_retencion_mapper_ganancias_e_iibb(): void
    {
        $filas = PagoproveedorAnitaImpresionRetencionMapper::aFilasPersistencia([
            'ganancias' => [
                (object) [
                    'retv_retencion' => 120.5,
                    'retv_sujeto' => 10000,
                    'retv_porc_ret' => 2,
                    'retv_nro_retencion' => 55,
                    'retv_codigo_ret' => '78',
                    'retv_gravado' => 10000,
                    'retv_pago_actual' => 10000,
                ],
            ],
            'iva' => [],
            'suss' => [],
            'ibr' => [
                (object) [
                    'retibr_retencion' => 30,
                    'retibr_sujeto' => 1000,
                    'retibr_porc_ret' => 3,
                    'retibr_nro_ret' => 9,
                    'retibr_provincia' => 2,
                    'retibr_gravado' => 1000,
                ],
            ],
        ], 1, 1.0);

        $this->assertCount(2, $filas);
        $this->assertSame(Pagoproveedor_Retencion::TIPO_GANANCIAS, $filas[0]['tiporetencion']);
        $this->assertSame(120.5, $filas[0]['importe']);
        $this->assertSame('55', $filas[0]['nro_certificado']);
        $this->assertSame(
            PagoproveedorAnitaImpresionElegibleSupport::ORIGEN_RETENCION,
            $filas[0]['detalle_calculo']['origen']
        );
        $this->assertSame(Pagoproveedor_Retencion::TIPO_IIBB, $filas[1]['tiporetencion']);
        $this->assertSame(30.0, $filas[1]['importe']);
    }
}
