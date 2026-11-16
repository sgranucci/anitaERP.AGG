<?php

namespace App\Support\Ai;

use App\Models\Ai\AiDecision;
use App\Models\Compras\PropuestaPago;
use App\Models\Stock\Recepcion_Proveedor;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use App\Services\Caja\ChequeCaucionService;
use App\Services\Caja\ChequeDepositoService;
use App\Services\Compras\PropuestaPagoService;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Services\Stock\RecepcionProveedorService;
use App\Services\Stock\TransferenciaMercaderiaService;
use App\Support\Compras\PropuestaPagoModoSupport;
use App\Support\Compras\PropuestaPagoTipoLote;
use App\Support\Stock\RecepcionProveedorEstados;
use App\Support\Stock\TransferenciaMercaderiaEstados;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Throwable;

/**
 * Acciones HITL disparables desde el chat (evento / depósito / caución de cheques).
 */
final class AiConsultaHitlAccionSupport
{
    /**
     * @return array{ok: bool, message?: string, evento_id?: int, estado?: string}
     */
    public static function transicionarEvento(string $accion, int $eventoId, ?int $usuarioId = null): array
    {
        if (! can('listar-ai-decisiones', false)) {
            return ['ok' => false, 'message' => 'Sin permiso para la cola HITL.'];
        }
        if ($eventoId <= 0) {
            return ['ok' => false, 'message' => 'Evento inválido.'];
        }

        $accion = strtolower(trim($accion));
        try {
            $evento = match ($accion) {
                'visto' => AiAgenteEventoHitlSupport::marcarVisto($eventoId, $usuarioId),
                'descartar', 'descartado' => AiAgenteEventoHitlSupport::descartar($eventoId, $usuarioId),
                'resolver', 'resuelto' => AiAgenteEventoHitlSupport::resolver($eventoId, $usuarioId),
                default => throw new InvalidArgumentException('Acción HITL no soportada: '.$accion),
            };
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo actualizar el evento: '.$e->getMessage()];
        }

        return [
            'ok' => true,
            'message' => 'Evento #'.$evento->id.' → '.$evento->estado,
            'evento_id' => (int) $evento->id,
            'estado' => (string) $evento->estado,
        ];
    }

