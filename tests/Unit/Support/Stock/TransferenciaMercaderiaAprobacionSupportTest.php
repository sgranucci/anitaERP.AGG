<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Stock;

use App\Models\Stock\Tipotransaccion_Stock;
use App\Support\Stock\TransferenciaMercaderiaAprobacionSupport;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TransferenciaMercaderiaAprobacionSupportTest extends TestCase
{
    public function test_legacy_inmediata_siempre_false(): void
    {
        Config::set('app.empresa', 'AGG');
        Config::set('stock.transferencia_modo_aprobacion', TransferenciaMercaderiaAprobacionSupport::MODO_INMEDIATA);

        $tipo = $this->tipo(['abreviatura' => 'TRA', 'requiere_aprobacion' => true]);

        $this->assertFalse(
            TransferenciaMercaderiaAprobacionSupport::requiereAprobacionLegacy($tipo)
        );
    }

    public function test_legacy_siempre_true(): void
    {
        Config::set('app.empresa', 'AGG');
        Config::set('stock.transferencia_modo_aprobacion', TransferenciaMercaderiaAprobacionSupport::MODO_SIEMPRE);

        $tipo = $this->tipo(['abreviatura' => 'X', 'requiere_aprobacion' => false]);

        $this->assertTrue(
            TransferenciaMercaderiaAprobacionSupport::requiereAprobacionLegacy($tipo)
        );
    }

    public function test_legacy_ferli_no_tra_false(): void
    {
        Config::set('app.empresa', 'Calzados Ferli');
        Config::set('stock.transferencia_modo_aprobacion', TransferenciaMercaderiaAprobacionSupport::MODO_TIPO_TRANSACCION);

        $tipo = $this->tipo(['abreviatura' => 'TXX', 'requiere_aprobacion' => true]);

        $this->assertFalse(
            TransferenciaMercaderiaAprobacionSupport::requiereAprobacionLegacy($tipo)
        );
    }

    public function test_legacy_por_flag_tipo_y_aviso_opcional(): void
    {
        Config::set('app.empresa', 'AGG');
        Config::set('stock.transferencia_modo_aprobacion', TransferenciaMercaderiaAprobacionSupport::MODO_TIPO_TRANSACCION);

        $conFlag = $this->tipo(['abreviatura' => 'TRA', 'requiere_aprobacion' => true, 'aviso_opcional' => false]);
        $this->assertTrue(TransferenciaMercaderiaAprobacionSupport::requiereAprobacionLegacy($conFlag));

        $opcional = $this->tipo(['abreviatura' => 'TRA', 'requiere_aprobacion' => false, 'aviso_opcional' => true]);
        $this->assertFalse(TransferenciaMercaderiaAprobacionSupport::requiereAprobacionLegacy($opcional, null));
        $this->assertTrue(TransferenciaMercaderiaAprobacionSupport::requiereAprobacionLegacy($opcional, true));
        $this->assertFalse(TransferenciaMercaderiaAprobacionSupport::requiereAprobacionLegacy($opcional, false));
    }

    public function test_contexto_regla_incluye_campos_del_punto(): void
    {
        Config::set('app.empresa', 'Calzados Ferli');
        Config::set('stock.transferencia_modo_aprobacion', 'tipo_transaccion');

        $ctx = TransferenciaMercaderiaAprobacionSupport::contextoRegla(
            $this->tipo(['id' => 7, 'abreviatura' => 'tra', 'requiere_aprobacion' => true, 'aviso_opcional' => false]),
            true
        );

        $this->assertSame('TRA', $ctx['tipo']);
        $this->assertSame(7, $ctx['tipo_id']);
        $this->assertTrue($ctx['es_ferli']);
        $this->assertSame('tipo_transaccion', $ctx['modo']);
        $this->assertTrue($ctx['decision_aviso_usuario']);
        $this->assertSame(TransferenciaMercaderiaAprobacionSupport::PUNTO_REGLA, 'stock.tm.requiere_aprobacion');
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function tipo(array $attrs): Tipotransaccion_Stock
    {
        $t = new Tipotransaccion_Stock;
        foreach ($attrs as $k => $v) {
            $t->{$k} = $v;
        }

        return $t;
    }
}
