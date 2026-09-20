<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorToleranciaImporteSupport as Tolerancia;
use PHPUnit\Framework\TestCase;

/**
 * Límite superior e inferior por separado: una factura parcial no tiene por qué tratarse igual
 * que una sobrefacturada, y cada sentido se puede dejar sin verificar.
 */
class ComprobanteProveedorToleranciaImporteSupportTest extends TestCase
{
    /** @return array{exceso_pct: ?float, defecto_pct: ?float, exceso_abs: ?float, defecto_abs: ?float} */
    private function politica(
        ?float $excesoPct = null,
        ?float $defectoPct = null,
        ?float $excesoAbs = null,
        ?float $defectoAbs = null,
    ): array {
        return [
            'exceso_pct' => $excesoPct,
            'defecto_pct' => $defectoPct,
            'exceso_abs' => $excesoAbs,
            'defecto_abs' => $defectoAbs,
        ];
    }

    public function test_importe_exacto_esta_dentro_de_cualquier_politica(): void
    {
        $this->assertNull(Tolerancia::sentidoFueraDePolitica(1000.0, 1000.0, $this->politica(0.0, 0.0)));
    }

    public function test_diferencia_de_centavos_siempre_se_acepta(): void
    {
        $this->assertNull(Tolerancia::sentidoFueraDePolitica(1000.04, 1000.0, $this->politica(0.0, 0.0)));
    }

    public function test_detecta_exceso_y_lo_distingue_del_defecto(): void
    {
        $this->assertSame(
            Tolerancia::SENTIDO_EXCESO,
            Tolerancia::sentidoFueraDePolitica(1100.0, 1000.0, $this->politica(5.0, 5.0))
        );
        $this->assertSame(
            Tolerancia::SENTIDO_DEFECTO,
            Tolerancia::sentidoFueraDePolitica(900.0, 1000.0, $this->politica(5.0, 5.0))
        );
    }

    public function test_dentro_del_porcentaje_no_marca_ningun_sentido(): void
    {
        $this->assertNull(Tolerancia::sentidoFueraDePolitica(1030.0, 1000.0, $this->politica(5.0, 5.0)));
        $this->assertNull(Tolerancia::sentidoFueraDePolitica(970.0, 1000.0, $this->politica(5.0, 5.0)));
    }

    public function test_defecto_sin_limite_permite_facturacion_parcial(): void
    {
        // Caso anticipada/contrato: se factura de a partes contra la misma COM.
        $politica = $this->politica(excesoPct: 5.0, defectoPct: null);

        $this->assertNull(Tolerancia::sentidoFueraDePolitica(200.0, 1540000.0, $politica));
        // Dentro del 5% sigue pasando, aunque sea por arriba.
        $this->assertNull(Tolerancia::sentidoFueraDePolitica(1600000.0, 1540000.0, $politica));
        $this->assertSame(
            Tolerancia::SENTIDO_EXCESO,
            Tolerancia::sentidoFueraDePolitica(1700000.0, 1540000.0, $politica)
        );
    }

    public function test_exceso_sin_limite_no_bloquea_sobrefacturacion(): void
    {
        $politica = $this->politica(excesoPct: null, defectoPct: 5.0);

        $this->assertNull(Tolerancia::sentidoFueraDePolitica(5000.0, 1000.0, $politica));
        $this->assertSame(
            Tolerancia::SENTIDO_DEFECTO,
            Tolerancia::sentidoFueraDePolitica(500.0, 1000.0, $politica)
        );
    }

    public function test_politica_sin_ningun_limite_no_verifica_nada(): void
    {
        $this->assertNull(Tolerancia::sentidoFueraDePolitica(1.0, 999999.0, $this->politica()));
    }

    public function test_limite_absoluto_alcanza_para_marcar_aunque_el_porcentaje_no_se_pase(): void
    {
        // 1% de diferencia sobre un millón: pasa el % pero excede el tope absoluto de 5.000.
        $politica = $this->politica(excesoPct: 5.0, defectoPct: 5.0, excesoAbs: 5000.0);

        $this->assertSame(
            Tolerancia::SENTIDO_EXCESO,
            Tolerancia::sentidoFueraDePolitica(1010000.0, 1000000.0, $politica)
        );
    }

    public function test_limite_absoluto_solo_aplica_a_su_sentido(): void
    {
        $politica = $this->politica(excesoPct: null, defectoPct: null, excesoAbs: 100.0);

        $this->assertSame(
            Tolerancia::SENTIDO_EXCESO,
            Tolerancia::sentidoFueraDePolitica(1200.0, 1000.0, $politica)
        );
        // El mismo desvío hacia abajo no tiene límite configurado.
        $this->assertNull(Tolerancia::sentidoFueraDePolitica(800.0, 1000.0, $politica));
    }

    public function test_sin_provision_cualquier_diferencia_queda_fuera_si_hay_limite(): void
    {
        $this->assertSame(
            Tolerancia::SENTIDO_EXCESO,
            Tolerancia::sentidoFueraDePolitica(500.0, 0.0, $this->politica(5.0, 5.0))
        );
    }

    public function test_sin_provision_y_sin_limites_no_marca(): void
    {
        $this->assertNull(Tolerancia::sentidoFueraDePolitica(500.0, 0.0, $this->politica()));
    }

    public function test_etiqueta_y_limite_configurado_son_legibles_para_el_operador(): void
    {
        $this->assertStringContainsString('supera', Tolerancia::etiquetaSentido(Tolerancia::SENTIDO_EXCESO));
        $this->assertStringContainsString('parcial', Tolerancia::etiquetaSentido(Tolerancia::SENTIDO_DEFECTO));

        $politica = $this->politica(excesoPct: 5.0, excesoAbs: 1000.0);
        $texto = Tolerancia::limiteConfigurado($politica, Tolerancia::SENTIDO_EXCESO);
        $this->assertStringContainsString('5,00%', $texto);
        $this->assertStringContainsString('1.000,00', $texto);

        $this->assertSame(
            'sin límite',
            Tolerancia::limiteConfigurado($this->politica(), Tolerancia::SENTIDO_DEFECTO)
        );
    }

    public function test_excede_tolerancia_simetrica_sigue_funcionando(): void
    {
        $this->assertFalse(Tolerancia::excedeTolerancia(1030.0, 1000.0, 5.0));
        $this->assertTrue(Tolerancia::excedeTolerancia(1080.0, 1000.0, 5.0));
        $this->assertTrue(Tolerancia::excedeTolerancia(920.0, 1000.0, 5.0));
    }
}
