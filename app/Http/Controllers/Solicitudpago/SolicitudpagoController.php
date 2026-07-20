<?php

namespace App\Http\Controllers\Solicitudpago;

use App\Exports\Solicitudpago\SolicitudpagoListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionSolicitudpago;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Solicitudpago\Concepto_SolicitudpagoRepositoryInterface;
use App\Repositories\Solicitudpago\FormapagosolRepositoryInterface;
use App\Repositories\Solicitudpago\Sector_SolicitudpagoRepositoryInterface;
use App\Repositories\Solicitudpago\SolicitudpagoRepositoryInterface;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use App\Support\Solicitudpago\SolicitudpagoListadoFiltros;
use App\Support\Solicitudpago\SolicitudpagoTratamientos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PDF;

class SolicitudpagoController extends Controller
{
    public function __construct(
        private SolicitudpagoRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
        private Sector_SolicitudpagoRepositoryInterface $sectorRepository,
        private Concepto_SolicitudpagoRepositoryInterface $conceptoRepository,
        private FormapagosolRepositoryInterface $formapagosolRepository,
        private MonedaRepositoryInterface $monedaRepository,
        private CentrocostoRepositoryInterface $centrocostoRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-solicitud-pago');

        $filtros = SolicitudpagoListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = SolicitudpagoListadoFiltros::paraQueryString($filtros);
        $camposFiltro = SolicitudpagoListadoFiltros::CAMPOS;
        $coleccion = $this->repository->leeSolicitudpago($filtros, true);
        $estado_enum = SolicitudpagoEstados::opciones();
        $tratamiento_enum = SolicitudpagoTratamientos::opciones();

        return view('solicitudpago.solicitudpago.index', compact(
            'coleccion',
            'filtros',
            'filtrosQuery',
            'camposFiltro',
            'estado_enum',
            'tratamiento_enum'
        ));
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-solicitud-pago');
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '300');

        $filtros = SolicitudpagoListadoFiltros::resolverDesdeRequest($request, $busqueda);
        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $datas = $this->repository->leeSolicitudpago($filtros, false);
                $titulo = 'Solicitudes de pago';
                $pdf = PDF::loadView('solicitudpago.solicitudpago.listado', compact('datas', 'titulo', 'filtros'));
                $pdf->setPaper('legal', 'landscape');
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $pdf->save($path.'/listado_solicitudpago.pdf');

