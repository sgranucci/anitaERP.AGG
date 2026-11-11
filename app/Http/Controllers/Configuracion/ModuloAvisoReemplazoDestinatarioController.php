<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Services\Configuracion\ModuloAvisoReemplazoDestinatarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ModuloAvisoReemplazoDestinatarioController extends Controller
{
    public function __construct(
        private ModuloAvisoReemplazoDestinatarioService $service,
    ) {
    }

    public function index()
    {
        can('listar-reemplazo-destinatario-aviso');

        $historial = DB::table('modulo_aviso_reemplazo_log as l')
            ->leftJoin('usuario as uo', 'uo.id', '=', 'l.usuario_origen_id')
            ->leftJoin('usuario as ud', 'ud.id', '=', 'l.usuario_destino_id')
            ->leftJoin('usuario as ue', 'ue.id', '=', 'l.usuario_ejecutor_id')
            ->orderByDesc('l.id')
            ->limit(30)
            ->get([
                'l.*',
                'uo.nombre as origen_nombre',
                'uo.usuario as origen_usuario',
                'ud.nombre as destino_nombre',
                'ud.usuario as destino_usuario',
                'ue.nombre as ejecutor_nombre',
            ]);

        return view('configuracion.reemplazo_destinatario_aviso.index', [
            'modulos' => $this->service->opcionesModulo(),
            'historial' => $historial,
            'puedeEjecutar' => can('ejecutar-reemplazo-destinatario-aviso', false),
            'rolesPermitidos' => ['administrador', 'Enc-sistemas', 'Enc-admin', 'Ger-administracion'],
        ]);
    }

    public function previsualizar(Request $request)
    {
        can('listar-reemplazo-destinatario-aviso');

        try {
            $payload = $this->payloadDesdeRequest($request);
            if ($payload['modo'] === 'restaurar') {
                $preview = $this->service->previsualizarRestaurar(
                    $payload['titular_id'],
                    $payload['opciones']
                );
            } else {
                $preview = $this->service->previsualizar(
                    $payload['origen_id'],
                    $payload['destino_id'],
                    $payload['opciones']
                );
                $preview['operacion'] = 'reemplazo';
            }

            return response()->json(['ok' => true, 'preview' => $preview]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function aplicar(Request $request)
    {
        can('ejecutar-reemplazo-destinatario-aviso');

        try {
            $payload = $this->payloadDesdeRequest($request);
            if ($payload['modo'] === 'restaurar') {
                $resultado = $this->service->restaurarTitular(
                    $payload['titular_id'],
                    $payload['opciones']
                );
            } else {
                $resultado = $this->service->aplicar(
                    $payload['origen_id'],
                    $payload['destino_id'],
                    $payload['opciones']
                );
            }

            return redirect()
                ->route('consultar_reemplazo_destinatario_aviso')
                ->with('mensaje', $resultado['mensaje'] ?? 'Operación aplicada.')
                ->with('resultado_reemplazo', $resultado);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('mensaje', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->withInput()->with('mensaje', 'Error al aplicar: '.$e->getMessage());
        }
    }

    /**
     * @return array{
     *   modo: string,
     *   origen_id: int,
     *   destino_id: int,
     *   titular_id: int,
     *   opciones: array<string, mixed>
     * }
     */
    private function payloadDesdeRequest(Request $request): array
    {
        $modo = strtolower(trim((string) $request->input('modo', 'reemplazo')));
        if (! in_array($modo, ['reemplazo', 'restaurar'], true)) {
            $modo = 'reemplazo';
        }

        $modulos = $request->input('modulos', []);
        if (! is_array($modulos)) {
            $modulos = [];
        }

        $origenId = (int) $request->input('usuario_origen_id', 0);
        $destinoId = (int) $request->input('usuario_destino_id', 0);
        $titularId = (int) $request->input('usuario_titular_id', 0);
        if ($modo === 'restaurar' && $titularId <= 0) {
            $titularId = $destinoId > 0 ? $destinoId : $origenId;
        }

        return [
            'modo' => $modo,
            'origen_id' => $origenId,
            'destino_id' => $destinoId,
            'titular_id' => $titularId,
            'opciones' => [
                'modulos' => array_values(array_map('strval', $modulos)),
                'con_fecha_tope' => $modo === 'reemplazo' && $request->boolean('con_fecha_tope'),
                'vence_el' => $modo === 'reemplazo' ? $request->input('vence_el') : null,
            ],
        ];
    }
}
