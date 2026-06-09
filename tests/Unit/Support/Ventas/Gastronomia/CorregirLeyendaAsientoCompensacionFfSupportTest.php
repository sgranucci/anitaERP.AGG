<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\CorregirLeyendaAsientoCompensacionFfSupport;
use PHPUnit\Framework\TestCase;

class CorregirLeyendaAsientoCompensacionFfSupportTest extends TestCase
{
    public function test_corregir_observacion_cabecera_reemplaza_sufijo_antiguo(): void
    {
        $support = new CorregirLeyendaAsientoCompensacionFfSupport;
        $vieja = 'Cierre Waitry jornada 2026-06-03 — 3 — Compensación efectivo no facturado (Waitry) vs fondo fijo máquinas';

        $this->assertSame(
            'Cierre Waitry jornada 2026-06-03 — 3 — Reduccion FF Maquinas',
            $support->corregirObservacionCabecera($vieja),
        );
    }

    public function test_corregir_titulo_snapshot(): void
    {
        $support = new CorregirLeyendaAsientoCompensacionFfSupport;

        $this->assertSame(
            '3 — Reduccion FF Maquinas',
            $support->corregirTituloSnapshot('3 — Compensación efectivo no facturado (Waitry) vs fondo fijo máquinas'),
        );
    }

    public function test_sanitizar_desc_mov_anita(): void
    {
        $support = new CorregirLeyendaAsientoCompensacionFfSupport;

        $this->assertSame('Reduccion FF Maquinas', $support->sanitizarDescMovAnita('Reduccion FF Maquinas'));
    }
}
