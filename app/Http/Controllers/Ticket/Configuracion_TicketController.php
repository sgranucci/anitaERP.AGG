<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use App\Services\Ticket\TicketConfiguracionService;
use App\Support\Ticket\TicketModoOperacionSupport;
use Illuminate\Http\Request;

class Configuracion_TicketController extends Controller
{
    public function __construct(
        private TicketConfiguracionService $configuracionService
    ) {
    }

    public function index()
    {
        can('listar-configuracion-ticket');

        $filas = $this->configuracionService->filasCentrocosto();
        $activos = $filas->where('notificar_comentario_a_cc', true)->count();

        $filasArea = $this->configuracionService->filasAreadestino();
        $areasClaim = $filasArea->where('modo_operacion', TicketModoOperacionSupport::MODO_CLAIM)->count();
        $modosOperacion = TicketModoOperacionSupport::opcionesModo();

        return view('ticket.configuracion.index', compact(
            'filas',
            'activos',
            'filasArea',
            'areasClaim',
            'modosOperacion'
        ));
    }

    public function actualizar(Request $request)
    {
        can('actualizar-configuracion-ticket');

        $flags = $request->input('notificar_comentario_a_cc', []);
        if (! is_array($flags)) {
            $flags = [];
        }

        $this->configuracionService->guardarNotificarComentarioACc($flags);

        return redirect()
            ->route('consulta_configuracion_ticket')
            ->with('mensaje', 'Notificaciones por centro de costo actualizadas');
    }

    public function actualizarAreadestino(Request $request)
    {
        can('actualizar-configuracion-ticket');

        $modos = $request->input('modo_operacion', []);
        if (! is_array($modos)) {
            $modos = [];
        }

        $this->configuracionService->guardarModosAreadestino($modos);

        return redirect()
            ->route('consulta_configuracion_ticket')
            ->with('mensaje', 'Modo de operación por área actualizado');
    }
}
