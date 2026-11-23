<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Support\Sueldos\EmpleadoLegajoVisualizacion;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Empleado_Talle_Sueldos;
use App\Models\Sueldos\Entrega_Prenda_Sueldos;
use App\Models\Sueldos\Prenda_Articulo_Sueldos;
use App\Models\Sueldos\Prenda_Sueldos;
use App\Models\Sueldos\Solicitud_Prenda_Sueldos;
use App\Repositories\Stock\Articulo_Saldo_DepositoRepositoryInterface;
use App\Services\Sueldos\EntregaPrendaService;
use App\Services\Sueldos\SolicitudPrendaService;
use App\Services\Sueldos\TuLegajoClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Empleado_IndumentariaSueldosController extends Controller
{
    public function __construct(
        private EntregaPrendaService $servicio,
        private SolicitudPrendaService $solicitudService,
        private TuLegajoClient $tuLegajo,
        private Articulo_Saldo_DepositoRepositoryInterface $saldoRepository,
    ) {}

    public function panel($empleadoId)
    {
        $this->autorizarLectura();
        $empleado = Empleado_Sueldos::findOrFail($empleadoId);

        return $this->responderPanel($empleado);
    }

    /** Variantes (color/talle -> SKU) de una prenda con saldo en el depósito de origen. */
    public function variantes($prendaId): JsonResponse
    {
        $this->autorizarLectura();

        return response()->json(['variantes' => $this->variantesDePrenda((int) $prendaId)]);
    }

    /** Modal HTML: variantes de una prenda (color / talle / SKU / saldo). */
    public function consultaVariante(Request $request, $prendaId): JsonResponse
    {
        $this->autorizarLectura();

        $consulta = mb_strtolower(trim((string) $request->input('consulta', '')));
        $html = '';
        foreach ($this->variantesDePrenda((int) $prendaId) as $v) {
            $etiqueta = trim(($v['color'] ?? '').' '.($v['talle'] ?? ''));
            $haystack = mb_strtolower($etiqueta.' '.($v['sku'] ?? '').' '.$v['id']);
            if ($consulta !== '' && ! str_contains($haystack, $consulta)) {
                continue;
            }
            $saldoTxt = $v['saldo'] === null ? '—' : number_format((float) $v['saldo'], 3, ',', '.');
            $html .= '<tr data-id="'.$v['id'].'"'
                .' data-sku="'.e((string) $v['sku']).'"'
                .' data-color="'.e((string) $v['color']).'"'
                .' data-talle="'.e((string) $v['talle']).'"'
                .' data-articulo="'.(int) $v['articulo_id'].'"'
                .' data-saldo="'.($v['saldo'] === null ? '' : $v['saldo']).'"'
                .' data-nombre="'.e(trim($etiqueta.' · '.(($v['sku'] !== '') ? $v['sku'] : 's/SKU'))).'">'
                .'<td>'.$v['id'].'</td>'
                .'<td>'.e((string) $v['color']).'</td>'
                .'<td>'.e((string) $v['talle']).'</td>'
                .'<td>'.e((string) ($v['sku'] !== '' ? $v['sku'] : 's/SKU')).'</td>'
                .'<td class="text-right">'.$saldoTxt.'</td>'
                .'<td class="text-nowrap"><button type="button" class="btn btn-sm btn-primary elige-variante-prenda">Elegir</button></td>'
                .'</tr>';
        }

        return response()->json([
            'data' => $html !== '' ? $html : '<tr><td colspan="6" class="text-muted">Sin variantes para esta prenda</td></tr>',
        ]);
    }

    public function resolverVariante(Request $request): JsonResponse
    {
        $this->autorizarLectura();

        $prendaId = (int) $request->input('prenda_id');
        $valor = trim((string) $request->input('codigo', $request->input('valor', '')));
        if ($prendaId <= 0 || $valor === '') {
            return response()->json(['ok' => false]);
        }

        foreach ($this->variantesDePrenda($prendaId) as $v) {
            $matchSku = $v['sku'] !== '' && strcasecmp((string) $v['sku'], $valor) === 0;
            $matchId = (string) $v['id'] === $valor;
            if (! $matchSku && ! $matchId) {
                continue;
            }

            return response()->json([
                'ok' => true,
                'id' => $v['id'],
                'sku' => $v['sku'],
                'color' => $v['color'],
                'talle' => $v['talle'],
                'articulo_id' => $v['articulo_id'],
                'saldo' => $v['saldo'],
                'nombre' => trim(($v['color'] ?? '').' '.($v['talle'] ?? '').' · '.(($v['sku'] !== '') ? $v['sku'] : 's/SKU')),
            ]);
        }

        return response()->json(['ok' => false, 'error' => 'Variante no encontrada para la prenda']);
    }

    /**
     * @return list<array{id:int,color:string,talle:string,sku:string,articulo_id:int,saldo:float|null}>
     */
    private function variantesDePrenda(int $prendaId): array
    {
        $config = $this->servicio->configuracion();
        $depositoId = (int) ($config->deposito_id ?? 0);

        $variantes = Prenda_Articulo_Sueldos::query()
            ->with(['color:id,nombre', 'talle:id,nombre'])
            ->where('prenda_id', $prendaId)
            ->get();

        $data = [];
        foreach ($variantes as $v) {
            $saldo = null;
            if ($depositoId > 0 && (int) $v->articulo_id > 0) {
                $saldo = (float) $this->saldoRepository->saldo((int) $v->articulo_id, $depositoId);
            }
            $data[] = [
                'id' => (int) $v->id,
                'color' => $v->color->nombre ?? '',
                'talle' => $v->talle->nombre ?? '',
                'sku' => (string) ($v->sku ?? ''),
                'articulo_id' => (int) ($v->articulo_id ?? 0),
                'saldo' => $saldo,
            ];
        }

        return $data;
    }

    public function entregar(Request $request, $empleadoId)
    {
        can('entregar-prenda');
        $empleado = Empleado_Sueldos::findOrFail($empleadoId);

        $lineas = [];
        foreach ((array) $request->input('lineas', []) as $l) {
            $lineas[] = [
                'prenda_articulo_id' => (int) ($l['prenda_articulo_id'] ?? 0),
                'cantidad' => (float) ($l['cantidad'] ?? 0),
            ];
        }

        try {
            $entrega = $this->servicio->registrar(
                $empleado,
                $lineas,
                $request->input('fecha'),
                $request->input('observacion'),
                $this->usuarioId(),
                $request->boolean('omitir_cupo') && can('entregar-prenda', false),
            );
        } catch (\Throwable $e) {
            return response()->json(['mensaje' => $e->getMessage()], 422);
        }

        return $this->responderPanel($empleado, 'Entrega registrada (comprobante #'.$entrega->id.').');
    }

    public function anular(Request $request, $entregaId)
    {
        can('anular-entrega-prenda');
        $entrega = Entrega_Prenda_Sueldos::findOrFail($entregaId);
        $empleado = Empleado_Sueldos::findOrFail($entrega->empleado_id);

        try {
            $this->servicio->anular($entrega);
        } catch (\Throwable $e) {
            return response()->json(['mensaje' => $e->getMessage()], 422);
        }

        return $this->responderPanel($empleado, 'Entrega anulada (stock y asiento revertidos).');
    }

    public function crearSolicitud(Request $request, $empleadoId)
    {
        can('crear-solicitud-indumentaria');
        $empleado = Empleado_Sueldos::findOrFail($empleadoId);

        $lineas = [];
        foreach ((array) $request->input('lineas', []) as $l) {
            $lineas[] = [
                'prenda_articulo_id' => (int) ($l['prenda_articulo_id'] ?? 0),
                'cantidad' => (float) ($l['cantidad'] ?? 0),
            ];
        }

        try {
            $solicitud = $this->solicitudService->crear(
                $empleado,
                $lineas,
                $request->input('fecha'),
                $request->input('observacion'),
                $this->usuarioId(),
                true,
            );
        } catch (\Throwable $e) {
            return response()->json(['mensaje' => $e->getMessage()], 422);
        }

        $msg = $solicitud->estado === Solicitud_Prenda_Sueldos::APROBADA
            ? 'Solicitud #'.$solicitud->id.' aprobada automáticamente (sin árbol). Ya puede entregarse.'
            : 'Solicitud #'.$solicitud->id.' enviada a aprobación.';

        return $this->responderPanel($empleado, $msg);
    }

    public function aprobarSolicitud(Request $request, $solicitudId)
    {
        can('aprobar-solicitud-indumentaria');
        $solicitud = Solicitud_Prenda_Sueldos::findOrFail($solicitudId);
        $empleado = Empleado_Sueldos::findOrFail($solicitud->empleado_id);

        try {
            $this->solicitudService->aprobar($solicitud, (int) $this->usuarioId(), $request->input('observacion'));
        } catch (\Throwable $e) {
            return response()->json(['mensaje' => $e->getMessage()], 422);
        }

        return $this->responderPanel($empleado, 'Solicitud aprobada.');
    }

    public function rechazarSolicitud(Request $request, $solicitudId)
    {
        can('aprobar-solicitud-indumentaria');
        $solicitud = Solicitud_Prenda_Sueldos::findOrFail($solicitudId);
        $empleado = Empleado_Sueldos::findOrFail($solicitud->empleado_id);

        try {
            $this->solicitudService->rechazar($solicitud, (int) $this->usuarioId(), $request->input('observacion'));
        } catch (\Throwable $e) {
            return response()->json(['mensaje' => $e->getMessage()], 422);
        }

        return $this->responderPanel($empleado, 'Solicitud rechazada.');
    }

    public function entregarSolicitud(Request $request, $solicitudId)
    {
        can('entregar-prenda');
        $solicitud = Solicitud_Prenda_Sueldos::findOrFail($solicitudId);
        $empleado = Empleado_Sueldos::findOrFail($solicitud->empleado_id);

        try {
            $solicitud = $this->solicitudService->convertirEnEntrega($solicitud, $this->usuarioId(), $request->input('fecha'));
        } catch (\Throwable $e) {
            return response()->json(['mensaje' => $e->getMessage()], 422);
        }

        return $this->responderPanel($empleado, 'Solicitud entregada (comprobante #'.$solicitud->entrega_id.').');
    }

    public function anularSolicitud(Request $request, $solicitudId)
    {
        can('crear-solicitud-indumentaria');
        $solicitud = Solicitud_Prenda_Sueldos::findOrFail($solicitudId);
        $empleado = Empleado_Sueldos::findOrFail($solicitud->empleado_id);

        try {
            $this->solicitudService->anular($solicitud, $this->usuarioId());
        } catch (\Throwable $e) {
            return response()->json(['mensaje' => $e->getMessage()], 422);
        }

        return $this->responderPanel($empleado, 'Solicitud anulada.');
    }

    public function enviarTulegajo(Request $request, $entregaId)
    {
        can('entregar-prenda');
        $entrega = Entrega_Prenda_Sueldos::findOrFail($entregaId);
        $empleado = Empleado_Sueldos::findOrFail($entrega->empleado_id);

        $r = $this->tuLegajo->subirComprobanteEntrega($entrega);

        return $this->responderPanel($empleado, $r['mensaje'], $r['ok'] ? 'ok' : 'error');
    }

    public function guardarTalles(Request $request, $empleadoId)
    {
        can('entregar-prenda');
        $empleado = Empleado_Sueldos::findOrFail($empleadoId);

        $talles = (array) $request->input('talles', []); // [prenda_id => talle_id]
        foreach ($talles as $prendaId => $talleId) {
            $prendaId = (int) $prendaId;
            $talleId = (int) $talleId;
            if ($prendaId <= 0) {
                continue;
            }
            if ($talleId <= 0) {
                Empleado_Talle_Sueldos::query()
                    ->where('empleado_id', $empleado->id)->where('prenda_id', $prendaId)->delete();

                continue;
            }
            Empleado_Talle_Sueldos::updateOrCreate(
                ['empleado_id' => $empleado->id, 'prenda_id' => $prendaId],
                ['talle_id' => $talleId],
            );
        }

        return $this->responderPanel($empleado, 'Perfil de talles guardado.');
    }

    private function responderPanel(Empleado_Sueldos $empleado, ?string $mensaje = null, string $mensajeTipo = 'ok')
    {
        $config = $this->servicio->configuracion();
        $resumen = $this->servicio->resumenEmpleado($empleado);

        $entregas = Entrega_Prenda_Sueldos::query()
            ->with(['articulos.prenda:id,codigo,descripcion', 'articulos.color:id,nombre', 'articulos.talle:id,nombre', 'usuario:id,nombre'])
            ->where('empleado_id', $empleado->id)
            ->orderByDesc('fecha')->orderByDesc('id')
            ->get();

        // Prendas disponibles: dotación primero, luego todas las activas.
        $prendasDotacion = collect($resumen['prendas'])->pluck('prenda_id')->all();
        $prendas = Prenda_Sueldos::query()
            ->where('activo', true)
            ->orderBy('orden')->orderBy('descripcion')
            ->get(['id', 'codigo', 'descripcion']);

        $tallesEmpleadoDetalle = Empleado_Talle_Sueldos::query()
            ->with('talle:id,codigo,nombre')
            ->where('empleado_id', $empleado->id)
            ->get()
            ->keyBy('prenda_id');
        $tallesEmpleado = $tallesEmpleadoDetalle->map(fn ($r) => (int) $r->talle_id)->all();

        $usuarioId = (int) $this->usuarioId();
        $solicitudes = Solicitud_Prenda_Sueldos::query()
            ->with(['articulos.prenda:id,codigo,descripcion', 'articulos.color:id,nombre', 'articulos.talle:id,nombre', 'solicitante:id,nombre', 'aprobaciones.usuario:id,nombre'])
            ->where('empleado_id', $empleado->id)
            ->orderByDesc('fecha')->orderByDesc('id')
            ->get();
        $puedeAprobarMap = [];
        foreach ($solicitudes as $s) {
            $puedeAprobarMap[$s->id] = EmpleadoLegajoVisualizacion::puedeMutar(
                can('aprobar-solicitud-indumentaria', false) && $this->solicitudService->puedeAprobar($s, $usuarioId)
            );
        }

        $html = view('sueldos.empleado.partials.indumentaria', [
            'empleado' => $empleado,
            'config' => $config,
            'resumen' => $resumen,
            'entregas' => $entregas,
            'prendas' => $prendas,
            'prendasDotacion' => $prendasDotacion,
            'tallesEmpleado' => $tallesEmpleado,
            'tallesEmpleadoDetalle' => $tallesEmpleadoDetalle,
            'puedeEntregar' => EmpleadoLegajoVisualizacion::puedeMutar(can('entregar-prenda', false) && $config->estaCompleta()),
            'puedeTalles' => EmpleadoLegajoVisualizacion::puedeMutar(can('entregar-prenda', false)),
            'puedeAnular' => EmpleadoLegajoVisualizacion::puedeMutar(can('anular-entrega-prenda', false)),
            'puedeEditarEntrega' => EmpleadoLegajoVisualizacion::puedeMutar(
                can('editar-entrega-prenda', false) || can('editar-lineas-entrega-prenda', false)
            ),
            'solicitudes' => $solicitudes,
            'puedeAprobarMap' => $puedeAprobarMap,
            'puedeSolicitar' => EmpleadoLegajoVisualizacion::puedeMutar(can('crear-solicitud-indumentaria', false)),
            'tieneAprobacion' => $this->solicitudService->tieneAprobacion((int) ($empleado->empresa_id ?? 0), $empleado->agrupamiento_id ? (int) $empleado->agrupamiento_id : null),
            'tulegajoHabilitado' => $this->tuLegajo->habilitado(),
            'puedeTulegajo' => EmpleadoLegajoVisualizacion::puedeMutar(can('entregar-prenda', false)),
        ])->render();

        return response()->json(['html' => $html, 'mensaje' => $mensaje, 'mensaje_tipo' => $mensajeTipo]);
    }

    private function autorizarLectura(): void
    {
        if (EmpleadoLegajoVisualizacion::activa()) {
            can(EmpleadoLegajoVisualizacion::PERMISO);

            return;
        }
        if (! can('listar-entrega-prenda', false) && ! can('entregar-prenda', false) && ! can('actualizar-empleado-sueldos', false)
            && ! can('editar-entrega-prenda', false) && ! can('editar-lineas-entrega-prenda', false)
            && ! can('crear-solicitud-indumentaria', false) && ! can('aprobar-solicitud-indumentaria', false)) {
            abort(403);
        }
    }

    private function usuarioId(): ?int
    {
        $id = auth()->id();

        return $id !== null ? (int) $id : null;
    }
}
