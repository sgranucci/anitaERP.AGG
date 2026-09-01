<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\InterbankingArchivoPagoFormatoSupport;
use PHPUnit\Framework\TestCase;

/**
 * Layout byte a byte contra p-pagoxbanco.c (fprintf de cabecera *U* y renglón *M*).
 */
class InterbankingArchivoPagoFormatoSupportTest extends TestCase
{
    public function test_cabecera_y_renglon_coinciden_con_anita(): void
    {
        $cbuOrigen = '2850651330094012507151';
        $cbuDestino = '0070091830004035975387';
        $obs = 'Desde OP: 1 hasta 99 Desde fecha: 01/09/2026 hasta 01/09/2026';

        $archivo = InterbankingArchivoPagoFormatoSupport::generarArchivo(
            $cbuOrigen,
            20260901,
            1,
            $obs,
            [['cbu' => $cbuDestino, 'importe' => 12345.67]]
        );

        $this->assertStringNotContainsString("\0", $archivo);

        $lineas = explode("\n", $archivo);
        $this->assertCount(3, $lineas);
        $this->assertSame('', $lineas[2]);

        $cab = $lineas[0];
        $mov = $lineas[1];

        $this->assertSame(InterbankingArchivoPagoFormatoSupport::ANCHO_REGISTRO, strlen($cab));
        $this->assertSame(InterbankingArchivoPagoFormatoSupport::ANCHO_REGISTRO, strlen($mov));

        $this->assertSame('*U*', substr($cab, 0, 3));
        $this->assertSame($cbuOrigen, substr($cab, 3, 22));
        $this->assertSame('D', $cab[25]);
        $this->assertSame('20260901', substr($cab, 26, 8));
        $this->assertSame('N', $cab[34]);
        $this->assertSame(str_pad(substr($obs, 0, 61), 61), substr($cab, 35, 61));
        $this->assertSame('000', substr($cab, 96, 3));
        $this->assertSame('00', substr($cab, 99, 2));
        $this->assertSame('01/09/26', substr($cab, 101, 8));
        $this->assertSame('00000001', substr($cab, 109, 8));
        $this->assertSame(str_repeat(' ', 123), substr($cab, 117, 123));

        $this->assertSame('*M*', substr($mov, 0, 3));
        $this->assertSame($cbuDestino, substr($mov, 3, 22));
        $this->assertSame('00000000001234567', substr($mov, 25, 17));
        $this->assertSame(str_repeat(' ', 60), substr($mov, 42, 60));
        $this->assertSame(str_repeat('0', 12), substr($mov, 144, 12));
        $this->assertSame(str_repeat('0', 10), substr($mov, 168, 10));
    }

    public function test_no_inserta_nul_como_hacia_sprintf_c(): void
    {
        $roto = sprintf(
            "%-3.3s%-22.22s%c%08d%c%-61.61s\n",
            '*U*',
            '2850651330094012507151',
            'D',
            20260901,
            'N',
            'obs'
        );
        $this->assertSame("\0", $roto[25]);

        $ok = InterbankingArchivoPagoFormatoSupport::generarArchivo(
            '2850651330094012507151',
            20260901,
            1,
            'obs',
            []
        );
        $this->assertSame('D', $ok[25]);
        $this->assertSame('N', $ok[34]);
    }
}
