<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Support\Ai\AiAgenteEventoHitlSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Throwable;

/**
 * Cola HITL de agentes por evento (ai_agente_evento).
 */
class AiAgenteEventoController extends Controller
{
    public function index(Request $request)
    {
        can('listar-ai-decisiones');

        $filtros = [
            'estado' => $request->input('estado', \App\Models\Ai\AiAgenteEvento::ESTADO_PENDIENTE),
            'severidad' => $request->input('severidad'),
            'evento' => $request->input('evento'),
        ];

        $coleccion = AiAgenteEventoHitlSupport::listar($filtros, true, 15);
        $coleccion->appends($request->query());

        return view('configuracion.ai_agente_evento.index', [
            'filtros' => $filtros,
            'coleccion' => $coleccion,
            'estados' => AiAgenteEventoHitlSupport::estadosEtiquetas(),
        ]);
    }

    public function marcarVisto(Request $request, int $id)
    {
        return $this->transicion($id, 'visto');
    }

    public function descartar(Request $request, int $id)
    {
        return $this->transicion($id, 'descartar');
    }

    public function resolver(Request $request, int $id)
    {
        return $this->transicion($id, 'resolver');
    }

    private function transicion(int $id, string $accion)
    {
        can('listar-ai-decisiones');

        $usuarioId = Auth::id() ? (int) Auth::id() : null;

        try {
            $evento = match ($accion) {
                'visto' => AiAgenteEventoHitlSupport::marcarVisto($id, $usuarioId),
                'descartar' => AiAgenteEventoHitlSupport::descartar($id, $usuarioId),
                'resolver' => AiAgenteEventoHitlSupport::resolver($id, $usuarioId),
                default => throw new InvalidArgumentException('Acción inválida.'),
            };
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'No se pudo actualizar el evento.'], 500);
        }

        return response()->json([
            'ok' => true,
            'evento' => [
                'id' => $evento->id,
                'estado' => $evento->estado,
                'visto_at' => optional($evento->visto_at)->format('d/m/Y H:i'),
                'resuelto_at' => optional($evento->resuelto_at)->format('d/m/Y H:i'),
            ],
        ]);
    }
}
