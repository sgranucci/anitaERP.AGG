<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\OrdencompraLegajoAnitaScanFacturaSupport;
use PHPUnit\Framework\TestCase;

class OrdencompraLegajoAnitaScanDocumentoIdCompatibleTest extends TestCase
{
    public function test_prioriza_documento_explicito(): void
    {
        $scans = [
            $this->scan(10, 'FC', 'A 1151-00004870'),
            $this->scan(20, 'NC', 'A 1151-00000437'),
        ];

        $this->assertSame(
            20,
            OrdencompraLegajoAnitaScanFacturaSupport::documentoIdCompatible(
                $scans,
                'A',
                1151,
                4870,
                'FC',
                20
            )
        );
    }

    public function test_matchea_por_letra_sucursal_numero_y_no_toma_el_primero(): void
    {
        $scans = [
            $this->scan(10, 'FC', 'FPB A 1151-00004870'),
            $this->scan(20, 'NC', 'CPB A 1151-00000437'),
            $this->scan(30, 'NC', 'CPB A 1151-00001261'),
        ];

        $this->assertSame(
            20,
            OrdencompraLegajoAnitaScanFacturaSupport::documentoIdCompatible(
                $scans,
                'A',
                1151,
                437,
                'NC'
            )
        );
    }

    public function test_con_varios_scans_sin_numero_no_elige_arbitrario(): void
    {
        $scans = [
            $this->scan(10, 'FC', 'A 1151-00004870'),
            $this->scan(20, 'NC', 'A 1151-00000437'),
        ];

        $this->assertNull(
            OrdencompraLegajoAnitaScanFacturaSupport::documentoIdCompatible($scans)
        );
    }

    public function test_unico_scan_sin_numero_usa_ese(): void
    {
        $scans = [
            $this->scan(99, 'FC', 'A 0001-00000001'),
        ];

        $this->assertSame(
            99,
            OrdencompraLegajoAnitaScanFacturaSupport::documentoIdCompatible($scans)
        );
    }

    public function test_sin_match_de_numero_no_cae_al_primero(): void
    {
        $scans = [
            $this->scan(10, 'FC', 'A 1151-00004870'),
            $this->scan(20, 'NC', 'A 1151-00000437'),
        ];

        $this->assertNull(
            OrdencompraLegajoAnitaScanFacturaSupport::documentoIdCompatible(
                $scans,
                'A',
                1151,
                99999,
                'NC'
            )
        );
    }

    public function test_parsea_anita_ref(): void
    {
        $this->assertSame(365039, OrdencompraLegajoAnitaScanFacturaSupport::documentoIdDesdeAnitaRef('anita-365039'));
        $this->assertSame(365039, OrdencompraLegajoAnitaScanFacturaSupport::documentoIdDesdeAnitaRef('365039'));
        $this->assertSame(0, OrdencompraLegajoAnitaScanFacturaSupport::documentoIdDesdeAnitaRef(''));
    }

    /** @return array<string, mixed> */
    private function scan(int $documentoId, string $tipo, string $etiqueta): array
    {
        return [
            'id' => 'anita-'.$documentoId,
            'documento_id' => $documentoId,
            'tipo' => $tipo,
            'numero' => $etiqueta,
            'etiqueta' => $etiqueta,
        ];
    }
}
