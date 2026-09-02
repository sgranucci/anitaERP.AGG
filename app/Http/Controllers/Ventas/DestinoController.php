<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\DestinoListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionDestino;
use App\Models\Ventas\Destino;
use App\Repositories\Ventas\DestinoRepositoryInterface;
use App\Support\Listado\QueryRetornoListado;
use App\Support\Ventas\DestinoListadoFiltros;
use Illuminate\Http\Request;

class DestinoController extends Controller
{
    public function __construct(private readonly DestinoRepositoryInterface $repository)
    {
    }

    public function index(Request $request)
    {
        can('listar-destino');

        $filtros = DestinoListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeDestino($filtros, true);

        return view('ventas.destino.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => DestinoListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => DestinoListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-destino');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = DestinoListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeDestino($filtros, false);
                $view = \View::make('ventas.destino.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_destino';

                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new DestinoListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('destinos_senasa.xlsx');

            case 'CSV':
                return (new DestinoListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('destinos_senasa.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('destino', DestinoListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-destino');
        $data = new Destino(['patagonico' => false]);

        return view('ventas.destino.crear', $this->datosFormulario($request, $data));
    }

    public function guardar(ValidacionDestino $request)
    {
        can('crear-destino');
        $this->repository->create($request->validated());

        return redirect()->route('destino', QueryRetornoListado::desdeRequest($request, DestinoListadoFiltros::class))
            ->with('mensaje', 'Destino creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-destino');
        $data = $this->repository->findOrFail($id);

        return view('ventas.destino.editar', array_merge(
            $this->datosFormulario($request, $data),
            ['puede_actualizar' => can('actualizar-destino', false)]
        ));
    }

    public function actualizar(ValidacionDestino $request, $id)
    {
        can('actualizar-destino');
        $this->repository->update($request->validated(), $id);

        return redirect()->route('destino', QueryRetornoListado::desdeRequest($request, DestinoListadoFiltros::class))
            ->with('mensaje', 'Destino actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-destino');

        if (! $request->ajax()) {
            abort(404);
        }

        if ($this->repository->delete($id)) {
            return response()->json(['mensaje' => 'ok']);
        }

        return response()->json(['mensaje' => 'ng']);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(Request $request, Destino $data): array
    {
        $data->loadMissing('zonavta');

        return [
            'data' => $data,
            'filtrosQuery' => QueryRetornoListado::desdeRequestSiIndex($request, DestinoListadoFiltros::class),
        ];
    }
}
