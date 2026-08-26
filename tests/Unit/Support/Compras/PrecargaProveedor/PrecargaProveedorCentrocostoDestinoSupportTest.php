<?php

namespace Tests\Unit\Support\Compras\PrecargaProveedor;

use App\Support\Compras\PrecargaProveedor\PrecargaProveedorCentrocostoDestinoSupport;
use PHPUnit\Framework\TestCase;

class PrecargaProveedorCentrocostoDestinoSupportTest extends TestCase
{
    public function test_usa_destino_de_linea_aunque_la_cabecera_tenga_el_origen(): void
    {
        $cabecera = (object) [
            'penmp_ccosto' => '90',
            'penmp_ccosto_dest' => '90',
        ];
        $items = [
            (object) ['penvp_ccosto' => '89'],
        ];

        $this->assertSame(
            '89',
            PrecargaProveedorCentrocostoDestinoSupport::codigoDesdeOcAnita($cabecera, $items)
        );
    }

    public function test_cae_a_cabecera_dest_si_las_lineas_no_tienen_cc(): void
    {
        $cabecera = (object) [
            'penmp_ccosto' => '90',
            'penmp_ccosto_dest' => '96',
        ];
        $items = [
            (object) ['penvp_ccosto' => '0'],
        ];

        $this->assertSame(
            '96',
            PrecargaProveedorCentrocostoDestinoSupport::codigoDesdeOcAnita($cabecera, $items)
        );
    }

    public function test_cae_a_origen_si_no_hay_destino(): void
    {
        $cabecera = (object) [
            'penmp_ccosto' => '90',
            'penmp_ccosto_dest' => '',
        ];

        $this->assertSame(
            '90',
            PrecargaProveedorCentrocostoDestinoSupport::codigoDesdeOcAnita($cabecera, [])
        );
    }

    public function test_estampa_destino_de_linea_en_cabecera_leida(): void
    {
        $cabecera = (object) [
            'penmp_ccosto' => '90',
            'penmp_ccosto_dest' => '90',
        ];
        $items = [
            (object) ['penvp_ccosto' => '89'],
        ];

        $resultado = PrecargaProveedorCentrocostoDestinoSupport::aplicarDestinoEnCabecera($cabecera, $items);

        $this->assertSame('89', $resultado->penmp_ccosto_dest);
        $this->assertSame('89', $cabecera->penmp_ccosto_dest);
    }
}
