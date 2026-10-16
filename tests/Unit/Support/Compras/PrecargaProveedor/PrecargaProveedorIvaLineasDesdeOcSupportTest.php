<?php

namespace Tests\Unit\Support\Compras\PrecargaProveedor;

use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Ordencompra;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorIvaLineasDesdeOcSupport;
use Tests\TestCase;

class PrecargaProveedorIvaLineasDesdeOcSupportTest extends TestCase
{
    public function test_prorratea_iva_fpb_desde_oc_real(): void
    {
        try {
            \DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Sin conexión a base de datos: '.$e->getMessage());
        }

        $oc = Ordencompra::query()->where('numeroordencompra', 224328)->first();
        if (! $oc) {
            $this->markTestSkipped('OC 224328 no está en la base.');
        }

        $c311 = Concepto_Ivacompra::query()->where('codigo', 311)->first();
        $c50 = Concepto_Ivacompra::query()->where('codigo', 50)->first();
        if (! $c311 || ! $c50) {
            $this->markTestSkipped('Faltan conceptos 50/311.');
        }

        $lineas = [
            ['concepto_ivacompra_id' => (int) $c50->id, 'monto' => 316754.90],
            ['concepto_ivacompra_id' => (int) $c311->id, 'monto' => 66518.53],
        ];

        $res = app(PrecargaProveedorIvaLineasDesdeOcSupport::class)->corregir($lineas, $oc, 'FPB');

        $this->assertTrue($res['cambio']);
        $this->assertSame('prorrateo', $res['modo']);

        $ivas = [];
        foreach ($res['lineas'] as $l) {
            $c = Concepto_Ivacompra::query()->find((int) $l['concepto_ivacompra_id']);
            if ($c && strtoupper((string) $c->tipoconcepto) === 'I') {
                $ivas[(int) $c->codigo] = round((float) $l['monto'], 2);
            }
        }

        $this->assertSame(30420.07, $ivas[503] ?? null);
        $this->assertSame(18054.15, $ivas[311] ?? null);
        $this->assertSame(18044.31, $ivas[411] ?? null);
        $this->assertEqualsWithDelta(66518.53, array_sum($ivas), 0.02);
    }

    public function test_reubica_311_a_411_en_fnb(): void
    {
        try {
            \DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Sin conexión a base de datos: '.$e->getMessage());
        }

        $oc = Ordencompra::query()->where('numeroordencompra', 223400)->first();
        $c311 = Concepto_Ivacompra::query()->where('codigo', 311)->first();
        if (! $oc || ! $c311) {
            $this->markTestSkipped('Faltan OC 223400 o concepto 311.');
        }

        $res = app(PrecargaProveedorIvaLineasDesdeOcSupport::class)->corregir(
            [['concepto_ivacompra_id' => (int) $c311->id, 'monto' => 189000.0]],
            $oc,
            'FNB',
        );

        $this->assertTrue($res['cambio']);
        $this->assertSame('reubicar', $res['modo']);
        $nuevo = Concepto_Ivacompra::query()->find((int) $res['lineas'][0]['concepto_ivacompra_id']);
        $this->assertSame(411, (int) $nuevo->codigo);
    }
}