                return response()->download($path.'/listado_solicitudpago.pdf');
            case 'EXCEL':
                return (new SolicitudpagoListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('solicitudpago.xlsx');
            case 'CSV':
                return (new SolicitudpagoListadoExport($this->repository))
                    ->parametros($filtros, true)
                    ->download('solicitudpago.csv', \Maatwebsite\Excel\Excel::CSV);
            default:
                return redirect()->route('consultar_solicitudpago', SolicitudpagoListadoFiltros::paraQueryString($filtros));
        }
    }

    public function crear()
    {
        can('crear-solicitud-pago');

        return view('solicitudpago.solicitudpago.crear', $this->datosFormulario());
    }

    public function guardar(ValidacionSolicitudpago $request)
    {
        try {
            $data = $request->validated();
            $data['archivos_nuevos'] = $request->file('archivos_nuevos', []);
            $this->repository->create($data);

            return redirect('solicitudpago/solicitudpago')->with('mensaje', 'Solicitud de pago creada con éxito');
        } catch (\Throwable $e) {
            return back()->withInput()->with('mensaje', $e->getMessage());
        }
    }

    public function editar($id)
    {
        can('editar-solicitud-pago');
        $data = $this->repository->findOrFail($id);

        return view('solicitudpago.solicitudpago.editar', array_merge($this->datosFormulario(), compact('data')));
    }

    public function actualizar(ValidacionSolicitudpago $request, $id)
    {
        can('actualizar-solicitud-pago');

        try {
            $data = $request->validated();
            $data['archivos_nuevos'] = $request->file('archivos_nuevos', []);
            if ($request->boolean('archivos_gestionados')) {
                $data['archivo_ids_existentes'] = $request->input('archivo_ids_existentes', []);
            }
            $this->repository->update($data, $id);

            return redirect('solicitudpago/solicitudpago')->with('mensaje', 'Solicitud de pago actualizada con éxito');
        } catch (\Throwable $e) {
            return back()->withInput()->with('mensaje', $e->getMessage());
        }
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-solicitud-pago');

        if ($request->ajax()) {
            return response()->json(['mensaje' => $this->repository->delete($id) ? 'ok' : 'ng']);
        }

        abort(404);
    }

    public function suspender($id)
    {
        can('actualizar-solicitud-pago');
        $this->repository->cambiarEstado((int) $id, SolicitudpagoEstados::SUSPENDIDA, 'SUSPENDIDA');

        return redirect()->route('editar_solicitudpago', $id)->with('mensaje', 'Solicitud suspendida');
    }

    public function levantarSuspension($id)
    {
        can('actualizar-solicitud-pago');
        $this->repository->cambiarEstado((int) $id, SolicitudpagoEstados::EMITIDA, 'Levanta suspensión');

        return redirect()->route('editar_solicitudpago', $id)->with('mensaje', 'Suspensión levantada');
    }

    public function descargarArchivo($id, $archivoId)
    {
        can('listar-solicitud-pago');
        $sp = $this->repository->findOrFail($id);
        $arch = $sp->archivos->firstWhere('id', (int) $archivoId);
        if (! $arch || ! Storage::disk('public')->exists($arch->archivo)) {
            abort(404);
        }

        return Storage::disk('public')->download($arch->archivo, $arch->nombre_original ?: basename($arch->archivo));
    }

    public function importarCuotas(Request $request, $id)
    {
        can('actualizar-solicitud-pago');
        $request->validate([
            'archivo_cuotas' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $filas = \App\Support\Solicitudpago\SolicitudpagoCuotasImportColumnasSupport::leerFilas(
                $request->file('archivo_cuotas')
            );
            if ($filas === []) {
                return back()->with('mensaje', 'No se encontraron cuotas válidas en el Excel.');
            }

            $sp = $this->repository->findOrFail($id);
            $payload = $sp->toArray();
            $payload['nro_cuotas'] = array_column($filas, 'nro_cuota');
            $payload['fecha_vencimientos_cuota'] = array_column($filas, 'fecha_vencimiento');
            $payload['montos_cuota'] = array_column($filas, 'monto');
            $payload['solicitudpago_hija_ids'] = array_fill(0, count($filas), null);

            // Conservar cuentas existentes
            $payload['empresa_ids'] = $sp->cuentas->pluck('empresa_id')->all();
            $payload['cuentacontable_ids'] = $sp->cuentas->pluck('cuentacontable_id')->all();
            $payload['centrocosto_ids'] = $sp->cuentas->pluck('centrocosto_id')->all();
            $payload['debe_haberes'] = $sp->cuentas->pluck('debe_haber')->all();
            $payload['montos_cuenta'] = $sp->cuentas->pluck('monto')->all();
            $payload['archivo_ids_existentes'] = $sp->archivos->pluck('id')->all();

            $this->repository->update($payload, $id);

            return redirect()->route('editar_solicitudpago', $id)
                ->with('mensaje', 'Se importaron '.count($filas).' cuota(s) desde Excel.');
        } catch (\Throwable $e) {
            return back()->with('mensaje', $e->getMessage());
        }
    }

    public function marcarPagada($id)
    {
        can('actualizar-solicitud-pago');
        $sp = $this->repository->findOrFail($id);
        if ($sp->estado !== SolicitudpagoEstados::AUTORIZADA) {
            return redirect()->route('editar_solicitudpago', $id)
                ->with('mensaje', 'Solo se puede marcar PAGADA una solicitud AUTORIZADA.');
        }
        $this->repository->cambiarEstado((int) $id, SolicitudpagoEstados::PAGADA, 'Pagada manual');

        return redirect()->route('editar_solicitudpago', $id)->with('mensaje', 'Solicitud marcada como pagada');
    }

    public function irAPago($id)
    {
        can('actualizar-solicitud-pago');
        $sp = $this->repository->findOrFail($id);
        if ($sp->estado !== SolicitudpagoEstados::AUTORIZADA) {
            return redirect()->route('editar_solicitudpago', $id)
                ->with('mensaje', 'Solo se puede pagar una solicitud AUTORIZADA.');
        }

        return redirect()->route('crear_ingresoegreso', [
            'solicitudpago_id' => $sp->id,
            'empresa_id' => $sp->empresa_id,
            'proveedor_id' => $sp->proveedor_id,
            'detalle' => 'Pago SP '.$sp->codigo,
        ]);
    }

    /** @return array<string, mixed> */
    private function datosFormulario(): array
    {
        return [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'sector_query' => $this->sectorRepository->all(),
            'concepto_query' => $this->conceptoRepository->all(),
            'formapagosol_query' => $this->formapagosolRepository->all(),
            'moneda_query' => $this->monedaRepository->all(),
            'centrocosto_query' => $this->centrocostoRepository->all(),
            'estado_enum' => SolicitudpagoEstados::opciones(),
            'tratamiento_enum' => SolicitudpagoTratamientos::opciones(),
        ];
    }
}
