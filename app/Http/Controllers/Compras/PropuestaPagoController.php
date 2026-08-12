<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\PropuestaPagoListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionPropuestaPago;
use App\Models\Compras\PropuestaPago;
use App\Repositories\Caja\CajaRepositoryInterface;
use App\Repositories\Caja\ChequeraRepositoryInterface;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use App\Repositories\Compras\PropuestaPagoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Services\Compras\PropuestaPagoService;
use App\Support\Compras\PropuestaPagoAuditoriaSupport;
use App\Support\Compras\PropuestaPagoConvenioBancarioSupport;
use App\Support\Compras\PropuestaPagoListadoFiltros;
use App\Support\Compras\PropuestaPagoLoteBancarioSupport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropuestaPagoController extends Controller
{
    public function __construct(
        private PropuestaPagoRepositoryInterface $propuestaPagoRepository,
        private PropuestaPagoService $propuestaPagoService,
        private EmpresaRepositoryInterface $empresaRepository,
        private MonedaRepositoryInterface $monedaRepository,
        private CajaRepositoryInterface $cajaRepository,
        private CuentacajaRepositoryInterface $cuentacajaRepository,
        private ChequeraRepositoryInterface $chequeraRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-propuesta-pago');

        $filtros = PropuestaPagoListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = PropuestaPagoListadoFiltros::paraQueryString($filtros);
        $coleccion = $this->propuestaPagoRepository->leePropuestaPago($filtros, true);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('compras.propuesta_pago.index', compact(
            'coleccion',
            'filtros',
            'filtrosQuery',
            'empresa_query'
        ));
    }

    public function listar(Request $request, $formato = null)
    {
        can('listar-propuesta-pago');
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '120');

        $filtros = PropuestaPagoListadoFiltros::resolverDesdeRequest($request);
        if (! $formato) {
            return redirect()->route('propuesta_pago', PropuestaPagoListadoFiltros::paraQueryString($filtros));
        }

        $formato = strtoupper((string) $formato);
        $datas = $this->propuestaPagoRepository->leePropuestaPago($filtros, false);
        foreach ($datas as $fila) {
            $fila->nombreempresa = $fila->empresas->nombre ?? '';
        }

        if ($formato === 'PDF') {
            $pdf = Pdf::loadView('compras.propuesta_pago.listado_index', ['datas' => $datas])
                ->setPaper('legal', 'landscape');
            $path = storage_path('pdf/listados/listado_propuesta_pago.pdf');
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            $pdf->save($path);

            return response()->file($path);
        }

        if (in_array($formato, ['EXCEL', 'CSV'], true)) {
            $export = app(PropuestaPagoListadoExport::class)->parametros($filtros);
            $nombre = 'propuestas_pago_'.date('Ymd_His');

            return $formato === 'CSV'
                ? Excel::download($export, $nombre.'.csv', \Maatwebsite\Excel\Excel::CSV)
                : Excel::download($export, $nombre.'.xlsx');
        }

        return redirect()->route('propuesta_pago', PropuestaPagoListadoFiltros::paraQueryString($filtros));
    }

    public function imprimir(int $id)
    {
        can('listar-propuesta-pago');
        $data = $this->propuestaPagoRepository->find($id);
        $pdf = Pdf::loadView('compras.propuesta_pago.listado', compact('data'))
            ->setPaper('legal', 'landscape');

        return $pdf->stream('propuesta_pago_'.$id.'.pdf');
    }

    public function crear()
    {
        can('crear-propuesta-pago');
        $data = new PropuestaPago([
            'fecha' => date('Y-m-d'),
            'fecha_vencimiento_hasta' => date('Y-m-d', strtotime('+7 days')),
            'estado' => 'BORRADOR',
        ]);

        return view('compras.propuesta_pago.crear', $this->datosFormulario($data));
    }

    public function guardar(ValidacionPropuestaPago $request)
    {
        can('crear-propuesta-pago');
        $resultado = $this->propuestaPagoService->guardar($request);
        if (! empty($resultado['errores'])) {
            return back()->withErrors(['error' => $resultado['errores']])->withInput();
        }

        return redirect()
            ->route('editar_propuesta_pago', $resultado['propuesta_pago_id'])
            ->with('mensaje', 'Propuesta de pago creada. Revise líneas y envíe a aprobación.');
    }

    public function editar(int $id)
    {
        can('editar-propuesta-pago');
        $data = $this->propuestaPagoRepository->find($id);

        return view('compras.propuesta_pago.editar', $this->datosFormulario($data));
    }

    public function actualizar(ValidacionPropuestaPago $request, int $id)
    {
        can('actualizar-propuesta-pago');
        $resultado = $this->propuestaPagoService->actualizar($request, $id);
        if (! empty($resultado['errores'])) {
            return back()->withErrors(['error' => $resultado['errores']])->withInput();
        }

        return redirect()
            ->route('editar_propuesta_pago', $id)
            ->with('mensaje', 'Propuesta actualizada.');
    }

    public function eliminar(Request $request, int $id)
    {
        can('borrar-propuesta-pago');
        $propuesta = $this->propuestaPagoRepository->findOrFail($id);
        if (! in_array((string) $propuesta->estado, ['BORRADOR', 'RECHAZADA', 'ANULADA'], true)) {
            return back()->withErrors(['error' => 'Solo se pueden eliminar propuestas en borrador, rechazadas o anuladas.']);
        }
        $this->propuestaPagoRepository->delete($id);

        return redirect()->route('propuesta_pago')->with('mensaje', 'Propuesta eliminada.');
    }

    public function enviarAprobacion(int $id)
    {
        can('enviar-aprobacion-propuesta-pago');
        $resultado = $this->propuestaPagoService->enviarAprobacion($id);
        if (! $resultado['ok']) {
            return back()->withErrors(['error' => $resultado['mensaje']]);
        }

        return back()->with('mensaje', $resultado['mensaje']);
    }

    public function ejecutar(int $id)
    {
        can('ejecutar-propuesta-pago');
        $resultado = $this->propuestaPagoService->ejecutar($id);
        if (! $resultado['ok']) {
            return back()->withErrors(['error' => $resultado['mensaje']]);
        }

        return back()->with('mensaje', $resultado['mensaje']);
    }

    public function reabrir(int $id)
    {
        can('actualizar-propuesta-pago');
        $resultado = $this->propuestaPagoService->reabrir($id);
        if (! $resultado['ok']) {
            return back()->withErrors(['error' => $resultado['mensaje']]);
        }

        return back()->with('mensaje', $resultado['mensaje']);
    }

    public function reabrirParcial(int $id)
    {
        can('actualizar-propuesta-pago');
        $resultado = $this->propuestaPagoService->reabrirParcial($id);
        if (! $resultado['ok']) {
            return back()->withErrors(['error' => $resultado['mensaje']]);
        }

        return back()->with('mensaje', $resultado['mensaje']);
    }

    public function clonarDelta(int $id)
    {
        can('crear-propuesta-pago');
        $resultado = $this->propuestaPagoService->clonarDelta($id);
        if (! $resultado['ok']) {
            return back()->withErrors(['error' => $resultado['mensaje']]);
        }

        return redirect()
            ->route('editar_propuesta_pago', $resultado['propuesta_pago_id'])
            ->with('mensaje', $resultado['mensaje']);
    }

    public function marcarLoteEnviado(int $id)
    {
        can('ejecutar-propuesta-pago');
        $resultado = $this->propuestaPagoService->marcarLoteEnviado($id);
        if (! $resultado['ok']) {
            return back()->withErrors(['error' => $resultado['mensaje']]);
        }

        return back()->with('mensaje', $resultado['mensaje']);
    }

    public function auditoria(int $id)
    {
        can('listar-propuesta-pago');
        $pack = PropuestaPagoAuditoriaSupport::armar($id);

        return view('compras.propuesta_pago.auditoria', $pack);
    }

    public function exportarAuditoria(int $id)
    {
        can('listar-propuesta-pago');
        $pack = PropuestaPagoAuditoriaSupport::armar($id);
        $pdf = Pdf::loadView('compras.propuesta_pago.auditoria_pdf', $pack)
            ->setPaper('legal', 'portrait');
        $path = storage_path('pdf/listados/auditoria_propuesta_pago_'.$id.'.pdf');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $pdf->save($path);

        return response()->file($path);
    }

    public function conciliarBridge(int $id)
    {
        can('ejecutar-propuesta-pago');
        $resultado = $this->propuestaPagoService->conciliarBridge($id);

        return back()->with('mensaje', $resultado['mensaje']);
    }

    public function generarLoteBancario(int $id)
    {
        can('ejecutar-propuesta-pago');
        $resultado = $this->propuestaPagoService->generarLoteBancario($id);
        if (! $resultado['ok']) {
            return back()->withErrors(['error' => $resultado['mensaje']]);
        }

        return back()->with('mensaje', $resultado['mensaje']);
    }

    public function exportarLoteBancario(int $id): StreamedResponse|\Illuminate\Http\RedirectResponse
    {
        can('listar-propuesta-pago');
        $lote = PropuestaPagoLoteBancarioSupport::ultimoLote($id);
        if (! $lote) {
            $gen = $this->propuestaPagoService->generarLoteBancario($id);
            if (! $gen['ok']) {
                return back()->withErrors(['error' => $gen['mensaje']]);
            }
            $lote = PropuestaPagoLoteBancarioSupport::ultimoLote($id);
        }
        if (! $lote) {
            return back()->withErrors(['error' => 'No hay lote bancario para exportar.']);
        }

        $export = PropuestaPagoConvenioBancarioSupport::exportar($lote);
        PropuestaPagoLoteBancarioSupport::marcarExportado($lote, $export['nombre']);

        return response()->streamDownload(function () use ($export) {
            echo $export['contenido'];
        }, $export['nombre'], [
            'Content-Type' => $export['mime'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(PropuestaPago $data): array
    {
        $lote = $data->id ? PropuestaPagoLoteBancarioSupport::ultimoLote((int) $data->id) : null;
        $lotesHist = $data->id ? PropuestaPagoLoteBancarioSupport::historial((int) $data->id) : collect();

        return [
            'data' => $data,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'moneda_query' => $this->monedaRepository->all(),
            'caja_query' => $this->cajaRepository->all(),
            'cuentacaja_query' => $this->cuentacajaRepository->all(),
            'chequera_query' => $this->chequeraRepository->all(),
            'lote_bancario' => $lote,
            'lotes_bancarios' => $lotesHist,
            'estadosEnum' => PropuestaPago::$enumEstado,
        ];
    }
}
