<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Services\Configuracion\ArbolReemplazoFirmanteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ArbolReemplazoFirmanteController extends Controller
{
    public function __construct(
        private ArbolReemplazoFirmanteService $service,
    ) {
    }

    public function index()
    {
        can('listar-reemplazo-firmante-arbol');

        $historial = DB::table('arbol_reemplazo_firmante_log as l')
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

        return view('configuracion.reemplazo_firmante_arbol.index', [
            'tipos' => $this->service->opcionesTipoArbol(),
            'historial' => $historial,
            'puedeEjecutar' => can('ejecutar-reemplazo-firmante-arbol', false),
            'rolesPermitidos' => ['administrador', 'Enc-sistemas', 'Enc-admin', 'Ger-administracion'],
        ]);
    }

    public function previsualizar(Request $request)
    {
        can('listar-reemplazo-firmante-arbol');

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
        can('ejecutar-reemplazo-firmante-arbol');

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
                ->route('consultar_reemplazo_firmante_arbol')
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

        $tipos = $request->input('tipos', []);
        if (! is_array($tipos)) {
            $tipos = [];
        }

        $origenId = (int) $request->input('usuario_origen_id', 0);
        $destinoId = (int) $request->input('usuario_destino_id', 0);
        $titularId = (int) $request->input('usuario_titular_id', 0);
        if ($modo === 'restaurar' && $titularId <= 0) {
            // Compat: si no mandaron titular, usar destino o origen.
            $titularId = $destinoId > 0 ? $destinoId : $origenId;
        }

        return [
            'modo' => $modo,
            'origen_id' => $origenId,
            'destino_id' => $destinoId,
            'titular_id' => $titularId,
            'opciones' => [
                'incluir_globales' => $request->boolean('incluir_globales'),
                'incluir_conceptos_sp' => $request->boolean('incluir_conceptos_sp'),
                'actualizar_pendientes' => $request->boolean('actualizar_pendientes'),
                'reenviar_correo' => $request->boolean('reenviar_correo'),
                'tipos' => array_values(array_map('strval', $tipos)),
            ],
        ];
    }
}
