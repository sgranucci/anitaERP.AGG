<?php

namespace Tests\Unit\Support\Contable\PercepcionSufrida;

use App\Support\Contable\PercepcionSufrida\PercepcionSufridaArchivoSupport;
use PHPUnit\Framework\TestCase;

class PercepcionSufridaArchivoRetencionTest extends TestCase
{
    public function test_retencion_sale_con_79_posiciones_como_p_sifere(): void
    {
        $txt = PercepcionSufridaArchivoSupport::sifereRetenciones([
            [
                'jurisdiccion' => 902,
                'cuit' => '30714466603',
                'fecha' => '2025-11-05',
                'sucursal' => 0,
                'nro_retencion' => 2536,
                'tipo_documento' => 'R',
                'letra' => ' ',
                'nro_comprobante' => 307,
                'importe' => 12148.76,
            ],
        ]);

        $linea = rtrim($txt, "\n");
        $this->assertSame(79, strlen($linea));
        $this->assertSame('902', substr($linea, 0, 3));
        $this->assertSame('30-71446660-3', substr($linea, 3, 13));
        $this->assertSame('05/11/2025', substr($linea, 16, 10));
        $this->assertSame('0000', substr($linea, 26, 4));
        $this->assertSame('0000000000002536', substr($linea, 30, 16));
        $this->assertSame('R', substr($linea, 46, 1));
        $this->assertSame(' ', substr($linea, 47, 1));
        $this->assertSame('00000000000000000307', substr($linea, 48, 20));
        $this->assertSame('00012148,76', substr($linea, 68, 11));
    }

    public function test_retencion_filtra_por_jurisdiccion(): void
    {
        $base = [
            'cuit' => '30521151818',
            'fecha' => '2025-11-04',
            'sucursal' => 0,
            'nro_retencion' => 1,
            'tipo_documento' => 'R',
            'letra' => ' ',
            'nro_comprobante' => 10,
            'importe' => 100.5,
        ];
        $txt = PercepcionSufridaArchivoSupport::sifereRetenciones([
            array_merge($base, ['jurisdiccion' => 901]),
            array_merge($base, ['jurisdiccion' => 902, 'nro_retencion' => 2]),
        ], 901);

        $lineas = array_values(array_filter(explode("\n", $txt)));
        $this->assertCount(1, $lineas);
        $this->assertSame('901', substr($lineas[0], 0, 3));
    }
}
