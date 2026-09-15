<?php

namespace App\Http\Controllers\Seguridad;

use App\Exports\Seguridad\IngresoProveedorCatalogoListadoExport;
use App\Http\Controllers\Controller;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Seguridad\IngresoProveedorCatalogoListadoFiltros;
use App\Support\Seguridad\IngresoProveedorCatalogoSupport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class IngresoProveedorCatalogoController extends Controller
{
    public function index(Request $request)
    {
        can('listar-ingreso-proveedor-catalogo');
        $tipo = IngresoProveedorCatalogoSupport::tipoDesdeRequest($request);
        $def = IngresoProveedorCatalogoSupport::def($tipo);
        $filtros = IngresoProveedorCatalogoListadoFiltros::resolverDesdeRequest($request);

        $query = IngresoProveedorCatalogoSupport::modelo($tipo)::query();
        if (IngresoProveedorCatalogoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            IngresoProveedorCatalogoListadoFiltros::aplicar($query, $filtros);
        }
        $datas = $query->orderBy('nombre')->paginate(25);

        return view('seguridad.ingreso_proveedor_catalogo.index', [
            'tipo' => $tipo,
            'def' => $def,
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => IngresoProveedorCatalogoListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => IngresoProveedorCatalogoListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null)
    {
        can('listar-ingreso-proveedor-catalogo');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $tipo = IngresoProveedorCatalogoSupport::tipoDesdeRequest($request);
        $def = IngresoProveedorCatalogoSupport::def($tipo);
        $filtros = IngresoProveedorCatalogoListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = IngresoProveedorCatalogoListadoFiltros::paraQueryString($filtros);
        $slugArchivo = str_replace('-', '_', $def['ruta']);

        switch ($formato) {
            case 'PDF':
                $query = IngresoProveedorCatalogoSupport::modelo($tipo)::query();
                if (IngresoProveedorCatalogoListadoFiltros::tieneCriteriosAplicados($filtros)) {
                    IngresoProveedorCatalogoListadoFiltros::aplicar($query, $filtros);
                }
                $datas = $query->orderBy('nombre')->get();
                $titulo = $def['titulo'];
                $view = \View::make('seguridad.ingreso_proveedor_catalogo.listado', compact('datas', 'titulo'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $pdf = \PDF::loadHTML($view)->setPaper('a4', 'portrait');
                $pdf->save($path.'/listado_'.$slugArchivo.'.pdf');

                return $pdf->download('listado_'.$slugArchivo.'.pdf');
            case 'EXCEL':
                return Excel::download(
                    (new IngresoProveedorCatalogoListadoExport)->parametros($tipo, $filtros),
                    'listado_'.$slugArchivo.'.xlsx'
                );
            case 'CSV':
                return Excel::download(
                    (new IngresoProveedorCatalogoListadoExport)->parametros($tipo, $filtros),
                    'listado_'.$slugArchivo.'.csv',
                    \Maatwebsite\Excel\Excel::CSV
                );
            default:
                return redirect()->route($def['ruta'], $filtrosQuery);
        }
    }

    public function crear(Request $request)
    {
        can('crear-ingreso-proveedor-catalogo');
        $tipo = IngresoProveedorCatalogoSupport::tipoDesdeRequest($request);

        return view('seguridad.ingreso_proveedor_catalogo.crear', [
            'tipo' => $tipo,
            'def' => IngresoProveedorCatalogoSupport::def($tipo),
        ]);
    }

    public function guardar(Request $request)
    {
        can('crear-ingreso-proveedor-catalogo');
        $tipo = IngresoProveedorCatalogoSupport::tipoDesdeRequest($request);
        $modelo = IngresoProveedorCatalogoSupport::modelo($tipo);
        $data = $this->validar($request, $modelo->getTable());
        $modelo->newQuery()->create($data);

        return redirect()->route($this->rutaIndex($tipo))->with('mensaje', 'Registro creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-ingreso-proveedor-catalogo');
        $tipo = IngresoProveedorCatalogoSupport::tipoDesdeRequest($request);
        $data = IngresoProveedorCatalogoSupport::modelo($tipo)::query()->findOrFail($id);

        return view('seguridad.ingreso_proveedor_catalogo.editar', [
            'tipo' => $tipo,
            'def' => IngresoProveedorCatalogoSupport::def($tipo),
            'data' => $data,
        ]);
    }

    public function actualizar(Request $request, $id)
    {
        can('actualizar-ingreso-proveedor-catalogo');
        $tipo = IngresoProveedorCatalogoSupport::tipoDesdeRequest($request);
        $modelo = IngresoProveedorCatalogoSupport::modelo($tipo);
        $data = $this->validar($request, $modelo->getTable(), (int) $id);
        $fila = $modelo->newQuery()->findOrFail($id);
        $fila->update($data);

        return redirect()->route($this->rutaIndex($tipo))->with('mensaje', 'Registro actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-ingreso-proveedor-catalogo');
        $tipo = IngresoProveedorCatalogoSupport::tipoDesdeRequest($request);
        EloquentAuditDeleteSupport::each(
            IngresoProveedorCatalogoSupport::modelo($tipo)::query()->where('id', $id)
        );

        return redirect()->route($this->rutaIndex($tipo))->with('mensaje', 'Registro eliminado');
    }

    /**
     * @return array{codigo: ?string, nombre: string, activo: bool}
     */
    private function validar(Request $request, string $tabla, ?int $id = null): array
    {
        $validated = $request->validate([
            'codigo' => 'nullable|string|max:20',
            'nombre' => [
                'required',
                'string',
                'max:120',
                Rule::unique($tabla, 'nombre')->ignore($id),
            ],
            'activo' => 'nullable|boolean',
        ]);

        return [
            'codigo' => $validated['codigo'] ?? null,
            'nombre' => $validated['nombre'],
            'activo' => $request->boolean('activo', true),
        ];
    }

    private function rutaIndex(string $tipo): string
    {
        return IngresoProveedorCatalogoSupport::def($tipo)['ruta'];
    }
}
