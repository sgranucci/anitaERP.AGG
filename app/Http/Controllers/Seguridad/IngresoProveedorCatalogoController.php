<?php

namespace App\Http\Controllers\Seguridad;

use App\Http\Controllers\Controller;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Seguridad\IngresoProveedorCatalogoListadoFiltros;
use App\Support\Seguridad\IngresoProveedorCatalogoSupport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        $datas = $query->orderBy('nombre')->paginate(10);

        return view('seguridad.ingreso_proveedor_catalogo.index', [
            'tipo' => $tipo,
            'def' => $def,
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => IngresoProveedorCatalogoListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => IngresoProveedorCatalogoListadoFiltros::CAMPOS,
        ]);
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
