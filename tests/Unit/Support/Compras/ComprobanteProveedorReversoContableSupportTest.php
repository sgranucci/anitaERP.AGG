<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorOrigenEntrada;
use App\Support\Compras\ComprobanteProveedorReversoContableSupport;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorReversoContableSupportTest extends TestCase
{
    public function test_puede_mostrar_boton_solo_factura_contabilizada_con_asiento(): void
    {
        $ok = (object) [
            'estado' => ComprobanteProveedorEstados::CONTABILIZADO,
            'asiento_id' => 10,
            'origen_entrada' => ComprobanteProveedorOrigenEntrada::MANUAL,
            'tipotransaccion_compras' => (object) ['signo' => 'S'],
        ];
        $this->assertTrue(ComprobanteProveedorReversoContableSupport::puedeMostrarBoton($ok));
    }

    public function test_no_muestra_boton_si_es_nc_o_sin_asiento_o_ie(): void
    {
        $base = [
            'estado' => ComprobanteProveedorEstados::CONTABILIZADO,
            'asiento_id' => 10,
            'origen_entrada' => ComprobanteProveedorOrigenEntrada::MANUAL,
            'tipotransaccion_compras' => (object) ['signo' => 'S'],
        ];

        $nc = (object) array_merge($base, [
            'tipotransaccion_compras' => (object) ['signo' => 'R'],
        ]);
        $this->assertFalse(ComprobanteProveedorReversoContableSupport::puedeMostrarBoton($nc));

        $sinAsiento = (object) array_merge($base, ['asiento_id' => null]);
        $this->assertFalse(ComprobanteProveedorReversoContableSupport::puedeMostrarBoton($sinAsiento));

        $ie = (object) array_merge($base, [
            'origen_entrada' => ComprobanteProveedorOrigenEntrada::INGRESO_EGRESO,
        ]);
        $this->assertFalse(ComprobanteProveedorReversoContableSupport::puedeMostrarBoton($ie));

        $borrador = (object) array_merge($base, [
            'estado' => ComprobanteProveedorEstados::BORRADOR,
        ]);
        $this->assertFalse(ComprobanteProveedorReversoContableSupport::puedeMostrarBoton($borrador));
    }

    public function test_leyenda_y_parseo_origen(): void
    {
        $origen = (object) [
            'id' => 12345,
            'letra' => 'A',
            'sucursal' => 1,
            'numerocomprobante' => 999,
            'tipotransaccion_compras' => (object) ['abreviatura' => 'FNB'],
        ];

        $leyenda = ComprobanteProveedorReversoContableSupport::leyendaPara($origen);
        $this->assertStringStartsWith('Reverso contable de CP#12345', $leyenda);
        $this->assertStringContainsString('FNB A-0001-999', $leyenda);
        $this->assertSame(12345, ComprobanteProveedorReversoContableSupport::origenIdDesdeLeyenda($leyenda));
        $this->assertNull(ComprobanteProveedorReversoContableSupport::origenIdDesdeLeyenda('otra leyenda'));
        $this->assertSame(
            'Reverso contable de CP#12345%',
            ComprobanteProveedorReversoContableSupport::leyendaLike(12345)
        );
    }
}
