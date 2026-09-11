<?php

namespace Tests\Unit\Support\Contable\LibroIvaDigital;

use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalCacheSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalVentasPeriodoSupport;
use PHPUnit\Framework\TestCase;

class LibroIvaDigitalCacheSupportTest extends TestCase
{
    public function test_compactar_saca_registros_y_conserva_archivos(): void
    {
        $compacto = LibroIvaDigitalCacheSupport::compactar([
            'ventas' => [
                'ventas_cbte' => 'CBTE',
                'registros' => [['cabecera' => []]],
                'resumen' => ['comprobantes' => 1],
            ],
            'compras' => [
                'compras_cbte' => 'COMPRA',
                'registros' => [['cabecera' => []]],
            ],
            'importaciones' => [
                'registros' => [1],
            ],
            'iva_simple' => [
                'debito_fiscal' => 'csv',
                'detalle_debito' => [['neto' => 1]],
            ],
        ]);

        $this->assertSame('CBTE', $compacto['ventas']['ventas_cbte']);
        $this->assertSame(1, $compacto['ventas']['resumen']['comprobantes']);
        $this->assertArrayNotHasKey('registros', $compacto['ventas']);
        $this->assertArrayNotHasKey('registros', $compacto['compras']);
        $this->assertArrayNotHasKey('detalle_debito', $compacto['iva_simple']);
        $this->assertSame('csv', $compacto['iva_simple']['debito_fiscal']);
    }

    public function test_firma_cambia_con_empresa_y_opciones(): void
    {
        $base = LibroIvaDigitalCacheSupport::firma(2, 2026, 8, [
            'por_fecha_jornada' => true,
            'prorrateo_cf_global' => true,
        ]);
        $otraEmpresa = LibroIvaDigitalCacheSupport::firma(1, 2026, 8, [
            'por_fecha_jornada' => true,
            'prorrateo_cf_global' => true,
        ]);
        $sinJornada = LibroIvaDigitalCacheSupport::firma(2, 2026, 8, [
            'por_fecha_jornada' => false,
            'prorrateo_cf_global' => true,
        ]);

        $this->assertNotSame($base, $otraEmpresa);
        $this->assertNotSame($base, $sinJornada);
        $this->assertSame(
            $base,
            LibroIvaDigitalCacheSupport::firma(2, 2026, 8, [
                'por_fecha_jornada' => true,
                'prorrateo_cf_global' => true,
            ]),
        );
    }

    public function test_columna_fecha_jornada_usa_fechajornada(): void
    {
        $this->assertSame('venta.fechajornada', LibroIvaDigitalVentasPeriodoSupport::columnaFecha(true));
        $this->assertSame('venta.fecha', LibroIvaDigitalVentasPeriodoSupport::columnaFecha(false));
    }
}
