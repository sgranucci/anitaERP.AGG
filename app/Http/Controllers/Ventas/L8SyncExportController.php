<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Exportación de solo lectura para que L12 consuma datos de este servidor (L8).
 * Activar con FERLI_L8_EXPORT_ENABLED=true y el mismo FERLI_L8_HTTP_TOKEN en ambos lados.
 */
class L8SyncExportController extends Controller
{
    public function tareasOt(Request $request)
    {
        $this->assertExportHabilitado($request);

        $codigos = array_filter(array_map('intval', explode(',', (string) $request->input('codigos', ''))));

        // En L8 la "fuente" es la BD local (default), no mysql_l8.
        $otCodigos = array_values(array_unique(array_filter($codigos, static fn ($c) => $c > 0)));
        if ($otCodigos === []) {
            return response()->json([
                'fuente' => 'local',
                'ordentrabajo' => [],
                'ordentrabajo_tarea' => [],
                'movimientoordentrabajo' => [],
                'ordentrabajo_combinacion_talle' => [],
            ]);
        }

        $ots = DB::table('ordentrabajo')->whereIn('codigo', $otCodigos)->get()->map(static fn ($r) => (array) $r)->all();
        $otIds = array_map(static fn ($r) => (int) $r['id'], $ots);

        return response()->json([
            'fuente' => 'local',
            'ordentrabajo' => $ots,
            'ordentrabajo_tarea' => $otIds === [] ? [] : DB::table('ordentrabajo_tarea')->whereIn('ordentrabajo_id', $otIds)->get()->map(static fn ($r) => (array) $r)->all(),
            'movimientoordentrabajo' => $otIds === [] ? [] : DB::table('movimientoordentrabajo')->whereIn('ordentrabajo_id', $otIds)->get()->map(static fn ($r) => (array) $r)->all(),
            'ordentrabajo_combinacion_talle' => $otIds === [] ? [] : DB::table('ordentrabajo_combinacion_talle')->whereIn('ordentrabajo_id', $otIds)->get()->map(static fn ($r) => (array) $r)->all(),
        ]);
    }

    /**
     * Códigos de OT con tareas en el rango (para que L12 filtre las que faltan).
     * L12 manda ids_l12[] opcionales; si no, devuelve todos los códigos del rango.
     */
    public function tareasFaltantesRango(Request $request)
    {
        $this->assertExportHabilitado($request);

        $fechaDesde = trim((string) $request->input('fecha_desde', ''));
        $fechaHasta = trim((string) $request->input('fecha_hasta', ''));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) {
            return response()->json(['fuente' => 'local', 'ot_codigos' => []], 422);
        }
        $limite = max(1, min(2000, (int) $request->input('limite', 500)));
        $idsL12 = array_flip(array_filter(array_map('intval', (array) $request->input('ids_l12', []))));

        $q = DB::table('ordentrabajo_tarea as ott')
            ->join('ordentrabajo as ot', 'ot.id', '=', 'ott.ordentrabajo_id')
            ->select('ot.codigo', 'ott.id')
            ->where(function ($w) use ($fechaDesde, $fechaHasta) {
                $w->whereBetween('ott.hastafecha', [$fechaDesde, $fechaHasta])
                    ->orWhereBetween('ott.desdefecha', [$fechaDesde, $fechaHasta]);
            })
            ->orderBy('ot.codigo');

        $codigos = [];
        foreach ($q->cursor() as $row) {
            $tareaId = (int) ($row->id ?? 0);
            if ($idsL12 !== [] && $tareaId > 0 && isset($idsL12[$tareaId])) {
                continue;
            }
            $codigo = (int) ($row->codigo ?? 0);
            if ($codigo <= 0) {
                continue;
            }
            $codigos[$codigo] = true;
            if (count($codigos) >= $limite) {
                break;
            }
        }

