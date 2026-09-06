<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Services\Configuracion\AnitaNotificacionService;
use Illuminate\Http\Request;

class AnitaNotificacionController extends Controller
{
    public function __construct(
        private AnitaNotificacionService $notificaciones
    ) {}

    public function feed(Request $request)
    {
        $usuarioId = (int) (auth()->id() ?? 0);
        $items = $this->notificaciones->listarRecientes($usuarioId, 20)->map(function ($n) {
            return [
                'id' => (int) $n->id,
                'tipo' => (string) $n->tipo,
                'titulo' => (string) $n->titulo,
                'cuerpo' => (string) ($n->cuerpo ?? ''),
                'url' => (string) ($n->url ?? url('mis-aprobaciones')),
                'leida' => $n->leida_at !== null,
                'cuando' => optional($n->created_at)->diffForHumans() ?? '',
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'unread' => $this->notificaciones->contarNoLeidas($usuarioId),
            'items' => $items,
        ]);
    }

    public function contador()
    {
        $usuarioId = (int) (auth()->id() ?? 0);

        return response()->json([
            'ok' => true,
            'unread' => $this->notificaciones->contarNoLeidas($usuarioId),
        ]);
    }

    public function leer(Request $request, $id)
    {
        $usuarioId = (int) (auth()->id() ?? 0);
        $ok = $this->notificaciones->marcarLeida($usuarioId, (int) $id);

        return response()->json([
            'ok' => $ok,
            'unread' => $this->notificaciones->contarNoLeidas($usuarioId),
        ]);
    }

    public function leerTodas(Request $request)
    {
        $usuarioId = (int) (auth()->id() ?? 0);
        $n = $this->notificaciones->marcarTodasLeidas($usuarioId);

        return response()->json([
            'ok' => true,
            'marcadas' => $n,
            'unread' => 0,
        ]);
    }
}
