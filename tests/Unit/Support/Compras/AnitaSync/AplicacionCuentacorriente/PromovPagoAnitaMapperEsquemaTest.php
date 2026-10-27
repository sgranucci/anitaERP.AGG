<?php

namespace Tests\Unit\Support\Compras\AnitaSync\AplicacionCuentacorriente;

use App\Support\Compras\AnitaSync\AplicacionCuentacorriente\AplicacionCuentacorrienteAnitaLadoSupport;
use App\Support\Compras\AnitaSync\AplicacionCuentacorriente\PromovPagoAnitaMapper;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Tests\TestCase;

class PromovPagoAnitaMapperEsquemaTest extends TestCase
{
    public function test_insert_op_respeta_cuota_cero(): void
    {
        config(['app.empresa' => EntornoEmpresaSupport::AGG]);

        $lado = AplicacionCuentacorrienteAnitaLadoSupport::armar('4518', 'OPP', ' ', 1, 124883, 0, 0, 1);
        $this->assertSame(0, $lado['nro_cuota']);
        $valores = PromovPagoAnitaMapper::valoresInsert($lado, 146912851.56, '20260911');
        $this->assertStringContainsString("'146912851.5600'", $valores);
        $this->assertStringContainsString("'0'", $valores);
    }

    public function test_crown_alinea_valores_de_op_y_apa_con_las_18_columnas(): void
    {
        config(['app.empresa' => EntornoEmpresaSupport::CROWN]);

        $lado = AplicacionCuentacorrienteAnitaLadoSupport::armar('1234', 'OPP', 'A', 0, 219621, 0, 0, 1);
        $campos = PromovPagoAnitaMapper::camposInsert();
        $valores = PromovPagoAnitaMapper::valoresInsert($lado, 450000, '20261027');
        $apa = PromovPagoAnitaMapper::valoresInsertApa($lado, 1401, 450000, '20261027');

        $this->assertSame(18, $this->contarCampos($campos));
        $this->assertSame(18, $this->contarValores($valores));
        $this->assertSame(18, $this->contarValores($apa));
        $this->assertStringNotContainsString('prov_empresa', $campos);
        $this->assertStringContainsString("'219621'", $valores);
        $this->assertStringContainsString("'450000.0000'", $valores);
    }

    public function test_ferli_alinea_18_valores_y_agg_agrega_empresa(): void
    {
        config(['app.empresa' => EntornoEmpresaSupport::FERLI]);
        $lado = AplicacionCuentacorrienteAnitaLadoSupport::armar('1234', 'OPP', 'A', 1, 100, 0, 0, 1);
        $this->assertSame(18, $this->contarCampos(PromovPagoAnitaMapper::camposInsert()));
        $this->assertSame(18, $this->contarValores(PromovPagoAnitaMapper::valoresInsert($lado, 10, '20261027')));

        config(['app.empresa' => EntornoEmpresaSupport::AGG]);
        $campos = PromovPagoAnitaMapper::camposInsert();
        $this->assertSame(22, $this->contarCampos($campos));
        $this->assertStringContainsString('prov_empresa', $campos);
        $this->assertSame(22, $this->contarValores(PromovPagoAnitaMapper::valoresInsert($lado, 10, '20261027')));
        $this->assertSame(22, $this->contarValores(PromovPagoAnitaMapper::valoresInsertApa($lado, 1401, 10, '20261027')));
    }

    private function contarCampos(string $campos): int
    {
        preg_match_all('/prov_[a-z_]+/', $campos, $m);

        return count($m[0]);
    }

    private function contarValores(string $valores): int
    {
        preg_match_all("/'(?:[^']|'')*'/", $valores, $m);

        return count($m[0]);
    }
}