        return response()->json([
            'fuente' => 'local',
            'ot_codigos' => array_map('intval', array_keys($codigos)),
        ]);
    }

    public function pedidosFaltantes(Request $request)
    {
        $this->assertExportHabilitado($request);

        $fechaDesde = trim((string) $request->input('fecha_desde', ''));
        if ($fechaDesde !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) {
            $fechaDesde = '';
        }
        $limite = max(1, min(300, (int) $request->input('limite', 100)));
        $codigosL12 = array_filter(array_map('strval', (array) $request->input('codigos_l12', [])));
        $idsL12 = array_filter(array_map('intval', (array) $request->input('ids_l12', [])));

        // Si L12 no manda exclusiones, devolvemos candidatos recientes; L12 filtra al importar.
        $q = DB::table('pedido')->orderByDesc('id');
        if ($fechaDesde !== '') {
            $q->whereDate('fecha', '>=', $fechaDesde);
        }
        $candidatos = $q->limit(max(50, $limite * 5))->get();

        $faltantes = [];
        foreach ($candidatos as $row) {
            $codigo = (string) ($row->codigo ?? '');
            $id = (int) $row->id;
            if ($codigosL12 !== [] && in_array($codigo, $codigosL12, true)) {
                continue;
            }
            if ($idsL12 !== [] && in_array($id, $idsL12, true)) {
                continue;
            }
            $faltantes[] = (array) $row;
            if (count($faltantes) >= $limite) {
                break;
            }
        }

        if ($faltantes === []) {
            return response()->json([
                'fuente' => 'local',
                'pedidos' => [],
                'pedido_combinacion' => [],
                'pedido_combinacion_talle' => [],
                'pedido_combinacion_estado' => [],
                'ordentrabajo' => [],
                'ordentrabajo_combinacion_talle' => [],
                'ordentrabajo_tarea' => [],
                'movimientoordentrabajo' => [],
            ]);
        }

        // Reusar reader con payload construido localmente vía mismas queries que mysql_l8 path:
        // forzamos lectura local construyendo el mismo shape.
        $pedidoIds = array_map(static fn ($r) => (int) $r['id'], $faltantes);
        $combinaciones = DB::table('pedido_combinacion')->whereIn('pedido_id', $pedidoIds)->get()->map(static fn ($r) => (array) $r)->all();
        $pcIds = array_map(static fn ($r) => (int) $r['id'], $combinaciones);
        $talles = $pcIds === [] ? [] : DB::table('pedido_combinacion_talle')->whereIn('pedido_combinacion_id', $pcIds)->get()->map(static fn ($r) => (array) $r)->all();
        $estados = [];
        try {
            $estados = $pcIds === [] ? [] : DB::table('pedido_combinacion_estado')->whereIn('pedido_combinacion_id', $pcIds)->get()->map(static fn ($r) => (array) $r)->all();
        } catch (\Throwable) {
        }
        $otCodigos = array_values(array_unique(array_filter(array_map(static fn ($r) => (int) ($r['ot_id'] ?? 0), $combinaciones), static fn ($c) => $c > 0)));
        $ots = $otCodigos === [] ? [] : DB::table('ordentrabajo')->whereIn('codigo', $otCodigos)->get()->map(static fn ($r) => (array) $r)->all();
        $otIds = array_map(static fn ($r) => (int) $r['id'], $ots);

        return response()->json([
            'fuente' => 'local',
            'pedidos' => $faltantes,
            'pedido_combinacion' => $combinaciones,
            'pedido_combinacion_talle' => $talles,
            'pedido_combinacion_estado' => $estados,
            'ordentrabajo' => $ots,
            'ordentrabajo_combinacion_talle' => $otIds === [] ? [] : DB::table('ordentrabajo_combinacion_talle')->whereIn('ordentrabajo_id', $otIds)->get()->map(static fn ($r) => (array) $r)->all(),
            'ordentrabajo_tarea' => $otIds === [] ? [] : DB::table('ordentrabajo_tarea')->whereIn('ordentrabajo_id', $otIds)->get()->map(static fn ($r) => (array) $r)->all(),
            'movimientoordentrabajo' => $otIds === [] ? [] : DB::table('movimientoordentrabajo')->whereIn('ordentrabajo_id', $otIds)->get()->map(static fn ($r) => (array) $r)->all(),
        ]);
    }

    private function assertExportHabilitado(Request $request): void
    {
        if (! config('ferli_l8.export_enabled')) {
            abort(404);
        }
        $esperado = (string) config('ferli_l8.http_token');
        if ($esperado === '') {
            abort(403, 'Token L8 no configurado.');
        }
        $recibido = (string) ($request->header('X-L8-Sync-Token') ?: $request->input('token', ''));
        if (! hash_equals($esperado, $recibido)) {
            abort(403, 'Token L8 inválido.');
        }
    }
}
