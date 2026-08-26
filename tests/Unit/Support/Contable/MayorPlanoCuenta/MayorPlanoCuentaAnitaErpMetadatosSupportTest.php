<?php

namespace Tests\Unit\Support\Contable\MayorPlanoCuenta;

use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaAnitaErpMetadatosSupport;
use PHPUnit\Framework\TestCase;

class MayorPlanoCuentaAnitaErpMetadatosSupportTest extends TestCase
{
    public function test_copia_emisor_y_asiento_id_del_erp_a_la_fila_anita(): void
    {
        $linea = (object) [
            'ctav_empresa' => '2',
            'ctav_nro_asiento' => '230192',
            'ctav_desc_mov' => 'Aplicacin anticipo CC OPA A000',
        ];

        $resultado = MayorPlanoCuentaAnitaErpMetadatosSupport::aplicar([$linea], [
            '2|230192' => ['emisor' => '003593', 'asiento_id' => 134240],
        ]);

        $this->assertSame('3593', $resultado[0]->erp_emisor_anita);
        $this->assertSame(134240, $resultado[0]->erp_asiento_id);
    }

    public function test_no_pisa_filas_que_ya_vienen_del_reader_erp(): void
    {
        $linea = (object) [
            'ctav_empresa' => '2',
            'ctav_nro_asiento' => '230192',
            'erp_asiento_id' => 99,
            'erp_emisor_anita' => '111',
        ];

        $resultado = MayorPlanoCuentaAnitaErpMetadatosSupport::aplicar([$linea], [
            '2|230192' => ['emisor' => '3593', 'asiento_id' => 134240],
        ]);

        $this->assertSame(99, $resultado[0]->erp_asiento_id);
        $this->assertSame('111', $resultado[0]->erp_emisor_anita);
    }
}