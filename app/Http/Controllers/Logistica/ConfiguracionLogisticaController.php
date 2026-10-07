<?php

namespace App\Http\Controllers\Logistica;

use App\Http\Controllers\Controller;
use App\Models\Admin\Rol;
use App\Models\Logistica\LogisticaCatalogoCategoria;
use App\Models\Logistica\LogisticaHabilitacion;
use App\Models\Logistica\LogisticaParametro;
use App\Models\Logistica\LogisticaTipoSolicitud;
use App\Models\Logistica\LogisticaTrabajoTipo;
use App\Models\Logistica\LogisticaUbicacion;
use App\Support\Logistica\ArticuloCatalogoLogisticaSupport;
use App\Support\Logistica\LogisticaConfiguracionSupport;
use App\Support\Logistica\LogisticaVisibilidadSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfiguracionLogisticaController extends Controller
{
    public function editar(Request $request)
    {
        can('editar-configuracion-logistica');
        if (! ArticuloCatalogoLogisticaSupport::uiActiva()) {
            abort(404);
        }

        $categorias = LogisticaCatalogoCategoria::query()->orderBy('orden')->orderBy('nombre')->get();
        $tipos = LogisticaTipoSolicitud::query()->orderBy('orden')->get();
        $trabajos = LogisticaTrabajoTipo::query()->orderBy('orden')->orderBy('nombre')->get();
        $ubicaciones = LogisticaUbicacion::query()->orderBy('orden')->orderBy('nombre')->get();
        $habilitaciones = LogisticaHabilitacion::query()
            ->with(['rol:id,nombre', 'usuario:id,usuario,nombre', 'tipoSolicitud:id,codigo,nombre', 'categoria:id,codigo,nombre'])
            ->whereIn('nivel', ['tipo', 'categoria'])
            ->orderBy('id')
            ->get();

        $verComo = null;
        $usuarioPreviewId = (int) $request->query('ver_usuario_id', 0);
        if ($usuarioPreviewId > 0) {
            $usuario = LogisticaVisibilidadSupport::resolverUsuario($usuarioPreviewId, null);
            if ($usuario !== null) {
                $verComo = [
                    'usuario' => $usuario,
                    'catalogo' => \App\Support\Logistica\LogisticaCatalogoPortalSupport::catalogoParaUsuario((int) $usuario->id),
                ];
            }
        }

        $montoAprobacion = LogisticaParametro::montoAprobacion();

        return view('logistica.configuracion.editar', compact(
            'categorias',
            'tipos',
            'trabajos',
            'ubicaciones',
            'habilitaciones',
            'verComo',
            'montoAprobacion'
        ));
    }

    public function actualizar(Request $request)
    {
        can('actualizar-configuracion-logistica');
        if (! ArticuloCatalogoLogisticaSupport::uiActiva()) {
            abort(404);
        }

        try {
            DB::transaction(function () use ($request) {
                LogisticaConfiguracionSupport::guardar($request);
            });
        } catch (\Throwable $e) {
            return redirect()
                ->route('editar_configuracion_logistica')
                ->withInput()
                ->with('mensaje-error', $e->getMessage());
        }

        return redirect()
            ->route('editar_configuracion_logistica')
            ->with('mensaje', 'Catálogo de logística actualizado.');
    }

    public function consultaCategoria(Request $request)
    {
        $this->puedeConsultarCatalogo();
        $texto = trim((string) $request->input('consulta', ''));
        $query = LogisticaCatalogoCategoria::query()->where('activo', true)->orderBy('orden')->orderBy('nombre');
        if ($texto !== '') {
            $query->where(function ($q) use ($texto) {
                $q->where('codigo', 'like', '%'.$texto.'%')
                    ->orWhere('nombre', 'like', '%'.$texto.'%');
            });
        }
        $html = '';
        foreach ($query->limit(40)->get() as $categoria) {
            $html .= '<tr>';
            $html .= '<td class="idcategoria">'.e((string) $categoria->id).'</td>';
            $html .= '<td class="codigocategoria">'.e($categoria->codigo).'</td>';
            $html .= '<td class="nombrecategoria">'.e($categoria->nombre).'</td>';
            $html .= '<td><a class="btn btn-warning btn-sm eligeconsultacatalogocategoria">Elegir</a></td>';
            $html .= '</tr>';
        }
        if ($html === '') {
            $html = '<tr><td colspan="4">Sin categorías.</td></tr>';
        }

        return response()->json(['data' => $html]);
    }

    public function resolverCategoria(Request $request)
    {
        $this->puedeConsultarCatalogo();
        $id = (int) $request->input('id', 0);
        $codigo = trim((string) $request->input('codigo', ''));
        $categoria = null;
        if ($id > 0) {
            $categoria = LogisticaCatalogoCategoria::query()->where('activo', true)->find($id);
        } elseif ($codigo !== '') {
            $categoria = LogisticaCatalogoCategoria::query()->where('activo', true)->where('codigo', $codigo)->first();
        }
        if ($categoria === null) {
            return response()->json(null);
        }

        return response()->json([
            'id' => (int) $categoria->id,
            'codigo' => $categoria->codigo,
            'nombre' => $categoria->nombre,
        ]);
    }

    public function resolverRol(Request $request)
    {
        $this->puedeConsultarCatalogo();
        $rol = LogisticaVisibilidadSupport::resolverRol(
            (int) $request->input('id', 0),
            (string) $request->input('nombre', '')
        );
        if ($rol === null) {
            return response()->json(null);
        }

        return response()->json([
            'id' => (int) $rol->id,
            'nombre' => $rol->nombre,
        ]);
    }

    public function consultaRol(Request $request)
    {
        $this->puedeConsultarCatalogo();
        $texto = trim((string) $request->input('consulta', ''));
        $query = Rol::query()->orderBy('nombre');
        if ($texto !== '') {
            $query->where('nombre', 'like', '%'.$texto.'%');
        }
        $html = '';
        foreach ($query->limit(40)->get() as $rol) {
            $html .= '<tr>';
            $html .= '<td class="idrol">'.e((string) $rol->id).'</td>';
            $html .= '<td class="nombrerol">'.e($rol->nombre).'</td>';
            $html .= '<td><a class="btn btn-warning btn-sm eligeconsultalogisticarol">Elegir</a></td>';
            $html .= '</tr>';
        }
        if ($html === '') {
            $html = '<tr><td colspan="3">Sin roles.</td></tr>';
        }

        return response()->json(['data' => $html]);
    }

    public function resolverTipo(Request $request)
    {
        $this->puedeConsultarCatalogo();
        $id = (int) $request->input('id', 0);
        $codigo = trim((string) $request->input('codigo', ''));
        $tipo = null;
        if ($id > 0) {
            $tipo = LogisticaTipoSolicitud::query()->where('activo', true)->find($id);
        } elseif ($codigo !== '') {
            $tipo = LogisticaTipoSolicitud::query()->where('activo', true)->where('codigo', $codigo)->first();
        }
        if ($tipo === null) {
            return response()->json(null);
        }

        return response()->json([
            'id' => (int) $tipo->id,
            'codigo' => $tipo->codigo,
            'nombre' => $tipo->nombre,
        ]);
    }

    private function puedeConsultarCatalogo(): void
    {
        if (! ArticuloCatalogoLogisticaSupport::uiActiva()) {
            abort(404);
        }
        if (can('editar-articulos', false)
            || can('actualizar-articulos', false)
            || can('editar-configuracion-logistica', false)
            || can('actualizar-configuracion-logistica', false)
        ) {
            return;
        }
        abort(403);
    }
}
