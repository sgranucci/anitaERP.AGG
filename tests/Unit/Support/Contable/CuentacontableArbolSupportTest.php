<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\CuentacontableArbolSupport;
use App\Support\Contable\CuentacontableGemeloSupport;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CuentacontableArbolSupportTest extends TestCase
{
    public function test_candidatos_padre_van_de_especifico_a_amplio(): void
    {
        $cands = CuentacontableArbolSupport::candidatosPadre('111010001');

        $this->assertSame('111010000', $cands[0]);
        $this->assertContains('111000000', $cands);
        $this->assertContains('100000000', $cands);
        $this->assertNotContains('111010001', $cands);
    }

    public function test_codigo_totalizadora_de_grupo(): void
    {
        $this->assertSame('111019999', CuentacontableArbolSupport::codigoTotalizadoraDeGrupo('111010000'));
        $this->assertSame('111999999', CuentacontableArbolSupport::codigoTotalizadoraDeGrupo('111000000'));
        $this->assertNull(CuentacontableArbolSupport::codigoTotalizadoraDeGrupo('111010001'));
    }

    public function test_arma_arbol_caja_sin_totalizadoras(): void
    {
        $arbol = CuentacontableArbolSupport::armar($this->ramaCaja(), false);

        $this->assertCount(1, $arbol);
        $this->assertSame('CAJA Y BANCOS', $arbol[0]['nombre']);
        $this->assertSame('CAJA', $arbol[0]['hijos'][0]['nombre']);
        $this->assertSame('CAJA PESOS', $arbol[0]['hijos'][0]['hijos'][0]['nombre']);
        $this->assertSame('CAJA DOLAR', $arbol[0]['hijos'][0]['hijos'][1]['nombre']);
        $this->assertCount(2, $arbol[0]['hijos'][0]['hijos']);
    }

    public function test_totalizadora_cuelga_del_grupo(): void
    {
        $arbol = CuentacontableArbolSupport::armar($this->ramaCaja(), true);
        $caja = $arbol[0]['hijos'][0];
        $nombres = array_column($caja['hijos'], 'nombre');

        $this->assertContains('TOTAL CAJA', $nombres);
        $this->assertContains('CAJA PESOS', $nombres);
    }

    public function test_podar_por_busqueda_deja_ancestros(): void
    {
        $arbol = CuentacontableArbolSupport::armar($this->ramaCaja(), false);
        $podado = CuentacontableArbolSupport::podarPorBusqueda($arbol, 'pesos');

        $this->assertSame('CAJA Y BANCOS', $podado[0]['nombre']);
        $this->assertTrue($podado[0]['expandido']);
        $this->assertSame('CAJA PESOS', $podado[0]['hijos'][0]['hijos'][0]['nombre']);
        $this->assertTrue($podado[0]['hijos'][0]['hijos'][0]['coincide']);
        $this->assertCount(1, $podado[0]['hijos'][0]['hijos']);
    }

    public function test_parent_id_manual_pisa_el_prefijo_del_codigo(): void
    {
        $cuentas = $this->ramaCaja();
        $cuentas[2]->parent_id = 1;

        $arbol = CuentacontableArbolSupport::armar($cuentas, false);
        $hijosCajaBancos = array_column($arbol[0]['hijos'], 'nombre');

        $this->assertContains('CAJA PESOS', $hijosCajaBancos);
        $this->assertContains('CAJA', $hijosCajaBancos);
    }

    public function test_aplanar_trae_ancestros_y_hijos_para_el_preview(): void
    {
        $plano = CuentacontableArbolSupport::aplanar(
            CuentacontableArbolSupport::armar($this->ramaCaja(), false)
        );
        $pesos = collect($plano)->firstWhere('nombre', 'CAJA PESOS');

        $this->assertNotNull($pesos);
        $this->assertSame(['CAJA Y BANCOS', 'CAJA'], array_column($pesos['ancestros'], 'nombre'));
        $this->assertSame([], $pesos['hijo_ids']);

        $caja = collect($plano)->firstWhere('nombre', 'CAJA');
        $this->assertContains(3, $caja['hijo_ids']);
        $this->assertContains(4, $caja['hijo_ids']);
    }

    public function test_payload_gemelo_solo_para_titulo(): void
    {
        $this->assertNull(CuentacontableGemeloSupport::payloadTotalizadora([
            'tipocuenta' => '1',
            'codigo' => '111010001',
            'nombre' => 'CAJA PESOS',
        ]));

        $payload = CuentacontableGemeloSupport::payloadTotalizadora([
            'empresa_id' => 1,
            'rubrocontable_id' => 1,
            'tipocuenta' => '2',
            'codigo' => '111010000',
            'nombre' => 'CAJA',
            'nivel' => 4,
            'monetaria' => 'N',
        ]);

        $this->assertSame('111019999', $payload['codigo']);
        $this->assertSame('TOTAL CAJA', $payload['nombre']);
        $this->assertSame('3', $payload['tipocuenta']);
        $this->assertSame(4, $payload['nivel']);
    }

    /**
     * @return Collection<int, object>
     */
    private function ramaCaja(): Collection
    {
        $filas = [
            ['id' => 1, 'codigo' => '111000000', 'nombre' => 'CAJA Y BANCOS', 'nivel' => 3, 'tipocuenta' => '2'],
            ['id' => 2, 'codigo' => '111010000', 'nombre' => 'CAJA', 'nivel' => 4, 'tipocuenta' => '2'],
            ['id' => 3, 'codigo' => '111010001', 'nombre' => 'CAJA PESOS', 'nivel' => 5, 'tipocuenta' => '1'],
            ['id' => 4, 'codigo' => '111010002', 'nombre' => 'CAJA DOLAR', 'nivel' => 5, 'tipocuenta' => '1'],
            ['id' => 5, 'codigo' => '111019999', 'nombre' => 'TOTAL CAJA', 'nivel' => 4, 'tipocuenta' => '3'],
        ];

        return collect(array_map(static function (array $f) {
            return (object) array_merge($f, [
                'empresa_id' => 1,
                'manejaccosto' => 'N',
                'rubrocontables' => (object) ['nombre' => 'Activo'],
                'conceptogastos' => null,
            ]);
        }, $filas));
    }
}
