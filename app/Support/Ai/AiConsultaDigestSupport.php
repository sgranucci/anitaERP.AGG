<?php

namespace App\Support\Ai;

use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Models\Ticket\Ticket;
use App\Support\Compras\CashPositionSupport;
use App\Support\Ticket\AdministracionTicketListadoFiltros;
use Throwable;

/**
 * Snippets livianos para digest mail (sin sesión / sin armar brief completo).
 */
final class AiConsultaDigestSupport
{
    /**
     * @return list<string>
     */
    public static function lineasResumen(?int $empresaId = null): array
    {
        $lineas = [];

        try {
            $cash = CashPositionSupport::resumir($empresaId && $empresaId > 0 ? $empresaId : null);
            $lineas[] = 'Cash: IB $ '.number_format((float) ($cash['total_saldos_ib'] ?? 0), 2, ',', '.')
                .' · deuda venc. $ '.number_format((float) ($cash['total_deuda'] ?? 0), 2, ',', '.');
        } catch (Throwable) {
            // seguir
        }

        try {
            $abiertos = (int) Ticket::query()
                ->whereIn('estado_ticket', AdministracionTicketListadoFiltros::ESTADOS_EN_CURSO)
                ->count();
            $lineas[] = 'Tickets abiertos: '.$abiertos;
        } catch (Throwable) {
            // seguir
        }

        try {
            $periodo = date('Ym');
            $q = Liquidacion_Sueldos::query()->where('periodo', $periodo);
            if ($empresaId && $empresaId > 0) {
                $q->where('empresa_id', $empresaId);
            }
            $cant = (int) $q->count();
            if ($cant > 0) {
                $lineas[] = 'Liquidaciones '.$periodo.': '.$cant.' corrida(s)';
            }
        } catch (Throwable) {
            // seguir
        }

        return array_slice($lineas, 0, 4);
    }
}
