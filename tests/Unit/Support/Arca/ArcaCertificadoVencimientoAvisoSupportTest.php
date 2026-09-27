<?php

namespace Tests\Unit\Support\Arca;

use App\Support\Arca\ArcaCertificadoVencimientoAvisoSupport;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class ArcaCertificadoVencimientoAvisoSupportTest extends TestCase
{
    public function test_avisa_dia_por_medio_desde_30_dias_inclusive_vencidos(): void
    {
        self::assertTrue(ArcaCertificadoVencimientoAvisoSupport::correspondeAvisar(30, 30, 2));
        self::assertFalse(ArcaCertificadoVencimientoAvisoSupport::correspondeAvisar(29, 30, 2));
        self::assertTrue(ArcaCertificadoVencimientoAvisoSupport::correspondeAvisar(0, 30, 2));
        self::assertFalse(ArcaCertificadoVencimientoAvisoSupport::correspondeAvisar(-1, 30, 2));
        self::assertTrue(ArcaCertificadoVencimientoAvisoSupport::correspondeAvisar(-2, 30, 2));
        self::assertFalse(ArcaCertificadoVencimientoAvisoSupport::correspondeAvisar(31, 30, 2));
    }

    public function test_dias_calendario_en_argentina(): void
    {
        $tz = new DateTimeZone('America/Argentina/Buenos_Aires');
        $hoy = new DateTimeImmutable('2026-09-27', $tz);
        $vence = (new DateTimeImmutable('2026-09-27 13:44:00', $tz))->getTimestamp();

        self::assertSame(0, ArcaCertificadoVencimientoAvisoSupport::diasCalendario($vence, $hoy));
        self::assertSame(
            -1,
            ArcaCertificadoVencimientoAvisoSupport::diasCalendario($vence, $hoy->modify('+1 day'))
        );
    }

    public function test_agrupa_servicios_del_mismo_certificado(): void
    {
        $tz = new DateTimeZone('America/Argentina/Buenos_Aires');
        $hoy = new DateTimeImmutable('2026-09-27', $tz);
        $ts = (new DateTimeImmutable('2026-09-27 13:44:00', $tz))->getTimestamp();
        $fila = [
            'existe_cert' => true,
            'fingerprint_sha256' => 'abc',
            'valid_to_ts' => $ts,
            'valid_to' => '27/09/2026 13:44',
            'empresa_nombre' => 'REBISCO S.A.',
            'alias' => 'serverbiyemas',
            'cuit' => '30705464592',
        ];

        $sel = ArcaCertificadoVencimientoAvisoSupport::seleccionar([
            $fila + ['etiqueta' => 'Factura electrónica WSFE'],
            $fila + ['etiqueta' => 'Factura electrónica MTXCA'],
        ], 30, 2, $hoy);

        self::assertCount(1, $sel);
        self::assertSame('REBISCO S.A.', $sel[0]['empresa']);
        self::assertStringContainsString('WSFE', $sel[0]['servicios']);
        self::assertStringContainsString('MTXCA', $sel[0]['servicios']);
        self::assertSame('vence hoy', $sel[0]['estado']);
    }
}
