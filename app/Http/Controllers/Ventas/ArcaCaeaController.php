<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Ventas\ArcaCaea;
use App\Services\Arca\ArcaCaeaAnitaSyncService;
use App\Services\Arca\ArcaWsfeCaeaService;
use App\Support\Ventas\CaeaQuincenaSupport;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArcaCaeaController extends Controller
{
    public function __construct(
        private ArcaWsfeCaeaService $caeaService,
        private ArcaCaeaAnitaSyncService $anitaSync,
    ) {}

    public function index(Request $request)
    {
        can('listar-arca-caea');

        $user = Auth::user();
        $user->loadMissing('usuario_empresas');
        $empresaIdsPermitidas = $user->usuario_empresas->pluck('id')->map(fn ($id) => (int) $id)->all();

        $empresaId = (int) $request->get('empresa_id', 0);
        $periodo = (int) $request->get('periodo', 0);
        $estado = trim((string) $request->get('estado', ''));

        $query = ArcaCaea::query()
            ->with(['empresa', 'solicitadoPor'])
            ->whereIn('empresa_id', $empresaIdsPermitidas)
            ->orderByDesc('periodo')
            ->orderByDesc('orden')
            ->orderByDesc('id');

        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }
        if ($periodo > 0) {
            $query->where('periodo', $periodo);
        }
        if ($estado !== '') {
            $query->where('estado', $estado);
        }

        $registros = $query->paginate(30)->appends($request->query());
        $empresas = $user->usuario_empresas->sortBy('nombre');
        $quincenasVentana = CaeaQuincenaSupport::quincenasEnVentanaSolicitud();
        $puedeSolicitar = can('solicitar-arca-caea', false);
        $puedeGrabarAnita = $puedeSolicitar && $this->anitaSync->estaHabilitado();

        return view('ventas.arca_caea.index', compact(
            'registros',
            'empresas',
            'empresaId',
            'periodo',
            'estado',
            'quincenasVentana',
            'puedeSolicitar',
            'puedeGrabarAnita',
        ));
    }

    public function show(Request $request, int $id)
    {
        can('ver-arca-caea');

        $registro = $this->resolverRegistroPermitido($id);
        $registro->load(['empresa', 'solicitadoPor']);
        $puedeReintentar = can('solicitar-arca-caea', false) && ! $registro->estaAutorizado();
        $puedeGrabarAnita = can('solicitar-arca-caea', false)
            && $this->anitaSync->estaHabilitado()
            && $registro->estaAutorizado();

        if ($request->ajax()) {
            return view('ventas.arca_caea.partials.detalle_contenido', compact(
                'registro',
                'puedeReintentar',
                'puedeGrabarAnita',
            ));
        }

        return redirect()->route('arca_caea');
    }

    public function solicitar(Request $request)
    {
        can('solicitar-arca-caea');

        $user = Auth::user();
        $user->loadMissing('usuario_empresas');
        $empresaIdsPermitidas = $user->usuario_empresas->pluck('id')->map(fn ($id) => (int) $id)->all();

        $data = $request->validate([
            'empresa_id' => ['required', 'integer', Rule::in($empresaIdsPermitidas)],
            'periodo' => ['required', 'integer', 'min:200001', 'max:299912'],
            'orden' => ['required', 'integer', Rule::in([1, 2])],
            'solo_consultar' => ['nullable', 'boolean'],
        ]);

        $resultado = $this->caeaService->solicitarYGuardar(
            (int) $data['empresa_id'],
            (int) $data['periodo'],
            (int) $data['orden'],
            ArcaCaea::ORIGEN_MANUAL,
            (int) $user->id,
            (bool) ($data['solo_consultar'] ?? false),
        );

        if ($resultado['ok']) {
            return redirect()
                ->route('arca_caea')
                ->with('mensaje', $resultado['mensaje']);
        }

        return redirect()
            ->route('arca_caea')
            ->with('error', $resultado['mensaje']);
    }

    public function reintentar(int $id)
    {
        can('solicitar-arca-caea');

        $registro = $this->resolverRegistroPermitido($id);

        $resultado = $this->caeaService->solicitarYGuardar(
            (int) $registro->empresa_id,
            (int) $registro->periodo,
            (int) $registro->orden,
            ArcaCaea::ORIGEN_MANUAL,
            (int) Auth::id(),
            false,
        );

        if ($resultado['ok']) {
            return redirect()
                ->route('arca_caea')
                ->with('mensaje', $resultado['mensaje']);
        }

        return redirect()
            ->route('arca_caea')
            ->with('error', $resultado['mensaje']);
    }

    public function grabarAnita(int $id)
    {
        can('solicitar-arca-caea');

        $registro = $this->resolverRegistroPermitido($id);

        try {
            $resultado = $this->anitaSync->grabarEnAnita($registro);
        } catch (\Throwable $e) {
            return redirect()
                ->route('arca_caea')
                ->with('error', 'No se pudo grabar en Anita: '.$e->getMessage());
        }

        if ($resultado['ok']) {
            return redirect()
                ->route('arca_caea')
                ->with('mensaje', $resultado['mensaje']);
        }

        return redirect()
            ->route('arca_caea')
            ->with('error', $resultado['mensaje']);
    }

    private function resolverRegistroPermitido(int $id): ArcaCaea
    {
        $user = Auth::user();
        $user->loadMissing('usuario_empresas');
        $empresaIdsPermitidas = $user->usuario_empresas->pluck('id')->map(fn ($i) => (int) $i)->all();

        return ArcaCaea::query()
            ->whereIn('empresa_id', $empresaIdsPermitidas)
            ->findOrFail($id);
    }
}
