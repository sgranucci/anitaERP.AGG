<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\PagoproveedorCuentacorrienteVinculoBackfillSupport;
use PHPUnit\Framework\TestCase;

class PagoproveedorCuentacorrienteVinculoBackfillSupportTest extends TestCase
{
    public function test_vincula_un_credito_cuando_hay_una_sola_op(): void
    {
        $clave = PagoproveedorCuentacorrienteVinculoBackfillSupport::claveMovimiento(2586, 1, [
            'tipo' => 'OPP',
            'letra' => 'A',
            'sucursal' => 1,
            'numero' => 124776,
        ]);

        $plan = PagoproveedorCuentacorrienteVinculoBackfillSupport::plan(
            [
                (object) [
                    'credito_id' => 22261,
                    'proveedor_id' => 2586,
                    'empresa_id' => 1,
                    'app_id' => 23469,
                    'comprobanteaplicado' => 'OPP A 1-124776',
                ],
            ],
            [$clave => [27484]],
            []
        );

        $this->assertSame(1, $plan['creditos_candidatos']);
        $this->assertSame([
            [
                'cc_id' => 22261,
                'pago_id' => 27484,
                'app_ids' => [23469],
            ],
        ], $plan['vinculos']);
    }

    public function test_no_vincula_si_la_op_ya_tiene_cuenta_corriente_o_la_clave_es_ambigua(): void
    {
        $this->assertNull(PagoproveedorCuentacorrienteVinculoBackfillSupport::pagoUnico([27484], true));
        $this->assertNull(PagoproveedorCuentacorrienteVinculoBackfillSupport::pagoUnico([10, 11], false));
        $this->assertSame(27484, PagoproveedorCuentacorrienteVinculoBackfillSupport::pagoUnico([27484], false));

        $clave = PagoproveedorCuentacorrienteVinculoBackfillSupport::claveMovimiento(2586, 1, [
            'tipo' => 'OPP',
            'letra' => 'A',
            'sucursal' => 1,
            'numero' => 124776,
        ]);
        $plan = PagoproveedorCuentacorrienteVinculoBackfillSupport::plan(
            [
                (object) [
                    'credito_id' => 22261,
                    'proveedor_id' => 2586,
                    'empresa_id' => 1,
                    'app_id' => 23469,
                    'comprobanteaplicado' => 'OPP A 1-124776',
                ],
            ],
            [$clave => [27484, 99999]],
            []
        );

        $this->assertSame([], $plan['vinculos']);
        $this->assertSame(1, $plan['ambiguo_op']);
    }

    public function test_dos_etiquetas_distintas_en_el_mismo_credito_no_se_vinculan(): void
    {
        $plan = PagoproveedorCuentacorrienteVinculoBackfillSupport::plan(
            [
                (object) [
                    'credito_id' => 1,
                    'proveedor_id' => 9,
                    'empresa_id' => 1,
                    'app_id' => 10,
                    'comprobanteaplicado' => 'OPP A 1-100',
                ],
                (object) [
                    'credito_id' => 1,
                    'proveedor_id' => 9,
                    'empresa_id' => 1,
                    'app_id' => 11,
                    'comprobanteaplicado' => 'OPP A 1-200',
                ],
            ],
            [],
            []
        );

        $this->assertSame([], $plan['vinculos']);
        $this->assertSame(1, $plan['ambiguo_etiqueta']);
    }
}
