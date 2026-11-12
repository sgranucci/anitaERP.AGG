<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Contable\IngresosBrutos;

use App\Models\Configuracion\Provincia;
use App\Support\Contable\IngresosBrutos\IngresosBrutosProvinciaAnitaSupport;
use PHPUnit\Framework\TestCase;

final class IngresosBrutosProvinciaAnitaSupportTest extends TestCase
{
    public function test_incluir_sin_codigo_false_si_no_es_rio_negro(): void
    {
        $prov = new Provincia([
            'nombre' => 'Buenos Aires',
            'jurisdiccion' => '902',
            'codigoexterno' => 2,
            'codigo' => '2',
        ]);

        $this->assertFalse(IngresosBrutosProvinciaAnitaSupport::incluirSinCodigoProvincia($prov, 1));
    }

    public function test_incluir_sin_codigo_false_sin_empresa(): void
    {
        $prov = new Provincia([
            'nombre' => 'Rio Negro',
            'jurisdiccion' => '916',
            'codigoexterno' => 3,
            'codigo' => '3',
        ]);

        $this->assertFalse(IngresosBrutosProvinciaAnitaSupport::incluirSinCodigoProvincia($prov, 0));
        $this->assertFalse(IngresosBrutosProvinciaAnitaSupport::incluirSinCodigoProvincia(null, 1));
    }

    public function test_codigos_anita_rio_negro_incluye_3_y_916(): void
    {
        $prov = new Provincia([
            'nombre' => 'Rio Negro',
            'jurisdiccion' => '916',
            'codigoexterno' => 3,
            'codigo' => '3',
        ]);
        $codigos = IngresosBrutosProvinciaAnitaSupport::codigosAnita($prov);

        $this->assertContains(3, $codigos);
        $this->assertContains(916, $codigos);
    }
}
