<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorReversoAsientoEspejoSupport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ComprobanteProveedorReversoAsientoEspejoSupportTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function asientoFactura(): array
    {
        return [
            ['cuentacontable_id' => 10, 'centrocosto_id' => 5, 'moneda_id' => 1, 'monto' => 100.0, 'cotizacion' => 1, 'observacion' => 'COM'],
            ['cuentacontable_id' => 20, 'centrocosto_id' => null, 'moneda_id' => 1, 'monto' => 21.0, 'cotizacion' => 1, 'observacion' => 'IVA'],
            ['cuentacontable_id' => 30, 'centrocosto_id' => null, 'moneda_id' => 1, 'monto' => -121.0, 'cotizacion' => 1, 'observacion' => 'Proveedor'],
        ];
    }

    public function test_invertir_da_vuelta_cada_linea_y_balancea(): void
    {
        $lineas = ComprobanteProveedorReversoAsientoEspejoSupport::invertir($this->asientoFactura());

        $this->assertCount(3, $lineas);
        $this->assertSame(-100.0, $lineas[0]['monto']);
        $this->assertSame(-21.0, $lineas[1]['monto']);
        $this->assertSame(121.0, $lineas[2]['monto']);
        $this->assertSame(0.0, round(array_sum(array_column($lineas, 'monto')), 2));
        $this->assertStringStartsWith('Reverso: ', $lineas[0]['observacion']);
    }

    public function test_el_asiento_espejo_anula_al_original_por_cuenta_centro_y_moneda(): void
    {
        $origen = $this->asientoFactura();
        $espejo = ComprobanteProveedorReversoAsientoEspejoSupport::invertir($origen);

        $dif = ComprobanteProveedorReversoAsientoEspejoSupport::diferenciasEspejo(
            ComprobanteProveedorReversoAsientoEspejoSupport::sumasPorClave($origen),
            ComprobanteProveedorReversoAsientoEspejoSupport::sumasPorClave($espejo),
        );

        $this->assertSame([], $dif);
    }

    public function test_diferencias_detecta_una_linea_que_no_es_espejo(): void
    {
        $origen = $this->asientoFactura();
        $espejo = ComprobanteProveedorReversoAsientoEspejoSupport::invertir($origen);
        $espejo[1]['monto'] = -20.0;

        $dif = ComprobanteProveedorReversoAsientoEspejoSupport::diferenciasEspejo(
            ComprobanteProveedorReversoAsientoEspejoSupport::sumasPorClave($origen),
            ComprobanteProveedorReversoAsientoEspejoSupport::sumasPorClave($espejo),
        );

        $this->assertSame(['20|0|1' => 1.0], $dif);
    }

    public function test_invertir_rechaza_asiento_que_no_balancea(): void
    {
        $this->expectException(RuntimeException::class);
        ComprobanteProveedorReversoAsientoEspejoSupport::invertir([
            ['cuentacontable_id' => 10, 'monto' => 100.0],
            ['cuentacontable_id' => 30, 'monto' => -90.0],
        ]);
    }

    public function test_invertir_rechaza_linea_sin_cuenta(): void
    {
        $this->expectException(RuntimeException::class);
        ComprobanteProveedorReversoAsientoEspejoSupport::invertir([
            ['cuentacontable_id' => 0, 'monto' => 100.0],
            ['cuentacontable_id' => 30, 'monto' => -100.0],
        ]);
    }

    public function test_armar_campos_payload_separa_debe_y_haber(): void
    {
        $lineas = ComprobanteProveedorReversoAsientoEspejoSupport::invertir($this->asientoFactura());
        $armado = ComprobanteProveedorReversoAsientoEspejoSupport::armarCamposPayload($lineas);

        $this->assertSame(121.0, $armado['total_debe']);
        $this->assertSame(121.0, $armado['total_haber']);
        $this->assertSame([0, 0, 121.0], $armado['campos']['debes']);
        $this->assertSame([100.0, 21.0, 0], $armado['campos']['haberes']);
        $this->assertSame([10, 20, 30], $armado['campos']['cuentacontable_ids']);
    }
}
