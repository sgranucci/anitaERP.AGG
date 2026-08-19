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
}
