<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use App\Services\Ticket\TicketBandejaService;
use Illuminate\Http\Request;

class TicketBandejaController extends Controller
{
    public function __construct(
        private TicketBandejaService $bandejaService
    ) {
    }

    public function index(Request $request)
    {
        can('listar-bandeja-ticket');

        $resultado = $this->bandejaService->listar([
            'tab' => $request->input('tab'),
            'q' => $request->input('q'),
        ]);

        return view('ticket.bandeja.index', [
            'tab' => $resultado['tab'],
            'items' => $resultado['items'],
            'contadores' => $resultado['contadores'],
            'puedeTodos' => $resultado['puede_todos'],
            'puedeCola' => $resultado['puede_cola'] ?? false,
            'areasVacias' => $resultado['areas'] === [],
            'filtroQ' => trim((string) $request->input('q', '')),
            'tecnicosPorArea' => $resultado['tecnicos_por_area'] ?? [],
        ]);
    }

    public function tomar(Request $request, int $id)
    {
        can('tomar-ticket');

        try {
            $resultado = $this->bandejaService->tomar($id);
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('edita_administracion_ticket', ['id' => $resultado['ticket_id']])
            ->with('mensaje', 'Ticket tomado. Ya podés trabajarlo.');
    }

    public function liberar(Request $request, int $id)
    {
        can('liberar-ticket');

        try {
            $this->bandejaService->liberar($id);
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }

        $tab = $request->input('tab', TicketBandejaService::TAB_COLA);

        return redirect()
            ->route('consulta_bandeja_ticket', ['tab' => $tab])
            ->with('mensaje', 'Ticket liberado a la cola del área.');
    }

    public function asignar(Request $request, int $id)
    {
        can('asignar-ticket-bandeja');

        $tecnicoId = (int) $request->input('tecnico_ticket_id', 0);
        if ($tecnicoId <= 0) {
            return redirect()
                ->back()
                ->with('error', 'Seleccioná un técnico para asignar.');
        }

        try {
            $resultado = $this->bandejaService->asignar($id, $tecnicoId);
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }

        $tab = $request->input('tab', TicketBandejaService::TAB_COLA);

        return redirect()
            ->route('consulta_bandeja_ticket', ['tab' => $tab])
            ->with('mensaje', 'Técnico asignado al ticket #'.$resultado['ticket_id'].'.');
    }

    public function contador()
    {
        if (! can('listar-bandeja-ticket', false)) {
            return response()->json(['ok' => true, 'count' => 0]);
        }

        return response()->json([
            'ok' => true,
            'count' => $this->bandejaService->contarBadge(),
        ]);
    }
}
