<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\ArcaMtxcaComprobanteTotalesSupport as Totales;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD). Coherencia ítems ↔ cabecera exigida por WSMTXCA (validaciones 110-116, 401).
 */
class ArcaMtxcaComprobanteTotalesSupportTest extends TestCase
{
    public function test_la_alicuota_real_manda_sobre_el_codigo_del_impuesto(): void
    {
        self::assertSame(5, Totales::resolverCodigoCondicion(4, 21.0));
        self::assertSame(4, Totales::resolverCodigoCondicion(null, 10.5));
        self::assertSame(9, Totales::resolverCodigoCondicion(null, 2.5));
    }

    public function test_sin_alicuota_la_linea_va_exenta_no_gravada_al_cero(): void
    {
        self::assertSame(Totales::CONDICION_EXENTO, Totales::resolverCodigoCondicion(5, 0.0));
        self::assertSame(Totales::CONDICION_EXENTO, Totales::resolverCodigoCondicion(3, 0.0));
        self::assertSame(Totales::CONDICION_NO_GRAVADO, Totales::resolverCodigoCondicion(1, 0.0));
    }

    public function test_agrega_una_fila_por_lo_que_la_cabecera_grava_y_el_detalle_no(): void
    {
        $filas = Totales::conciliar(
            [$this->fila(5, 21.0, 1000.0)],
            [
                'gravado' => 1200,
                'iva' => 252,
                'impuestos' => [['id' => 5, 'base_imp' => 1200, 'importe' => 252]],
            ],
        );

        self::assertCount(2, $filas);
        self::assertSame(200.0, $filas[1]['neto']);
        self::assertSame(42.0, $filas[1]['iva']);
        self::assertSame(252.0, round($filas[0]['iva'] + $filas[1]['iva'], 2));
    }

    public function test_cierra_los_centavos_contra_el_subtotal_de_iva(): void
    {
        $filas = Totales::conciliar(
            [$this->fila(5, 21.0, 10.01), $this->fila(5, 21.0, 10.01)],
            [
                'gravado' => 20.02,
                'iva' => 4.21,
                'impuestos' => [['id' => 5, 'base_imp' => 20.02, 'importe' => 4.21]],
            ],
        );

        self::assertCount(2, $filas);
        self::assertSame(4.21, round($filas[0]['iva'] + $filas[1]['iva'], 2));
        self::assertSame(20.02, round($filas[0]['neto'] + $filas[1]['neto'], 2));
    }

    public function test_arma_el_detalle_desde_la_cabecera_cuando_no_hay_lineas(): void
    {
        $filas = Totales::conciliar([], [
            'gravado' => 1000,
            'exento' => 300,
            'nogravado' => 0,
            'iva' => 210,
            'impuestos' => [['id' => 5, 'base_imp' => 1000, 'importe' => 210]],
        ]);

        self::assertCount(2, $filas);
        self::assertSame(1000.0, $filas[0]['neto']);
        self::assertSame(210.0, $filas[0]['iva']);
        self::assertSame(Totales::CONDICION_EXENTO, $filas[1]['codigo_condicion_iva']);
        self::assertSame(300.0, $filas[1]['neto']);
    }

    public function test_denuncia_el_total_que_no_cierra_con_el_detalle(): void
    {
        $errores = Totales::inconsistencias([
            'importeGravado' => 1000.0,
            'importeNoGravado' => 0.0,
            'importeExento' => 0.0,
            'importeSubtotal' => 1000.0,
            'importeOtrosTributos' => 0.0,
            'importeTotal' => 1210.0,
            'arrayItems' => ['item' => [[
                'codigoCondicionIVA' => 5,
                'importeItem' => 968.0,
                'importeIVA' => 168.0,
            ]]],
            'arraySubtotalesIVA' => ['subtotalIVA' => [['codigo' => 5, 'importe' => 210.0]]],
        ]);

        self::assertNotSame([], $errores);
        self::assertStringContainsString('[110]', implode(' ', $errores));
        self::assertStringContainsString('[116]', implode(' ', $errores));
    }

    public function test_un_comprobante_coherente_no_reporta_nada(): void
    {
        $errores = Totales::inconsistencias([
            'importeGravado' => 1000.0,
            'importeNoGravado' => 0.0,
            'importeExento' => 300.0,
            'importeSubtotal' => 1300.0,
            'importeOtrosTributos' => 50.0,
            'importeTotal' => 1560.0,
            'arrayItems' => ['item' => [
                ['codigoCondicionIVA' => 5, 'importeItem' => 1210.0, 'importeIVA' => 210.0],
                ['codigoCondicionIVA' => 2, 'importeItem' => 300.0],
            ]],
            'arraySubtotalesIVA' => ['subtotalIVA' => [['codigo' => 5, 'importe' => 210.0]]],
        ]);

        self::assertSame([], $errores);
    }

    /**
     * @return array<string, mixed>
     */
    private function fila(int $codigo, float $alicuota, float $neto): array
    {
        return [
            'codigo' => 'A',
            'descripcion' => 'Artículo',
            'cantidad' => 1.0,
            'codigo_unidad_medida' => 7,
            'precio_lista' => $neto,
            'bonificacion' => 0.0,
            'codigo_condicion_iva' => $codigo,
            'alicuota' => $alicuota,
            'neto' => $neto,
            'iva' => round($neto * $alicuota / 100, 2),
        ];
    }
}
