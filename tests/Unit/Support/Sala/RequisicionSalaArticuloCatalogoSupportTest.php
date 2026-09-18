<?php

namespace Tests\Unit\Support\Sala;

use App\Models\Stock\Depmae;
use App\Support\Sala\RequisicionSalaArticuloCatalogoSupport;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class RequisicionSalaArticuloCatalogoSupportTest extends TestCase
{
    protected function tearDown(): void
    {
        Session::forget('usuario_depositos_ids');
        parent::tearDown();
    }

    public function test_sin_restriccion_no_filtra_catalogo(): void
    {
        Session::forget('usuario_depositos_ids');

        $this->assertNull(RequisicionSalaArticuloCatalogoSupport::idsDepositosCatalogo());
        $this->assertTrue(RequisicionSalaArticuloCatalogoSupport::articuloPermitido(999));
        $this->assertTrue(RequisicionSalaArticuloCatalogoSupport::articuloPermitido(null));
    }

    public function test_con_restriccion_incluye_origen_lab_y_deposito_usuario(): void
    {
        Session::put('usuario_depositos_ids', [30]);

        $ids = RequisicionSalaArticuloCatalogoSupport::idsDepositosCatalogo();
        $this->assertIsArray($ids);
        $this->assertContains(30, $ids);

        $id4003 = (int) Depmae::query()->where('codigo', '4003')->orderBy('id')->value('id');
        $this->assertGreaterThan(0, $id4003);
        $this->assertContains($id4003, $ids);
        $this->assertTrue(RequisicionSalaArticuloCatalogoSupport::articuloPermitido($id4003));

        $idGastro = (int) Depmae::query()->where('codigo', '1')->orderBy('id')->value('id');
        if ($idGastro > 0) {
            $this->assertFalse(RequisicionSalaArticuloCatalogoSupport::articuloPermitido($idGastro));
        }

        $this->assertFalse(RequisicionSalaArticuloCatalogoSupport::articuloPermitido(null));
    }
}
