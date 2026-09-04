<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\ArcaCaeaCbteFchHsGenSupport;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ArcaCaeaCbteFchHsGenSupportTest extends TestCase
{
    public function test_resuelve_desde_cbte_fch_hs_gen(): void
    {
        self::assertSame(
            '20260801143522',
            ArcaCaeaCbteFchHsGenSupport::resolverDigits(['cbte_fch_hs_gen' => '2026-08-01 14:35:22'])
        );
    }

    public function test_fallback_fechacomprobante_mediodia(): void
    {
        self::assertSame(
            '20260801120000',
            ArcaCaeaCbteFchHsGenSupport::resolverDigits(['fechacomprobante' => '20260801'])
        );
    }

    public function test_para_wsfe_y_mtxca(): void
    {
        $datos = ['cbte_fch_hs_gen' => '20260801143522'];
        self::assertSame('20260801143522', ArcaCaeaCbteFchHsGenSupport::paraWsfe($datos));
        self::assertSame('2026-08-01T14:35:22', ArcaCaeaCbteFchHsGenSupport::paraMtxca($datos));
    }

    public function test_sin_datos_lanza(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CbteFchHsGen');
        ArcaCaeaCbteFchHsGenSupport::resolverDigits([]);
    }

    public function test_fecha_invalida_lanza(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ArcaCaeaCbteFchHsGenSupport::resolverDigits(['cbte_fch_hs_gen' => '20260230120000']);
    }
}
