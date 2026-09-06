<?php

namespace Tests\Unit;

use App\Support\Compras\Tracking\TrackingComprobanteFamilia as Familia;
use PHPUnit\Framework\TestCase;

class TrackingComprobanteFamiliaTest extends TestCase
{
    /**
     * En este ERP `codigoafip` funciona como código de familia: 01 factura,
     * 02 nota de débito, 03 nota de crédito.
     */
    public function test_agrupa_por_codigo_de_familia(): void
    {
        $this->assertSame(Familia::FACTURA, Familia::desde('01', 'FIB'));
        $this->assertSame(Familia::FACTURA, Familia::desde('01', 'FGA'));
        $this->assertSame(Familia::NOTA_DEBITO, Familia::desde('02', 'DIS'));
        $this->assertSame(Familia::NOTA_CREDITO, Familia::desde('03', 'CIS'));
        $this->assertSame(Familia::ORDEN_PAGO, Familia::desde('05', 'OPP'));
    }

    /**
     * REC tiene codigoafip 01 porque es un recibo-factura, pero en el tracking
     * es una familia propia: hay proveedores que en lugar de factura emiten
     * recibo y el usuario necesita poder aislarlos.
     */
    public function test_el_recibo_no_cae_en_la_familia_factura(): void
    {
        $this->assertSame(Familia::RECIBO, Familia::desde('01', 'REC'));
        $this->assertSame(Familia::RECIBO, Familia::desde('01', ' rec '));
    }

    public function test_codigo_desconocido_cae_en_otro(): void
    {
        $this->assertSame(Familia::OTRO, Familia::desde('99', 'XXX'));
        $this->assertSame(Familia::OTRO, Familia::desde(null, null));
        $this->assertSame(Familia::OTRO, Familia::desde('', ''));
    }

    /**
     * El repositorio de escaneo del Anita indexa los recibos con ctipo '05',
     * no con su codigoafip. Sin esta traducción el PDF de un recibo no se
     * encuentra nunca (ver a-compprov.c en el sistema anterior).
     */
    public function test_el_ctipo_de_escaneo_del_recibo_es_05(): void
    {
        $this->assertSame('05', Familia::ctipoScan('01', 'REC'));
        $this->assertSame('01', Familia::ctipoScan('01', 'FIB'));
        $this->assertSame('03', Familia::ctipoScan('03', 'CIS'));
    }

    public function test_normaliza_el_codigo_a_dos_digitos(): void
    {
        $this->assertSame('01', Familia::ctipoScan('1', 'FIB'));
        $this->assertSame('02', Familia::ctipoScan('002', 'DIS'));
        $this->assertSame('', Familia::ctipoScan(null, null));
    }

    /**
     * Pedir facturas no puede arrastrar los recibos: comparten el código 01,
     * así que la familia factura se filtra por código y además excluye REC.
     */
    public function test_expone_como_filtrar_cada_familia_en_sql(): void
    {
        $this->assertSame(['01'], Familia::codigosAfipDeFamilia(Familia::FACTURA));
        $this->assertSame([], Familia::abreviaturasDeFamilia(Familia::FACTURA));

        $this->assertSame([], Familia::codigosAfipDeFamilia(Familia::RECIBO));
        $this->assertSame(['REC'], Familia::abreviaturasDeFamilia(Familia::RECIBO));
    }

    public function test_valida_las_familias_del_filtro(): void
    {
        $this->assertTrue(Familia::esFamiliaValida('FC'));
        $this->assertTrue(Familia::esFamiliaValida('rc'));
        $this->assertFalse(Familia::esFamiliaValida('ZZ'));
        $this->assertFalse(Familia::esFamiliaValida(null));
    }
}
