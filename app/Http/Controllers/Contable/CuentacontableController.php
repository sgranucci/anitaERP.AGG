<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionCuentacontable;
use App\Models\Contable\Cuentacontable;
use App\Models\Contable\Rubrocontable;
use App\Repositories\Caja\ConceptogastoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Contable\Cuentacontable_CentrocostoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Contable\Usuario_CuentacontableRepositoryInterface;
use App\Support\Contable\AsientoCuentaUsuarioSupport;
use App\Support\Contable\CuentacontableArbolSupport;
use App\Support\Contable\CuentacontableConsultaSupport;
use App\Support\Contable\CuentacontableGemeloSupport;
use App\Support\Contable\CuentacontableListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
use DB;
use Illuminate\Http\Request;

class CuentacontableController extends Controller
{
    private $conceptogastoRepository;

    private $cuentacontableRepository;

    private $empresaRepository;

    private $cuentacontable_centrocostoRepository;

    private $centrocostoRepository;

    private $usuario_cuentacontableRepository;

    public function __construct(
        ConceptogastoRepositoryInterface $conceptogastorepository,
        CuentacontableRepositoryInterface $cuentacontablerepository,
        EmpresaRepositoryInterface $empresarepository,
        CentrocostoRepositoryInterface $centrocostorepository,
        Cuentacontable_CentrocostoRepositoryInterface $cuentacontable_centrocostorepository,
        Usuario_CuentaContableRepositoryInterface $usuario_cuentacontablerepository
    ) {
        $this->conceptogastoRepository = $conceptogastorepository;
        $this->cuentacontableRepository = $cuentacontablerepository;
        $this->empresaRepository = $empresarepository;
        $this->centrocostoRepository = $centrocostorepository;
        $this->cuentacontable_centrocostoRepository = $cuentacontable_centrocostorepository;
        $this->usuario_cuentacontableRepository = $usuario_cuentacontablerepository;
    }

    public function index(Request $request)
    {
        can('listar-cuentas-contables');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaIdsPermitidos = $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
        $filtros = $this->resolverFiltrosListado($request, $empresaQuery);

        $query = $this->cuentacontableRepository->queryListado();
        $query->leftJoin('empresa', 'empresa.id', '=', 'cuentacontable.empresa_id')
            ->leftJoin('rubrocontable', 'rubrocontable.id', '=', 'cuentacontable.rubrocontable_id')
            ->leftJoin('conceptogasto', 'conceptogasto.id', '=', 'cuentacontable.conceptogasto_id')
            ->select('cuentacontable.*');

        $vistaArbol = ($filtros['vista'] ?? '') === CuentacontableListadoFiltros::VISTA_ARBOL
            && ($filtros['empresa_scope'] ?? '') === 'una';

        if ($vistaArbol) {
            if (! empty($filtros['empresa_id'])) {
                $query->where('cuentacontable.empresa_id', (int) $filtros['empresa_id']);
            } elseif ($empresaIdsPermitidos !== []) {
                $query->whereIn('cuentacontable.empresa_id', $empresaIdsPermitidos);
            }
            $cuentas = $query->orderBy('cuentacontable.codigo')->get();
            $arbol = CuentacontableArbolSupport::armar(
                $cuentas,
                (bool) ($filtros['mostrar_totalizadoras'] ?? false)
            );
            $busqueda = trim((string) ($filtros['valor'] ?? ''));
            if ($busqueda !== '') {
                $arbol = CuentacontableArbolSupport::podarPorBusqueda($arbol, $busqueda);
            }
            $arbol = CuentacontableArbolSupport::podarPorTipoNivel(
                $arbol,
                (string) ($filtros['tipocuenta'] ?? ''),
                (int) ($filtros['nivel'] ?? 0)
            );
            $cuentacontables = collect();
            $arbolCount = CuentacontableArbolSupport::contarNodos($arbol);
        } else {
            CuentacontableListadoFiltros::aplicar($query, $filtros, $empresaIdsPermitidos);
            if (empty($filtros['mostrar_totalizadoras']) && (string) ($filtros['tipocuenta'] ?? '') === '') {
                $query->where('cuentacontable.tipocuenta', '!=', CuentacontableArbolSupport::TIPO_TOTALIZADORA);
            }
            $cuentacontables = $query->orderBy('cuentacontable.codigo')->paginate(80)->withQueryString();
            $arbol = [];
            $arbolCount = 0;
        }

        return view('contable.cuentacontable.index', [
            'cuentacontables' => $cuentacontables,
            'arbol' => $arbol,
            'arbolCount' => $arbolCount,
            'vistaArbol' => $vistaArbol,
            'filtros' => $filtros,
            'filtrosQuery' => CuentacontableListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => CuentacontableListadoFiltros::CAMPOS,
            'empresa_query' => $empresaQuery,
            'tiposCuenta' => CuentacontableArbolSupport::etiquetasTipo(),
            'rubrocontable_query' => Rubrocontable::all(),
            'puedeEditarArbol' => can('editar-cuentas-contables', false) || can('actualizar-cuentas-contables', false),
        ]);
    }

