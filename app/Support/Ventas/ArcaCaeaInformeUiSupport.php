<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\ArcaCaea;

/**
 * Textos y flags de UI para presentación quincenal CAEA.
 */
final class ArcaCaeaInformeUiSupport
{
    /**
     * Hay comprobantes sin cerrar (pendientes o con error), aunque no se puedan informar aún.
     *
     * @param  array<string, mixed>|null  $resumen
     */
    public static function tienePendienteInforme(?array $resumen): bool
    {
        if ($resumen === null || $resumen === []) {
            return false;
        }

        $total = (int) ($resumen['total'] ?? 0);
        if ($total === 0) {
            return false;
        }

        $pendientes = (int) ($resumen['pendientes'] ?? 0);
        $errores = (int) ($resumen['errores'] ?? 0);

        return $pendientes > 0 || $errores > 0;
    }

    /**
     * El botón presentar solo debe estar activo si hay al menos un comprobante informable ahora
     * (número = último ARCA + 1 para su PV/tipo).
     *
     * @param  array<string, mixed>|null  $resumen
     */
    public static function puedePresentarAhora(?array $resumen): bool
    {
        if ($resumen === null || $resumen === []) {
            return false;
        }

        if (! self::tienePendienteInforme($resumen)) {
            return false;
        }

        if (! array_key_exists('informables_ahora', $resumen)) {
            return true;
        }

        if ((int) ($resumen['informables_ahora'] ?? 0) > 0) {
            return true;
        }

        // Sin último ARCA consultado el job lo pide al arrancar (Anita + ERP).
        $ultimos = $resumen['ultimos_arca'] ?? [];

        return ! is_array($ultimos) || $ultimos === [];
    }

    /**
     * Texto corto para el index: qué falta en ESTA quincena (sin ruido de otras).
     *
     * @param  array<string, mixed>|null  $resumen
     */
    public static function leyendaFaltante(?array $resumen): string
    {
        if ($resumen === null || $resumen === []) {
            return 'Sin datos de informe';
        }

        $total = (int) ($resumen['total'] ?? 0);
        if ($total === 0) {
            return 'Sin comprobantes CAEA en la quincena';
        }

        $pendientes = (int) ($resumen['pendientes'] ?? 0);
        $errores = (int) ($resumen['errores'] ?? 0);
        $ok = (int) ($resumen['informados_ok'] ?? 0);
        $obs = (int) ($resumen['informados_obs'] ?? 0);
        $informados = $ok + $obs;

        if ($pendientes === 0 && $errores === 0) {
            return sprintf('Informado: %d comprobante(s)', $informados);
        }

        $faltantes = self::textoFaltantesEstaQuincena($resumen);
        if ($faltantes !== '') {
            $base = $faltantes;
            if ($informados > 0) {
                $base .= sprintf(' · %d ya informado(s)', $informados);
            }

            return $base;
        }

        // Fallback si no hay detalle de cola (p. ej. resumen viejo sin sync ARCA).
        $partes = [];
        if ($pendientes > 0) {
            $partes[] = $pendientes.' sin informar';
        }
        if ($errores > 0) {
            $partes[] = $errores.' con error';
        }
        if ($informados > 0) {
            $partes[] = $informados.' ya informado(s)';
        }

        return implode(' · ', $partes);
    }

