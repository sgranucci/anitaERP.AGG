<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\ArticuloIndumentariaTipoSupport;
use PHPUnit\Framework\TestCase;

class ArticuloIndumentariaTipoSupportTest extends TestCase
{
    public function test_categoria_indumentaria_de_personal(): void
    {
        $this->assertTrue(
            ArticuloIndumentariaTipoSupport::categoriaEsIndumentaria(
                (object) ['nombre' => 'INDUMENTARIA DE PERSONAL']
            )
        );
    }

    public function test_categoria_maquinas_no_es_indumentaria(): void
    {
        $this->assertFalse(
            ArticuloIndumentariaTipoSupport::categoriaEsIndumentaria(
                (object) ['nombre' => 'Maquinas']
            )
        );
    }

    public function test_categoria_nula_no_es_indumentaria(): void
    {
        $this->assertFalse(ArticuloIndumentariaTipoSupport::categoriaEsIndumentaria(null));
    }
}
