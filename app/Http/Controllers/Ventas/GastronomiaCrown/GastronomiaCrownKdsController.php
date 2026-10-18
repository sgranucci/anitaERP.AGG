<?php

namespace App\Http\Controllers\Ventas\GastronomiaCrown;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\Sala;
use App\Models\Stock\Articulo;
use App\Models\Stock\Formula_Articulo;
use App\Models\Ventas\AreaComandaGastronomia;
use App\Models\Ventas\GastronomiaCrown\GastroCrownComanda;
use App\Models\Ventas\GastronomiaCrown\GastroCrownComandaEvento;
use App\Models\Ventas\GastronomiaCrown\GastroCrownComandaLinea;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Ventas\GastronomiaCrown\GastronomiaCrownPosService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\SqlDialectSupport;
use App\Support\Stock\FormulaArticuloSubformulaPosSupport;
use App\Support\Ventas\GastronomiaCrown\GastronomiaCrownKdsPreparaSupport;
use App\Support\Ventas\GastronomiaFormulaOpcionalSeleccion;
use App\Support\Ventas\UsuarioSalaAsignadaSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * KDS visual Crown: comandas abiertas por sala con foto del plato y de los insumos.
 */
class GastronomiaCrownKdsController extends Controller
{
    /** @var list<string> */
    private const ESTADOS_ABIERTOS = [
        GastroCrownComanda::ESTADO_FIRED,
        GastroCrownComanda::ESTADO_PREPARING,
        GastroCrownComanda::ESTADO_READY,
    ];

    public function __construct(
        private GastronomiaCrownPosService $posService,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request): View
    {
        $this->assertCrown();
        $this->assertPermiso();

        $config = $this->posService->resolverConfig($request);
        $salas = $this->salasVisibles();
        $salaId = $this->resolverSalaId($request, $config?->sala_id ? (int) $config->sala_id : null, $salas);

        return view('ventas.gastronomia_crown.kds.index', [
            'salas' => $salas,
            'salaId' => $salaId,
            'areas' => $this->areasVisibles(),
            'areaId' => $request->filled('area_id') ? (int) $request->input('area_id') : null,
            'config' => $config,
            'refrescoSegundos' => max(3, (int) config('gastronomia.crown.kds_refresco_segundos', 5)),
        ]);
    }

    public function apiEstado(Request $request): JsonResponse
    {
        $this->assertCrown();
        $this->assertPermiso();

        $request->validate([
            'sala_id' => 'nullable|integer',
            'area_id' => 'nullable|integer',
        ]);

        $salas = $this->salasVisibles();
        $salaId = $this->resolverSalaId($request, null, $salas);
        $areaId = $request->filled('area_id') ? (int) $request->input('area_id') : null;

        $query = GastroCrownComanda::query()
            ->with([
                'lineas.cuentaLinea.articulo',
                'mesa:id,codigo,nombre,sala_id',
                'mozo:id,nombre,codigo',
                'areaComanda:id,codigo,nombre',
                'cuenta:id,sala_id,mesa_gastronomia_id,cubiertos,observacion',
            ])
            ->whereIn('estado', self::ESTADOS_ABIERTOS);

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id');

        if ($areaId !== null && $areaId > 0) {
            $query->where('area_comanda_gastronomia_id', $areaId);
        }

        if ($salaId !== null) {
            $query->where(function ($q) use ($salaId) {
                $q->whereHas('cuenta', fn ($c) => $c->where('sala_id', $salaId))
                    ->orWhereHas('mesa', fn ($m) => $m->where('sala_id', $salaId));
            });
        } else {
            $idsSalas = UsuarioSalaAsignadaSupport::idsDesdeSesion();
            if ($idsSalas !== []) {
                $query->where(function ($q) use ($idsSalas) {
                    $q->whereHas('cuenta', fn ($c) => $c->whereIn('sala_id', $idsSalas))
                        ->orWhereHas('mesa', fn ($m) => $m->whereIn('sala_id', $idsSalas));
                });
            }
        }

        $comandas = $query
            ->orderByRaw(SqlDialectSupport::ordenPorLista('estado', self::ESTADOS_ABIERTOS))
            ->orderBy('fired_at')
            ->orderBy('id')
            ->limit(120)
            ->get()
            ->map(fn (GastroCrownComanda $c) => $this->serializarComanda($c))
            ->values();

        return response()->json([
            'ok' => true,
            'sala_id' => $salaId,
            'area_id' => $areaId,
            'generado' => now()->format('H:i:s'),
            'total' => $comandas->count(),
            'comandas' => $comandas,
        ]);
    }

