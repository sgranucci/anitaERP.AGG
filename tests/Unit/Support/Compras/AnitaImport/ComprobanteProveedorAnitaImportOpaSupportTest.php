<?php

namespace Tests\Unit\Support\Compras\AnitaImport;

use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportOpaSupport;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorAnitaImportOpaSupportTest extends TestCase
{
    public function test_solo_opa_es_adelanto(): void
    {
        $this->assertTrue(ComprobanteProveedorAnitaImportOpaSupport::esTipoAdelanto('OPA'));
        $this->assertTrue(ComprobanteProveedorAnitaImportOpaSupport::esTipoAdelanto('opa'));
        $this->assertFalse(ComprobanteProveedorAnitaImportOpaSupport::esTipoAdelanto('OPP'));
        $this->assertFalse(ComprobanteProveedorAnitaImportOpaSupport::esTipoAdelanto('APA'));
        $this->assertFalse(ComprobanteProveedorAnitaImportOpaSupport::esTipoAdelanto('FNS'));
    }

    public function test_pendiente_es_monto_menos_pagado(): void
    {
        $this->assertSame(9055.89, ComprobanteProveedorAnitaImportOpaSupport::pendiente([
            'prov_monto' => 9055.89,
            'prov_t_pagado' => 0,
        ]));
        $this->assertSame(0.0, ComprobanteProveedorAnitaImportOpaSupport::pendiente([
            'prov_monto' => 1231146.26,
            'prov_t_pagado' => 1231146.26,
        ]));
        $this->assertSame(100.0, ComprobanteProveedorAnitaImportOpaSupport::pendiente([
            'prov_monto' => 250,
            'prov_t_pagado' => 150,
        ]));
    }

    public function test_adelantos_pendientes_omite_aplicados_y_no_opa(): void
    {
        $adelantos = ComprobanteProveedorAnitaImportOpaSupport::adelantosPendientes([
            $this->promov('OPA', 124102, 1, 9055.89, 0, 20260730),
            $this->promov('OPA', 91156, 1, 1231146.26, 1231146.26, 20210923),
            $this->promov('OPP', 120184, 1, 16125309.20, 0, 20260106),
            $this->promov('OPA', 57372, 2, 8139.51, 0, 20260730, 2),
        ]);

        $this->assertCount(2, $adelantos);
        $this->assertSame('OPA A 1-124102', $adelantos[0]['etiqueta']);
        $this->assertSame(9055.89, $adelantos[0]['pendiente']);
        $this->assertSame('2026-07-30', $adelantos[0]['fecha']);
        $this->assertSame('003593|OPA|A|1|124102', $adelantos[0]['clave']);
        $this->assertSame('OPA A 2-57372', $adelantos[1]['etiqueta']);
        $this->assertSame(2, $adelantos[1]['empresa_codigo']);
    }

    public function test_agrupa_cuotas_de_la_misma_opa(): void
    {
        $adelantos = ComprobanteProveedorAnitaImportOpaSupport::adelantosPendientes([
            $this->promov('OPA', 10, 1, 100, 40, 20260101),
            $this->promov('OPA', 10, 1, 80, 0, 20260115),
        ]);

        $this->assertCount(1, $adelantos);
        $this->assertSame(140.0, $adelantos[0]['pendiente']);
        $this->assertSame(180.0, $adelantos[0]['monto']);
        $this->assertSame(40.0, $adelantos[0]['pagado']);
        $this->assertSame('2026-01-01', $adelantos[0]['fecha']);
    }

    /**
     * @return array<string, mixed>
     */
    private function promov(
        string $tipo,
        int $nro,
        int $sucursal,
        float $monto,
        float $pagado,
        int $fecha,
        int $empresa = 1,
    ): array {
        return [
            'prov_proveedor' => '3593',
            'prov_tipo' => $tipo,
            'prov_letra' => 'A',
            'prov_sucursal' => $sucursal,
            'prov_nro' => $nro,
            'prov_fecha' => $fecha,
            'prov_fecha_vto' => $fecha,
            'prov_monto' => $monto,
            'prov_t_pagado' => $pagado,
            'prov_cod_mon' => 2,
            'prov_cotizacion' => 1515,
            'prov_empresa' => $empresa,
        ];
    }
}
