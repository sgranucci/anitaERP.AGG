<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\ProgramaPagoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionProgramaPago;
use App\Models\Compras\ProgramaPago;
use App\Repositories\Compras\ProgramaPagoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\ProgramaPagoService;
use App\Support\Compras\ProgramaPagoListadoFiltros;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ProgramaPagoController extends Controller
{
    public function __construct(
        private ProgramaPagoRepositoryInterface $programaPagoRepository,
        private ProgramaPagoService $programaPagoService,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        $this->assertFerli();
        can('listar-programa-pago');

        $filtros = ProgramaPagoListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = ProgramaPagoListadoFiltros::paraQueryString($filtros);
        $coleccion = $this->programaPagoRepository->leeProgramaPago($filtros, true);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('compras.programa_pago.index', compact(
            'coleccion',
            'filtros',
            'filtrosQuery',
            'empresa_query'
        ));
    }

    public function listar(Request $request, $formato = null)
    {
        $this->assertFerli();
        can('listar-programa-pago');
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '120');

        $filtros = ProgramaPagoListadoFiltros::resolverDesdeRequest($request);
        if (! $formato) {
            return redirect()->route('programa_pago', ProgramaPagoListadoFiltros::paraQueryString($filtros));
        }

        $formato = strtoupper((string) $formato);
        $datas = $this->programaPagoRepository->leeProgramaPago($filtros, false);
        foreach ($datas as $fila) {
            $fila->nombreempresa = $fila->empresas->nombre ?? '';
        }

        if ($formato === 'PDF') {
            $pdf = Pdf::loadView('compras.programa_pago.listado_index', ['datas' => $datas])
                ->setPaper('legal', 'landscape');
            $path = storage_path('pdf/listados/listado_programa_pago.pdf');
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            $pdf->save($path);

            return response()->file($path);
        }

        if (in_array($formato, ['EXCEL', 'CSV'], true)) {
            $export = app(ProgramaPagoExport::class)->parametrosListado($filtros);
            $nombre = 'programas_pago_'.date('Ymd_His');

            return $formato === 'CSV'
                ? Excel::download($export, $nombre.'.csv', \Maatwebsite\Excel\Excel::CSV)
                : Excel::download($export, $nombre.'.xlsx');
        }

        return redirect()->route('programa_pago', ProgramaPagoListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        $this->assertFerli();
        can('crear-programa-pago');

        $data = new ProgramaPago([
            'fecha_base' => date('Y-m-d'),
            'anio_mes_inicio' => date('Y-m'),
            'cantidad_meses' => 4,
            'incluye_transf' => true,
            'estado' => ProgramaPago::ESTADO_BORRADOR,
        ]);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('compras.programa_pago.crear', compact('data', 'empresa_query'));
    }

    public function guardar(ValidacionProgramaPago $request)
    {
        $this->assertFerli();
        can('crear-programa-pago');

        $empresaId = (int) $request->input('empresa_id');
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return back()->with('error', 'Empresa no permitida.')->withInput();
        }

        try {
            $programa = $this->programaPagoService->crear([
                'empresa_id' => $empresaId,
                'titulo' => $request->input('titulo'),
                'fecha_base' => $request->input('fecha_base'),
                'anio_mes_inicio' => $request->input('anio_mes_inicio'),
                'cantidad_meses' => (int) $request->input('cantidad_meses', 4),
                'incluye_transf' => (bool) $request->input('incluye_transf', true),
                'detalle' => $request->input('detalle'),
                'sembrar_deuda' => (bool) $request->input('sembrar_deuda', true),
            ]);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()
            ->route('editar_programa_pago', $programa->id)
            ->with('mensaje', 'Programa creado. Ajuste montos por mes y guarde.');
    }

    public function editar(int $id)
    {
        $this->assertFerli();
        can('editar-programa-pago');

        $data = $this->programaPagoRepository->find($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $data->empresa_id)) {
            abort(403);
        }

        $matriz = $this->programaPagoService->armarVistaMatriz($data);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $puedeEditar = can('actualizar-programa-pago', false) && $data->esEditable();

        return view('compras.programa_pago.editar', compact(
            'data',
            'matriz',
            'empresa_query',
            'puedeEditar'
        ));
    }

    public function actualizar(ValidacionProgramaPago $request, int $id)
    {
        $this->assertFerli();
        can('actualizar-programa-pago');

        $programa = $this->programaPagoRepository->find($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $programa->empresa_id)) {
            abort(403);
        }

        try {
            $this->programaPagoService->actualizar($programa, $request->validated());
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()
            ->route('editar_programa_pago', $id)
            ->with('mensaje', 'Programa actualizado.');
    }

    public function eliminar(int $id)
    {
        $this->assertFerli();
        can('borrar-programa-pago');

        $programa = $this->programaPagoRepository->find($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $programa->empresa_id)) {
            abort(403);
        }

        try {
            $this->programaPagoService->eliminar($programa);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('programa_pago')->with('mensaje', 'Programa eliminado.');
    }

    public function refrescarSaldos(int $id)
    {
        $this->assertFerli();
        can('actualizar-programa-pago');

        $programa = $this->programaPagoRepository->find($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $programa->empresa_id)) {
            abort(403);
        }

        try {
            $n = $this->programaPagoService->refrescarSaldos($programa);
            $creadas = $this->programaPagoService->sembrarDesdeDeuda($programa);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('mensaje', "Saldos actualizados ({$n}). Proveedores nuevos: {$creadas}.");
    }

    public function agregarProveedor(Request $request, int $id)
    {
        $this->assertFerli();
        can('actualizar-programa-pago');

        $programa = $this->programaPagoRepository->find($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $programa->empresa_id)) {
            abort(403);
        }

        $proveedorId = (int) $request->input('proveedor_id');
        try {
            $this->programaPagoService->agregarProveedor(
                $programa,
                $proveedorId,
                $request->input('observacion')
            );
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('mensaje', 'Proveedor agregado al programa.');
    }

    public function cerrar(int $id)
    {
        $this->assertFerli();
        can('actualizar-programa-pago');

        $programa = $this->programaPagoRepository->find($id);
        try {
            $this->programaPagoService->cerrar($programa);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('mensaje', 'Programa cerrado.');
    }

    public function reabrir(int $id)
    {
        $this->assertFerli();
        can('actualizar-programa-pago');

        $programa = $this->programaPagoRepository->find($id);
        try {
            $this->programaPagoService->reabrir($programa);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('mensaje', 'Programa reabierto.');
    }

    public function exportarMatriz(int $id, string $formato = 'EXCEL')
    {
        $this->assertFerli();
        can('listar-programa-pago');
        ini_set('memory_limit', '512M');

        $programa = $this->programaPagoRepository->find($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $programa->empresa_id)) {
            abort(403);
        }

        $matriz = $this->programaPagoService->armarVistaMatriz($programa);
        $formato = strtoupper($formato);

        if ($formato === 'PDF') {
            $pdf = Pdf::loadView('compras.programa_pago.listado', [
                'data' => $programa,
                'matriz' => $matriz,
            ])->setPaper('legal', 'landscape');

            return $pdf->stream('programa_pago_'.$id.'.pdf');
        }

        $export = app(ProgramaPagoExport::class)->parametrosMatriz($programa, $matriz);
        $nombre = 'programa_pago_'.$id.'_'.date('Ymd_His');

        return $formato === 'CSV'
            ? Excel::download($export, $nombre.'.csv', \Maatwebsite\Excel\Excel::CSV)
            : Excel::download($export, $nombre.'.xlsx');
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}
