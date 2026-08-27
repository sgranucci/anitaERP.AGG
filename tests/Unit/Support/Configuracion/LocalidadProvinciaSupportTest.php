<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Configuracion;

use App\Support\Configuracion\LocalidadProvinciaSupport;
use PHPUnit\Framework\TestCase;

final class LocalidadProvinciaSupportTest extends TestCase
{
    public function test_id_vacio_queda_null(): void
    {
        $this->assertNull(LocalidadProvinciaSupport::idEnteroONull(null));
        $this->assertNull(LocalidadProvinciaSupport::idEnteroONull(''));
        $this->assertNull(LocalidadProvinciaSupport::idEnteroONull('  '));
        $this->assertNull(LocalidadProvinciaSupport::idEnteroONull(0));
        $this->assertSame(112, LocalidadProvinciaSupport::idEnteroONull('112'));
    }

    public function test_si_el_combo_llega_vacio_recupera_la_previa(): void
    {
        $this->assertSame(274, LocalidadProvinciaSupport::idConFallback('', '274'));
        $this->assertSame(99, LocalidadProvinciaSupport::idConFallback('99', '274'));
        $this->assertNull(LocalidadProvinciaSupport::idConFallback('', ''));
    }

    public function test_recupera_localidades_de_entrega_por_renglon(): void
    {
        $this->assertSame(
            [10, 20, null],
            LocalidadProvinciaSupport::recuperarIdsEnLista(['', '20', ''], ['10', '', ''])
        );
    }
}
