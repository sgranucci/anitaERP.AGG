<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\ClienteVipCaja;
use App\Repositories\Caja\TicketCanjeCajaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\Estacionamiento\EstacionamientoPvService;
use App\Services\Caja\TicketCanjeCajaEmisionService;
use App\Services\Caja\TicketCanjeCajaImpresionService;
use App\Support\Caja\Estacionamiento\EstacionamientoIdentificadorPc;
use App\Support\Caja\TicketCanjeCajaListadoFiltros;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Throwable;

class TicketCanjeCajaController extends Controller
{
    public function __construct(
        private readonly TicketCanjeCajaRepositoryInterface $repository,
        private readonly TicketCanjeCajaEmisionService $emisionService,
        private readonly TicketCanjeCajaImpresionService $impresionService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly EstacionamientoPvService $pvService,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-ticket-canje-caja');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $pc = EstacionamientoIdentificadorPc::resolver($request);
        $empresasOperables = $this->pvService->empresasConPvEnTerminal($pc, $empresa_query);
        if ($empresasOperables->isEmpty()) {
            $empresasOperables = $empresa_query;
        }

        $filtros = TicketCanjeCajaListadoFiltros::resolverDesdeRequest($request);
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId <= 0) {
            $empresaId = (int) ($empresasOperables->first()->id ?? 0);
            $filtros['empresa_id'] = $empresaId > 0 ? $empresaId : null;
        }
        if ($empresaId > 0 && ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'Empresa no permitida.');
        }

        $filtros['usuario_id'] = (int) (Auth::id() ?? 0);
        $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();

        $datas = $empresaId > 0
            ? $this->repository->leeTickets($filtros, true)
            : $this->repository->leeTickets(array_merge($filtros, ['empresa_id' => -1]), true);

        $contexto = $empresaId > 0
            ? $this->emisionService->contextoOperativo($request, $empresaId)
            : ['ok' => false, 'error' => 'Seleccione empresa.'];

        return view('caja.canjes.generacion.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => TicketCanjeCajaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => TicketCanjeCajaListadoFiltros::CAMPOS,
            'empresa_query' => $empresasOperables,
            'empresa_id' => $empresaId,
            'contexto' => $contexto,
            'porcentaje_ticket' => (float) config('caja.ticket_canje_porcentaje', 5),
            'puede_crear' => can('crear-ticket-canje-caja', false),
            'puede_reimprimir' => can('reimprimir-ticket-canje-caja', false),
            'puede_anular' => can('anular-ticket-canje-caja', false),
        ]);
    }

    public function apiContexto(Request $request)
    {
        can('listar-ticket-canje-caja');
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0 || ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return response()->json(['ok' => false, 'error' => 'Empresa inválida.'], 422);
        }

        return response()->json($this->emisionService->contextoOperativo($request, $empresaId));
    }

    public function apiResolverCliente(Request $request)
    {
        can('crear-ticket-canje-caja');
        $empresaId = (int) $request->input('empresa_id', 0);
        $doc = (string) $request->input('nro_documento', '');
        if ($empresaId <= 0 || ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return response()->json(['ok' => false, 'error' => 'Empresa inválida.'], 422);
        }

        try {
            $this->emisionService->exigirContextoOperativo($request, $empresaId);
            $cli = $this->emisionService->resolverCliente(
                $empresaId,
                $doc,
                (int) $request->input('cliente_vip_caja_id', 0) ?: null,
            );

            return response()->json(['ok' => true, ...$cli]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiPreview(Request $request)
    {
        can('crear-ticket-canje-caja');
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0 || ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return response()->json(['ok' => false, 'error' => 'Empresa inválida.'], 422);
        }

        try {
            $this->emisionService->exigirContextoOperativo($request, $empresaId);
            $prev = $this->emisionService->preview(
                $empresaId,
                (string) $request->input('nro_documento', ''),
                (float) $request->input('monto_venta', 0),
                (int) $request->input('cantidad', 1),
                (int) $request->input('cliente_vip_caja_id', 0) ?: null,
            );

            return response()->json(['ok' => true, ...$prev]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiEmitir(Request $request)
    {
        can('crear-ticket-canje-caja');
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0 || ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return response()->json(['ok' => false, 'error' => 'Empresa inválida.'], 422);
        }

        try {
            $resultado = $this->emisionService->emitir(
                $request,
                $empresaId,
                (string) $request->input('nro_documento', ''),
                (float) $request->input('monto_venta', 0),
                (int) $request->input('cantidad', 1),
                (int) $request->input('cliente_vip_caja_id', 0) ?: null,
            );

            return response()->json($resultado);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'Error al emitir: '.$e->getMessage()], 500);
        }
    }

    public function apiReimprimir(Request $request, int $id)
    {
        can('reimprimir-ticket-canje-caja');
        $ticket = $this->repository->findOrFail($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $ticket->empresa_id)) {
            return response()->json(['ok' => false, 'error' => 'Empresa no permitida.'], 403);
        }
        if ((int) $ticket->usuario_id !== (int) Auth::id() && ! can('listar-ticket-canje-caja', false)) {
            return response()->json(['ok' => false, 'error' => 'Solo puede reimprimir tickets propios.'], 403);
        }

        $resultado = $this->impresionService->imprimir($ticket, $request, 'Duplicado');

        return response()->json($resultado, $resultado['ok'] ? 200 : 422);
    }

    public function apiAnular(Request $request, int $id)
    {
        can('anular-ticket-canje-caja');
        $ticket = $this->repository->findOrFail($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $ticket->empresa_id)) {
            return response()->json(['ok' => false, 'error' => 'Empresa no permitida.'], 403);
        }
        if ((int) $ticket->usuario_id !== (int) Auth::id()) {
            return response()->json(['ok' => false, 'error' => 'Solo puede anular tickets propios.'], 403);
        }

        $resultado = $this->emisionService->anular($ticket);

        return response()->json($resultado, $resultado['ok'] ? 200 : 422);
    }

    public function consultaClienteVip(Request $request)
    {
        can('crear-ticket-canje-caja');
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0 || ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return response()->json(['data' => '<tr><td colspan="8" class="text-center text-danger">Empresa inválida</td></tr>']);
        }

        try {
            $this->emisionService->exigirContextoOperativo($request, $empresaId);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'data' => '<tr><td colspan="8" class="text-center text-danger">'.e($e->getMessage()).'</td></tr>',
            ], 422);
        }

        $consulta = trim((string) $request->input('consulta', ''));
        $query = ClienteVipCaja::query()
            ->with('empresa')
            ->where('empresa_id', $empresaId)
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->limit(50);

        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('nrodocumento', 'like', '%'.$consulta.'%')
                    ->orWhere('apellido', 'like', '%'.$consulta.'%')
                    ->orWhere('nombre', 'like', '%'.$consulta.'%')
                    ->orWhere('nickname', 'like', '%'.$consulta.'%')
                    ->orWhere('numeroid', 'like', '%'.$consulta.'%');
            });
        }

        $html = '';
        foreach ($query->get() as $row) {
            $nombre = e($row->nombreCompleto());
            $html .= '<tr>';
            $html .= '<td>'.(int) $row->id.'</td>';
            $html .= '<td>'.e((string) $row->numeroid).'</td>';
            $html .= '<td>'.e((string) $row->nrodocumento).'</td>';
            $html .= '<td>'.$nombre.'</td>';
            $html .= '<td>'.e((string) ($row->nickname ?? '')).'</td>';
            $html .= '<td>'.e((string) ($row->localidad ?? '')).'</td>';
            $html .= '<td>'.e((string) ($row->empresa->nombre ?? '')).'</td>';
            $html .= '<td><a href="#" class="btn btn-warning btn-sm eligeconsultaclientevip"'
                .' data-id="'.(int) $row->id.'"'
                .' data-numeroid="'.e((string) $row->numeroid).'"'
                .' data-nrodocumento="'.e((string) $row->nrodocumento).'"'
                .' data-nombre-completo="'.$nombre.'">Elegir</a></td>';
            $html .= '</tr>';
        }

        if ($html === '') {
            $html = '<tr><td colspan="8" class="text-center text-muted">Sin resultados</td></tr>';
        }

        return response()->json(['data' => $html]);
    }
}
