<?php

namespace Tests\Unit\Services\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Legajo_Scan_Anita_Descartado;
use App\Services\Compras\OrdencompraLegajoScanAnitaDescartarService;
use App\Support\Compras\OrdencompraLegajoAnitaScanFacturaSupport;
use App\Support\Compras\PrecargaComprobanteOrigenEntrada;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OrdencompraLegajoScanAnitaDescartarServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        OrdencompraLegajoAnitaScanFacturaSupport::forgetCache();
        parent::tearDown();
    }

    public function test_ignora_precarga_que_no_es_scan_anita(): void
    {
        $antes = Ordencompra_Legajo_Scan_Anita_Descartado::query()->count();

        $svc = app(OrdencompraLegajoScanAnitaDescartarService::class);
        $precarga = new Precarga_Comprobante_Proveedor([
            'origen_entrada' => PrecargaComprobanteOrigenEntrada::MANUAL,
            'numeroordencompra' => '223659',
        ]);
        $precarga->id = 1;

        $svc->descartarSiCorresponde($precarga);

        $this->assertSame($antes, Ordencompra_Legajo_Scan_Anita_Descartado::query()->count());
    }

    public function test_registra_descarte_y_filtra_en_facturas_de_oc(): void
    {
        $oc = new Ordencompra;
        $oc->id = 9_900_001;
        $oc->numeroordencompra = '9900001';
        $oc->empresa_id = 1;

        Ordencompra_Legajo_Scan_Anita_Descartado::query()->create([
            'ordencompra_id' => (int) $oc->id,
            'empresa_id' => 1,
            'numeroordencompra' => '9900001',
            'documento_id' => 365039,
        ]);

        OrdencompraLegajoAnitaScanFacturaSupport::forgetCache();

        $ref = new \ReflectionClass(OrdencompraLegajoAnitaScanFacturaSupport::class);
        $prop = $ref->getProperty('facturasPorOcCacheCrudo');
        $prop->setAccessible(true);
        $prop->setValue(null, [
            (int) $oc->id => [
                [
                    'id' => 'anita-365039',
                    'documento_id' => 365039,
                    'etiqueta' => 'NC A 1151-00000437',
                    'numero' => 'NC A 1151-00000437',
                ],
                [
                    'id' => 'anita-365040',
                    'documento_id' => 365040,
                    'etiqueta' => 'NC A 1151-00000749',
                    'numero' => 'NC A 1151-00000749',
                ],
            ],
        ]);

        $filtrados = OrdencompraLegajoAnitaScanFacturaSupport::facturasPorOcs([$oc])[(int) $oc->id] ?? [];
        $docs = array_map(static fn (array $s) => (int) ($s['documento_id'] ?? 0), $filtrados);

        $this->assertNotContains(365039, $docs);
        $this->assertContains(365040, $docs);

        $svc = app(OrdencompraLegajoScanAnitaDescartarService::class);
        $this->assertTrue($svc->estaDescartado($oc, 365039));
        $this->assertFalse($svc->estaDescartado($oc, 365040));
    }

    public function test_documento_ids_descartados_por_oc(): void
    {
        Ordencompra_Legajo_Scan_Anita_Descartado::query()->create([
            'ordencompra_id' => 9_900_010,
            'documento_id' => 100,
        ]);
        Ordencompra_Legajo_Scan_Anita_Descartado::query()->create([
            'ordencompra_id' => 9_900_010,
            'documento_id' => 200,
        ]);
        Ordencompra_Legajo_Scan_Anita_Descartado::query()->create([
            'ordencompra_id' => 9_900_011,
            'documento_id' => 300,
        ]);

        $mapa = app(OrdencompraLegajoScanAnitaDescartarService::class)
            ->documentoIdsDescartadosPorOcIds([9_900_010, 9_900_011, 9_900_012]);

        $this->assertTrue($mapa[9_900_010][100] ?? false);
        $this->assertTrue($mapa[9_900_010][200] ?? false);
        $this->assertTrue($mapa[9_900_011][300] ?? false);
        $this->assertSame([], $mapa[9_900_012] ?? []);
    }
}