    public function apiCambiarEstado(Request $request, int $comandaId): JsonResponse
    {
        $this->assertCrown();
        $this->assertPermiso();

        $request->validate([
            'estado' => 'required|in:preparing,ready,delivered',
        ]);

        $query = GastroCrownComanda::query()->whereKey($comandaId);
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id');
        $comanda = $query->first();

        if (! $comanda) {
            return response()->json(['ok' => false, 'mensaje' => 'Comanda no encontrada.'], 404);
        }
        if ($comanda->estado === GastroCrownComanda::ESTADO_VOID) {
            return response()->json(['ok' => false, 'mensaje' => 'La comanda está anulada.'], 422);
        }

        $estado = (string) $request->input('estado');
        $comanda->estado = $estado;
        $comanda->save();

        GastroCrownComandaEvento::query()->create([
            'gastro_crown_comanda_id' => $comanda->id,
            'evento' => $estado,
            'usuario_id' => Auth::id(),
            'detalle' => 'KDS → '.$estado,
            'created_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'comanda_id' => (int) $comanda->id,
            'estado' => $comanda->estado,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarComanda(GastroCrownComanda $comanda): array
    {
        $fired = $comanda->fired_at ?? $comanda->created_at;

        return [
            'id' => (int) $comanda->id,
            'numero' => $comanda->numero,
            'tipo' => $comanda->tipo,
            'estado' => $comanda->estado,
            'cuenta_id' => (int) $comanda->gastro_crown_cuenta_id,
            'sala_id' => $comanda->cuenta?->sala_id ?? $comanda->mesa?->sala_id,
            'mesa' => $comanda->mesa
                ? trim(($comanda->mesa->codigo ? $comanda->mesa->codigo.' — ' : '').$comanda->mesa->nombre)
                : 'Cuenta #'.$comanda->gastro_crown_cuenta_id,
            'mozo' => $comanda->mozo->nombre ?? null,
            'area' => $comanda->areaComanda->nombre ?? null,
            'area_codigo' => $comanda->areaComanda->codigo ?? null,
            'cubiertos' => (int) ($comanda->cuenta->cubiertos ?? 0),
            'observacion' => $comanda->observacion ?: ($comanda->cuenta->observacion ?? null),
            'fired_at' => $fired?->toIso8601String(),
            'fired_hora' => $fired?->format('H:i'),
            'minutos' => $fired ? (int) $fired->diffInMinutes(now()) : 0,
            'lineas' => $comanda->lineas->map(fn (GastroCrownComandaLinea $l) => $this->serializarLinea($l))->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarLinea(GastroCrownComandaLinea $linea): array
    {
        $cuentaLinea = $linea->cuentaLinea;
        $articulo = $cuentaLinea?->articulo;
        if (! $articulo && $linea->sku) {
            $articulo = Articulo::query()->where('sku', $linea->sku)->first();
        }

        $cantidad = (float) $linea->cantidad;
        $visual = $articulo
            ? GastronomiaCrownKdsPreparaSupport::payloadVisualKds($articulo, $cantidad)
            : ['foto_url' => null, 'insumos' => []];

        return [
            'id' => (int) $linea->id,
            'sku' => $linea->sku,
            'descripcion' => $linea->descripcion,
            'cantidad' => $cantidad,
            'observacion' => $linea->observacion,
            'prepara' => (bool) $linea->prepara,
            'foto_url' => $visual['foto_url'] ?? null,
            'insumos' => $visual['insumos'] ?? [],
            'opcionales' => $this->etiquetasOpcionales($cuentaLinea?->opcionales_json ?? []),
        ];
    }

    /**
     * @param  array<string|int, mixed>  $opcionales
     * @return list<array{orden:string, sku:string, descripcion:string}>
     */
    private function etiquetasOpcionales(array $opcionales): array
    {
        $out = [];
        foreach ($opcionales as $orden => $valor) {
            $decoded = GastronomiaFormulaOpcionalSeleccion::decodificar($valor);
            if ($decoded === null) {
                continue;
            }

            if ($decoded['tipo'] === 'articulo') {
                $art = Articulo::query()->find($decoded['id']);
                $out[] = [
                    'orden' => (string) $orden,
                    'sku' => (string) ($art->sku ?? ''),
                    'descripcion' => (string) ($art->descripcion ?? 'Opcional'),
                ];

                continue;
            }

            $etiqueta = FormulaArticuloSubformulaPosSupport::etiquetaOpcional(
                Formula_Articulo::query()->with('articulos')->find($decoded['id']),
                $decoded['id'],
            );
            $out[] = [
                'orden' => (string) $orden,
                'sku' => $etiqueta['sku'],
                'descripcion' => $etiqueta['descripcion'],
            ];
        }

        return $out;
    }

    /**
     * @return list<array{id:int, codigo:?string, nombre:string}>
     */
    private function salasVisibles(): array
    {
        $query = Sala::query();
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id', true);
        UsuarioSalaAsignadaSupport::aplicarFiltro($query, 'id');

        return $query
            ->orderBy('codigo')
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre'])
            ->map(fn (Sala $s) => [
                'id' => (int) $s->id,
                'codigo' => $s->codigo,
                'nombre' => (string) $s->nombre,
            ])
            ->all();
    }

    /**
     * @return list<array{id:int, codigo:?string, nombre:string}>
     */
    private function areasVisibles(): array
    {
        $query = AreaComandaGastronomia::query();
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id', true);

        return $query
            ->orderBy('codigo')
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre'])
            ->map(fn (AreaComandaGastronomia $a) => [
                'id' => (int) $a->id,
                'codigo' => $a->codigo,
                'nombre' => (string) $a->nombre,
            ])
            ->all();
    }

    /**
     * @param  list<array{id:int, codigo:?string, nombre:string}>  $salas
     */
    private function resolverSalaId(Request $request, ?int $fallback, array $salas): ?int
    {
        $salaId = $request->filled('sala_id') ? (int) $request->input('sala_id') : ($fallback ?: null);
        if ($salaId === null || $salaId <= 0) {
            return null;
        }
        if (! UsuarioSalaAsignadaSupport::salaIdPermitida($salaId)) {
            return null;
        }
        $permitidas = array_map(static fn (array $s): int => $s['id'], $salas);
        if ($permitidas !== [] && ! in_array($salaId, $permitidas, true)) {
            return null;
        }

        return $salaId;
    }

    private function assertCrown(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            abort(404);
        }
    }

    private function assertPermiso(): void
    {
        if (can('usar-kds-gastronomia-crown', false) || can('usar-pos-gastronomia-crown', false)) {
            return;
        }

        abort(403, 'No tiene permisos para el KDS de gastronomía Crown.');
    }
}
