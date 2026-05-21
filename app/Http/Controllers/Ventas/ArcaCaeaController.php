<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Ventas\ArcaCaea;
use App\Services\Arca\ArcaWsfeCaeaService;
use App\Support\Ventas\CaeaQuincenaSupport;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArcaCaeaController extends Controller
{
    public function __construct(
        private ArcaWsfeCaeaService $caeaService,
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

        return view('ventas.arca_caea.index', compact(
            'registros',
            'empresas',
            'empresaId',
            'periodo',
            'estado',
            'quincenasVentana',
            'puedeSolicitar',
        ));
    }

    public function show(int $id)
    {
        can('ver-arca-caea');

        $registro = $this->resolverRegistroPermitido($id);
        $registro->load(['empresa', 'solicitadoPor']);

        return view('ventas.arca_caea.show', compact('registro'));
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
                ->route('arca_caea_ver', $resultado['registro']?->id ?? $id)
                ->with('mensaje', $resultado['mensaje']);
        }

        return redirect()
            ->route('arca_caea_ver', $id)
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
