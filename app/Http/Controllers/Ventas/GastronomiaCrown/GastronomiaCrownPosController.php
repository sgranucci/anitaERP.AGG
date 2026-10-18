<?php

namespace App\Http\Controllers\Ventas\GastronomiaCrown;

use App\Http\Controllers\Controller;
use App\Models\Stock\Articulo;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\GastronomiaCrown\GastroCrownComanda;
use App\Models\Ventas\GastronomiaCrown\GastroCrownCuenta;
use App\Models\Ventas\GastronomiaCrown\GastroCrownCuentaLinea;
use App\Models\Ventas\GastronomiaCrown\GastroPlanoMesa;
use App\Models\Ventas\MesaGastronomia;
use App\Services\Stock\FormulaArticuloService;
use App\Services\Ventas\Gastronomia\GastronomiaFormulaOpcionalesService;
use App\Services\Ventas\GastronomiaCrown\GastronomiaCrownPosService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Stock\FormulaArticuloGastronomia;
use App\Support\Ventas\GastronomiaCrown\GastronomiaCrownPosModos;
use App\Support\Ventas\GastronomiaCrown\GastroPlanoMesaLayoutSupport;
use App\Support\Ventas\GastronomiaFormulaOpcionalSeleccion;
use App\Support\Ventas\GastronomiaIdentificadorPc;
use App\Support\Ventas\GastronomiaSalaSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GastronomiaCrownPosController extends Controller
{
    public function __construct(
        private GastronomiaCrownPosService $posService,
        private GastronomiaFormulaOpcionalesService $opcionalesService,
        private FormulaArticuloService $formulaArticuloService,
    ) {
    }

    public function index(Request $request): View
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $config = $this->posService->resolverConfig($request);
        if ($config === null) {
            return view('ventas.gastronomia_crown.pos.sin_config', [
                'identificador' => GastronomiaIdentificadorPc::resolver($request),
            ]);
        }

        $modo = $config->modoPosEfectivo();

        $usaMesas = GastronomiaCrownPosModos::usaMesas($modo);
        $planos = $usaMesas ? $this->planosParaConfig($config) : [];

        return view('ventas.gastronomia_crown.pos.index', [
            'config' => $config,
            'modo' => $modo,
            'modoLabel' => GastronomiaCrownPosModos::modos()[$modo] ?? $modo,
            'usaMesas' => $usaMesas,
            'planos' => $planos,
            'hotkeys' => $this->hotkeysParaConfig($config),
            'herramientas' => $this->herramientasParaConfig($config),
        ]);
    }

    public function apiConfig(Request $request): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        $modo = $config->modoPosEfectivo();
        $usaMesas = GastronomiaCrownPosModos::usaMesas($modo);

        return response()->json([
            'ok' => true,
            'planos' => $usaMesas ? $this->planosParaConfig($config) : [],
            'identificador_pc' => $config->identificador_pc,
            'empresa_id' => (int) $config->empresa_id,
            'modo_pos' => $modo,
            'politica_comanda' => $config->politica_comanda,
            'solo_marcha' => (bool) $config->solo_marcha,
            'habilita_hotel' => (bool) $config->habilita_hotel,
            'habilita_canje_gst' => (bool) $config->habilita_canje_gst,
            'pide_cubiertos' => (bool) $config->pide_cubiertos,
            'sala_id' => $config->sala_id ? (int) $config->sala_id : null,
            'listaprecio_id' => (int) $config->listaprecio_id,
            'usa_mesas' => GastronomiaCrownPosModos::usaMesas($modo),
            'hotkeys' => $this->hotkeysParaConfig($config),
            'herramientas' => $this->herramientasParaConfig($config),
        ]);
    }

    public function apiMesas(Request $request): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        $query = MesaGastronomia::query()
            ->where('empresa_id', $config->empresa_id)
            ->orderBy('codigo')
            ->orderBy('nombre');

        GastronomiaSalaSupport::aplicarFiltroSiCorresponde(
            $query,
            $request->filled('sala_id') ? (int) $request->input('sala_id') : ($config->sala_id ? (int) $config->sala_id : null)
        );

        if ($config->ubicacion_id) {
            $query->where('ubicacion_id', $config->ubicacion_id);
        }

        $abiertas = GastroCrownCuenta::query()
            ->where('empresa_id', $config->empresa_id)
            ->whereIn('estado', [GastroCrownCuenta::ESTADO_ABIERTA, GastroCrownCuenta::ESTADO_COBRANDO])
            ->whereNotNull('mesa_gastronomia_id')
            ->pluck('id', 'mesa_gastronomia_id');

        $mesas = $query->get(['id', 'codigo', 'nombre', 'sala_id', 'ubicacion_id'])->map(function ($m) use ($abiertas) {
            return [
                'id' => $m->id,
                'codigo' => $m->codigo,
                'nombre' => $m->nombre,
                'sala_id' => $m->sala_id,
                'cuenta_abierta_id' => $abiertas[$m->id] ?? null,
                'ocupada' => isset($abiertas[$m->id]),
            ];
        });

        return response()->json(['ok' => true, 'mesas' => $mesas]);
    }

    /**
     * Planos asignados a la PC + ocupación actual de las mesas que contienen.
     */
    public function apiPlanosEstado(Request $request): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        $planos = $this->planosParaConfig($config);

        $mesaIds = collect($planos)
            ->flatMap(fn (array $p) => collect($p['layout']['nodes'] ?? [])->pluck('mesa_id'))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($mesaIds->isEmpty()) {
            return response()->json(['ok' => true, 'planos' => $planos, 'mesas' => []]);
        }

        $abiertas = GastroCrownCuenta::query()
            ->where('empresa_id', $config->empresa_id)
            ->whereIn('estado', [GastroCrownCuenta::ESTADO_ABIERTA, GastroCrownCuenta::ESTADO_COBRANDO])
            ->whereIn('mesa_gastronomia_id', $mesaIds->all())
            ->pluck('id', 'mesa_gastronomia_id');

        $mesas = MesaGastronomia::query()
            ->whereIn('id', $mesaIds->all())
            ->get(['id', 'codigo', 'nombre', 'numeromesa', 'sala_id'])
            ->map(fn (MesaGastronomia $m) => [
                'id' => (int) $m->id,
                'codigo' => (string) ($m->codigo ?: $m->numeromesa ?: $m->id),
                'nombre' => (string) $m->nombre,
                'sala_id' => $m->sala_id ? (int) $m->sala_id : null,
                'cuenta_abierta_id' => isset($abiertas[$m->id]) ? (int) $abiertas[$m->id] : null,
                'ocupada' => isset($abiertas[$m->id]),
            ])
            ->values();

        return response()->json(['ok' => true, 'planos' => $planos, 'mesas' => $mesas]);
    }

    /**
     * Planos activos asignados a la PC, en orden, con el layout listo para el visor.
     *
     * @return list<array<string, mixed>>
     */
    private function planosParaConfig(ConfiguracionPuntoventaGastronomia $config): array
    {
        return $config->planos()
            ->where('gastro_plano_mesa.activo', true)
            ->get()
            ->map(fn (GastroPlanoMesa $plano) => [
                'id' => (int) $plano->id,
                'codigo' => (string) ($plano->codigo ?? ''),
                'nombre' => (string) $plano->nombre,
                'sala_id' => $plano->sala_id ? (int) $plano->sala_id : null,
                'layout' => GastroPlanoMesaLayoutSupport::paraVisor($plano->layout_json),
            ])
            ->values()
            ->all();
    }

    public function apiCuentasActivas(Request $request): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        $cuentas = GastroCrownCuenta::query()
            ->with(['mesa:id,codigo,nombre', 'mozo:id,nombre', 'lineas'])
            ->where('empresa_id', $config->empresa_id)
            ->whereIn('estado', [GastroCrownCuenta::ESTADO_ABIERTA, GastroCrownCuenta::ESTADO_COBRANDO])
            ->tap(function ($q) use ($config) {
                GastronomiaSalaSupport::aplicarFiltroSiCorresponde(
                    $q,
                    $config->sala_id ? (int) $config->sala_id : null
                );
            })
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (GastroCrownCuenta $c) => $this->posService->serializarCuenta($c));

        return response()->json(['ok' => true, 'cuentas' => $cuentas]);
    }

    public function apiAbrirMesa(Request $request): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $request->validate([
            'mesa_id' => 'required|integer',
            'cubiertos' => 'nullable|integer|min:0|max:999',
            'mozo_gastronomia_id' => 'nullable|integer',
        ]);

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        try {
            $cuenta = $this->posService->abrirMesa($config, (int) $request->input('mesa_id'), $request->only([
                'cubiertos', 'mozo_gastronomia_id',
            ]));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'cuenta' => $this->posService->serializarCuenta($cuenta),
        ]);
    }

    public function apiAbrirCuenta(Request $request): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        try {
            $cuenta = $this->posService->abrirCuentaLibre($config, $request->only([
                'cubiertos', 'mozo_gastronomia_id',
            ]));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'cuenta' => $this->posService->serializarCuenta($cuenta),
        ]);
    }

    public function apiCuentaVer(int $id, Request $request): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        try {
            $cuenta = $this->posService->cuentaAbierta($id, (int) $config->empresa_id);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'cuenta' => $this->posService->serializarCuenta($cuenta),
        ]);
    }

    public function apiAgregarLinea(Request $request, int $id): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $request->validate([
            'sku' => 'required|string|max:30',
            'cantidad' => 'nullable|numeric|min:0.001',
            'observacion' => 'nullable|string|max:255',
            'area_comanda_gastronomia_id' => 'nullable|integer',
            'prepara' => 'nullable|boolean',
            'opcionales' => 'nullable|array',
        ]);

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        $opcionales = GastronomiaFormulaOpcionalSeleccion::normalizarMapaDesdeRequest(
            (array) ($request->input('opcionales') ?? [])
        );

        try {
            $cuenta = $this->posService->cuentaAbierta($id, (int) $config->empresa_id);
            $articulo = $this->posService->resolverArticuloPorSku($config, (string) $request->input('sku'));
            if (! $articulo) {
                throw new \InvalidArgumentException('Artículo no encontrado: '.$request->input('sku'));
            }

            // Paridad AGG: el ítem no se carga hasta tener los opcionales elegidos.
            $faltanOpcionales = $this->erroresOpcionalesAntesDeAgregar($articulo, $opcionales);
            if ($faltanOpcionales !== null) {
                return response()->json($faltanOpcionales, 422);
            }

            $this->posService->agregarLineaPorSku(
                $cuenta,
                $config,
                (string) $request->input('sku'),
                (float) ($request->input('cantidad') ?: 1),
                $request->input('observacion'),
                $request->filled('area_comanda_gastronomia_id')
                    ? (int) $request->input('area_comanda_gastronomia_id')
                    : null,
                $request->has('prepara') ? $request->boolean('prepara') : null,
                $opcionales,
            );
            $cuenta = $this->posService->cuentaAbierta($id, (int) $config->empresa_id);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'cuenta' => $this->posService->serializarCuenta($cuenta),
        ]);
    }

    public function apiOpcionalesArticulo(Request $request, int $articuloId): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        $articulo = Articulo::query()->find($articuloId);
        if (! $articulo) {
            return response()->json(['ok' => false, 'mensaje' => 'Artículo no encontrado.'], 404);
        }

        return response()->json([
            'ok' => true,
            'articulo' => [
                'id' => (int) $articulo->id,
                'sku' => $articulo->sku,
                'descripcion' => $articulo->descripcion,
            ],
            'grupos' => $this->gruposOpcionales($articulo),
        ]);
    }

    public function apiOpcionalesLinea(Request $request, int $cuentaId, int $lineaId): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $request->validate(['opcionales' => 'nullable|array']);

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        $opcionales = GastronomiaFormulaOpcionalSeleccion::normalizarMapaDesdeRequest(
            (array) ($request->input('opcionales') ?? [])
        );

        try {
            $cuenta = $this->posService->cuentaAbierta($cuentaId, (int) $config->empresa_id);
            $linea = $this->lineaDeCuenta($cuenta, $lineaId);
            if (! $linea->articulo_id) {
                throw new \InvalidArgumentException('La línea no tiene artículo (separador).');
            }

            $articulo = Articulo::query()->find((int) $linea->articulo_id);
            if (! $articulo) {
                throw new \InvalidArgumentException('Artículo de la línea no encontrado.');
            }

            $faltanOpcionales = $this->erroresOpcionalesAntesDeAgregar($articulo, $opcionales);
            if ($faltanOpcionales !== null) {
                return response()->json($faltanOpcionales + ['linea_id' => (int) $linea->id], 422);
            }

            $this->posService->actualizarOpcionalesLinea($cuenta, $linea, $opcionales);
            $cuenta = $this->posService->cuentaAbierta($cuentaId, (int) $config->empresa_id);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'cuenta' => $this->posService->serializarCuenta($cuenta)]);
    }

    /**
     * Receta (fórmula) del artículo para abrir el ABM en otra solapa.
     */
    public function apiRecetaArticulo(Request $request, int $articuloId): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $resuelto = $this->formulaArticuloService->resolverIdParaArticulo($articuloId);
        $formulaId = (int) ($resuelto['formula_id'] ?? 0);
        if ($formulaId <= 0) {
            return response()->json([
                'ok' => false,
                'mensaje' => $resuelto['mensaje'] ?? 'El artículo no tiene receta asociada.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'formula_id' => $formulaId,
            'origen' => $resuelto['origen'] ?? null,
            'url' => route('editar_formula_articulo', [
                'id' => $formulaId,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]),
        ]);
    }

    public function apiAreasKds(Request $request): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        return response()->json([
            'ok' => true,
            'areas' => $this->posService->listarAreasKds($config),
        ]);
    }

    public function apiDestinoLinea(Request $request, int $cuentaId, int $lineaId): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $request->validate([
            'area_comanda_gastronomia_id' => 'nullable|integer',
            'prepara' => 'nullable|boolean',
        ]);

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        try {
            $cuenta = $this->posService->cuentaAbierta($cuentaId, (int) $config->empresa_id);
            $linea = $this->lineaDeCuenta($cuenta, $lineaId);
            $this->posService->actualizarDestinoLinea(
                $cuenta,
                $linea,
                $config,
                $request->has('area_comanda_gastronomia_id')
                    ? (int) $request->input('area_comanda_gastronomia_id')
                    : null,
                $request->has('prepara') ? $request->boolean('prepara') : null,
            );
            $cuenta = $this->posService->cuentaAbierta($cuentaId, (int) $config->empresa_id);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'cuenta' => $this->posService->serializarCuenta($cuenta)]);
    }

    public function apiListaPrecio(Request $request, int $id): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $request->validate(['listaprecio_id' => 'required|integer|min:1']);

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        try {
            $cuenta = $this->posService->cuentaAbierta($id, (int) $config->empresa_id);
            $this->posService->cambiarListaPrecio($cuenta, (int) $request->input('listaprecio_id'));
            $cuenta = $this->posService->cuentaAbierta($id, (int) $config->empresa_id);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'cuenta' => $this->posService->serializarCuenta($cuenta)]);
    }

    public function apiMarcarLinea(Request $request, int $cuentaId, int $lineaId): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        try {
            $cuenta = $this->posService->cuentaAbierta($cuentaId, (int) $config->empresa_id);
            $linea = $this->lineaDeCuenta($cuenta, $lineaId);
            $this->posService->setMarcado($linea, $request->boolean('marcado', ! $linea->marcado));
            $cuenta = $this->posService->cuentaAbierta($cuentaId, (int) $config->empresa_id);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'cuenta' => $this->posService->serializarCuenta($cuenta)]);
    }

    public function apiObservacionLinea(Request $request, int $cuentaId, int $lineaId): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $request->validate(['observacion' => 'nullable|string|max:255']);

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        try {
            $cuenta = $this->posService->cuentaAbierta($cuentaId, (int) $config->empresa_id);
            $linea = $this->lineaDeCuenta($cuenta, $lineaId);
            $this->posService->actualizarObservacionLinea($linea, (string) $request->input('observacion', ''));
            $cuenta = $this->posService->cuentaAbierta($cuentaId, (int) $config->empresa_id);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'cuenta' => $this->posService->serializarCuenta($cuenta)]);
    }

    public function apiVoidLinea(Request $request, int $cuentaId, int $lineaId): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        try {
            $cuenta = $this->posService->cuentaAbierta($cuentaId, (int) $config->empresa_id);
            $linea = $this->lineaDeCuenta($cuenta, $lineaId);
            $this->posService->voidLinea($cuenta, $linea);
            $cuenta = $this->posService->cuentaAbierta($cuentaId, (int) $config->empresa_id);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'cuenta' => $this->posService->serializarCuenta($cuenta)]);
    }

    public function apiSeparador(Request $request, int $id): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        try {
            $cuenta = $this->posService->cuentaAbierta($id, (int) $config->empresa_id);
            $this->posService->agregarSeparador($cuenta);
            $cuenta = $this->posService->cuentaAbierta($id, (int) $config->empresa_id);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'cuenta' => $this->posService->serializarCuenta($cuenta)]);
    }

    public function apiAnularCuenta(Request $request, int $id): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        try {
            $cuenta = $this->posService->cuentaAbierta($id, (int) $config->empresa_id);
            $this->posService->anularCuenta($cuenta);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function apiMarcha(Request $request, int $id): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $request->validate([
            'tipo' => 'nullable|in:normal,postres',
            'solo_marcados' => 'nullable|boolean',
        ]);

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        try {
            $cuenta = $this->posService->cuentaAbierta($id, (int) $config->empresa_id);
            $tipo = $request->input('tipo') === 'postres'
                ? GastroCrownComanda::TIPO_POSTRES
                : GastroCrownComanda::TIPO_NORMAL;
            $comandas = $this->posService->marchar(
                $cuenta,
                $config,
                $tipo,
                $request->boolean('solo_marcados')
            );
            $cuenta = $this->posService->cuentaAbierta($id, (int) $config->empresa_id);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'comandas' => collect($comandas)->map(fn (GastroCrownComanda $c) => [
                'id' => $c->id,
                'numero' => $c->numero,
                'tipo' => $c->tipo,
                'area_id' => $c->area_comanda_gastronomia_id,
                'ruta_pdf' => $c->ruta_pdf,
            ])->values(),
            'cuenta' => $this->posService->serializarCuenta($cuenta),
        ]);
    }

    public function apiObsMesa(Request $request, int $id): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $request->validate(['observacion' => 'nullable|string|max:255']);

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        try {
            $cuenta = $this->posService->cuentaAbierta($id, (int) $config->empresa_id);
            $cuenta->observacion = mb_substr(trim((string) $request->input('observacion', '')), 0, 255);
            $cuenta->save();
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'cuenta' => $this->posService->serializarCuenta($cuenta->fresh(['lineas', 'mesa', 'mozo']))]);
    }

    public function apiArticulo(Request $request): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $request->validate(['sku' => 'required|string|max:30']);

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        $art = $this->posService->buscarArticuloParaPos($config, (string) $request->input('sku'));
        if (! $art) {
            return response()->json(['ok' => false, 'mensaje' => 'Artículo no encontrado'], 404);
        }

        $articulo = Articulo::query()->find((int) $art['id']);
        $grupos = $articulo ? $this->gruposOpcionales($articulo) : [];
        $receta = $this->formulaArticuloService->resolverIdParaArticulo((int) $art['id']);
        $formulaId = (int) ($receta['formula_id'] ?? 0);

        $art['grupos_opcionales'] = $grupos;
        $art['requiere_opcionales'] = $grupos !== [];
        $art['formula_id'] = $formulaId > 0 ? $formulaId : null;
        $art['url_formula'] = $formulaId > 0
            ? route('editar_formula_articulo', [
                'id' => $formulaId,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ])
            : null;

        return response()->json(['ok' => true, 'articulo' => $art]);
    }

    public function apiDescuento(Request $request, int $id): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $request->validate([
            'porcentaje' => 'required|numeric|min:0|max:100',
            'detalle' => 'nullable|string|max:80',
        ]);

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        try {
            $cuenta = $this->posService->cuentaAbierta($id, (int) $config->empresa_id);
            $this->posService->aplicarDescuentoMesa(
                $cuenta,
                (float) $request->input('porcentaje'),
                $request->input('detalle')
            );
            $cuenta = $this->posService->cuentaAbierta($id, (int) $config->empresa_id);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'cuenta' => $this->posService->serializarCuenta($cuenta)]);
    }

    public function apiCliente(Request $request, int $id): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $request->validate(['codigo' => 'nullable|string|max:30']);

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        try {
            $cuenta = $this->posService->cuentaAbierta($id, (int) $config->empresa_id);
            $this->posService->asignarCliente($cuenta, (string) $request->input('codigo', ''));
            $cuenta = $this->posService->cuentaAbierta($id, (int) $config->empresa_id);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'cuenta' => $this->posService->serializarCuenta($cuenta)]);
    }

    public function apiTransferir(Request $request, int $id): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $request->validate(['mesa_id' => 'required|integer']);

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        try {
            $cuenta = $this->posService->cuentaAbierta($id, (int) $config->empresa_id);
            $this->posService->transferirMesa($cuenta, $config, (int) $request->input('mesa_id'));
            $cuenta = $this->posService->cuentaAbierta($id, (int) $config->empresa_id);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'cuenta' => $this->posService->serializarCuenta($cuenta)]);
    }

    public function apiCobrar(Request $request, int $id): JsonResponse
    {
        $this->assertCrown();
        can('usar-pos-gastronomia-crown');

        $request->validate([
            'tipo' => 'required|in:efectivo,interna,hotel,factura_sola',
            'habitacion' => 'nullable|string|max:20',
            'reserva_hotel' => 'nullable|string|max:30',
            'tarjeta_gst' => 'nullable|string|max:30',
            'sin_gst' => 'nullable|boolean',
            'observacion_cobro' => 'nullable|string|max:120',
            'actividad_arca_id' => 'nullable|integer|min:1',
            'sin_cobranza' => 'nullable|boolean',
        ]);

        $config = $this->requireCfg($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        try {
            $cuenta = $this->posService->cuentaAbierta($id, (int) $config->empresa_id);
            $resultado = $this->posService->cobrar(
                $cuenta,
                $config,
                (string) $request->input('tipo'),
                $request->only([
                    'habitacion',
                    'reserva_hotel',
                    'tarjeta_gst',
                    'sin_gst',
                    'observacion_cobro',
                    'actividad_arca_id',
                    'sin_cobranza',
                ])
            );
            $printHotel = null;
            if ($request->input('tipo') === GastroCrownCuenta::COBRO_HOTEL) {
                $printHotel = \App\Support\Ventas\GastronomiaCrown\GastronomiaCrownPrintSupport::imprimirTicketHotel(
                    $resultado['cuenta'],
                    $config
                );
            }
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        $emision = $resultado['emision'] ?? [];
        $factura = trim((string) ($emision['factura'] ?? ''));
        $mensaje = $factura !== ''
            ? ($emision['mensaje'] ?? 'Factura '.$factura.' emitida correctamente.')
            : 'Cobro '.$request->input('tipo').' registrado (sin emisión fiscal).';

        return response()->json(array_filter([
            'ok' => true,
            'mensaje' => $mensaje,
            'venta_id' => isset($emision['venta_id']) ? (int) $emision['venta_id'] : null,
            'factura' => $factura !== '' ? $factura : null,
            'warn' => $emision['warn'] ?? null,
            'sin_cobranza' => ! empty($emision['sin_cobranza']),
            'cobranza_id' => isset($emision['cobranza_id']) ? (int) $emision['cobranza_id'] : null,
            'totales' => $resultado['totales'],
            'cuenta' => $this->posService->serializarCuenta($resultado['cuenta']),
            'print_hotel' => $printHotel,
        ], fn ($v) => $v !== null));
    }

    private function assertCrown(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            abort(404);
        }
    }

    private function requireCfg(Request $request): ConfiguracionPuntoventaGastronomia|JsonResponse
    {
        $config = $this->posService->resolverConfig($request);
        if ($config === null) {
            return response()->json(['ok' => false, 'mensaje' => 'Sin configuración de PC'], 422);
        }

        return $config;
    }

    /**
     * @return list<array{orden:int|string, opciones:list<array<string, mixed>>}>
     */
    private function gruposOpcionales(Articulo $articulo): array
    {
        if (! FormulaArticuloGastronomia::opcionalesHabilitados()) {
            return [];
        }

        return $this->opcionalesService->gruposOpcionalesPorArticulo($articulo);
    }

    /**
     * Respuesta 422 con los grupos para que el POS abra el modal, o null si la selección es válida.
     *
     * @param  array<string, int|string>  $opcionales
     * @return array<string, mixed>|null
     */
    private function erroresOpcionalesAntesDeAgregar(Articulo $articulo, array $opcionales): ?array
    {
        $grupos = $this->gruposOpcionales($articulo);
        if ($grupos === []) {
            return null;
        }

        try {
            $this->opcionalesService->validarSeleccionOpcionales($articulo, $opcionales);
        } catch (\InvalidArgumentException $e) {
            return [
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'requiere_opcionales' => true,
                'grupos' => $grupos,
                'articulo' => [
                    'id' => (int) $articulo->id,
                    'sku' => $articulo->sku,
                    'descripcion' => $articulo->descripcion,
                ],
            ];
        }

        return null;
    }

    private function lineaDeCuenta(GastroCrownCuenta $cuenta, int $lineaId): GastroCrownCuentaLinea
    {
        $linea = GastroCrownCuentaLinea::query()
            ->where('id', $lineaId)
            ->where('gastro_crown_cuenta_id', $cuenta->id)
            ->first();
        if (! $linea) {
            throw new \InvalidArgumentException('Línea no encontrada.');
        }

        return $linea;
    }

    /**
     * @return array<string, string>
     */
    private function hotkeysParaConfig($config): array
    {
        $keys = [
            'Enter' => 'Validar / agregar',
            'F1' => 'Consultar artículo',
            'F5' => 'Factura efectivo',
            'F8' => 'Interna / GST',
            'F12' => 'Marcha',
            'F6' => 'Borrar ítem',
            'F11' => 'Obs. ítem',
            'F10' => 'Dto. mesa',
        ];
        if ($config->habilita_hotel) {
            $keys['F2'] = 'Hotel';
        }
        if (GastronomiaCrownPosModos::usaMesas($config->modoPosEfectivo()) && ! $config->solo_marcha) {
            $keys['F9'] = 'Factura sola';
        }
        if ($config->solo_marcha) {
            unset($keys['F5'], $keys['F8'], $keys['F9'], $keys['F10']);
        }

        return $keys;
    }

    /**
     * Herramientas Anita (`KEY_HERRAMIENTA`) como botones con icono.
     *
     * @return list<array{code:string,label:string,icon:string,action:string,enabled:bool}>
     */
    private function herramientasParaConfig($config): array
    {
        $conComanda = $config->politica_comanda !== GastronomiaCrownPosModos::POLITICA_SIN_COMANDA;

        return [
            ['code' => 'e', 'label' => 'Postres', 'icon' => 'fa-birthday-cake', 'action' => 'marcha_postres', 'enabled' => $conComanda],
            ['code' => 'x', 'label' => 'Buscar art.', 'icon' => 'fa-search', 'action' => 'buscar_articulo', 'enabled' => true],
            ['code' => 'c', 'label' => 'Cliente', 'icon' => 'fa-user', 'action' => 'cliente', 'enabled' => true],
            ['code' => 's', 'label' => 'Obs. mesa', 'icon' => 'fa-comment', 'action' => 'obs_mesa', 'enabled' => true],
            ['code' => 't', 'label' => 'Separador', 'icon' => 'fa-minus', 'action' => 'separador', 'enabled' => true],
            ['code' => 'b', 'label' => 'Transferir', 'icon' => 'fa-exchange-alt', 'action' => 'transferir', 'enabled' => true],
            ['code' => 'o', 'label' => 'Opcionales', 'icon' => 'fa-plus-square', 'action' => 'opcionales', 'enabled' => true],
            ['code' => 'r', 'label' => 'Receta', 'icon' => 'fa-book', 'action' => 'receta', 'enabled' => true],
            ['code' => 'w', 'label' => 'Lista precio', 'icon' => 'fa-tags', 'action' => 'lista_precio', 'enabled' => true],
            ['code' => 'l', 'label' => 'Datos mesa', 'icon' => 'fa-edit', 'action' => 'datos_mesa', 'enabled' => true],
            ['code' => 'd', 'label' => 'Cobrar', 'icon' => 'fa-credit-card', 'action' => 'cobrar', 'enabled' => ! $config->solo_marcha],
            ['code' => 'm', 'label' => 'Anular mesa', 'icon' => 'fa-trash', 'action' => 'anular_mesa', 'enabled' => true],
        ];
    }
}
