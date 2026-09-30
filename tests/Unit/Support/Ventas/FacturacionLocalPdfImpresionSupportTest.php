<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\FacturacionLocal\FacturacionLocalPdfImpresionSupport as S;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD): unitario de lista y texto de forma de pago del PDF local.
 */
final class FacturacionLocalPdfImpresionSupportTest extends TestCase
{
    public function test_el_unitario_recupera_el_precio_de_lista_sin_volver_a_descontar(): void
    {
        $importes = S::importesLinea(77400.0, 10.0, 1.0);

        self::assertSame(86000.0, $importes['unitario']);
        self::assertSame(8600.0, $importes['descuento']);
        self::assertSame(77400.0, $importes['importe']);
    }

    public function test_sin_descuento_el_unitario_es_el_precio_grabado(): void
    {
        $importes = S::importesLinea(77400.0, 0.0, 1.0);

        self::assertSame(77400.0, $importes['unitario']);
        self::assertSame(0.0, $importes['descuento']);
        self::assertSame(77400.0, $importes['importe']);
    }

    public function test_porcentaje_con_decimales_vuelve_al_precio_entero_de_lista(): void
    {
        $importes = S::importesLinea(38998.44, 16.67, 1.0);

        self::assertSame(46800.0, $importes['unitario']);
        self::assertSame(7801.56, $importes['descuento']);
        self::assertSame(38998.44, $importes['importe']);
    }

    public function test_transferencia_no_repite_el_importe_y_la_tarjeta_muestra_el_cupon(): void
    {
        self::assertSame('Transferencias', S::textoDesdeMedios([
            ['nombre' => 'Transferencias', 'monto' => 77400, 'observacion' => 'Facturación Local'],
        ]));

        self::assertSame('VISA — Cupón 20', S::textoDesdeMedios([
            ['nombre' => 'VISA', 'monto' => 80200, 'observacion' => 'Cupón 20'],
        ]));

        self::assertSame(
            'VISA $ 80,200.00 — Cupón 20 · VISA $ 8,900.00 — Cupón 10',
            S::textoDesdeMedios([
                ['nombre' => 'VISA', 'monto' => 80200, 'observacion' => 'Cupón 20'],
                ['nombre' => 'VISA', 'monto' => 8900, 'observacion' => 'Cupón 10'],
            ])
        );
    }
}
