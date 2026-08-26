<?php

namespace Tests\Unit\Support\Compras\PrecargaProveedor;

use App\Support\Compras\PrecargaProveedor\PrecargaProveedorTipoItemSupport;
use PHPUnit\Framework\TestCase;

class PrecargaProveedorTipoItemSupportTest extends TestCase
{
    public function test_default_es_bienes(): void
    {
        $this->assertSame('B', PrecargaProveedorTipoItemSupport::resolverDesdeItemsOc([]));
    }

    public function test_anita_u_sin_indumentaria_es_bien_de_uso(): void
    {
        $items = [
            (object) [
                'stkm_tipo_articulo' => 'U',
                'es_indumentaria' => false,
            ],
        ];

        $this->assertSame('U', PrecargaProveedorTipoItemSupport::resolverDesdeItemsOc($items));
    }

    public function test_ropa_marcada_u_en_anita_va_como_bienes(): void
    {
        $items = [
            (object) [
                'stkm_tipo_articulo' => 'U',
                'penvp_articulo' => 'RRHH301237',
                'es_indumentaria' => true,
            ],
        ];

        $this->assertSame('B', PrecargaProveedorTipoItemSupport::resolverDesdeItemsOc($items));
    }

    public function test_categoria_indumentaria_de_personal_es_ropa(): void
    {
        $this->assertTrue(
            PrecargaProveedorTipoItemSupport::esIndumentariaDesdeMaestros(
                'U',
                'BIEN DE USO',
                'INDUMENTARIA DE PERSONAL'
            )
        );
    }

    public function test_tipo_ind_es_ropa(): void
    {
        $this->assertTrue(
            PrecargaProveedorTipoItemSupport::esIndumentariaDesdeMaestros('IND', 'INDUMENTARIA', 'OTRA')
        );
    }

    public function test_bien_de_uso_maquinas_no_es_ropa(): void
    {
        $this->assertFalse(
            PrecargaProveedorTipoItemSupport::esIndumentariaDesdeMaestros(
                'U',
                'BIEN DE USO',
                'Maquinas'
            )
        );
    }
}
