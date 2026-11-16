<?php

namespace App\Support\Ai;

use App\Models\Ticket\Ticket;
use App\Support\Caja\ChequeCarteraAgingSupport;
use App\Support\Compras\CashPositionSupport;
use App\Support\Ticket\AdministracionTicketListadoFiltros;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Evalúa umbrales de cheques vencidos / deuda AP (prefs del usuario) para campanita.
 */
final class AiConsultaAlertaUmbralSupport
{
    public const CACHE_KEY_PREFIX = 'ai_alerta_umbral_enviada:';

    /**
     * @param  array{
     *   empresa_id?: int|null,
     *   notify_alerta_umbral?: bool,
     *   umbral_cheques_vencidos?: float|null,
     *   umbral_deuda_ap?: float|null
     * }  $prefs
     * @return array{
     *   disparar: bool,
     *   lineas: list<string>,
     *   cheques_monto: float,
     *   deuda_monto: float
     * }
     */
    public static function evaluar(array $prefs): array
    {
        $vacio = [
            'disparar' => false,
            'lineas' => [],
            'cheques_monto' => 0.0,
            'deuda_monto' => 0.0,
        ];

        if (! ($prefs['notify_alerta_umbral'] ?? false)) {
            return $vacio;
        }

        $umbralCheques = self::montoOpcional($prefs['umbral_cheques_vencidos'] ?? null);
        $umbralDeuda = self::montoOpcional($prefs['umbral_deuda_ap'] ?? null);
        $umbralTickets = self::enteroOpcional($prefs['umbral_tickets_abiertos'] ?? null);
        $umbralStock = self::enteroOpcional($prefs['umbral_stock_critico'] ?? null);
        if ($umbralCheques === null && $umbralDeuda === null
            && $umbralTickets === null && $umbralStock === null) {
            return $vacio;
        }

        $empresaId = isset($prefs['empresa_id']) && is_numeric($prefs['empresa_id'])
            ? (int) $prefs['empresa_id']
            : null;
        if ($empresaId !== null && $empresaId <= 0) {
            $empresaId = null;
        }

        $chequesMonto = 0.0;
        $deudaMonto = 0.0;
        $lineas = [];

        if ($umbralCheques !== null) {
            try {
                $resumen = ChequeCarteraAgingSupport::resumir($empresaId, date('Y-m-d'), []);
                $chequesMonto = (float) (($resumen['buckets']['vencido']['monto'] ?? 0));
                if ($chequesMonto > $umbralCheques) {
                    $cant = (int) ($resumen['buckets']['vencido']['cantidad'] ?? 0);
                    $lineas[] = 'Cheques vencidos: '.$cant
                        .' · $ '.number_format($chequesMonto, 2, ',', '.')
                        .' (umbral $ '.number_format($umbralCheques, 2, ',', '.').')';
                }
            } catch (Throwable) {
                // seguir
            }
        }

        if ($umbralDeuda !== null) {
            try {
                $cash = CashPositionSupport::resumir($empresaId);
                $deudaMonto = (float) ($cash['total_deuda'] ?? 0);
                if ($deudaMonto > $umbralDeuda) {
                    $lineas[] = 'Deuda AP vencida: $ '.number_format($deudaMonto, 2, ',', '.')
                        .' (umbral $ '.number_format($umbralDeuda, 2, ',', '.').')';
                }
            } catch (Throwable) {
                // seguir
            }
        }

        if ($umbralTickets !== null) {
            try {
                $cant = (int) Ticket::query()
                    ->whereIn('estado_ticket', AdministracionTicketListadoFiltros::ESTADOS_EN_CURSO)
                    ->count();
                if ($cant > $umbralTickets) {
                    $lineas[] = 'Tickets abiertos: '.$cant.' (umbral '.$umbralTickets.')';
                }
            } catch (Throwable) {
                // seguir
            }
        }

        if ($umbralStock !== null) {
            try {
                $stock = AiConsultaDecisionSupport::stockCritico([
                    'empresa_id' => $empresaId,
                    'max_lineas' => 200,
                    '_mcp_bridge' => true,
                ]);
                $cantStock = (int) ($stock['datos']['cantidad'] ?? 0);
                if ($cantStock > $umbralStock) {
                    $lineas[] = 'Insumos en stock crítico: '.$cantStock.' (umbral '.$umbralStock.')';
                }
            } catch (Throwable) {
                // seguir
            }
        }

        return [
            'disparar' => $lineas !== [],
            'lineas' => $lineas,
            'cheques_monto' => $chequesMonto,
            'deuda_monto' => $deudaMonto,
        ];
    }

    /**
     * Evalúa prefs del usuario en sesión; envía campanita 1×/día si cruza umbral.
     *
     * @return array{disparar: bool, lineas: list<string>}|null
     */
    public static function evaluarYAvisarUsuarioActual(): ?array
    {
        $uid = Auth::id() ? (int) Auth::id() : 0;
        if ($uid <= 0 || ! self::columnasListas()) {
            return null;
        }

        $prefs = AiConsultaOperativaPreferenciasSupport::leer();
        $eval = self::evaluar($prefs);
        if (! $eval['disparar']) {
            return $eval;
        }

        $cacheKey = self::CACHE_KEY_PREFIX.$uid.':'.date('Y-m-d');
        if (! Cache::has($cacheKey)) {
            AiConsultaNotificacionSupport::avisarAlertaUmbral($uid, $eval['lineas']);
            Cache::put($cacheKey, 1, now()->endOfDay());
        }

        return $eval;
    }

    /**
     * @return list<int>
     */
    public static function usuariosOptInAlerta(): array
    {
        if (! self::columnasListas()) {
            return [];
        }
        try {
            return \App\Models\Ai\AiCopilotoPreferencia::query()
                ->where('notify_alerta_umbral', true)
                ->where(function ($q) {
                    $q->where('umbral_cheques_vencidos', '>', 0)
                        ->orWhere('umbral_deuda_ap', '>', 0);
                    if (Schema::hasColumn('ai_copiloto_preferencia', 'umbral_tickets_abiertos')) {
                        $q->orWhere('umbral_tickets_abiertos', '>', 0)
                            ->orWhere('umbral_stock_critico', '>', 0);
                    }
                })
                ->orderBy('usuario_id')
                ->pluck('usuario_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    public static function columnasListas(): bool
    {
        try {
            return Schema::hasTable('ai_copiloto_preferencia')
                && Schema::hasColumn('ai_copiloto_preferencia', 'notify_alerta_umbral');
        } catch (Throwable) {
            return false;
        }
    }

    private static function montoOpcional(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (! is_numeric($v)) {
            return null;
        }
        $n = (float) $v;

        return $n > 0 ? $n : null;
    }

    private static function enteroOpcional(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (! is_numeric($v)) {
            return null;
        }
        $n = (int) $v;

        return $n > 0 ? $n : null;
    }
}
