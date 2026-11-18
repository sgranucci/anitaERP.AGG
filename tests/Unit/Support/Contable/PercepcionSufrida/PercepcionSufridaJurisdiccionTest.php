<?php

namespace Tests\Unit\Support\Contable\PercepcionSufrida;

use App\Support\Contable\PercepcionSufrida\PercepcionSufridaArchivoSupport;
use App\Support\Contable\PercepcionSufrida\PercepcionSufridaCruceSupport;
use App\Support\Contable\PercepcionSufrida\PercepcionSufridaLineaSupport;
use PHPUnit\Framework\TestCase;

class PercepcionSufridaJurisdiccionTest extends TestCase
{
    public function test_texto_reconoce_neuquen_y_rio_negro(): void
    {
        $this->assertSame(915, PercepcionSufridaLineaSupport::jurisdiccionDesdeTexto('Perc. IIBB Neuquén'));
        $this->assertSame(915, PercepcionSufridaLineaSupport::jurisdiccionDesdeTexto('Perc NQN'));
        $this->assertSame(916, PercepcionSufridaLineaSupport::jurisdiccionDesdeTexto('Perc. Río Negro'));
        $this->assertSame(916, PercepcionSufridaLineaSupport::jurisdiccionDesdeTexto('IIBB 916'));
        $this->assertSame(901, PercepcionSufridaLineaSupport::jurisdiccionDesdeTexto('Perc Capital'));
        $this->assertSame(902, PercepcionSufridaLineaSupport::jurisdiccionDesdeTexto('Perc ARBA'));
    }

    public function test_provincia_usa_codigo_afip(): void
    {
        $fila = (object) [
            'jurisdiccion' => '915',
            'provincia_nombre' => 'Neuquen',
            'provincia_codigo' => '2',
            'codigoexterno' => '2',
            'concepto' => 'Perc. IIBB',
        ];
        $this->assertSame(915, PercepcionSufridaLineaSupport::jurisdiccionDesdeProvincia($fila));

        $rn = (object) [
            'jurisdiccion' => '916',
            'provincia_nombre' => 'Rio Negro',
            'provincia_codigo' => '3',
            'codigoexterno' => '3',
            'concepto' => 'Perc. IIBB',
        ];
        $this->assertSame(916, PercepcionSufridaLineaSupport::jurisdiccionDesdeProvincia($rn));
    }

    public function test_archivo_sifere_incluye_915_y_916(): void
    {
        $base = [
            'cuit' => '30708363037',
            'fecha' => '2023-10-15',
            'sucursal' => 1,
            'nro' => 100,
            'tipo' => 'FAC',
            'letra' => 'A',
            'importe' => 150.25,
        ];
        $txt = PercepcionSufridaArchivoSupport::sifere([
            array_merge($base, ['jurisdiccion' => 915]),
            array_merge($base, ['jurisdiccion' => 916, 'nro' => 101]),
            array_merge($base, ['jurisdiccion' => 902, 'nro' => 102]),
        ]);
        $lineas = array_values(array_filter(explode("\n", $txt)));
        $this->assertCount(3, $lineas);
        $this->assertSame('915', substr($lineas[0], 0, 3));
        $this->assertSame('916', substr($lineas[1], 0, 3));
        $this->assertSame('902', substr($lineas[2], 0, 3));

        $soloRn = PercepcionSufridaArchivoSupport::sifere([
            array_merge($base, ['jurisdiccion' => 915]),
            array_merge($base, ['jurisdiccion' => 916, 'nro' => 101]),
        ], 916);
        $this->assertCount(1, array_values(array_filter(explode("\n", $soloRn))));
        $this->assertSame('916', substr(trim($soloRn), 0, 3));
    }

    public function test_cruce_suma_jurisdicciones_fuera_de_901_902(): void
    {
        $mayor = [
            $this->linea(['importe' => 100.0, 'jurisdiccion' => 915, 'nro' => 1]),
            $this->linea(['importe' => 200.0, 'jurisdiccion' => 916, 'nro' => 2]),
        ];
        $reporte = [
            $this->linea(['importe' => 100.0, 'jurisdiccion' => 915, 'nro' => 1, 'descripcion' => 'Perc NQN']),
            $this->linea(['importe' => 200.0, 'jurisdiccion' => 916, 'nro' => 2, 'descripcion' => 'Perc RN']),
        ];

        $cruce = PercepcionSufridaCruceSupport::cruzar($mayor, $reporte, true);

        $this->assertCount(2, $cruce['cruzados']);
        $this->assertSame([], $cruce['diferencias']);
        $this->assertEqualsWithDelta(100.0, $cruce['totales']['cruzado_por_jurisdiccion'][915], 0.001);
        $this->assertEqualsWithDelta(200.0, $cruce['totales']['cruzado_por_jurisdiccion'][916], 0.001);
        $this->assertEqualsWithDelta(0.0, $cruce['totales']['desvio_cierre'], 0.001);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function linea(array $datos): array
    {
        $linea = PercepcionSufridaLineaSupport::armar(array_merge([
            'tipo' => 'FAC',
            'letra' => 'A',
            'sucursal' => 1,
            'nro' => 1,
            'cuit' => '30708363037',
            'fecha' => '2023-10-10',
        ], $datos));
        $linea['importe_pesos'] = $linea['importe'];
        $linea['moneda_id'] = 1;

        return $linea;
    }
}
