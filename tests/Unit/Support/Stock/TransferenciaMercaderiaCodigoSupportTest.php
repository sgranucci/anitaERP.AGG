<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\TransferenciaMercaderiaCodigoSupport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TransferenciaMercaderiaCodigoSupportTest extends TestCase
{
    public function test_formatea_correlativo_de_8_digitos(): void
    {
        $this->assertSame('TR-00000001', TransferenciaMercaderiaCodigoSupport::formatear(1));
        $this->assertSame('TR-00000840', TransferenciaMercaderiaCodigoSupport::formatear(840));
    }

    public function test_extrae_secuencial_y_ignora_timestamp_legacy(): void
    {
        $this->assertSame(840, TransferenciaMercaderiaCodigoSupport::extraerSecuencial('TR-00000840'));
        $this->assertSame(12, TransferenciaMercaderiaCodigoSupport::extraerSecuencial('TR-12'));
        $this->assertNull(TransferenciaMercaderiaCodigoSupport::extraerSecuencial('TR-20260826122337'));
        $this->assertNull(TransferenciaMercaderiaCodigoSupport::extraerSecuencial('TR-20260826122337-REV-122349'));
    }

    public function test_piso_toma_el_mayor_secuencial_y_omite_legacy(): void
    {
        $piso = TransferenciaMercaderiaCodigoSupport::pisoDesdeCodigos([
            'TR-20260826122337',
            'TR-00000839',
            'TR-00000100',
            'OTRO',
        ]);

        $this->assertSame(839, $piso);
    }

    public function test_detecta_duplicado_del_indice_de_codigo(): void
    {
        $e = new RuntimeException(
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'TR-00000840' for key 'transferencia_mercaderia.uk_transferencia_mercaderia_codigo'"
        );

        $this->assertTrue(TransferenciaMercaderiaCodigoSupport::esCodigoDuplicado($e));
        $this->assertFalse(TransferenciaMercaderiaCodigoSupport::esCodigoDuplicado(
            new RuntimeException('otro error')
        ));
    }
}