    /**
     * @param  list<int|string>  $chequeIds
     * @return array{ok: bool, message?: string, depositados?: int, errores?: list<string>}
     */
    public static function depositarCheques(
        array $chequeIds,
        int|string|null $cuentacajaRef,
        ?string $fecha = null,
        ?string $nroBoleta = null,
        ?int $decisionId = null,
    ): array {
        if (! can('editar-cheque', false)) {
            return ['ok' => false, 'message' => 'Sin permiso para depositar cheques.'];
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', $chequeIds),
            static fn (int $id) => $id > 0
        )));
        if ($ids === []) {
            return ['ok' => false, 'message' => 'Seleccione al menos un cheque.'];
        }

        $cuentaId = self::resolverCuentacajaId($cuentacajaRef);
        if ($cuentaId <= 0) {
            return ['ok' => false, 'message' => 'Indique una cuenta de caja válida (código o ID).'];
        }

        try {
            /** @var ChequeDepositoService $svc */
            $svc = app(ChequeDepositoService::class);
            $resultado = $svc->depositarMasivo($ids, $cuentaId, $fecha, $nroBoleta);
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Error al depositar: '.$e->getMessage()];
        }

        $ok = (int) ($resultado['ok'] ?? 0);
        $errores = [];
        foreach (($resultado['detalle'] ?? []) as $d) {
            if (is_array($d) && empty($d['ok']) && ! empty($d['error'])) {
                $errores[] = 'Cheque '.($d['cheque_id'] ?? '?').': '.$d['error'];
            }
        }

        self::cerrarDecision(
            $decisionId,
            $ok > 0 ? AiDecision::ACCION_CONFIRMADA : AiDecision::ACCION_DESCARTADA,
            ['depositados' => $ok, 'cheque_ids' => $ids, 'cuentacaja_id' => $cuentaId]
        );

        $msg = $ok > 0
            ? 'Depositados: '.$ok.' cheque(s).'
            : 'No se depositó ningún cheque.';
        if ($errores !== []) {
            $msg .= ' '.implode(' ', array_slice($errores, 0, 3));
        }

        return [
            'ok' => $ok > 0,
            'message' => $msg,
            'depositados' => $ok,
            'errores' => $errores,
        ];
    }

    /**
     * @param  list<int|string>  $chequeIds
     * @return array{ok: bool, message?: string, caucionados?: int, errores?: list<string>}
     */
    public static function caucionarCheques(
        array $chequeIds,
        string $nroCaucion,
        ?string $fecha = null,
        ?int $decisionId = null,
    ): array {
        if (! can('editar-cheque', false)) {
            return ['ok' => false, 'message' => 'Sin permiso para caucionar cheques.'];
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', $chequeIds),
            static fn (int $id) => $id > 0
        )));
        if ($ids === []) {
            return ['ok' => false, 'message' => 'Seleccione al menos un cheque.'];
        }

        $nro = trim($nroCaucion);
        if ($nro === '' || $nro === '0') {
            return ['ok' => false, 'message' => 'Indique el número de caución.'];
        }

        try {
            /** @var ChequeCaucionService $svc */
            $svc = app(ChequeCaucionService::class);
            $resultado = $svc->caucionarMasivo($ids, $nro, $fecha);
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Error al caucionar: '.$e->getMessage()];
        }

        $ok = (int) ($resultado['ok'] ?? 0);
        $errores = [];
        foreach (($resultado['detalle'] ?? []) as $d) {
            if (is_array($d) && empty($d['ok']) && ! empty($d['error'])) {
                $errores[] = 'Cheque '.($d['cheque_id'] ?? '?').': '.$d['error'];
            }
        }

        self::cerrarDecision(
            $decisionId,
            $ok > 0 ? AiDecision::ACCION_CONFIRMADA : AiDecision::ACCION_DESCARTADA,
            ['caucionados' => $ok, 'cheque_ids' => $ids, 'nro_caucion' => $nro]
        );

        $msg = $ok > 0
            ? 'Caucionados: '.$ok.' cheque(s) (nro '.$nro.').'
            : 'No se caucionó ningún cheque.';
        if ($errores !== []) {
            $msg .= ' '.implode(' ', array_slice($errores, 0, 3));
        }

        return [
            'ok' => $ok > 0,
            'message' => $msg,
            'caucionados' => $ok,
            'errores' => $errores,
        ];
    }

    /**
     * Confirma recepciones COM en BORRADOR (una a una vía RecepcionProveedorService).
     *
     * @param  list<int|string>  $recepcionIds
     * @return array{ok: bool, message?: string, confirmadas?: int, errores?: list<string>}
     */
    public static function confirmarRecepciones(array $recepcionIds, ?int $decisionId = null): array
    {
        if (! can('confirmar-recepcion-proveedor', false)) {
            return ['ok' => false, 'message' => 'Sin permiso para confirmar recepciones.'];
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', $recepcionIds),
            static fn (int $id) => $id > 0
        )));
        if ($ids === []) {
            return ['ok' => false, 'message' => 'Seleccione al menos una recepción.'];
        }

        /** @var RecepcionProveedorService $svc */
        $svc = app(RecepcionProveedorService::class);
        $ok = 0;
        $errores = [];
        foreach ($ids as $id) {
            try {
                $r = Recepcion_Proveedor::query()->find($id);
                if (! $r || $r->estado !== RecepcionProveedorEstados::BORRADOR) {
                    $errores[] = 'Recepción '.$id.': no está en BORRADOR.';
                    continue;
                }
                $svc->confirmar($id);
                $ok++;
            } catch (Throwable $e) {
                $errores[] = 'Recepción '.$id.': '.$e->getMessage();
            }
        }

        self::cerrarDecision(
            $decisionId,
            $ok > 0 ? AiDecision::ACCION_CONFIRMADA : AiDecision::ACCION_DESCARTADA,
            ['confirmadas' => $ok, 'recepcion_ids' => $ids]
        );

        $msg = $ok > 0
            ? 'Confirmadas: '.$ok.' recepción(es).'
            : 'No se confirmó ninguna recepción.';
        if ($errores !== []) {
            $msg .= ' '.implode(' ', array_slice($errores, 0, 3));
        }

        return [
            'ok' => $ok > 0,
            'message' => $msg,
            'confirmadas' => $ok,
            'errores' => $errores,
        ];
    }

    /**
     * Elimina recepciones COM en BORRADOR (AGG no anula; borradores se borran).
     *
     * @param  list<int|string>  $recepcionIds
     * @return array{ok: bool, message?: string, eliminadas?: int, errores?: list<string>}
     */
    public static function eliminarRecepcionesBorrador(array $recepcionIds, ?int $decisionId = null): array
    {
        if (! can('borrar-recepcion-proveedor', false)) {
            return ['ok' => false, 'message' => 'Sin permiso para eliminar recepciones.'];
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', $recepcionIds),
            static fn (int $id) => $id > 0
        )));
        if ($ids === []) {
            return ['ok' => false, 'message' => 'Seleccione al menos una recepción.'];
        }

        /** @var RecepcionProveedorService $svc */
        $svc = app(RecepcionProveedorService::class);
        $ok = 0;
        $errores = [];
        foreach ($ids as $id) {
            try {
                $r = Recepcion_Proveedor::query()->find($id);
                if (! $r || $r->estado !== RecepcionProveedorEstados::BORRADOR) {
                    $errores[] = 'Recepción '.$id.': no está en BORRADOR.';
                    continue;
                }
                $svc->eliminarBorrador($id);
                $ok++;
            } catch (Throwable $e) {
                $errores[] = 'Recepción '.$id.': '.$e->getMessage();
            }
        }

        self::cerrarDecision(
            $decisionId,
            $ok > 0 ? AiDecision::ACCION_CONFIRMADA : AiDecision::ACCION_DESCARTADA,
            ['eliminadas' => $ok, 'recepcion_ids' => $ids, 'accion_hitl' => 'eliminar_borrador_com']
        );

        $msg = $ok > 0
            ? 'Eliminadas: '.$ok.' recepción(es) en borrador.'
            : 'No se eliminó ninguna recepción.';
        if ($errores !== []) {
            $msg .= ' '.implode(' ', array_slice($errores, 0, 3));
        }

        return [
            'ok' => $ok > 0,
            'message' => $msg,
            'eliminadas' => $ok,
            'errores' => $errores,
        ];
    }

    /**
     * Aprueba recepción de TM pendientes.
     *
     * @param  list<int|string>  $transferenciaIds
     * @return array{ok: bool, message?: string, confirmadas?: int, errores?: list<string>}
     */
    public static function confirmarTransferenciasPendientes(array $transferenciaIds, ?int $decisionId = null): array
    {
        if (! can('aprobar-transferencia-mercaderia', false)) {
            return ['ok' => false, 'message' => 'Sin permiso para aprobar transferencias.'];
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', $transferenciaIds),
            static fn (int $id) => $id > 0
        )));
        if ($ids === []) {
            return ['ok' => false, 'message' => 'Seleccione al menos una transferencia.'];
        }

        /** @var TransferenciaMercaderiaService $svc */
        $svc = app(TransferenciaMercaderiaService::class);
        $ok = 0;
        $errores = [];
        foreach ($ids as $id) {
            try {
                $t = Transferencia_Mercaderia::query()->find($id);
                if (! $t || $t->estado !== TransferenciaMercaderiaEstados::PENDIENTE_RECEPCION) {
                    $errores[] = 'TM '.$id.': no está pendiente de recepción.';
                    continue;
                }
                $svc->aprobarRecepcion($id);
                $ok++;
            } catch (Throwable $e) {
                $errores[] = 'TM '.$id.': '.$e->getMessage();
            }
        }

        self::cerrarDecision(
            $decisionId,
            $ok > 0 ? AiDecision::ACCION_CONFIRMADA : AiDecision::ACCION_DESCARTADA,
            ['confirmadas' => $ok, 'transferencia_ids' => $ids, 'accion_hitl' => 'confirmar_tm']
        );

        $msg = $ok > 0
            ? 'Confirmadas: '.$ok.' transferencia(s).'
            : 'No se confirmó ninguna transferencia.';
        if ($errores !== []) {
            $msg .= ' '.implode(' ', array_slice($errores, 0, 3));
        }

        return [
            'ok' => $ok > 0,
            'message' => $msg,
            'confirmadas' => $ok,
            'errores' => $errores,
        ];
    }

    /**
     * Crea propuesta de pago en BORRADOR con deuda vencida (tipo LEGACY por vencimiento).
     *
     * @return array{ok: bool, message?: string, propuesta_pago_id?: int, url?: string}
     */
    public static function crearBorradorPropuestaPago(
        int $empresaId,
        ?string $fechaHasta = null,
        ?int $decisionId = null,
    ): array {
        if (! can('crear-propuesta-pago', false)) {
            return ['ok' => false, 'message' => 'Sin permiso para crear propuestas de pago.'];
        }
        if ($empresaId <= 0) {
            return ['ok' => false, 'message' => 'Indique la empresa (preferencias del FAB o parámetro).'];
        }

        $hasta = $fechaHasta && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta) === 1
            ? $fechaHasta
            : date('Y-m-d');

        $request = Request::create('/', 'POST', [
            'empresa_id' => $empresaId,
            'fecha' => date('Y-m-d'),
            'tipo_lote' => PropuestaPagoTipoLote::LEGACY,
            'fecha_vencimiento_hasta' => $hasta,
            'detalle' => '[IA] Borrador copiloto — deuda vencida hasta '.$hasta,
        ]);

        try {
            /** @var PropuestaPagoService $svc */
            $svc = app(PropuestaPagoService::class);
            $resultado = $svc->guardar($request);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Error al crear borrador: '.$e->getMessage()];
        }

        if (! empty($resultado['errores'])) {
            return ['ok' => false, 'message' => (string) $resultado['errores']];
        }

        $id = (int) ($resultado['propuesta_pago_id'] ?? 0);
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'No se obtuvo el ID de la propuesta.'];
        }

        $url = route('editar_propuesta_pago', $id).'?origen=modal_consulta&vista=consulta';
        self::cerrarDecision($decisionId, AiDecision::ACCION_CONFIRMADA, [
            'propuesta_pago_id' => $id,
            'empresa_id' => $empresaId,
            'fecha_vencimiento_hasta' => $hasta,
        ]);

        $puedeEnviar = can('enviar-aprobacion-propuesta-pago', false);
        $exigeArbol = PropuestaPagoModoSupport::exigeArbol($empresaId);

        return [
            'ok' => true,
            'message' => 'Propuesta #'.$id.' creada en BORRADOR (deuda con vencimiento ≤ '.$hasta.'). Revisá y enviá a aprobación.',
            'propuesta_pago_id' => $id,
            'url' => $url,
            'enviar_aprobacion' => [
                'puede' => $puedeEnviar,
                'propuesta_pago_id' => $id,
                'exige_arbol' => $exigeArbol,
                'url' => $url,
            ],
        ];
    }

    /**
     * Envía propuesta BORRADOR/RECHAZADA al árbol (o autoriza en modo light).
     * No ejecuta OP ni firma niveles del árbol por el usuario.
     *
     * @return array{ok: bool, message?: string, propuesta_pago_id?: int, url?: string, estado?: string}
     */
    public static function enviarAprobacionPropuestaPago(int $propuestaPagoId, ?int $decisionId = null): array
    {
        if (! can('enviar-aprobacion-propuesta-pago', false)) {
            return ['ok' => false, 'message' => 'Sin permiso para enviar propuestas a aprobación.'];
        }
        if ($propuestaPagoId <= 0) {
            return ['ok' => false, 'message' => 'Indique el ID de la propuesta.'];
        }

        try {
            /** @var PropuestaPagoService $svc */
            $svc = app(PropuestaPagoService::class);
            $resultado = $svc->enviarAprobacion($propuestaPagoId);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Error al enviar: '.$e->getMessage()];
        }

        if (! ($resultado['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string) ($resultado['mensaje'] ?? 'No se pudo enviar.')];
        }

        $url = route('editar_propuesta_pago', $propuestaPagoId).'?origen=modal_consulta&vista=consulta';
        $estado = null;
        try {
            $estado = (string) (PropuestaPago::query()->whereKey($propuestaPagoId)->value('estado') ?? '');
        } catch (Throwable) {
            $estado = null;
        }

        self::cerrarDecision($decisionId, AiDecision::ACCION_CONFIRMADA, [
            'propuesta_pago_id' => $propuestaPagoId,
            'accion_hitl' => 'enviar_aprobacion',
            'estado' => $estado,
        ]);

        return [
            'ok' => true,
            'message' => (string) ($resultado['mensaje'] ?? 'Propuesta enviada.'),
            'propuesta_pago_id' => $propuestaPagoId,
            'url' => $url,
            'estado' => $estado ?: null,
        ];
    }

    /**
     * @param  array<string,mixed>  $extra
     */
    private static function cerrarDecision(?int $decisionId, string $accion, array $extra = []): void
    {
        if ($decisionId === null || $decisionId <= 0) {
            return;
        }
        try {
            $decision = AiDecision::query()->find($decisionId);
            if (! $decision) {
                return;
            }
            $payload = is_array($decision->payload) ? $decision->payload : [];
            $payload['confirmacion'] = $extra;
            $payload['origen'] = $payload['origen'] ?? 'fab';
            $payload['canal'] = 'fab_hitl';
            $decision->accion = $accion;
            $decision->payload = $payload;
            $decision->resuelto_por = Auth::id();
            $decision->resuelto_at = now();
            $decision->save();
        } catch (Throwable) {
            // no interrumpir acción
        }
    }

    private static function resolverCuentacajaId(int|string|null $ref): int
    {
        if ($ref === null || $ref === '') {
            return 0;
        }
        if (is_numeric($ref) && (int) $ref > 0 && ! preg_match('/[A-Za-z]/', (string) $ref)) {
            // ID numérico puro
            return (int) $ref;
        }
        $codigo = trim((string) $ref);
        if ($codigo === '') {
            return 0;
        }
        try {
            /** @var CuentacajaRepositoryInterface $repo */
            $repo = app(CuentacajaRepositoryInterface::class);
            $cuenta = $repo->findPorCodigo($codigo);

            return $cuenta ? (int) $cuenta->id : 0;
        } catch (Throwable) {
            return 0;
        }
    }
}
