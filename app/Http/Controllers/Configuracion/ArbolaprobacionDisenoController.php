<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Nivel;
use App\Support\Configuracion\ArbolAprobacionDisenoSupport;
use App\Support\Configuracion\ArbolaprobacionNivelDocumentoEstadoSupport;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ArbolaprobacionDisenoController extends Controller
{
    public function show(int $id): View
    {
        can('edita-arbol-de-aprobacion');

        $arbol = Arbolaprobacion::query()
            ->with([
                'empresas:id,nombre',
                'arbolaprobacion_niveles.usuarios:id,nombre,usuario',
                'arbolaprobacion_niveles.centrocosto_ids:id,codigo,nombre',
                'arbolaprobacion_niveles.moneda_ids:id,nombre,abreviatura',
            ])
            ->findOrFail($id);

        $grafo = ArbolAprobacionDisenoSupport::construir($arbol);

        return view('configuracion.arbolaprobacion.disenador', [
            'arbol' => $arbol,
            'grafo' => $grafo,
        ]);
    }

    public function guardarLayout(Request $request, int $id): JsonResponse|RedirectResponse
    {
        can('actualiza-arbol-de-aprobacion');

        $arbol = Arbolaprobacion::query()->findOrFail($id);
        if (! Schema::hasColumn('arbolaprobacion', 'diseno_layout')) {
            return response()->json(['ok' => false, 'mensaje' => 'Falta columna diseno_layout.'], 422);
        }

        $datos = $request->validate([
            'nodes' => 'nullable|array',
            'nodes.*.x' => 'nullable|numeric',
            'nodes.*.y' => 'nullable|numeric',
            'zoom' => 'nullable|numeric|min:0.4|max:2.5',
            'pan_x' => 'nullable|numeric',
            'pan_y' => 'nullable|numeric',
            'auto' => 'nullable|boolean',
        ]);

        if ($request->boolean('auto')) {
            $arbol->load([
                'arbolaprobacion_niveles.usuarios:id,nombre,usuario',
                'arbolaprobacion_niveles.centrocosto_ids:id,codigo,nombre',
                'arbolaprobacion_niveles.moneda_ids:id,nombre',
                'empresas:id,nombre',
            ]);
            $porId = $arbol->arbolaprobacion_niveles->keyBy('id');
            $grafoTmp = ArbolAprobacionDisenoSupport::construir($arbol);
            $auto = ArbolAprobacionDisenoSupport::autoLayout($grafoTmp['lanes'], $porId);
            $nodes = [];
            foreach ($auto as $nodeId => $pos) {
                $nodes[$nodeId] = ['x' => $pos['x'], 'y' => $pos['y']];
            }
        } else {
            $nodes = [];
            foreach (($datos['nodes'] ?? []) as $nodeId => $pos) {
                if (! is_array($pos)) {
                    continue;
                }
                $nodes[(string) $nodeId] = [
                    'x' => (float) ($pos['x'] ?? 0),
                    'y' => (float) ($pos['y'] ?? 0),
                ];
            }
        }

        $arbol->diseno_layout = [
            'nodes' => $nodes,
            'zoom' => (float) ($datos['zoom'] ?? 1),
            'pan_x' => (float) ($datos['pan_x'] ?? 0),
            'pan_y' => (float) ($datos['pan_y'] ?? 0),
            'actualizado_en' => now()->toDateTimeString(),
        ];
        $arbol->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'mensaje' => 'Layout guardado.', 'nodes' => $nodes]);
        }

        return redirect()->route('disenador_arbolaprobacion', $id)
            ->with('mensaje', 'Layout del diseñador guardado.');
    }

    public function actualizarNodo(Request $request, int $id, int $nivelId): JsonResponse
    {
        can('actualiza-arbol-de-aprobacion');

        $nivel = Arbolaprobacion_Nivel::query()
            ->where('arbolaprobacion_id', $id)
            ->whereKey($nivelId)
            ->firstOrFail();

        $arbol = Arbolaprobacion::query()->findOrFail($id);
        $usaEstado = ArbolaprobacionNivelDocumentoEstadoSupport::usaEstadoDocumento((string) $arbol->tipoarbol);

        $datos = $request->validate([
            'nivel' => 'nullable|integer|min:1|max:99',
            'usuario_id' => 'nullable|integer|min:1',
            'limpiar_usuario' => 'nullable|boolean',
            'desdemonto' => 'nullable|numeric|min:0',
            'hastamonto' => 'nullable|numeric|min:0',
            'documento_estado_al_aprobar' => 'nullable|string|max:80',
            'doble_aprobacion' => 'nullable|boolean',
        ]);

        if (array_key_exists('nivel', $datos) && $datos['nivel'] !== null) {
            $nivel->nivel = (int) $datos['nivel'];
        }
        if ($request->boolean('limpiar_usuario')) {
            $nivel->usuario_id = null;
        } elseif (! empty($datos['usuario_id'])) {
            $uid = (int) $datos['usuario_id'];
            if (! UsuarioOperativoSupport::find($uid)) {
                return response()->json(['ok' => false, 'mensaje' => 'Usuario no operativo.'], 422);
            }
            $nivel->usuario_id = $uid;
        }
        if (array_key_exists('desdemonto', $datos)) {
            $nivel->desdemonto = $datos['desdemonto'];
        }
        if (array_key_exists('hastamonto', $datos)) {
            $nivel->hastamonto = $datos['hastamonto'];
        }
        if ($usaEstado && array_key_exists('documento_estado_al_aprobar', $datos)) {
            $nivel->documento_estado_al_aprobar = $datos['documento_estado_al_aprobar'] ?: null;
        }
        if (array_key_exists('doble_aprobacion', $datos) || $request->has('doble_aprobacion')) {
            $nivel->doble_aprobacion = $request->boolean('doble_aprobacion') ? 'S' : 'N';
        }
        $nivel->save();
        $nivel->load(['usuarios:id,nombre,usuario', 'centrocosto_ids:id,codigo,nombre', 'moneda_ids:id,nombre']);

        return response()->json([
            'ok' => true,
            'mensaje' => 'Nivel actualizado.',
            'nodo' => [
                'id' => 'n'.$nivel->id,
                'nivel_id' => (int) $nivel->id,
                'nivel' => (int) $nivel->nivel,
                'usuario_id' => $nivel->usuario_id ? (int) $nivel->usuario_id : null,
                'usuario_codigo' => $nivel->usuarios->usuario ?? '',
                'usuario_nombre' => $nivel->usuarios->nombre ?? '',
                'auto' => ! $nivel->usuario_id,
                'desdemonto' => $nivel->desdemonto !== null ? (float) $nivel->desdemonto : null,
                'hastamonto' => $nivel->hastamonto !== null ? (float) $nivel->hastamonto : null,
                'documento_estado_al_aprobar' => $nivel->documento_estado_al_aprobar,
                'doble_aprobacion' => strtoupper((string) ($nivel->doble_aprobacion ?? 'N')) === 'S',
            ],
        ]);
    }
}
