<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Uif;

use App\Support\Uif\LocalidadUifProvinciaAnitaSupport;
use PHPUnit\Framework\TestCase;

final class LocalidadUifProvinciaAnitaSupportTest extends TestCase
{
    /** @return array<string, int> */
    private function mapaProvinciasUif(): array
    {
        return LocalidadUifProvinciaAnitaSupport::mapaCodigoAnitaAId([
            (object) ['id' => 1, 'codigo' => '1'],
            (object) ['id' => 2, 'codigo' => '2'],
            (object) ['id' => 6, 'codigo' => '4'],
            (object) ['id' => 14, 'codigo' => '5'],
            (object) ['id' => 25, 'codigo' => '0'],
            (object) ['id' => 26, 'codigo' => '0'],
        ]);
    }

    public function test_mapa_omite_codigo_cero_y_resuelve_por_codigo_anita_no_por_id(): void
    {
        $mapa = $this->mapaProvinciasUif();

        $this->assertSame(1, $mapa['1']);
        $this->assertSame(2, $mapa['2']);
        $this->assertSame(6, $mapa['4']);
        $this->assertSame(14, $mapa['5']);
        $this->assertArrayNotHasKey('0', $mapa);
        $this->assertArrayNotHasKey(6, $mapa);
    }

    public function test_cordoba_anita_4_mapea_a_provincia_uif_id_6(): void
    {
        $mapa = $this->mapaProvinciasUif();

        $this->assertSame(6, LocalidadUifProvinciaAnitaSupport::provinciaUifIdDesdeCodigoAnita(4, $mapa));
        $this->assertSame(6, LocalidadUifProvinciaAnitaSupport::provinciaUifIdDesdeCodigoAnita('4', $mapa));
        $this->assertSame(6, LocalidadUifProvinciaAnitaSupport::provinciaUifIdDesdeCodigoAnita('04', $mapa));
    }

    public function test_caba_y_buenos_aires_siguen_mapeando(): void
    {
        $mapa = $this->mapaProvinciasUif();

        $this->assertSame(1, LocalidadUifProvinciaAnitaSupport::provinciaUifIdDesdeCodigoAnita(1, $mapa));
        $this->assertSame(2, LocalidadUifProvinciaAnitaSupport::provinciaUifIdDesdeCodigoAnita(2, $mapa));
    }

    public function test_codigo_vacio_cero_o_desconocido_queda_sin_provincia(): void
    {
        $mapa = $this->mapaProvinciasUif();

        $this->assertNull(LocalidadUifProvinciaAnitaSupport::provinciaUifIdDesdeCodigoAnita(null, $mapa));
        $this->assertNull(LocalidadUifProvinciaAnitaSupport::provinciaUifIdDesdeCodigoAnita('', $mapa));
        $this->assertNull(LocalidadUifProvinciaAnitaSupport::provinciaUifIdDesdeCodigoAnita(0, $mapa));
        $this->assertNull(LocalidadUifProvinciaAnitaSupport::provinciaUifIdDesdeCodigoAnita('0', $mapa));
        $this->assertNull(LocalidadUifProvinciaAnitaSupport::provinciaUifIdDesdeCodigoAnita(99, $mapa));
    }
}
