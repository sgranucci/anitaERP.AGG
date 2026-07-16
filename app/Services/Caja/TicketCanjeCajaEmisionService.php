<?php

namespace App\Services\Caja;

use App\Models\Caja\ClienteVipCaja;
use App\Models\Caja\TicketCanjeCaja;
use App\Repositories\Caja\ClienteVipCajaRepositoryInterface;
use App\Repositories\Caja\TicketCanjeCajaRepositoryInterface;
use App\Services\Caja\Estacionamiento\EstacionamientoPvService;
use App\Services\Caja\Estacionamiento\EstacionamientoTurnoOperativoService;
use App\Services\Caja\Estacionamiento\JornadaEstacionamientoService;
use App\Support\Caja\Estacionamiento\EstacionamientoIdentificadorPc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Emisión de vales canje caja (por empresa). VIP → monto ticket 0 sin impresión.
 */
final class TicketCanjeCajaEmisionService
{
    public function __construct(
        private readonly TicketCanjeCajaRepositoryInterface $repository,
        private readonly ClienteVipCajaRepositoryInterface $clienteVipRepository,
        private readonly EstacionamientoPvService $pvService,
        private readonly EstacionamientoTurnoOperativoService $turnoOperativoService,
        private readonly JornadaEstacionamientoService $jornadaService,
        private readonly TicketCanjeCajaImpresionService $impresionService,
    ) {
    }

    /**
     * Contexto operativo por empresa + PC (jornada abierta; cajero informativo si hay turno).
     *
     * @return array{
     *   ok:bool,
     *   error?:string,
     *   empresa_id?:int,
     *   identificador_pc?:string,
     *   jornada_abierta?:bool,
     *   fecha_jornada?:?string,
     *   fecha_jornada_fmt?:?string,
     *   turno_habilitado?:bool,
     *   turno_operativo_id?:?int,
     *   cajero_id?:?int,
     *   cajero_nombre?:?string,
     *   porcentaje_ticket?:float
     * }
     */
    public function contextoOperativo(Request $request, int $empresaId): array
    {
        if ($empresaId <= 0) {
            return ['ok' => false, 'error' => 'Debe indicar la empresa.'];
        }

        $pc = EstacionamientoIdentificadorPc::resolver($request);
        $jornadaEstado = $this->jornadaService->estadoParaEmpresa($empresaId);
        $cfg = $this->pvService->resolverConfiguracionPv($request, $empresaId);
        $activo = $this->turnoOperativoService->turnoHabilitadoEnPc($pc, $empresaId);

        $jornadaAbierta = ! empty($jornadaEstado['jornada_abierta']);
        $fechaJornada = $jornadaEstado['fecha_jornada']
            ?? $activo?->jornada?->fecha_jornada?->format('Y-m-d');

        return [
            'ok' => true,
            'empresa_id' => $empresaId,
            'identificador_pc' => $pc,
            'jornada_abierta' => $jornadaAbierta,
            'fecha_jornada' => $fechaJornada,
            'fecha_jornada_fmt' => $fechaJornada
                ? date('d/m/Y', strtotime((string) $fechaJornada))
                : null,
            'turno_habilitado' => $activo !== null,
            'turno_operativo_id' => $activo?->id,
            'cajero_id' => $activo?->usuario_habilitado_id ? (int) $activo->usuario_habilitado_id : null,
            'cajero_nombre' => $activo?->usuarioHabilitado?->nombre,
            'porcentaje_ticket' => (float) config('caja.ticket_canje_porcentaje', 5),
            'cfg_pv' => $cfg !== null,
        ];
    }

    /**
     * Exige solo jornada abierta (la emisión de canjes no requiere turno habilitado).
     *
     * @return array<string, mixed>
     */
    public function exigirContextoOperativo(Request $request, int $empresaId): array
    {
        $ctx = $this->contextoOperativo($request, $empresaId);
        if (! ($ctx['ok'] ?? false)) {
            throw new InvalidArgumentException($ctx['error'] ?? 'Contexto operativo inválido.');
        }
        if (empty($ctx['jornada_abierta']) || empty($ctx['fecha_jornada'])) {
            throw new InvalidArgumentException('Debe abrir la jornada de estacionamiento para esta empresa antes de operar.');
        }

        return $ctx;
    }

    /**
     * @return array{es_vip:bool,cliente_vip_caja_id:?int,nombre_cliente:?string,nro_documento:string}
     */
    public function resolverCliente(int $empresaId, string $nroDocumento, ?int $clienteVipCajaId = null): array
    {
        if ($clienteVipCajaId !== null && $clienteVipCajaId > 0) {
            $vipPorId = $this->clienteVipRepository->findPorIdYEmpresa($clienteVipCajaId, $empresaId);
            if ($vipPorId instanceof ClienteVipCaja) {
                return [
                    'es_vip' => true,
                    'cliente_vip_caja_id' => (int) $vipPorId->id,
                    'nombre_cliente' => $vipPorId->nombreCompleto(),
                    'nro_documento' => preg_replace('/\D+/', '', trim((string) $vipPorId->nrodocumento)) ?: (string) $vipPorId->nrodocumento,
                ];
            }
        }

        $doc = preg_replace('/\D+/', '', trim($nroDocumento)) ?? '';
        if ($doc === '') {
            throw new InvalidArgumentException('Debe indicar el número de documento.');
        }

        $vip = $this->clienteVipRepository->findPorDocumento($empresaId, $doc);
        if ($vip instanceof ClienteVipCaja) {
            return [
                'es_vip' => true,
                'cliente_vip_caja_id' => (int) $vip->id,
                'nombre_cliente' => $vip->nombreCompleto(),
                'nro_documento' => preg_replace('/\D+/', '', trim((string) $vip->nrodocumento)) ?: $doc,
            ];
        }

        return [
            'es_vip' => false,
            'cliente_vip_caja_id' => null,
            'nombre_cliente' => null,
            'nro_documento' => $doc,
        ];
    }

