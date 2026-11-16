<?php

declare(strict_types=1);

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionImportacionGastoTipo;
use App\Models\Compras\Importacion_Gasto_Tipo;
use App\Support\Compras\ImportacionGastoTratamiento;
use Illuminate\Http\Request;

class ImportacionGastoTipoController extends Controller
{
    public function index(Request $request)
    {
        can('listar-importacion-gasto-tipo');
        $datas = Importacion_Gasto_Tipo::query()
            ->with('cuentacontable')
            ->orderBy('orden')
            ->orderBy('codigo')
            ->paginate(15);

        return view('compras.importacion_gasto_tipo.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-importacion-gasto-tipo');

        return view('compras.importacion_gasto_tipo.crear', [
            'data' => new Importacion_Gasto_Tipo([
                'tratamiento' => ImportacionGastoTratamiento::INVENTARIABLE,
                'base_prorrateo' => ImportacionGastoTratamiento::BASE_VALOR,
                'activo' => true,
                'orden' => 100,
            ]),
            'tratamientos' => ImportacionGastoTratamiento::ETIQUETAS,
            'bases' => ImportacionGastoTratamiento::BASES,
        ]);
    }

    public function guardar(ValidacionImportacionGastoTipo $request)
    {
        can('crear-importacion-gasto-tipo');
        Importacion_Gasto_Tipo::query()->create($request->validated());

        return redirect()->route('importacion_gasto_tipo')
            ->with('mensaje', 'Tipo de gasto creado');
    }

    public function editar($id)
    {
        can('editar-importacion-gasto-tipo');
        $data = Importacion_Gasto_Tipo::query()->findOrFail($id);

        return view('compras.importacion_gasto_tipo.editar', [
            'data' => $data,
            'tratamientos' => ImportacionGastoTratamiento::ETIQUETAS,
            'bases' => ImportacionGastoTratamiento::BASES,
        ]);
    }

    public function actualizar(ValidacionImportacionGastoTipo $request, $id)
    {
        can('actualizar-importacion-gasto-tipo');
        $data = Importacion_Gasto_Tipo::query()->findOrFail($id);
        $data->update($request->validated());

        return redirect()->route('importacion_gasto_tipo')
            ->with('mensaje', 'Tipo de gasto actualizado');
    }

    public function eliminar($id)
    {
        can('eliminar-importacion-gasto-tipo');
        $data = Importacion_Gasto_Tipo::query()->findOrFail($id);
        $data->delete();

        return redirect()->route('importacion_gasto_tipo')
            ->with('mensaje', 'Tipo de gasto eliminado');
    }

    public function consulta(Request $request)
    {
        $this->assertPuedeConsultarDesdeImportacion();

        $term = trim((string) $request->input('consulta', $request->input('term', '')));
        $q = Importacion_Gasto_Tipo::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('codigo')
            ->limit(80);
        if ($term !== '') {
            $like = '%'.$term.'%';
            $q->where(function ($w) use ($like) {
                $w->where('codigo', 'like', $like)->orWhere('nombre', 'like', $like);
            });
        }

        $html = '';
        foreach ($q->get(['id', 'codigo', 'nombre', 'tratamiento', 'base_prorrateo']) as $t) {
            $etiq = ImportacionGastoTratamiento::ETIQUETAS[$t->tratamiento] ?? (string) $t->tratamiento;
            $html .= '<tr data-id="'.(int) $t->id
                .'" data-codigo="'.e((string) $t->codigo)
                .'" data-nombre="'.e((string) $t->nombre)
                .'" data-tratamiento="'.e((string) $t->tratamiento)
                .'" data-base="'.e((string) ($t->base_prorrateo ?? '')).'">';
            $html .= '<td>'.e((string) $t->codigo).'</td>';
            $html .= '<td>'.e((string) $t->nombre).'</td>';
            $html .= '<td>'.e($etiq).'</td>';
            $html .= '<td class="text-nowrap"><a class="btn btn-warning btn-sm eligeconsulta-importacion-gasto-tipo">Elegir</a></td>';
            $html .= '</tr>';
        }

        return response()->json([
            'data' => $html !== '' ? $html : '<tr><td colspan="4" class="text-muted">Sin resultados</td></tr>',
        ]);
    }

    public function leeUnoPorCodigo(Request $request)
    {
        $this->assertPuedeConsultarDesdeImportacion();

        $codigo = trim((string) $request->input('codigo', $request->input('valor', '')));
        if ($codigo === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Código vacío']);
        }

        $t = Importacion_Gasto_Tipo::query()
            ->where('activo', true)
            ->where('codigo', $codigo)
            ->first();
        if (! $t) {
            return response()->json(['ok' => false, 'mensaje' => 'Tipo no encontrado']);
        }

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => (int) $t->id,
                'codigo' => (string) $t->codigo,
                'nombre' => (string) $t->nombre,
                'tratamiento' => (string) $t->tratamiento,
                'tratamiento_etiqueta' => ImportacionGastoTratamiento::ETIQUETAS[$t->tratamiento] ?? (string) $t->tratamiento,
                'base_prorrateo' => (string) ($t->base_prorrateo ?? ''),
            ],
        ]);
    }

    private function assertPuedeConsultarDesdeImportacion(): void
    {
        if (
            can('listar-importacion', false)
            || can('crear-importacion', false)
            || can('editar-importacion', false)
            || can('actualizar-importacion', false)
            || can('listar-importacion-gasto-tipo', false)
            || can('crear-comprobante-proveedor', false)
            || can('editar-comprobante-proveedor', false)
            || can('actualizar-comprobante-proveedor', false)
        ) {
            return;
        }
        can('listar-importacion');
    }
}
