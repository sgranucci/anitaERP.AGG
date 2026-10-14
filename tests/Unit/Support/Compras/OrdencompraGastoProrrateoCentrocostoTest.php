<?php

namespace Tests\Unit\Support\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Articulo;
use App\Models\Contable\Centrocosto;
use App\Models\Stock\Articulo;
use App\Support\Compras\OrdencompraContratoRutaFacturaSupport;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class OrdencompraGastoProrrateoCentrocostoTest extends TestCase
{
    public function test_oc_con_dos_centros_prorratea_aunque_el_neto_tenga_cuenta(): void
    {
        $oc = $this->ocConLineas([
            ['cc' => 85, 'precio' => 100],
            ['cc' => 90, 'precio' => 300],
        ]);

        $this->assertTrue(OrdencompraContratoRutaFacturaSupport::prorrateaGastoPorCentrocosto($oc));

        $lineas = OrdencompraContratoRutaFacturaSupport::lineasDebeNetoDesdeArticulosOc(
            $oc,
            40,
            1,
            0,
            'n/c',
            697,
        );

        $this->assertCount(2, $lineas);
        $this->assertSame(697, $lineas[0]['cuentacontable_id']);
        $this->assertSame(697, $lineas[1]['cuentacontable_id']);
        $this->assertSame(10.0, $lineas[0]['importe']);
        $this->assertSame(30.0, $lineas[1]['importe']);
        $this->assertNotSame($lineas[0]['centrocosto_id'], $lineas[1]['centrocosto_id']);
    }

    public function test_sin_cuenta_en_el_neto_abre_la_cuenta_de_cada_articulo(): void
    {
        $oc = $this->ocConLineas([
            ['cc' => 85, 'precio' => 100, 'cuenta' => 11],
            ['cc' => 90, 'precio' => 100, 'cuenta' => 22],
        ]);

        $lineas = OrdencompraContratoRutaFacturaSupport::lineasDebeNetoDesdeArticulosOc(
            $oc,
            50,
            1,
            0,
            'n/c',
            0,
        );

        $this->assertCount(2, $lineas);
        $this->assertSame(11, $lineas[0]['cuentacontable_id']);
        $this->assertSame(22, $lineas[1]['cuentacontable_id']);
        $this->assertSame(25.0, $lineas[0]['importe']);
        $this->assertSame(25.0, $lineas[1]['importe']);
    }

    public function test_un_solo_centro_no_es_prorrateo(): void
    {
        $oc = $this->ocConLineas([
            ['cc' => 85, 'precio' => 100],
            ['cc' => 85, 'precio' => 50],
        ]);

        $this->assertFalse(OrdencompraContratoRutaFacturaSupport::prorrateaGastoPorCentrocosto($oc));
    }

    /**
     * @param  list<array{cc: int, precio: float, cuenta?: int}>  $filas
     */
    private function ocConLineas(array $filas): Ordencompra
    {
        $oc = new Ordencompra;
        $oc->id = 1;
        $lineas = new Collection;
        foreach ($filas as $i => $fila) {
            $cc = new Centrocosto;
            $cc->id = $i + 1;
            $cc->codigo = (string) $fila['cc'];
            $cc->tipoiva = 'Indirecto';

            $linea = new Ordencompra_Articulo;
            $linea->cantidad = 1;
            $linea->precio = $fila['precio'];
            $linea->moneda_id = 1;
            $linea->cotizacion = 1;
            $linea->centrocostodestino_id = $cc->id;
            $linea->setRelation('centrocostos_destino', $cc);

            if (isset($fila['cuenta'])) {
                $articulo = new Articulo;
                $articulo->cuentacontablecompra_id = $fila['cuenta'];
                $articulo->setRelation('articulo_cuentacontables', new Collection);
                $linea->setRelation('articulos', $articulo);
            }

            $lineas->push($linea);
        }

        $oc->setRelation('ordencompra_articulos', $lineas);
        $oc->setRelation('centrocostos', $lineas->first()->centrocostos_destino);

        return $oc;
    }
}
