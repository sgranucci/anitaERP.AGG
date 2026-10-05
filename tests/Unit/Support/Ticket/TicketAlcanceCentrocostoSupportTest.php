<?php

namespace Tests\Unit\Support\Ticket;

use App\Models\Seguridad\Usuario;
use App\Support\Ticket\TicketAlcanceCentrocostoSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class TicketAlcanceCentrocostoSupportTest extends TestCase
{
    protected function tearDown(): void
    {
        Session::forget('rol_nombre');
        Session::forget('usuario_empresas');
        parent::tearDown();
    }

    public function test_capital_humano_sigue_filtrando_solo_por_centro_de_costo(): void
    {
        Session::put('rol_nombre', 'enc-Capital Humano');
        Session::put('usuario_empresas', [
            ['id' => 1, 'nombre' => 'BIYEMAS S.A.'],
        ]);

        $sql = $this->sqlFiltro($this->viewer());

        $this->assertStringContainsString('usuario.centrocosto_id', $sql);
        $this->assertStringNotContainsString('usuario_empresa', $sql);
    }

    public function test_encargado_seguridad_recorta_emisores_a_su_establecimiento(): void
    {
        Session::put('rol_nombre', 'enc-SEGURIDAD');
        Session::put('usuario_empresas', [
            ['id' => 2, 'nombre' => 'KANDIKO S.A.'],
        ]);

        $query = DB::table('ticket')->join('usuario', 'usuario.id', '=', 'ticket.usuario_id');
        TicketAlcanceCentrocostoSupport::aplicarFiltroEmisoresMismoCentrocosto($query, $this->viewer());

        $sql = $query->toSql();
        $this->assertStringContainsString('usuario.centrocosto_id', $sql);
        $this->assertStringContainsString('usuario_empresa', $sql);
        $this->assertSame([2, 2, 205], array_map('intval', $query->getBindings()));
    }

    private function sqlFiltro(Usuario $viewer): string
    {
        $query = DB::table('ticket')->join('usuario', 'usuario.id', '=', 'ticket.usuario_id');
        TicketAlcanceCentrocostoSupport::aplicarFiltroEmisoresMismoCentrocosto($query, $viewer);

        return $query->toSql();
    }

    private function viewer(): Usuario
    {
        $viewer = new Usuario;
        $viewer->id = 205;
        $viewer->centrocosto_id = 2;

        return $viewer;
    }
}
