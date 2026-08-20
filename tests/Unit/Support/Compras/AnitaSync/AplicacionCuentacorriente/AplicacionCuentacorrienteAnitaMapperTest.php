<?php

namespace Tests\Unit\Support\Compras\AnitaSync\AplicacionCuentacorriente;

use App\Support\Compras\AnitaSync\AplicacionCuentacorriente\AplicacionCuentacorrienteAnitaLadoSupport;
use App\Support\Compras\AnitaSync\AplicacionCuentacorriente\AplmovpAnitaMapper;
use App\Support\Compras\AnitaSync\AplicacionCuentacorriente\PromovPagadoAnitaMapper;
use PHPUnit\Framework\TestCase;

class AplicacionCuentacorrienteAnitaMapperTest extends TestCase
{
    public function test_t_pagado_es_el_absoluto_de_la_suma_de_aplicaciones(): void
    {
        $this->assertSame(9055.89, AplicacionCuentacorrienteAnitaLadoSupport::tPagadoDesdeSumaAplicaciones(-9055.89));
        $this->assertSame(100.0, AplicacionCuentacorrienteAnitaLadoSupport::tPagadoDesdeSumaAplicaciones(100));
        $this->assertSame(0.0, AplicacionCuentacorrienteAnitaLadoSupport::tPagadoDesdeSumaAplicaciones(0));
    }

    public function test_aplmovp_inserta_deuda_cuota_y_referencia_del_credito(): void
    {
        $deuda = AplicacionCuentacorrienteAnitaLadoSupport::armar('3593', 'FNB', 'A', 7, 857, 427700, 1, 1, '2', 1515);
        $credito = AplicacionCuentacorrienteAnitaLadoSupport::armar('3593', 'OPA', 'A', 1, 124102);

        $valores = AplmovpAnitaMapper::valoresInsert($deuda, $credito, '20260820', 9055.89);
        $this->assertStringContainsString("'003593'", $valores);
        $this->assertStringContainsString("'FNB'", $valores);
        $this->assertStringContainsString("'857'", $valores);
        $this->assertStringContainsString("'1'", $valores);
        $this->assertStringContainsString("'20260820'", $valores);
        $this->assertStringContainsString("'9055.8900'", $valores);
        $this->assertStringContainsString("'OPA'", $valores);
        $this->assertStringContainsString("'124102'", $valores);
        $this->assertStringContainsString("'427700'", $valores);
        $this->assertStringContainsString("'2'", $valores);
        $this->assertStringContainsString("'1515.0000'", $valores);

        $update = AplmovpAnitaMapper::valoresUpdate($deuda, $credito);
        $this->assertStringContainsString("aplvp_nro_cuota = '1'", $update);
        $this->assertStringContainsString("aplvp_ref_tipo = 'OPA'", $update);
        $this->assertStringContainsString("aplvp_ref_letra = 'A'", $update);
        $this->assertStringContainsString("aplvp_ref_sucursal = '1'", $update);
        $this->assertStringContainsString("aplvp_ref_nro = '124102'", $update);
        $this->assertStringContainsString("aplvp_nro_interno = '427700'", $update);
        $this->assertStringContainsString("aplvp_ref_interno = '0'", $update);
        $this->assertStringContainsString("aplvp_cod_mon = '2'", $update);
        $this->assertStringContainsString("aplvp_cotizacion = '1515.0000'", $update);

        $where = AplmovpAnitaMapper::whereFila($deuda, $credito, '20260820', 9055.89);
        $this->assertStringContainsString("aplvp_tipo = 'FNB'", $where);
        $this->assertStringContainsString("aplvp_tipo_cob = 'OPA'", $where);
        $this->assertStringContainsString("aplvp_nro = '857'", $where);
        $this->assertStringContainsString("aplvp_nro_cob = '124102'", $where);
    }

    public function test_aplmovp_nc_repite_la_nc_en_referencia_e_interno(): void
    {
        $deuda = AplicacionCuentacorrienteAnitaLadoSupport::armar('3593', 'FNS', 'A', 7, 789, 111, 1);
        $credito = AplicacionCuentacorrienteAnitaLadoSupport::armar('3593', 'NCL', 'A', 7, 94, 222, 1);

        $update = AplmovpAnitaMapper::valoresUpdate($deuda, $credito);
        $this->assertStringContainsString("aplvp_tipo_cob = 'NCL'", $update);
        $this->assertStringContainsString("aplvp_nro_cob = '94'", $update);
        $this->assertStringContainsString("aplvp_ref_tipo = 'NCL'", $update);
        $this->assertStringContainsString("aplvp_ref_nro = '94'", $update);
        $this->assertStringContainsString("aplvp_nro_interno = '111'", $update);
        $this->assertStringContainsString("aplvp_ref_interno = '222'", $update);
    }

    public function test_promov_update_graba_pagado_y_referencia(): void
    {
        $deuda = AplicacionCuentacorrienteAnitaLadoSupport::armar('3593', 'FNB', 'A', 7, 857, 427704, 1);
        $credito = AplicacionCuentacorrienteAnitaLadoSupport::armar('3593', 'OPA', 'A', 1, 124102);

        $where = PromovPagadoAnitaMapper::whereCuota($deuda);
        $this->assertStringContainsString("prov_tipo = 'FNB'", $where);
        $this->assertStringContainsString("prov_nro = '857'", $where);
        $this->assertStringContainsString("prov_nro_interno = '427704'", $where);
        $this->assertStringContainsString("prov_nro_cuota = '1'", $where);

        $valores = PromovPagadoAnitaMapper::valoresUpdate(9055.89, '20260820', $credito);
        $this->assertStringContainsString("prov_t_pagado = '9055.8900'", $valores);
        $this->assertStringContainsString("prov_fecha_pago = '20260820'", $valores);
        $this->assertStringContainsString("prov_ref_tipo = 'OPA'", $valores);
        $this->assertStringContainsString("prov_ref_nro = '124102'", $valores);
    }

    public function test_promov_sin_pagado_limpia_referencia(): void
    {
        $valores = PromovPagadoAnitaMapper::valoresUpdate(0, '20260820', null);
        $this->assertStringContainsString("prov_t_pagado = '0'", $valores);
        $this->assertStringContainsString("prov_fecha_pago = '0'", $valores);
        $this->assertStringContainsString("prov_ref_nro = '0'", $valores);
    }

    public function test_promov_opa_sin_interno_no_filtra_nro_interno(): void
    {
        $opa = AplicacionCuentacorrienteAnitaLadoSupport::armar('3593', 'OPA', 'A', 1, 124102);
        $where = PromovPagadoAnitaMapper::whereCuota($opa);
        $this->assertStringContainsString("prov_tipo = 'OPA'", $where);
        $this->assertStringContainsString("prov_nro = '124102'", $where);
        $this->assertStringNotContainsString('prov_nro_interno', $where);
    }
}
