<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\SuscripcionTarjetaListadoExport;
use App\Http\Controllers\Controller;
use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Compras\Suscripcion_Tarjeta;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Support\Compras\SuscripcionSupport;
use App\Support\Compras\SuscripcionTarjetaListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Maestro de tarjetas corporativas: los últimos 4 dígitos con los que se cruza el resumen.
 */
class SuscripcionTarjetaController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
        private MonedaRepositoryInterface $monedaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('configurar-suscripcion');

        $filtros = SuscripcionTarjetaListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = SuscripcionTarjetaListadoFiltros::paraQueryString($filtros);
        $tarjetas = $this->leeTarjetas($filtros, true);
        $usos = $this->conteoUsosSuscripcion($tarjetas->pluck('id')->all());

        return view('compras.suscripcion.tarjeta.index', [
            'tarjetas' => $tarjetas,
            'usos' => $usos,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => SuscripcionTarjetaListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    /**
     * Mismo listado en PDF, XLS o CSV (respeta filtros del index).
     */
    public function exportar(Request $request, string $formato)
    {
        can('configurar-suscripcion');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = SuscripcionTarjetaListadoFiltros::resolverDesdeRequest($request);
        $tarjetas = $this->leeTarjetas($filtros, false);
        $usos = $this->conteoUsosSuscripcion($tarjetas->pluck('id')->all());

        return match (strtoupper($formato)) {
            'PDF' => $this->descargarPdfListado($tarjetas, $filtros, $usos),
            'CSV' => (new SuscripcionTarjetaListadoExport)
                ->parametros($tarjetas, $filtros, $usos)
                ->download('tarjetas_suscripcion.csv', Excel::CSV),
            default => (new SuscripcionTarjetaListadoExport)
                ->parametros($tarjetas, $filtros, $usos)
                ->download('tarjetas_suscripcion.xlsx'),
        };
    }

    public function crear(Request $request)
    {
        can('configurar-suscripcion');

        $filtrosQuery = QueryRetornoListado::desdeRequestSiIndex($request, SuscripcionTarjetaListadoFiltros::class);

        return view('compras.suscripcion.tarjeta.crear', $this->datosFormulario() + [
            'tarjeta' => null,
            'filtrosQuery' => $filtrosQuery,
        ]);
    }

    public function guardar(Request $request)
    {
        can('configurar-suscripcion');

        $data = $this->validar($request);
        Suscripcion_Tarjeta::query()->create($data);

        return redirect()
            ->route('tarjetas_suscripcion', QueryRetornoListado::desdeRequest($request, SuscripcionTarjetaListadoFiltros::class))
            ->with('mensaje', 'Tarjeta creada.');
    }

    public function editar(Request $request, int $id)
    {
        can('configurar-suscripcion');

        $tarjeta = Suscripcion_Tarjeta::query()
            ->with(['centrocostos', 'responsables', 'cuentacajas'])
            ->findOrFail($id);
        $filtrosQuery = QueryRetornoListado::desdeRequestSiIndex($request, SuscripcionTarjetaListadoFiltros::class);

        return view('compras.suscripcion.tarjeta.crear', $this->datosFormulario() + [
            'tarjeta' => $tarjeta,
            'filtrosQuery' => $filtrosQuery,
        ]);
    }

    public function actualizar(Request $request, int $id)
    {
        can('configurar-suscripcion');

        $tarjeta = Suscripcion_Tarjeta::query()->findOrFail($id);
        $tarjeta->update($this->validar($request, $id));

        return redirect()
            ->route('tarjetas_suscripcion', QueryRetornoListado::desdeRequest($request, SuscripcionTarjetaListadoFiltros::class))
            ->with('mensaje', 'Tarjeta actualizada.');
    }

    public function eliminar(Request $request, int $id)
    {
        can('configurar-suscripcion');

        $enUso = DB::table('ordencompra')->where('suscripcion_tarjeta_id', $id)->exists()
            || DB::table('suscripcion_cargo')->where('suscripcion_tarjeta_id', $id)->exists();

        if ($enUso) {
            return back()->with('error', 'La tarjeta tiene suscripciones o cargos asociados. Desactivala en lugar de borrarla.');
        }

        Suscripcion_Tarjeta::query()->where('id', $id)->delete();

        return redirect()
            ->route('tarjetas_suscripcion', QueryRetornoListado::desdeRequest($request, SuscripcionTarjetaListadoFiltros::class))
            ->with('mensaje', 'Tarjeta eliminada.');
    }

    /**
     * @return LengthAwarePaginator<int, Suscripcion_Tarjeta>|Collection<int, Suscripcion_Tarjeta>
     */
    private function leeTarjetas(array $filtros, bool $paginar)
    {
        $query = $this->queryBaseTarjetas();
        SuscripcionTarjetaListadoFiltros::aplicar($query, $filtros);
        $query->orderBy('suscripcion_tarjeta.empresa_id')
            ->orderBy('suscripcion_tarjeta.etiqueta');

        return $paginar ? $query->paginate(10) : $query->get();
    }

    /**
     * @return Builder<\App\Models\Compras\Suscripcion_Tarjeta>
     */
    private function queryBaseTarjetas(): Builder
    {
        $query = Suscripcion_Tarjeta::query()
            ->select('suscripcion_tarjeta.*')
            ->leftJoin('empresa', 'empresa.id', '=', 'suscripcion_tarjeta.empresa_id')
            ->leftJoin('centrocosto', 'centrocosto.id', '=', 'suscripcion_tarjeta.centrocosto_id')
            ->leftJoin('usuario', 'usuario.id', '=', 'suscripcion_tarjeta.responsable_usuario_id')
            ->with(['empresas', 'centrocostos', 'responsables', 'monedas', 'cuentacajas', 'tipotransaccioncajas']);

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'suscripcion_tarjeta.empresa_id');

        return $query;
    }

    /**
     * @param  list<int>  $tarjetaIds
     * @return \Illuminate\Support\Collection<int|string, int>
     */
    private function conteoUsosSuscripcion(array $tarjetaIds): Collection
    {
        if ($tarjetaIds === []) {
            return collect();
        }

        return DB::table('ordencompra')
            ->where('es_suscripcion', true)
            ->whereIn('suscripcion_tarjeta_id', $tarjetaIds)
            ->selectRaw('suscripcion_tarjeta_id, COUNT(*) AS total')
            ->groupBy('suscripcion_tarjeta_id')
            ->pluck('total', 'suscripcion_tarjeta_id');
    }

    /**
     * @param  Collection<int, Suscripcion_Tarjeta>  $tarjetas
     * @param  array<string, mixed>  $filtros
     * @param  Collection<int|string, int>  $usos
     */
    private function descargarPdfListado(Collection $tarjetas, array $filtros, Collection $usos): BinaryFileResponse
    {
        $html = view('compras.suscripcion.tarjeta.listado', [
            'tarjetas' => $tarjetas,
            'filtros' => $filtros,
            'usos' => $usos,
        ])->render();

        $directorio = storage_path('pdf/listados');
        if (! is_dir($directorio)) {
            mkdir($directorio, 0775, true);
        }
        $archivo = $directorio.'/listado_tarjetas_suscripcion.pdf';

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html)->save($archivo);

        return response()->download($archivo);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?int $ignorarId = null): array
    {
        $unica = 'unique:suscripcion_tarjeta,ult4,'.($ignorarId ?? 'NULL').',id,empresa_id,'.(int) $request->input('empresa_id');

        $areasPermitidas = SuscripcionSupport::areas();
        if ($ignorarId) {
            $areaGuardada = trim((string) Suscripcion_Tarjeta::query()->whereKey($ignorarId)->value('area'));
            if ($areaGuardada !== '' && ! in_array($areaGuardada, $areasPermitidas, true)) {
                $areasPermitidas[] = $areaGuardada;
            }
        }

        $data = $request->validate([
            'empresa_id' => 'required|integer|exists:empresa,id',
            'ult4' => ['required', 'string', 'regex:/^\d{4}$/', $unica],
            'etiqueta' => 'required|string|max:80',
            'emisor' => 'nullable|string|max:60',
            'area' => ['nullable', 'string', 'max:80', Rule::in($areasPermitidas)],
            'centrocosto_id' => 'nullable|integer|exists:centrocosto,id',
            'responsable_usuario_id' => 'nullable|integer|exists:usuario,id',
            'moneda_id' => 'nullable|integer|exists:moneda,id',
            'cuentacaja_id' => 'nullable|integer|exists:cuentacaja,id',
            'tipotransaccion_caja_id' => 'nullable|integer|exists:tipotransaccion_caja,id',
            'limite_mensual' => 'nullable|numeric|min:0',
            'observacion' => 'nullable|string|max:255',
        ], [
            'ult4.unique' => 'Ya hay una tarjeta con esos últimos 4 dígitos en esa empresa.',
            'area.in' => 'El área debe coincidir con las de la solicitud de suscripción.',
        ]);

        $data['area'] = trim((string) ($data['area'] ?? '')) ?: null;
        $data['activo'] = $request->boolean('activo');

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(): array
    {
        return [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'moneda_query' => $this->monedaRepository->all(),
            'areas' => SuscripcionSupport::areas(),
            // Egresos y órdenes de pago: son los que descargan plata de la cuenta de la tarjeta.
            'tipotransaccion_query' => Tipotransaccion_Caja::query()
                ->whereIn('operacion', ['E', 'P'])
                ->where('estado', 'A')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'abreviatura']),
        ];
    }
}
