<?php

namespace App\Support\Caja;

use App\ApiAnita;
use Illuminate\Support\Facades\Log;

/**
 * Alinea el número de cobranza (pag_rec / numerotransaccion) con la serie operativa de Anita.
 *
 * En Ferli la serie viva está en che_ban.pago (pag_tipo=COB); el ERP vacío arrancaba en 1
 * y pisaba/duplicaba recibos. Se toma MAX(pag_rec) reciente y se combina con el MAX del ERP.
 */
final class CobranzaAnitaNumeracionSupport
{
    public static function estaHabilitada(): bool
    {
        return filter_var(
            config('cobranza.anita_numeracion_habilitada', true),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * Tipos de pago Anita cuya serie debe pisar el MAX ERP (abreviatura tipotransaccion_caja).
     *
     * @return list<string>
     */
    public static function tiposPagoAlineados(): array
    {
        $raw = config('cobranza.anita_numeracion_tipos_pago', ['COB']);
        if (! is_array($raw)) {
            $raw = explode(',', (string) $raw);
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($t) => strtoupper(trim((string) $t)),
            $raw
        ), static fn (string $t) => $t !== '')));
    }

    public static function usaAlineacionAnita(int $tipotransaccionCajaId): bool
    {
        if (! self::estaHabilitada()) {
            return false;
        }

        $abrev = IngresoEgresoAnitaNumeracionSupport::abreviaturaTipo($tipotransaccionCajaId);

        return $abrev !== '' && in_array($abrev, self::tiposPagoAlineados(), true);
    }

    /**
     * Máximo pag_rec operativo en Anita para el tipo (excluye series históricas ddmmyy).
     */
    public static function maximoPagRec(string $tipoPago = 'COB'): int
    {
        $tipo = strtoupper(trim($tipoPago));
        if ($tipo === '') {
            return 0;
        }

        $fechaDesde = (int) config('cobranza.anita_numeracion_fecha_desde', 20200101);
        $pagRecMax = (int) config('cobranza.anita_numeracion_pag_rec_max', 499999);
        if ($fechaDesde <= 0) {
            $fechaDesde = 20200101;
        }
        if ($pagRecMax <= 0) {
            $pagRecMax = 499999;
        }

        try {
            $api = new ApiAnita;
            $raw = $api->apiCall([
                'acc' => 'list',
                'sistema' => 'che_ban',
                'tabla' => 'pago',
                'campos' => 'MAX(pag_rec) as max_rec',
                'whereArmado' => ' WHERE pag_tipo = '.self::escSql($tipo)
                    .' AND pag_fecha >= '.$fechaDesde
                    .' AND pag_rec > 0'
                    .' AND pag_rec < '.$pagRecMax,
            ]);

            $err = ApiAnita::extraerMensajeError($raw);
            if ($err !== null) {
                Log::warning('cobranza.anita_numeracion.max_fail', [
                    'tipo' => $tipo,
                    'error' => $err,
                ]);

                return 0;
            }

            $fila = ApiAnita::primeraFilaLista((string) $raw);
            if ($fila === null) {
                $decoded = json_decode((string) $raw, true);
                if (is_array($decoded) && isset($decoded[0]['max_rec'])) {
                    return max(0, (int) $decoded[0]['max_rec']);
                }

                return 0;
            }

            return max(0, (int) ($fila->max_rec ?? 0));
        } catch (\Throwable $e) {
            Log::warning('cobranza.anita_numeracion.max_exception', [
                'tipo' => $tipo,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    public static function maximoParaTipoCaja(int $tipotransaccionCajaId): int
    {
        if (! self::usaAlineacionAnita($tipotransaccionCajaId)) {
            return 0;
        }

        $abrev = IngresoEgresoAnitaNumeracionSupport::abreviaturaTipo($tipotransaccionCajaId);

        return self::maximoPagRec($abrev);
    }

    private static function escSql(string $valor): string
    {
        return "'".str_replace("'", "''", $valor)."'";
    }
}
