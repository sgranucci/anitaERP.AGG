<?php

namespace Tests\Unit\Support\Contable\PercepcionSufrida;

use App\Support\Contable\PercepcionSufrida\PercepcionSufridaCruceSupport;
use App\Support\Contable\PercepcionSufrida\PercepcionSufridaLineaSupport;
use PHPUnit\Framework\TestCase;

class PercepcionSufridaCruceTest extends TestCase
{
    public function test_centavos_salen_por_el_importe_del_mayor_y_la_jurisdiccion_del_concepto(): void
    {
        $mayor = [$this->linea([
            'tipo' => 'FGA',
            'letra' => 'A',
            'sucursal' => 4,
            'nro' => 79901,
            'cuit' => '30708363037',
            'importe' => 18759.56,
            'descripcion' => 'COM: 168303',
        ])];
        $reporte = [$this->linea([
            'tipo' => 'FGA',
            'letra' => 'A',
            'sucursal' => 4,
            'nro' => 79901,
            'cuit' => '30708363037',
            'importe' => 18759.54,
            'jurisdiccion' => 902,
            'descripcion' => 'Perc. IBR BS.AS.',
        ])];

        $cruce = PercepcionSufridaCruceSupport::cruzar($mayor, $reporte, true);

        $this->assertCount(1, $cruce['cruzados']);
        $this->assertSame([], $cruce['diferencias']);
        $this->assertSame(902, $cruce['cruzados'][0]['jurisdiccion']);
        $this->assertEqualsWithDelta(18759.56, $cruce['cruzados'][0]['importe_pesos'], 0.001);
    }

    public function test_capital_y_buenos_aires_del_mismo_comprobante_no_se_pisan(): void
    {
        $base = [
            'tipo' => 'FIS',
            'letra' => 'A',
            'sucursal' => 41,
            'nro' => 1122,
            'cuit' => '33641720599',
        ];
        $mayor = [
            $this->linea($base + ['importe' => 111703.50]),
            $this->linea($base + ['importe' => 186.22]),
        ];
        $reporte = [
            $this->linea($base + ['importe' => 111703.50, 'jurisdiccion' => 902, 'descripcion' => 'Perc. IBR BS.AS.']),
            $this->linea($base + ['importe' => 186.17, 'jurisdiccion' => 901, 'descripcion' => 'Perc. IBR Capital']),
        ];

        $cruce = PercepcionSufridaCruceSupport::cruzar($mayor, $reporte, true);

        $this->assertCount(2, $cruce['cruzados']);
        $this->assertSame([], $cruce['diferencias']);
        $porJur = [];
        foreach ($cruce['cruzados'] as $linea) {
            $porJur[$linea['jurisdiccion']] = $linea['importe_pesos'];
        }
        $this->assertEqualsWithDelta(111703.50, $porJur[902], 0.001);
        $this->assertEqualsWithDelta(186.22, $porJur[901], 0.001);
    }

    public function test_anulacion_con_cuit_y_jurisdiccion_entra_sin_concepto(): void
    {
        $mayor = [$this->linea([
            'tipo' => 'EGR',
            'sucursal' => 2,
            'nro' => 10262,
            'cuit' => '30500010084',
            'importe' => -8.42,
            'jurisdiccion' => 902,
            'descripcion' => 'ANULA EGR 10261 Percepcion IIBB BSAS',
        ])];

        $cruce = PercepcionSufridaCruceSupport::cruzar($mayor, [], true);

        $this->assertCount(1, $cruce['cruzados']);
        $this->assertSame([], $cruce['diferencias']);
        $this->assertEqualsWithDelta(-8.42, $cruce['cruzados'][0]['importe_pesos'], 0.001);
        $this->assertSame(902, $cruce['cruzados'][0]['jurisdiccion']);
    }

    public function test_diferencia_grande_sigue_afuera_del_archivo(): void
    {
        $mayor = [$this->linea([
            'tipo' => 'FGA',
            'letra' => 'A',
            'sucursal' => 1,
            'nro' => 10,
            'cuit' => '30708363037',
            'importe' => 1000,
            'jurisdiccion' => 902,
        ])];
        $reporte = [$this->linea([
            'tipo' => 'FGA',
            'letra' => 'A',
            'sucursal' => 1,
            'nro' => 10,
            'cuit' => '30708363037',
            'importe' => 500,
            'jurisdiccion' => 902,
        ])];

        $cruce = PercepcionSufridaCruceSupport::cruzar($mayor, $reporte, true);

        $this->assertSame([], $cruce['cruzados']);
        $this->assertCount(1, $cruce['diferencias']);
        $this->assertEqualsWithDelta(500.0, $cruce['diferencias'][0]['diferencia'], 0.001);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function linea(array $datos): array
    {
        $linea = PercepcionSufridaLineaSupport::armar($datos);
        $linea['importe_pesos'] = $linea['importe'];
        $linea['moneda_id'] = 1;

        return $linea;
    }
}
