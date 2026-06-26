<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\GastronomiaConciliacionRendgAsientosDiaSupport;
use PHPUnit\Framework\TestCase;

final class GastronomiaConciliacionRendgAsientosDiaSupportTest extends TestCase
{
    public function test_clasifica_asiento_post_cierre(): void
    {
        $tipo = GastronomiaConciliacionRendgAsientosDiaSupport::clasificarObservacionCierreWaitry(
            'Cierre Waitry jornada 2026-06-23 — 1 — Waitry sin facturar (QR / Mercado Pago tras redistribución)',
        );

        $this->assertSame(GastronomiaConciliacionRendgAsientosDiaSupport::TIPO_POST_CIERRE, $tipo);
    }

    public function test_clasifica_asiento_factura_dia(): void
    {
        $tipo = GastronomiaConciliacionRendgAsientosDiaSupport::clasificarObservacionCierreWaitry(
            'Cierre Waitry jornada 2026-06-23 — 2 — Facturación Anita jornada (excl. TOTEM) — ventas / IVA / kiosco',
        );

        $this->assertSame(GastronomiaConciliacionRendgAsientosDiaSupport::TIPO_FACTURA_DIA, $tipo);
    }

    public function test_clasifica_asiento_agregados_migrados(): void
    {
        $tipo = GastronomiaConciliacionRendgAsientosDiaSupport::clasificarObservacionCierreWaitry(
            'Cierre Waitry jornada 2026-06-23 — agregados CAEA migrados (ex 10/06) — Waitry sin facturar (QR / Mercado Pago tras redistribución)',
        );

        $this->assertSame(GastronomiaConciliacionRendgAsientosDiaSupport::TIPO_AGREGADOS_CAEA, $tipo);
    }

    public function test_excluye_compensacion_ff(): void
    {
        $tipo = GastronomiaConciliacionRendgAsientosDiaSupport::clasificarObservacionCierreWaitry(
            'Cierre Waitry jornada 2026-06-23 — 3 — Reduccion FF Maquinas',
        );

        $this->assertNull($tipo);
    }

    public function test_clasifica_asiento_totem_ventas_grabado(): void
    {
        $tipo = GastronomiaConciliacionRendgAsientosDiaSupport::clasificarObservacionCierreWaitry(
            'Cierre Waitry jornada 2026-06-18 — 3 — TOTEM → ventas / IVA / kiosco',
        );

        $this->assertSame(GastronomiaConciliacionRendgAsientosDiaSupport::TIPO_TOTEM_VENTAS, $tipo);
    }

    public function test_clasifica_asiento_totem_puente(): void
    {
        $tipo = GastronomiaConciliacionRendgAsientosDiaSupport::clasificarObservacionCierreWaitry(
            'Cierre Waitry jornada 2026-06-18 — 4 — Puente TOTEM (medio real → TOTEM)',
        );

        $this->assertSame(GastronomiaConciliacionRendgAsientosDiaSupport::TIPO_TOTEM_PUENTE, $tipo);
    }
}
