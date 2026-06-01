<?php

namespace App\Support\Ventas\Gastronomia;

use App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport;

/**
 * Clasifica movimientos Waitry del cierre de jornada en grupos de negocio.
 */
final class CierreJornadaProcesoClasificacionSupport
{
    public const GRUPO_FACTURADO_MEDIO_REAL = 'facturado_medio_real';

    public const GRUPO_FACTURADO_TOTEM = 'facturado_totem';

    public const GRUPO_SIN_FACTURAR_QR = 'sin_facturar_qr';

    public const GRUPO_SIN_FACTURAR_OTRO = 'sin_facturar_otro';

    public const GRUPO_WAITRY_CASH_NO_FACTURAR = 'waitry_cash_sin_facturar';

    public const GRUPO_HUECO_AUDITORIA = 'hueco_auditoria';

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return array{
     *   movimientos: list<array<string, mixed>>,
     *   grupos: array<string, list<array<string, mixed>>>,
     *   conteos: array<string, int>,
     *   grilla: array<string, float>,
     *   total_facturacion: float
     * }
     */
    public static function clasificar(array $movimientos, int $empresaId): array
    {
        $grupos = [
            self::GRUPO_FACTURADO_MEDIO_REAL => [],
            self::GRUPO_FACTURADO_TOTEM => [],
            self::GRUPO_SIN_FACTURAR_QR => [],
            self::GRUPO_SIN_FACTURAR_OTRO => [],
            self::GRUPO_WAITRY_CASH_NO_FACTURAR => [],
            self::GRUPO_HUECO_AUDITORIA => [],
        ];

        $grilla = [
            'qr_sin_facturar' => 0.0,
            'qr_facturado_anita' => 0.0,
            'mp_facturado_anita' => 0.0,
            'efectivo_facturado_anita' => 0.0,
        ];
        $totalFacturacion = 0.0;

        $enriquecidos = [];
        foreach ($movimientos as $mov) {
            $m = self::enriquecerMovimiento($mov, $empresaId);
            $grupo = (string) ($m['grupo'] ?? self::GRUPO_SIN_FACTURAR_OTRO);
            if (! isset($grupos[$grupo])) {
                $grupos[$grupo] = [];
            }
            $grupos[$grupo][] = $m;
            $enriquecidos[] = $m;

            $total = (float) ($m['total'] ?? 0);
            if (! empty($m['facturada_erp'])) {
                $totalFacturacion += $total;
                $claveMedio = (string) ($m['medio_anita_clave'] ?? '');
                if ($claveMedio === CierreJornadaProcesoMedioSupport::CLAVE_QR) {
                    $grilla['qr_facturado_anita'] += $total;
                } elseif ($claveMedio === CierreJornadaProcesoMedioSupport::CLAVE_MP) {
                    $grilla['mp_facturado_anita'] += $total;
                } elseif ($claveMedio === CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO) {
                    $grilla['efectivo_facturado_anita'] += $total;
                }
            } elseif ($grupo === self::GRUPO_SIN_FACTURAR_QR) {
                $grilla['qr_sin_facturar'] += $total;
            }
        }

        foreach ($grilla as $k => $v) {
            $grilla[$k] = round($v, 2);
        }

        $conteos = [];
        foreach ($grupos as $clave => $items) {
            $conteos[$clave] = count($items);
        }

        return [
            'movimientos' => $enriquecidos,
            'grupos' => $grupos,
            'conteos' => $conteos,
            'grilla' => $grilla,
            'total_facturacion' => round($totalFacturacion, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $mov
     * @return array<string, mixed>
     */
    private static function enriquecerMovimiento(array $mov, int $empresaId): array
    {
        if (! empty($mov['discrepancia_gap'])) {
            $mov['grupo'] = self::GRUPO_HUECO_AUDITORIA;
            $mov['medio_anita_clave'] = CierreJornadaProcesoMedioSupport::CLAVE_OTRO;
            $mov['medio_waitry_clave'] = CierreJornadaProcesoMedioSupport::claveDesdeWaitryTipo($mov['waitry_tipo_pago'] ?? null);

            return $mov;
        }

        $waitryTipo = $mov['waitry_tipo_pago'] ?? null;
        $mov['medio_waitry_clave'] = CierreJornadaProcesoMedioSupport::claveDesdeWaitryTipo($waitryTipo);
        $anitaEsTotem = (bool) ($mov['anita_es_totem'] ?? false);
        $facturada = ! empty($mov['facturada_erp']);

        if ($facturada) {
            if ($anitaEsTotem) {
                $mov['grupo'] = self::GRUPO_FACTURADO_TOTEM;
                $mov['medio_anita_clave'] = $mov['medio_waitry_clave'];
                $mov['medio_real_waitry_label'] = WaitryMedioPagoCuentacajaSupport::etiquetaTipo($waitryTipo);
            } else {
                $mov['grupo'] = self::GRUPO_FACTURADO_MEDIO_REAL;
                $mov['medio_anita_clave'] = CierreJornadaProcesoMedioSupport::claveDesdeCuentacaja(
                    isset($mov['anita_cuentacaja_id']) ? ['id' => (int) $mov['anita_cuentacaja_id']] : null,
                    $empresaId,
                );
            }

            return $mov;
        }

        if (CierreJornadaProcesoMedioSupport::esWaitryCash($waitryTipo)) {
            $mov['grupo'] = self::GRUPO_WAITRY_CASH_NO_FACTURAR;

            return $mov;
        }

        if (CierreJornadaProcesoMedioSupport::esWaitryQr($waitryTipo)) {
            $mov['grupo'] = self::GRUPO_SIN_FACTURAR_QR;

            return $mov;
        }

        $mov['grupo'] = self::GRUPO_SIN_FACTURAR_OTRO;

        return $mov;
    }
}
