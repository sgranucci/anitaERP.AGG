<?php

namespace Tests\Unit\Support\Compras\AnitaImport;

use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportOrigenSupport;
use App\Support\Compras\ComprobanteProveedorOrigenEntrada;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorAnitaImportOrigenSupportTest extends TestCase
{
    public function test_anita_import_no_es_nativo(): void
    {
        $this->assertFalse(
            ComprobanteProveedorAnitaImportOrigenSupport::esNativo(
                ComprobanteProveedorOrigenEntrada::ANITA_IMPORT
            )
        );
    }

    public function test_precarga_legajo_y_null_son_nativos(): void
    {
        $this->assertTrue(ComprobanteProveedorAnitaImportOrigenSupport::esNativo(
            ComprobanteProveedorOrigenEntrada::PRECARGA
        ));
        $this->assertTrue(ComprobanteProveedorAnitaImportOrigenSupport::esNativo(
            ComprobanteProveedorOrigenEntrada::LEGAJO
        ));
        $this->assertTrue(ComprobanteProveedorAnitaImportOrigenSupport::esNativo(null));
        $this->assertTrue(ComprobanteProveedorAnitaImportOrigenSupport::esNativo(''));
    }

    public function test_empresas_operativas_agg(): void
    {
        $this->assertSame([1, 2, 3], ComprobanteProveedorAnitaImportOrigenSupport::empresasOperativasAgg());
    }
}