    /**
     * Preview de montos (sin grabar).
     *
     * @return array{
     *   es_vip:bool,
     *   nombre_cliente:?string,
     *   nro_documento:string,
     *   monto_venta:float,
     *   monto_ticket_total:float,
     *   cantidad:int,
     *   monto_por_ticket:float,
     *   imprime:bool
     * }
     */
    public function preview(
        int $empresaId,
        string $nroDocumento,
        float $montoVenta,
        int $cantidad,
        ?int $clienteVipCajaId = null,
    ): array {
        if ($montoVenta <= 0) {
            throw new InvalidArgumentException('El monto de venta debe ser mayor a cero.');
        }
        if ($cantidad < 1) {
            throw new InvalidArgumentException('La cantidad de tickets debe ser al menos 1.');
        }

        $cli = $this->resolverCliente($empresaId, $nroDocumento, $clienteVipCajaId);
        $porcentaje = (float) config('caja.ticket_canje_porcentaje', 5);
        $montoTicketTotal = $cli['es_vip']
            ? 0.0
            : round($montoVenta * $porcentaje / 100.0, 2);
        $montoPorTicket = $cantidad > 0
            ? round($montoTicketTotal / $cantidad, 2)
            : 0.0;

        return [
            ...$cli,
            'monto_venta' => round($montoVenta, 2),
            'monto_ticket_total' => $montoTicketTotal,
            'cantidad' => $cantidad,
            'monto_por_ticket' => $montoPorTicket,
            'imprime' => ! $cli['es_vip'] && $montoPorTicket > 0,
        ];
    }

    /**
     * Emite tickets para una empresa. VIP → monto_ticket=0 sin imprimir.
     *
     * @return array{ok:bool,movimiento_id:int,tickets:list<array<string,mixed>>,impresiones:list<array<string,mixed>>,mensaje:string}
     */
    public function emitir(
        Request $request,
        int $empresaId,
        string $nroDocumento,
        float $montoVenta,
        int $cantidad,
        ?int $clienteVipCajaId = null,
    ): array {
        $ctx = $this->exigirContextoOperativo($request, $empresaId);

        $prev = $this->preview($empresaId, $nroDocumento, $montoVenta, $cantidad, $clienteVipCajaId);
        $usuarioId = (int) (Auth::id() ?? 0);
        if ($usuarioId <= 0) {
            throw new InvalidArgumentException('Usuario no autenticado.');
        }

        $tickets = DB::transaction(function () use ($empresaId, $prev, $ctx, $usuarioId) {
            $movimientoId = $this->repository->siguienteMovimientoId($empresaId);
            $creados = [];
            $montoVentaUnitario = round($prev['monto_venta'] / $prev['cantidad'], 2);

            for ($i = 1; $i <= $prev['cantidad']; $i++) {
                $ticket = $this->repository->create([
                    'empresa_id' => $empresaId,
                    'movimiento_id' => $movimientoId,
                    'numero_ticket' => $i,
                    'fecha' => $ctx['fecha_jornada'],
                    'nro_documento' => $prev['nro_documento'],
                    'nombre_cliente' => $prev['nombre_cliente'],
                    'es_vip' => $prev['es_vip'],
                    'cliente_vip_caja_id' => $prev['cliente_vip_caja_id'],
                    'monto_venta' => $montoVentaUnitario,
                    'monto_ticket' => $prev['monto_por_ticket'],
                    'estado' => $prev['es_vip']
                        ? TicketCanjeCaja::ESTADO_VIP
                        : TicketCanjeCaja::ESTADO_PENDIENTE,
                    'usuario_id' => $usuarioId,
                    'cajero_id' => $ctx['cajero_id'],
                    'turno_operativo_estacionamiento_id' => $ctx['turno_operativo_id'],
                    'identificador_pc' => $ctx['identificador_pc'],
                    'numerocupon' => sprintf('%d-%d', $movimientoId, $i),
                ]);
                $creados[] = $ticket;
            }

            return $creados;
        });

        $impresiones = [];
        if ($prev['imprime']) {
            foreach ($tickets as $ticket) {
                $impresiones[] = $this->impresionService->imprimir($ticket, $request, 'Original');
            }
        }

        $movimientoId = (int) ($tickets[0]->movimiento_id ?? 0);
        $mensaje = $prev['es_vip']
            ? 'Ticket VIP grabado (monto 0). No se imprime.'
            : ('Se emitieron '.$prev['cantidad'].' ticket(s) del movimiento '.$movimientoId.'.');

        return [
            'ok' => true,
            'movimiento_id' => $movimientoId,
            'tickets' => array_map(fn (TicketCanjeCaja $t) => $this->ticketAArray($t), $tickets),
            'impresiones' => $impresiones,
            'mensaje' => $mensaje,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ticketAArray(TicketCanjeCaja $t): array
    {
        return [
            'id' => (int) $t->id,
            'empresa_id' => (int) $t->empresa_id,
            'movimiento_id' => (int) $t->movimiento_id,
            'numero_ticket' => (int) $t->numero_ticket,
            'etiqueta' => $t->etiquetaVale(),
            'fecha' => $t->fecha?->format('Y-m-d'),
            'nro_documento' => $t->nro_documento,
            'nombre_cliente' => $t->nombre_cliente,
            'es_vip' => (bool) $t->es_vip,
            'monto_venta' => (float) $t->monto_venta,
            'monto_ticket' => (float) $t->monto_ticket,
            'estado' => $t->estado,
        ];
    }
}