    public function crear(Request $request)
    {
        can('crear-cuentas-contables');

        $filtrosQuery = QueryRetornoListado::desdeRequestSiIndex($request, CuentacontableListadoFiltros::class);
        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaId = (int) ($request->query('empresa_id') ?: ($empresaQuery->first()->id ?? 0));
        $prefill = $this->prefillDesdePadre($request, $empresaId);

        return view('contable.cuentacontable.crear', $this->datosFormulario(null, $empresaQuery) + [
            'filtrosQuery' => $filtrosQuery,
            'empresaPrefillId' => (int) ($prefill['empresa_id'] ?? $empresaId),
            'prefill' => $prefill,
        ]);
    }

    public function guardar(ValidacionCuentacontable $request)
    {
        DB::beginTransaction();
        try {
            $payload = $this->payloadCuenta($request);
            $cuentacontable = $this->cuentacontableRepository->create($payload);
            if ($cuentacontable) {
                $this->cuentacontable_centrocostoRepository->create($request->all(), $cuentacontable->id);
                $this->asegurarGemeloTotalizadora($payload);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        $retorno = QueryRetornoListado::desdeRequestSiIndex($request, CuentacontableListadoFiltros::class);
        if ($retorno === []) {
            $retorno = CuentacontableListadoFiltros::paraQueryString([
                'empresa_id' => (int) $request->input('empresa_id'),
                'empresa_scope' => 'una',
                'vista' => CuentacontableListadoFiltros::VISTA_ARBOL,
            ]);
        }

        return redirect()->route('cuentacontable', $retorno)
            ->with('mensaje', 'Cuenta contable creada con éxito');
    }

    public function editar(Request $request, $id)
    {
        $soloConsulta = $request->query('origen') === 'modal_consulta';
        if ($soloConsulta) {
            can('listar-cuentas-contables');
        } else {
            can('editar-cuentas-contables');
        }

        $data = $this->cuentacontableRepository->findOrFail($id);
        $filtrosQuery = QueryRetornoListado::desdeRequestSiIndex($request, CuentacontableListadoFiltros::class);

        return view('contable.cuentacontable.editar', $this->datosFormulario($data) + [
            'data' => $data,
            'soloConsulta' => $soloConsulta,
            'filtrosQuery' => $filtrosQuery,
        ]);
    }

    public function actualizar(ValidacionCuentacontable $request, $id)
    {
        if ($request->input('origen') === 'modal_consulta') {
            abort(403);
        }

        can('actualizar-cuentas-contables');

        DB::beginTransaction();
        try {
            $this->cuentacontableRepository->update($this->payloadCuenta($request), $id);
            $this->cuentacontable_centrocostoRepository->update($request->all(), $id);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        $retorno = QueryRetornoListado::desdeRequestSiIndex($request, CuentacontableListadoFiltros::class);

        return redirect()->route('cuentacontable', $retorno)
            ->with('mensaje', 'Cuenta actualizada con éxito');
    }

    public function actualizarInspector(Request $request, $id)
    {
        can('actualizar-cuentas-contables');

        $cuenta = $this->cuentacontableRepository->findOrFail($id);
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'nivel' => 'required|integer|min:1|max:5',
            'tipocuenta' => 'required|in:1,2,3',
            'rubrocontable_id' => 'required|integer',
            'parent_id' => 'nullable|integer',
        ]);

        $parentId = (int) ($validated['parent_id'] ?? 0);
        if ($parentId === (int) $cuenta->id) {
            return response()->json(['ok' => false, 'error' => 'Una cuenta no puede colgar de sí misma.'], 422);
        }
        if ($parentId > 0) {
            $padre = $this->cuentacontableRepository->find($parentId);
            if (! $padre || (int) $padre->empresa_id !== (int) $cuenta->empresa_id) {
                return response()->json(['ok' => false, 'error' => 'El padre debe ser de la misma empresa.'], 422);
            }
            $ciclo = $this->parentIdCreaCiclo($cuenta, $parentId);
            if ($ciclo !== null) {
                return response()->json(['ok' => false, 'error' => $ciclo], 422);
            }
        }

        $payload = array_merge($cuenta->only([
            'empresa_id', 'rubrocontable_id', 'nombre', 'codigo', 'tipocuenta', 'nivel',
            'monetaria', 'manejaccosto', 'ajustamonedaextranjera', 'conceptogasto_id',
            'cuentacontable_difcambio_id',
        ]), [
            'nombre' => $validated['nombre'],
            'nivel' => (int) $validated['nivel'],
            'tipocuenta' => $validated['tipocuenta'],
            'rubrocontable_id' => (int) $validated['rubrocontable_id'],
            'parent_id' => $parentId > 0 ? $parentId : null,
        ]);

        $this->cuentacontableRepository->updateJerarquia($payload, (int) $id);

        $cuenta->refresh();

        return response()->json([
            'ok' => true,
            'cuenta' => [
                'id' => (int) $cuenta->id,
                'nombre' => (string) $cuenta->nombre,
                'nivel' => (int) $cuenta->nivel,
                'tipocuenta' => (string) $cuenta->tipocuenta,
                'tipo_label' => CuentacontableArbolSupport::etiquetaTipo($cuenta->tipocuenta),
                'rubrocontable_id' => (int) $cuenta->rubrocontable_id,
                'parent_id' => (int) ($cuenta->parent_id ?? 0) ?: null,
            ],
        ]);
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-cuentas-contables');

        if ($request->ajax()) {
            if ($this->cuentacontableRepository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    public function consultaCuentaContable(Request $request)
    {
        $columnsOut = ['cuentacontable_id', 'codigocuentacontable', 'nombrecuentacontable', 'nombreempresa'];
        $count = count($columnsOut);

        $empresaId = (int) $request->input('empresa_id');
        $consulta = trim((string) $request->input('consulta', ''));

        $query = Cuentacontable::select(
            'cuentacontable.id as cuentacontable_id',
            'cuentacontable.codigo as codigocuentacontable',
            'cuentacontable.nombre as nombrecuentacontable',
            'cuentacontable.empresa_id as empresa_id',
            'empresa.nombre as nombreempresa',
            'cuentacontable.tipocuenta'
        )
            ->leftJoin('empresa', 'cuentacontable.empresa_id', '=', 'empresa.id')
            ->where('cuentacontable.tipocuenta', '1');

        if ($empresaId > 0) {
            $query->where('cuentacontable.empresa_id', $empresaId);
        }

        CuentacontableConsultaSupport::aplicarFiltroTexto($query, $consulta);
        CuentacontableConsultaSupport::ordenarPorRelevancia($query, $consulta);

        $rows = $query->limit(250)->get();

        $output = [];
        $output['data'] = '';
        $flSinDatos = true;
        $puedeConsultar = can('listar-cuentas-contables', false) || can('editar-cuentas-contables', false);
        $usuarioTieneRestriccion = AsientoCuentaUsuarioSupport::usuarioTieneRestriccionCuentas((int) auth()->id());

        foreach ($rows as $row) {
            $usuario_cuentacontable = $this->usuario_cuentacontableRepository
                ->leePorUsuarioCuenta(auth()->id(), $row['cuentacontable_id']);

            $cuentaPermitida = ! $usuarioTieneRestriccion || count($usuario_cuentacontable) > 0;
            if (! $cuentaPermitida) {
                continue;
            }

            $flSinDatos = false;
            $output['data'] .= '<tr>';
            for ($i = 0; $i < $count; $i++) {
                $output['data'] .= '<td class="'.$columnsOut[$i].'">'.$row[$columnsOut[$i]].'</td>';
            }
            $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultacuentacontable">Elegir</a>';
            if ($puedeConsultar) {
                $urlConsulta = route('editar_cuentacontable', [
                    'id' => $row['cuentacontable_id'],
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]);
                $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e($urlConsulta).'" target="_blank" rel="noopener">Consultar</a>';
            }
            $output['data'] .= '</td>';
            $output['data'] .= '</tr>';
        }

        if ($flSinDatos) {
            $output['data'] .= '<tr>';
            $output['data'] .= '<td colspan="4">Sin resultados</td>';
            $output['data'] .= '</tr>';
        }

        return response()->json($output);
    }

    public function leerCuentaContablePorCodigo($empresa_id, $codigo)
    {
        return $this->cuentacontableRepository->findPorCodigo($empresa_id, $codigo);
    }

    public function leerCuentaContableCentroCosto(Request $request, $cuentacontable_id)
    {
        $cuentacontable = $this->cuentacontableRepository->find($cuentacontable_id);

        if ($cuentacontable) {
            if ($cuentacontable->manejaccosto === '1' || $cuentacontable->manejaccosto === 'S') {
                $centrocosto = $this->cuentacontable_centrocostoRepository->leeCuentacontable_Centrocosto($cuentacontable->id);

                if (count($centrocosto) > 0) {
                    return $this->incluirCentrocostoActualEnLista($centrocosto, (int) $request->query('incluir', 0));
                }

                return $this->centrocostoRepository->all();
            }

            return 'No maneja centro de costo';
        }

        return 'Cuenta inexistente';
    }

    /**
     * La SP / asiento puede traer un CC que no está en la matriz de la cuenta.
     * Si no se agrega, el combo no lo muestra y el grabado del IE falla.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $centrocostos
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    private function incluirCentrocostoActualEnLista($centrocostos, int $incluirId)
    {
        if ($incluirId <= 0) {
            return $centrocostos;
        }

        $yaEsta = $centrocostos->contains(static function ($cc) use ($incluirId) {
            return (int) ($cc->id ?? 0) === $incluirId;
        });
        if ($yaEsta) {
            return $centrocostos;
        }

        $extra = $this->centrocostoRepository->find($incluirId);
        if (! $extra) {
            return $centrocostos;
        }

        $centrocostos->push((object) [
            'id' => (int) $extra->id,
            'codigo' => $extra->codigo,
            'nombre' => $extra->nombre,
        ]);

        return $centrocostos;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, $empresaQuery): array
    {
        $empresaDefault = optional($empresaQuery->first())->id;
        $filtros = CuentacontableListadoFiltros::resolverDesdeRequest(
            $request,
            null,
            $empresaDefault ? (int) $empresaDefault : null
        );

        $permitidos = $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (! empty($filtros['empresa_id']) && $permitidos !== [] && ! in_array((int) $filtros['empresa_id'], $permitidos, true)) {
            $filtros['empresa_id'] = $permitidos[0] ?? null;
            $filtros['empresa_scope'] = $filtros['empresa_id'] ? 'una' : 'todas';
        }

        return $filtros;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>|null  $empresaQuery
     * @return array<string, mixed>
     */
    private function datosFormulario(?Cuentacontable $data, $empresaQuery = null): array
    {
        return [
            'rubrocontable_query' => Rubrocontable::all(),
            'empresa_query' => $empresaQuery ?? $this->empresaRepository->allFiltrado(),
            'cuentacontable_query' => $this->cuentacontableRepository->all(),
            'conceptogasto_query' => $this->conceptogastoRepository->all(),
            'centrocosto_query' => $this->centrocostoRepository->all(),
            'ajustamonedaextranjera_enum' => Cuentacontable::$enumAjustaMonedaExtranjera,
            'tiposCuenta' => CuentacontableArbolSupport::etiquetasTipo(),
            'data' => $data,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadCuenta(Request $request): array
    {
        $payload = $request->only([
            'empresa_id',
            'rubrocontable_id',
            'parent_id',
            'nombre',
            'codigo',
            'tipocuenta',
            'nivel',
            'monetaria',
            'manejaccosto',
            'ajustamonedaextranjera',
            'conceptogasto_id',
            'cuentacontable_difcambio_id',
        ]);
        foreach (['conceptogasto_id', 'cuentacontable_difcambio_id', 'parent_id'] as $campo) {
            if (($payload[$campo] ?? '') === '' || (int) ($payload[$campo] ?? 0) === 0) {
                $payload[$campo] = null;
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function prefillDesdePadre(Request $request, int $empresaId): array
    {
        $codigoPadre = preg_replace('/\D/', '', (string) $request->query('padre', '')) ?? '';
        if ($codigoPadre === '' || $empresaId <= 0) {
            return [];
        }

        $padre = $this->cuentacontableRepository->findPorCodigo($empresaId, $codigoPadre);
        if (! $padre) {
            $padre = $this->cuentacontableRepository->findPorCodigo($empresaId, (string) ((int) $codigoPadre));
        }
        if (! $padre) {
            return ['empresa_id' => $empresaId];
        }

        $nivel = min(5, max(1, (int) $padre->nivel + 1));

        return [
            'empresa_id' => (int) $padre->empresa_id,
            'rubrocontable_id' => (int) $padre->rubrocontable_id,
            'nivel' => $nivel,
            'tipocuenta' => $nivel >= 5
                ? CuentacontableArbolSupport::TIPO_IMPUTABLE
                : CuentacontableArbolSupport::TIPO_TITULO,
            'codigo_sugerido' => '',
            'padre_nombre' => (string) $padre->nombre,
            'padre_codigo' => CuentacontableArbolSupport::formatearCodigo((string) $padre->codigo),
        ];
    }

    /**
     * @param  array<string, mixed>  $grupo
     */
    private function asegurarGemeloTotalizadora(array $grupo): void
    {
        $payload = CuentacontableGemeloSupport::payloadTotalizadora($grupo);
        if ($payload === null || (int) $payload['empresa_id'] <= 0) {
            return;
        }

        $existe = $this->cuentacontableRepository->findPorCodigo($payload['empresa_id'], $payload['codigo']);
        if ($existe) {
            return;
        }

        $this->cuentacontableRepository->create($payload);
    }

    private function parentIdCreaCiclo(Cuentacontable $cuenta, int $parentId): ?string
    {
        $cur = $parentId;
        $guard = 0;
        while ($cur > 0 && $guard++ < 24) {
            if ($cur === (int) $cuenta->id) {
                return 'Ese padre crearía un ciclo.';
            }
            $nodo = $this->cuentacontableRepository->find($cur);
            if (! $nodo) {
                break;
            }
            $cur = (int) ($nodo->parent_id ?? 0);
        }

        $hermanas = Cuentacontable::query()
            ->where('empresa_id', (int) $cuenta->empresa_id)
            ->with('rubrocontables')
            ->get();
        foreach ($hermanas as $hermana) {
            if ((int) $hermana->id === (int) $cuenta->id) {
                $hermana->parent_id = $parentId;
            }
        }
        $plano = CuentacontableArbolSupport::aplanar(
            CuentacontableArbolSupport::armar($hermanas, true)
        );
        foreach ($plano as $nodo) {
            if ((int) ($nodo['id'] ?? 0) !== (int) $cuenta->id) {
                continue;
            }
            if (($nodo['padre_origen'] ?? '') !== 'manual') {
                return 'Ese padre crearía un ciclo.';
            }
        }

        return null;
    }
}
