<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finanzas;

use App\Exports\Finanzas\FinanzaMovimientoPrecargaListadoExport;
use App\Http\Controllers\Controller;
use App\Models\Caja\Cuentacaja;
use App\Models\Finanzas\FinanzaMovimientoPrecarga;
use App\Repositories\Configuracion\EmpresaRepository;
use App\Services\Finanzas\FinanzaMovimientoPrecargaService;
use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Finanzas\FinanzaMovimientoPrecargaListadoFiltros;
use App\Support\Finanzas\FinanzaMovimientoPrecargaRubro;
use App\Support\Finanzas\FinanzaPosicionHojaSupport;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Tesoreria\PosicionBancaria\PosicionBancariaPrecargaVolcadoSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Maatwebsite\Excel\Excel;

class MovimientoPrecargaController extends Controller
{
    public function __construct(
        private readonly EmpresaRepository $empresaRepository,
        private readonly FinanzaMovimientoPrecargaService $service,
        private readonly PosicionBancariaPrecargaVolcadoSupport $volcado,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-finanza-movimiento-precarga');

        $filtros = FinanzaMovimientoPrecargaListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = FinanzaMovimientoPrecargaListadoFiltros::paraQueryString($filtros);
        $datas = FinanzaMovimientoPrecargaListadoFiltros::query($filtros)
            ->paginate(15)
            ->appends($filtrosQuery);

        return view('finanzas.movimiento_precarga.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => FinanzaMovimientoPrecargaListadoFiltros::campos(),
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'hayFiltros' => FinanzaMovimientoPrecargaListadoFiltros::tieneCriteriosAplicados($filtros),
        ]);
    }

    public function listar(Request $request, ?string $formato = null)
    {
        can('listar-finanza-movimiento-precarga');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = FinanzaMovimientoPrecargaListadoFiltros::resolverDesdeRequest($request);
        $formato = strtoupper((string) $formato);

        if ($formato === 'PDF') {
            $datas = FinanzaMovimientoPrecargaListadoFiltros::query($filtros)->get();
            $html = view('finanzas.movimiento_precarga.listado', [
                'datas' => $datas,
                'subtitulo' => $this->subtituloExport($filtros),
            ])->render();
            $ruta = storage_path('pdf/listados/listado_finanza_movimiento_precarga.pdf');
            if (! is_dir(dirname($ruta))) {
                mkdir(dirname($ruta), 0775, true);
            }
            DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                'titulo_corto' => 'Precargas cash flow',
            ]);

            return response()->download($ruta, 'precargas-cash-flow.pdf');
        }

        if ($formato === 'EXCEL' || $formato === 'CSV') {
            $export = (new FinanzaMovimientoPrecargaListadoExport())
                ->parametros($filtros, $this->subtituloExport($filtros));
            if ($formato === 'CSV') {
                return $export->download('precargas-cash-flow.csv', Excel::CSV);
            }

            return $export->download('precargas-cash-flow.xlsx');
        }

        return redirect()->route(
            'finanza_movimiento_precarga',
            FinanzaMovimientoPrecargaListadoFiltros::paraQueryString($filtros)
        );
    }

    public function crear()
    {
        can('crear-finanza-movimiento-precarga');

        return view('finanzas.movimiento_precarga.form', $this->datosForm(null));
    }

    public function guardar(Request $request)
    {
        can('crear-finanza-movimiento-precarga');

        try {
            $this->service->grabar($request->all());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('errores', $e->getMessage());
        }

        return redirect()
            ->route('finanza_movimiento_precarga')
            ->with('mensaje', 'Precarga guardada. Entra a la posición bancaria del día.');
    }

    public function editar(int $id)
    {
        can('editar-finanza-movimiento-precarga');
        $precarga = $this->buscar($id);

        return view('finanzas.movimiento_precarga.form', $this->datosForm($precarga));
    }

    public function actualizar(Request $request, int $id)
    {
        can('actualizar-finanza-movimiento-precarga');
        $precarga = $this->buscar($id);

        try {
            $this->service->grabar($request->all(), $precarga);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('errores', $e->getMessage());
        }

        return redirect()
            ->route('finanza_movimiento_precarga')
            ->with('mensaje', 'Precarga actualizada.');
    }

    public function eliminar(int $id)
    {
        can('borrar-finanza-movimiento-precarga');
        $precarga = $this->buscar($id);
        if ($precarga->estaCerrada()) {
            return back()->with('errores', 'No se puede borrar: el ingreso/egreso sigue vigente.');
        }
        $precarga->delete();

        return redirect()
            ->route('finanza_movimiento_precarga')
            ->with('mensaje', 'Precarga eliminada.');
    }

    public function contabilizar(Request $request, int $id)
    {
        can('convertir-finanza-movimiento-precarga');
        $precarga = $this->precargaParaContabilizar($id);
        if ($precarga->estaCerrada()) {
            return redirect()
                ->route('editar_finanza_movimiento_precarga', $precarga->id)
                ->with('errores', 'Esta precarga ya está contabilizada.');
        }

        return view('finanzas.movimiento_precarga.contabilizar', [
            'precarga' => $precarga,
            'lineas' => $this->service->lineasAsientoParaVista(
                $precarga,
                $request->old('cuentacontable_ids'),
                $request->old('debeasientos'),
                $request->old('haberasientos'),
            ),
            'monto' => round(abs((float) $precarga->monto), 2),
        ]);
    }

    public function convertir(Request $request, int $id)
    {
        can('convertir-finanza-movimiento-precarga');
        $precarga = $this->precargaParaContabilizar($id);

        try {
            $lineas = $this->service->lineasAsientoDesdeInput($request->all(), (float) $precarga->monto);
            $precarga = $this->service->convertir($precarga, $lineas);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('errores', $e->getMessage());
        }

        $numero = (string) ($precarga->cajaMovimiento->numerotransaccion ?? $precarga->caja_movimiento_id);

        return redirect()
            ->route('finanza_movimiento_precarga')
            ->with('mensaje', 'Se generó el ingreso/egreso '.$numero.'. La precarga quedó cerrada.');
    }

    public function impacto(Request $request)
    {
        can('listar-finanza-movimiento-precarga');
        $fecha = $this->fechaRequest($request->query('fecha'));

        return response()->json($this->volcado->tablero($fecha));
    }

    public function cuenta(Request $request, int $id)
    {
        if (! can('crear-finanza-movimiento-precarga', false) && ! can('editar-finanza-movimiento-precarga', false)) {
            can('listar-finanza-movimiento-precarga');
        }

        $cuenta = Cuentacaja::query()->with(['monedas', 'bancos'])->find($id);
        if ($cuenta === null) {
            return response()->json(['id' => 0, 'error' => 'No se encontró la cuenta de caja.'], 404);
        }
        $empresaId = (int) $request->query('empresa_id');
        if ($empresaId > 0 && ! $cuenta->perteneceAEmpresa($empresaId) && $request->query('rol') !== 'hasta') {
            return response()->json(['id' => 0, 'error' => 'La cuenta no corresponde a la empresa.'], 422);
        }

        $monedaId = (int) ($cuenta->moneda_id ?? 0);
        $fecha = $this->fechaRequest($request->query('fecha'))->toDateString();
        $cotizacion = $monedaId <= 1 ? 1.0 : CotizacionVigenteSupport::ventaValor($fecha, $monedaId);
        $moneda = $cuenta->monedas;

        return response()->json([
            'id' => (int) $cuenta->id,
            'codigo' => (string) $cuenta->codigo,
            'nombre' => (string) $cuenta->nombre,
            'moneda_id' => $monedaId,
            'moneda' => $moneda ? (string) ($moneda->abreviatura ?: $moneda->nombre) : '',
            'cotizacion' => $cotizacion,
            'hoja' => FinanzaPosicionHojaSupport::hojaDesdeCuenta($cuenta),
            'empresa_id' => $cuenta->empresa_id ? (int) $cuenta->empresa_id : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosForm(?FinanzaMovimientoPrecarga $precarga): array
    {
        $precarga?->load([
            'cuentacaja',
            'cuentacajaDesde',
            'cuentacajaHasta',
            'moneda',
            'cuentacontableContrapartida',
            'cajaMovimiento',
            'empresa',
        ]);
        $fecha = $precarga?->fecha?->format('Y-m-d') ?? date('Y-m-d');
        $contra = $precarga?->cuentacontableContrapartida;

        return [
            'precarga' => $precarga,
            'cerrada' => $precarga?->estaCerrada() ?? false,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'rubros' => FinanzaMovimientoPrecargaRubro::ETIQUETAS,
            'tipos' => FinanzaMovimientoPrecargaRubro::TIPOS,
            'fecha' => $fecha,
            'tablero' => $this->volcado->tablero(Carbon::parse($fecha)),
            'contraCodigo' => (string) ($contra->codigo ?? ''),
            'contraNombre' => (string) ($contra->nombre ?? ''),
        ];
    }

    private function precargaParaContabilizar(int $id): FinanzaMovimientoPrecarga
    {
        $precarga = $this->buscar($id);
        $precarga->load([
            'empresa',
            'moneda',
            'cuentacaja.cuentacontables',
            'cuentacajaDesde.cuentacontables',
            'cuentacajaHasta.cuentacontables',
        ]);

        return $precarga;
    }

    private function buscar(int $id): FinanzaMovimientoPrecarga
    {
        $precarga = FinanzaMovimientoPrecargaListadoFiltros::query([
            'qbe' => [],
            'sort' => [],
            'empresa_id' => 0,
            'filtro_estado' => 'todos',
            'fecha_desde' => '',
            'fecha_hasta' => '',
        ])->where('finanza_movimiento_precarga.id', $id)->first();

        if ($precarga === null) {
            abort(404);
        }

        return $precarga;
    }

    private function fechaRequest(mixed $valor): Carbon
    {
        $texto = trim((string) $valor);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto) === 1) {
            return Carbon::parse($texto);
        }

        return Carbon::today();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function subtituloExport(array $filtros): string
    {
        $partes = [];
        if (($filtros['fecha_desde'] ?? '') !== '') {
            $partes[] = 'Desde '.$filtros['fecha_desde'];
        }
        if (($filtros['fecha_hasta'] ?? '') !== '') {
            $partes[] = 'Hasta '.$filtros['fecha_hasta'];
        }
        $estado = (string) ($filtros['filtro_estado'] ?? 'todos');
        if ($estado !== 'todos') {
            $partes[] = $estado === 'abierto' ? 'Abiertas' : 'Contabilizadas';
        }
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $partes[] = 'Empresa '.$filtros['empresa_id'];
        }

        return $partes === [] ? 'Todas las precargas' : implode(' · ', $partes);
    }
}