    /**
     * Texto de faltantes: incluye 1º pendiente de esta quincena aunque ARCA espere otro número antes.
     *
     * @param  array<string, mixed>  $resumen
     */
    private static function textoFaltantesEstaQuincena(array $resumen): string
    {
        $cola = $resumen['cola_informe'] ?? [];
        if (! is_array($cola) || $cola === []) {
            return '';
        }

        $fragmentos = [];
        foreach ($cola as $item) {
            if (! is_array($item)) {
                continue;
            }

            $pto = (int) ($item['pto_vta'] ?? 0);
            $tipo = (int) ($item['tipo_afip'] ?? 0);
            $proximo = (int) ($item['proximo_numero'] ?? 0);
            $primerEsta = (int) ($item['primer_pendiente_esta_quincena'] ?? $item['primer_pendiente'] ?? 0);
            $prefijo = self::etiquetaPvTipo($pto, $tipo);

            if (! empty($item['informable_ahora'])) {
                $nro = $proximo > 0 ? $proximo : $primerEsta;
                if ($nro > 0) {
                    $fragmentos[] = 'Falta informar '.$prefijo.' #'.$nro;
                }

                continue;
            }

            // Hay pendientes de esta quincena bloqueados por numeración / otra quincena.
            $tienePendientesAqui = ! empty($item['pendientes_esta_quincena'])
                || ! empty($item['en_esta_quincena'])
                || $primerEsta > 0;

            if (! $tienePendientesAqui) {
                continue;
            }

            if (! empty($item['existe_sin_caea']) && $proximo > 0) {
                $txt = $prefijo.': ARCA espera #'.$proximo.' (existe en ERP sin CAEA)';
                if ($primerEsta > 0 && $primerEsta !== $proximo) {
                    $txt .= '; 1º con CAEA pendiente #'.$primerEsta;
                }
                $fragmentos[] = $txt;

                continue;
            }

            if (! empty($item['falta_en_erp']) && $proximo > 0) {
                $txt = $prefijo.': ARCA espera #'.$proximo.' (no está en el ERP)';
                if ($primerEsta > 0 && $primerEsta !== $proximo) {
                    $txt .= '; 1º con CAEA pendiente #'.$primerEsta;
                }
                $fragmentos[] = $txt;

                continue;
            }

            $quincena = $item['quincena_pendiente'] ?? null;
            if ($primerEsta > 0 && $proximo > 0 && $primerEsta !== $proximo) {
                $txt = $prefijo.': 1º pendiente #'.$primerEsta.' (bloqueado)';
                if (is_array($quincena)) {
                    $txt .= '; informar primero #'.$proximo
                        .' ('.$quincena['periodo'].'/Q'.$quincena['orden'].')';
                } else {
                    $txt .= '; ARCA espera #'.$proximo;
                }
                $fragmentos[] = $txt;

                continue;
            }

            if ($proximo > 0) {
                $fragmentos[] = $prefijo.': bloqueado; ARCA espera #'.$proximo;
            } elseif ($primerEsta > 0) {
                $fragmentos[] = $prefijo.': 1º pendiente #'.$primerEsta;
            }
        }

        return implode('; ', $fragmentos);
    }

    private static function etiquetaPvTipo(int $pto, int $tipo): string
    {
        // Códigos comprobante AFIP (WSFE).
        $tipoLbl = match ($tipo) {
            1 => 'FA',
            2 => 'NDA',
            3 => 'NCA',
            6 => 'FB',
            7 => 'NDB',
            8 => 'NCB',
            11 => 'FC',
            12 => 'NDC',
            13 => 'NCC',
            default => 'T'.$tipo,
        };

        return 'PV '.str_pad((string) $pto, 5, '0', STR_PAD_LEFT).' '.$tipoLbl;
    }

    public static function etiquetaQuincena(ArcaCaea $registro): string
    {
        $periodo = (int) $registro->periodo;
        $anio = (int) floor($periodo / 100);
        $mes = $periodo % 100;
        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];
        $mesNombre = $meses[$mes] ?? (string) $mes;

        return sprintf(
            'quincena %d — %s %d',
            (int) $registro->orden,
            $mesNombre,
            $anio,
        );
    }

    public static function tituloProcesando(ArcaCaea $registro): string
    {
        $empresa = trim((string) ($registro->empresa->nombre ?? ''));

        return 'Presentando CAEA de '.$empresa.' — '.self::etiquetaQuincena($registro);
    }

    /**
     * @param  array<string, mixed>  $resumen
     */
    public static function badgeInformeEstado(?string $estado, array $resumen = []): string
    {
        $total = (int) ($resumen['total'] ?? -1);
        if ($total === 0) {
            return 'ok';
        }

        if ($estado === ArcaCaea::INFORME_ESTADO_OK || ((int) ($resumen['pendientes'] ?? 0) === 0 && (int) ($resumen['errores'] ?? 0) === 0 && $total > 0)) {
            return 'ok';
        }
        if ($estado === ArcaCaea::INFORME_ESTADO_OBSERVACION) {
            return 'observacion';
        }
        if ($estado === ArcaCaea::INFORME_ESTADO_PARCIAL) {
            return 'parcial';
        }
        if ($estado === ArcaCaea::INFORME_ESTADO_ERROR || ((int) ($resumen['errores'] ?? 0) > 0 && (int) ($resumen['pendientes'] ?? 0) === 0)) {
            return 'error';
        }

        return 'pendiente';
    }
}
