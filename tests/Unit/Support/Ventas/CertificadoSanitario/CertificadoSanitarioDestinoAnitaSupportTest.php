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
        self::assertNull($dest['senasa']);
    }

    public function test_senasa_sale_de_dest_cod_localidad(): void
    {
        $dest = CertificadoSanitarioDestinoAnitaSupport::desdeFilaAnita((object) [
            'dest_destino' => '349',
            'dest_localidad' => 'TORTUGUITAS',
            'dest_provincia' => 'BS AS',
            'dest_patagonico' => 'N',
            'dest_cod_localidad' => '1139',
        ]);

        self::assertSame(1139, $dest['senasa']);
        self::assertSame('TORTUGUITAS', $dest['localidad']);
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

    public function test_bs_as_equivale_a_buenos_aires(): void
    {
        $eq = CertificadoSanitarioDestinoAnitaSupport::equivalentesProvincia('BS-AS');
        self::assertContains('BUENOS AIRES', $eq);
        $eq2 = CertificadoSanitarioDestinoAnitaSupport::equivalentesProvincia('BS AS');
        self::assertContains('BUENOS AIRES', $eq2);
        self::assertSame('BSAS', CertificadoSanitarioDestinoAnitaSupport::compactarProvincia('BS-AS'));
    }

    public function test_sin_zonavta_id_usa_codigo_anita_no_un_id(): void
    {
        self::assertSame(273, CertificadoSanitarioDestinoAnitaSupport::codigoAnitaZona(273, null));
        self::assertSame(0, CertificadoSanitarioDestinoAnitaSupport::codigoAnitaZona(null, null));
    }

    public function test_senasa_xml_prioriza_cliente_y_cae_a_destino(): void
    {
        self::assertSame(171, CertificadoSanitarioDestinoAnitaSupport::senasaLocalidadXml(171, 1139));
        self::assertSame(1139, CertificadoSanitarioDestinoAnitaSupport::senasaLocalidadXml(null, 1139));
        self::assertSame(1139, CertificadoSanitarioDestinoAnitaSupport::senasaLocalidadXml(0, 1139));
        self::assertNull(CertificadoSanitarioDestinoAnitaSupport::senasaLocalidadXml(null, null));
        self::assertNull(CertificadoSanitarioDestinoAnitaSupport::senasaLocalidadXml(0, 0));
    }
}
