<?php

namespace App\Http\Controllers\Ventas\FacturacionLocal;

use App\Exports\Ventas\MotivoDevolucionListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionMotivoDevolucion;
use App\Models\Ventas\CambioDevolucionMarketplace;
use App\Models\Ventas\DevolucionHistorial;
use App\Models\Ventas\MotivoDevolucion;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Ventas\FacturacionLocal\MotivoDevolucionListadoFiltros;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class MotivoDevolucionController extends Controller
{
    public function index(Request $request)
    {
        $this->assertFerli();
        can('listar-motivo-devolucion-facturacion-local');

        $filtros = MotivoDevolucionListadoFiltros::resolverDesdeRequest($request);
        $query = MotivoDevolucion::query()->select('motivo_devolucion.*')->orderBy('orden')->orderBy('nombre');
        MotivoDevolucionListadoFiltros::aplicar($query, $filtros);
        $datas = $query->paginate(15);
        $filtrosQuery = MotivoDevolucionListadoFiltros::paraQueryString($filtros);

        return view('ventas.facturacion_local.motivo_devolucion.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => MotivoDevolucionListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null)
    {
        $this->assertFerli();
        can('listar-motivo-devolucion-facturacion-local');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = MotivoDevolucionListadoFiltros::resolverDesdeRequest($request);
        $query = MotivoDevolucion::query()->select('motivo_devolucion.*')->orderBy('orden')->orderBy('nombre');
        MotivoDevolucionListadoFiltros::aplicar($query, $filtros);
        $datas = $query->get();
        $formato = strtoupper((string) $formato);

        if ($formato === 'PDF') {
            $html = view('ventas.facturacion_local.motivo_devolucion.listado', [
                'datas' => $datas,
                'logosCabecera' => EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas),
                'totalFilas' => $datas->count(),
            ])->render();
            $dir = storage_path('pdf/listados');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $ruta = $dir.'/listado_motivo_devolucion.pdf';
            DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                'titulo_corto' => 'Motivos de devolución',
            ]);

            return response()->download($ruta, 'motivos_devolucion.pdf')->deleteFileAfterSend(true);
        }

        if ($formato === 'EXCEL') {
            return (new MotivoDevolucionListadoExport)->parametros($filtros)->download('motivos_devolucion.xlsx');
        }
        if ($formato === 'CSV') {
            return (new MotivoDevolucionListadoExport)->parametros($filtros)->download('motivos_devolucion.csv', Excel::CSV);
        }

        return redirect()->route('facturacion_local_motivos_devolucion', MotivoDevolucionListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        $this->assertFerli();
        can('crear-motivo-devolucion-facturacion-local');

        return view('ventas.facturacion_local.motivo_devolucion.crear', [
            'data' => new MotivoDevolucion(['vuelve_stock' => true, 'activo' => true, 'orden' => 0]),
        ]);
    }

    public function guardar(ValidacionMotivoDevolucion $request)
    {
        $this->assertFerli();
        can('crear-motivo-devolucion-facturacion-local');
        $motivo = MotivoDevolucion::query()->create($request->datos());

        return redirect()
            ->route('facturacion_local_motivos_devolucion')
            ->with('mensaje', 'Motivo '.$motivo->nombre.' creado.');
    }

    public function editar($id)
    {
        $this->assertFerli();
        can('editar-motivo-devolucion-facturacion-local');
        $data = MotivoDevolucion::query()->findOrFail($id);

        return view('ventas.facturacion_local.motivo_devolucion.editar', compact('data'));
    }

    public function actualizar(ValidacionMotivoDevolucion $request, $id)
    {
        $this->assertFerli();
        can('actualizar-motivo-devolucion-facturacion-local');
        $motivo = MotivoDevolucion::query()->findOrFail($id);
        $motivo->update($request->datos());

        return redirect()
            ->route('facturacion_local_motivos_devolucion')
            ->with('mensaje', 'Motivo actualizado.');
    }

    public function eliminar($id)
    {
        $this->assertFerli();
        can('eliminar-motivo-devolucion-facturacion-local');
        $motivo = MotivoDevolucion::query()->findOrFail($id);
        $usado = DevolucionHistorial::query()->where('motivo_devolucion_id', $motivo->id)->exists()
            || CambioDevolucionMarketplace::query()->where('motivo_devolucion_id', $motivo->id)->exists();
        if ($usado) {
            $motivo->activo = false;
            $motivo->save();

            return redirect()
                ->route('facturacion_local_motivos_devolucion')
                ->with('mensaje', 'El motivo tiene movimientos. Quedó inactivo.');
        }

        $motivo->delete();

        return redirect()
            ->route('facturacion_local_motivos_devolucion')
            ->with('mensaje', 'Motivo eliminado.');
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}
