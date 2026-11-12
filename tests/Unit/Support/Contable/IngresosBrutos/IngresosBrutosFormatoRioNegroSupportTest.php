<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Contable\IngresosBrutos;

use App\Support\Contable\IngresosBrutos\IngresosBrutosFormatoRioNegroSupport;
use PHPUnit\Framework\TestCase;

final class IngresosBrutosFormatoRioNegroSupportTest extends TestCase
{
    public function test_linea_retencion_coincide_con_formato_anita(): void
    {
        $linea = IngresosBrutosFormatoRioNegroSupport::formatearLineaRetencion([
            'nro_cert' => 12345,
            'nro_documento' => '30-70822285-9',
            'fecha_retencion' => '2026-10-15',
            'base_calculo' => 1000.0,
            'alicuota' => 1.0,
            'importe' => 10.0,
        ], 1);

        $this->assertSame(
            "    1,1,1,000000012345,30708222859,15/10/2026,     1000.00, 1.00,       10.00,050,916\n",
            $linea
        );
    }

    public function test_generar_omite_devoluciones_negativas(): void
    {
        $archivo = IngresosBrutosFormatoRioNegroSupport::generarArchivo([
            [
                'nro_cert' => 1,
                'nro_documento' => '20111111112',
                'fecha_retencion' => '2026-10-01',
                'base_calculo' => 100.0,
                'alicuota' => 1.0,
                'importe' => 1.0,
            ],
            [
                'nro_cert' => 2,
                'nro_documento' => '20111111112',
                'fecha_retencion' => '2026-10-02',
                'base_calculo' => 100.0,
                'alicuota' => 1.0,
                'importe' => -1.0,
            ],
        ]);

        $this->assertSame(1, substr_count($archivo, "\n"));
        $this->assertStringContainsString('000000000001', $archivo);
        $this->assertStringNotContainsString('000000000002', $archivo);
    }

    public function test_nombre_archivo_rentas_rn(): void
    {
        $this->assertSame(
            'R30708222859_20261001.txt',
            IngresosBrutosFormatoRioNegroSupport::nombreArchivo('30-70822285-9', '202610')
        );
    }
}
