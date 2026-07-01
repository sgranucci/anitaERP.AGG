<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\GastronomiaAnitaComprobantePkSupport;
use PHPUnit\Framework\TestCase;

final class GastronomiaAnitaComprobantePkSupportTest extends TestCase
{
    public function test_claves_alias_conciliacion_incluye_fak_y_fac_mismo_numero(): void
    {
        $aliases = GastronomiaAnitaComprobantePkSupport::clavesAliasConciliacionDesdeClave('FAC|B|31|14190');

        $this->assertContains('FAK|B|31|14190', $aliases);
    }

    public function test_cabeceras_unicas_desde_mapa_no_duplica_fak_fac(): void
    {
        $cabFak = (object) [
            'ven_tipo' => 'FAK',
            'ven_letra' => 'B',
            'ven_sucursal' => '31',
            'ven_nro' => 14190,
            'ven_monto' => 3900,
        ];
        $cabFac = (object) [
            'ven_tipo' => 'FAC',
            'ven_letra' => 'B',
            'ven_sucursal' => '31',
            'ven_nro' => 14190,
            'ven_monto' => 3900,
        ];

        $unicas = GastronomiaAnitaComprobantePkSupport::cabecerasUnicasDesdeMapa([
            'FAK|B|31|14190' => $cabFak,
            'FAC|B|31|14190' => $cabFac,
        ]);

        $this->assertCount(1, $unicas);
        $this->assertSame('FAK', $unicas[0]->ven_tipo);
    }
}
