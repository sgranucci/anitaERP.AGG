<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\ArcaCaeaInformeUiSupport;
use PHPUnit\Framework\TestCase;

final class ArcaCaeaInformeUiSupportTest extends TestCase
{
    public function test_puede_presentar_con_pendientes_anita_sin_consulta_arca(): void
    {
        self::assertTrue(ArcaCaeaInformeUiSupport::puedePresentarAhora([
            'total' => 55,
            'pendientes' => 55,
            'errores' => 0,
            'informables_ahora' => 0,
            'ultimos_arca' => [],
        ]));
    }

    public function test_no_puede_presentar_si_esta_bloqueado_por_otra_quincena(): void
    {
        self::assertFalse(ArcaCaeaInformeUiSupport::puedePresentarAhora([
            'total' => 1,
            'pendientes' => 1,
            'errores' => 0,
            'informables_ahora' => 0,
            'ultimos_arca' => [
                ['pto_vta' => 30, 'tipo_afip' => 1, 'ultimo_arca' => 1],
            ],
        ]));
    }

    public function test_leyenda_pide_avion_si_hay_pendientes_sin_consulta_arca(): void
    {
        $leyenda = ArcaCaeaInformeUiSupport::leyendaFaltante([
            'total' => 55,
            'pendientes' => 55,
            'errores' => 0,
            'informables_ahora' => 0,
            'ultimos_arca' => [],
        ]);

        self::assertStringContainsString('sin consulta a ARCA', $leyenda);
        self::assertStringContainsString('avión azul', $leyenda);
    }

    public function test_puede_presentar_si_hay_informable_ahora(): void
    {
        self::assertTrue(ArcaCaeaInformeUiSupport::puedePresentarAhora([
            'total' => 55,
            'pendientes' => 10,
            'errores' => 0,
            'informables_ahora' => 3,
            'ultimos_arca' => [
                ['pto_vta' => 5, 'tipo_afip' => 1, 'ultimo_arca' => 360385],
            ],
        ]));
    }
}
