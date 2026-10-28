<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorCentrocostoSupport;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorCentrocostoSupportTest extends TestCase
{
    public function test_prioriza_destino_de_linea_sobre_cabecera_y_proveedor(): void
    {
        $oc = (object) [
            'centrocosto_id' => 11,
            'ordencompra_articulos' => [
                (object) ['centrocostodestino_id' => 11],
            ],
        ];
        $comprobante = (object) [
            'ordencompras' => $oc,
            'proveedores' => (object) ['centrocostocompra_id' => 1],
        ];

        $this->assertSame(11, ComprobanteProveedorCentrocostoSupport::resolverDesdeComprobante($comprobante));
    }

    public function test_usa_destino_de_linea_aunque_la_cabecera_sea_otro_cc(): void
    {
        $oc = (object) [
            'centrocosto_id' => 5,
            'ordencompra_articulos' => [
                (object) ['centrocostodestino_id' => 11],
            ],
        ];

        $this->assertSame(11, ComprobanteProveedorCentrocostoSupport::resolverDesdeOc($oc));
    }

    public function test_cae_a_cabecera_si_las_lineas_no_tienen_destino(): void
    {
        $oc = (object) [
            'centrocosto_id' => 11,
            'ordencompra_articulos' => [
                (object) ['centrocostodestino_id' => null],
            ],
        ];

        $this->assertSame(11, ComprobanteProveedorCentrocostoSupport::resolverDesdeOc($oc));
    }

    public function test_sin_oc_usa_default_del_proveedor(): void
    {
        $comprobante = (object) [
            'ordencompras' => null,
            'proveedores' => (object) ['centrocostocompra_id' => 1],
        ];

        $this->assertSame(1, ComprobanteProveedorCentrocostoSupport::resolverDesdeComprobante($comprobante));
    }

    public function test_oc_prorrateada_parte_el_iva_en_cada_centro(): void
    {
        $pesos = ComprobanteProveedorCentrocostoSupport::pesosDestinoOc((object) [
            'ordencompra_articulos' => [
                (object) ['centrocostodestino_id' => 5, 'cantidad' => 1, 'precio' => 100],
                (object) ['centrocostodestino_id' => 9, 'cantidad' => 3, 'precio' => 100],
            ],
        ]);

        $trozos = ComprobanteProveedorCentrocostoSupport::trozosImpuesto(true, 0, $pesos, 100.0);

        $this->assertSame([
            ['centrocosto_id' => 5, 'importe' => 25.0],
            ['centrocosto_id' => 9, 'importe' => 75.0],
        ], $trozos);
        $this->assertSame(100.0, round(array_sum(array_column($trozos, 'importe')), 2));
    }

    public function test_oc_con_un_centro_imputa_todo_el_iva_ahi(): void
    {
        $trozos = ComprobanteProveedorCentrocostoSupport::trozosImpuesto(true, 0, [5 => 472306.99], 124535.76);

        $this->assertSame([
            ['centrocosto_id' => 5, 'importe' => 124535.76],
        ], $trozos);
    }

    public function test_cuenta_sin_centro_no_hereda_el_de_la_oc(): void
    {
        $trozos = ComprobanteProveedorCentrocostoSupport::trozosImpuesto(false, 0, [5 => 10, 9 => 30], 80.0);

        $this->assertSame([
            ['centrocosto_id' => 0, 'importe' => 80.0],
        ], $trozos);
    }

    public function test_concepto_ya_repartido_no_se_vuelve_a_partir(): void
    {
        $trozos = ComprobanteProveedorCentrocostoSupport::trozosImpuesto(
            true,
            0,
            [5 => 10, 9 => 30],
            25.0,
            9,
            true,
        );

        $this->assertSame([
            ['centrocosto_id' => 9, 'importe' => 25.0],
        ], $trozos);
    }

    public function test_iva_sin_oc_hereda_el_unico_centro_del_gasto(): void
    {
        $pesos = ComprobanteProveedorCentrocostoSupport::pesosIvaSinOc([210 => 7960000.0], 0);

        $this->assertSame([210 => 7960000.0], $pesos);
    }

    public function test_iva_sin_oc_se_abre_como_las_cuentas_de_gasto(): void
    {
        $pesos = ComprobanteProveedorCentrocostoSupport::pesosIvaSinOc([
            210 => 6000.0,
            88 => 4000.0,
        ], 15);

        $this->assertSame([
            210 => 6000.0,
            88 => 4000.0,
        ], $pesos);
    }

    public function test_iva_con_centro_propio_no_pisa_el_unico_del_gasto(): void
    {
        $this->assertSame([], ComprobanteProveedorCentrocostoSupport::pesosIvaSinOc([210 => 100.0], 88));
    }

    public function test_el_ultimo_centro_absorbe_el_redondeo(): void
    {
        $trozos = ComprobanteProveedorCentrocostoSupport::repartirImporte(10.0, [1 => 1, 2 => 1, 3 => 1]);

        $this->assertSame(10.0, round(array_sum(array_column($trozos, 'importe')), 2));
        $this->assertSame(3.34, $trozos[2]['importe']);
    }
}
