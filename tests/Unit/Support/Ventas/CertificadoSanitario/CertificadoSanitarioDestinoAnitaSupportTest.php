<?php

namespace Tests\Unit\Support\Ventas\CertificadoSanitario;

use App\Support\Ventas\CertificadoSanitario\CertificadoSanitarioDestinoAnitaSupport;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD). Mapeo de la tabla Anita destino como p-certsan.c / certsan.fc.
 */
class CertificadoSanitarioDestinoAnitaSupportTest extends TestCase
{
    public function test_destino_304_es_zapala_patagonico(): void
    {
        $dest = CertificadoSanitarioDestinoAnitaSupport::desdeFilaAnita((object) [
            'dest_destino' => '304',
            'dest_localidad' => 'ZAPALA',
            'dest_provincia' => 'NEUQUEN',
            'dest_patagonico' => 'S',
        ]);

        self::assertNotNull($dest);
        self::assertSame('ZAPALA', $dest['localidad']);
        self::assertSame('NEUQUEN', $dest['provincia']);
        self::assertTrue($dest['patagonico']);
    }

    public function test_no_usa_localidad_del_cliente(): void
    {
        $dest = CertificadoSanitarioDestinoAnitaSupport::desdeFilaAnita((object) [
            'dest_destino' => '304',
            'dest_localidad' => 'ZAPALA',
            'dest_provincia' => 'NEUQUEN',
            'dest_patagonico' => 'S',
        ]);

        self::assertNotSame('VILLA PEHUENIA', $dest['localidad'] ?? null);
    }

    public function test_sin_localidad_no_es_destino(): void
    {
        self::assertNull(CertificadoSanitarioDestinoAnitaSupport::desdeFilaAnita((object) [
            'dest_destino' => '304',
            'dest_localidad' => '   ',
            'dest_provincia' => 'NEUQUEN',
            'dest_patagonico' => 'N',
        ]));
    }
}
