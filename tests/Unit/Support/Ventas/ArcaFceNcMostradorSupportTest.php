<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\ArcaFceNcMostradorSupport;
use PHPUnit\Framework\TestCase;

final class ArcaFceNcMostradorSupportTest extends TestCase
{
    public function test_parsear_codigo_fce(): void
    {
        $asoc = ArcaFceNcMostradorSupport::parsearCodigoComprobante('FCE A-00008-00001234');
        self::assertNotNull($asoc);
        self::assertSame(201, $asoc['tipo']);
        self::assertSame(8, $asoc['ptovta']);
        self::assertSame(1234, $asoc['nro']);
        self::assertTrue(ArcaFceNcMostradorSupport::esTipoFacturaFce($asoc['tipo']));
    }

    public function test_anulacion_leyenda_roundtrip(): void
    {
        $leyenda = ArcaFceNcMostradorSupport::anexarMarcaAnulacionLeyenda('Bonificación', 'N');
        self::assertSame('N', ArcaFceNcMostradorSupport::leerAnulacionDesdeLeyenda($leyenda));
        self::assertSame('Bonificación', ArcaFceNcMostradorSupport::quitarMarcaAnulacionLeyenda($leyenda));
    }

    public function test_normalizar_anulacion(): void
    {
        self::assertSame('S', ArcaFceNcMostradorSupport::normalizarAnulacion('s'));
        self::assertSame('N', ArcaFceNcMostradorSupport::normalizarAnulacion('N'));
        self::assertNull(ArcaFceNcMostradorSupport::normalizarAnulacion(''));
        self::assertNull(ArcaFceNcMostradorSupport::normalizarAnulacion('X'));
    }
}
