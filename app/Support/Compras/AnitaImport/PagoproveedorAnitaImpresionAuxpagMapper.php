<?php

declare(strict_types=1);

namespace App\Support\Compras\AnitaImport;

use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportClaveSupport;
use App\Support\Compras\PagosSabanaAnitaArmadoSupport;

/**
 * auxpag → filas de impresión (aplicaciones / medios caja / cheques) para el PDF de OP.
 * Misma clasificación de axp_tipo_ap que la sábana Anita.
 */
final class PagoproveedorAnitaImpresionAuxpagMapper
{
    /** @var list<string> */
    private const MEDIOS_EFECTIVO = ['EFE', 'EPY'];

    /** @var list<string> */
    private const MEDIOS_TRANSFERENCIA = [
        'ATE', 'TMB', 'TMK', 'TMR', 'GPB', 'MEP', 'TC1', 'TCM',
        'BBB', 'CO1', 'CO2', 'CO3', 'CQR', 'CTG', 'IBP',
    ];

    /** @var list<string> */
    private const INTERCOMPANY = ['ITC', 'KAN', 'REB', 'BIY'];

    /**
     * @param  list<object>  $lineasAuxpag
     * @param  array<string, string>  $tesmae cuenta → descripción
     * @return array{
     *   aplicaciones: list<array<string, mixed>>,
     *   medios_caja: list<array<string, mixed>>,
     *   cheques: list<array<string, mixed>>
     * }
     */
    public static function aSnapshot(array $lineasAuxpag, array $tesmae = []): array
    {
        $aplicaciones = [];
        $medios = [];
        $cheques = [];

        foreach ($lineasAuxpag as $axp) {
            $tipoAp = strtoupper(trim((string) ($axp->axp_tipo_ap ?? '')));
            $monto = abs((float) ($axp->axp_monto_ap ?? 0));
            if ($tipoAp === '' || $tipoAp === 'FIN' || $monto < 0.005) {
                continue;
            }

            // Retenciones en auxpag: el certificado viene de ret*mov; no duplicar como medio.
            if (self::esRetencionAuxpag($tipoAp)) {
                continue;
            }

            if (in_array($tipoAp, ['CHP', 'CPC', 'CPA', 'CHT', 'CTC', 'CTA'], true)) {
                $bancoCod = strtoupper(trim((string) ($axp->axp_banco ?? '')));
                $bancoNom = $tesmae[$bancoCod] ?? $bancoCod;
                $nro = trim((string) ($axp->axp_nro ?? ''));
                $fecha = self::fechaVista(
                    (string) ($axp->axp_fecha_co ?? ''),
                    (string) ($axp->axp_fecha ?? '')
                );
                $cheques[] = [
                    'fecha' => $fecha,
                    'numerocheque' => ($nro !== '' && $nro !== '0') ? $nro : '',
                    'banco' => $bancoNom,
                    'caracter' => in_array($tipoAp, ['CHT', 'CTC', 'CTA'], true) ? 'Terceros' : 'Propio',
                    'monto' => round($monto, 2),
                    'moneda' => self::monedaAbrev((int) ($axp->axp_cod_mon_co ?? 0)),
                    'anombrede' => '',
                ];
                continue;
            }

            if (self::esComprobanteAplicado($tipoAp, $axp)) {
                $tipo = ComprobanteProveedorAnitaImportClaveSupport::tipo($tipoAp);
                if ($tipo === 'OPA') {
                    $tipo = 'APA';
                }
                $letra = strtoupper(trim((string) ($axp->axp_letra_comp ?? 'A'))) ?: 'A';
                $suc = (int) ($axp->axp_sucursal ?? 0);
                $nro = (int) preg_replace('/\D+/', '', (string) ($axp->axp_nro ?? '')) ?: (int) ($axp->axp_nro ?? 0);
                $nroFmt = $nro > 0
                    ? sprintf('%s %s%04d-%08d', $tipo, $letra, $suc, $nro)
                    : trim($tipo.' '.(string) ($axp->axp_nro ?? ''));
                $fecha = self::fechaVista(
                    (string) ($axp->axp_fecha_co ?? ''),
                    (string) ($axp->axp_fecha ?? '')
                );
                $aplicaciones[] = [
                    'cc_id' => 0,
                    'fecha' => $fecha,
                    'tipo' => $tipo,
                    'numero' => $nroFmt,
                    'nro_int' => (string) ((int) ($axp->axp_nro_interno ?? 0) ?: ''),
                    'monto' => round($monto, 2),
                    'moneda' => self::monedaAbrev((int) ($axp->axp_cod_mon_co ?? 0)),
                    'cotizacion' => 1.0,
                    'monto_aplicado' => round($monto, 2),
                    'neto_gravado' => round($monto, 2),
                    'signo' => 1,
                ];
                continue;
            }

            if (in_array($tipoAp, ['DOP', 'DPC', 'DOT', 'DTC', 'APA'], true)
                || in_array($tipoAp, self::INTERCOMPANY, true)
            ) {
                $medios[] = self::medioCaja(
                    self::etiquetaMedioEspecial($tipoAp, $axp, $tesmae),
                    $monto,
                    (int) ($axp->axp_cod_mon_co ?? 0)
                );
                continue;
            }

            if (in_array($tipoAp, self::MEDIOS_EFECTIVO, true)) {
                $medios[] = self::medioCaja('Efectivo ('.$tipoAp.')', $monto, (int) ($axp->axp_cod_mon_co ?? 0));
                continue;
            }

            $bancoCod = strtoupper(trim((string) ($axp->axp_banco ?? '')));
            $bancoNom = $tesmae[$bancoCod] ?? '';
            if (in_array($tipoAp, self::MEDIOS_TRANSFERENCIA, true)
                || ($bancoCod !== '' && $bancoCod !== '00000000' && isset($tesmae[$bancoCod]))
            ) {
                $cuenta = trim(
                    ($bancoNom !== '' ? $bancoNom : 'Transferencia')
                    .' '.$tipoAp
                    .($bancoCod !== '' && $bancoCod !== '00000000' ? ' ['.$bancoCod.']' : '')
                );
                $medios[] = self::medioCaja($cuenta, $monto, (int) ($axp->axp_cod_mon_co ?? 0));
                continue;
            }

            $concepto = trim((string) ($axp->axp_concepto ?? ''));
            $medios[] = self::medioCaja(
                trim($tipoAp.($concepto !== '' ? ' '.$concepto : ' (varios)')),
                $monto,
                (int) ($axp->axp_cod_mon_co ?? 0)
            );
        }

        return [
            'aplicaciones' => $aplicaciones,
            'medios_caja' => $medios,
            'cheques' => $cheques,
        ];
    }

    public static function claveOp(int $empresaAnita, string $tipo, int $rec): string
    {
        return PagosSabanaAnitaArmadoSupport::clavePago($empresaAnita, $tipo, $rec);
    }

    private static function esRetencionAuxpag(string $tipoAp): bool
    {
        if (in_array($tipoAp, ['RIP', 'RIV', 'IVA', 'RGP', 'GAN', 'RTP', 'SIR', 'RSP', 'RSU', 'SUSS'], true)) {
            return true;
        }

        return (bool) preg_match('/^[VGTS]\d+$/', $tipoAp);
    }

    private static function esComprobanteAplicado(string $tipoAp, object $axp): bool
    {
        if (str_starts_with($tipoAp, 'F') || in_array($tipoAp, ['ADP', 'ADT', 'FAC', 'FIS', 'CIS', 'NDP', 'NCP'], true)) {
            return true;
        }

        $interno = (int) ($axp->axp_nro_interno ?? 0);

        return $interno > 0
            && ! in_array($tipoAp, self::MEDIOS_TRANSFERENCIA, true)
            && ! in_array($tipoAp, self::MEDIOS_EFECTIVO, true)
            && ! self::esRetencionAuxpag($tipoAp)
            && ! in_array($tipoAp, ['CHP', 'CPC', 'CPA', 'CHT', 'CTC', 'CTA', 'DOP', 'DPC', 'DOT', 'DTC', 'APA'], true)
            && ! in_array($tipoAp, self::INTERCOMPANY, true);
    }

    /**
     * @return array<string, mixed>
     */
    private static function medioCaja(string $cuenta, float $monto, int $codMon): array
    {
        return [
            'cuenta' => $cuenta,
            'monto' => -round($monto, 2),
            'monto_abs' => round($monto, 2),
            'moneda' => self::monedaAbrev($codMon),
            'cotizacion' => 1.0,
        ];
    }

    /**
     * @param  array<string, string>  $tesmae
     */
    private static function etiquetaMedioEspecial(string $tipoAp, object $axp, array $tesmae): string
    {
        $nro = trim((string) ($axp->axp_nro ?? ''));
        $bancoCod = strtoupper(trim((string) ($axp->axp_banco ?? '')));
        $banco = $tesmae[$bancoCod] ?? $bancoCod;

        return match (true) {
            in_array($tipoAp, ['DOP', 'DPC'], true) => trim('Doc. propio '.$nro.($banco !== '' ? ' / '.$banco : '')),
            $tipoAp === 'DOT' => trim('Doc. terceros '.$nro),
            $tipoAp === 'DTC' => 'Crédito / DTC',
            $tipoAp === 'APA' => trim('Anticipo / OPA '.$nro),
            in_array($tipoAp, self::INTERCOMPANY, true) => 'Intercompany '.$tipoAp,
            default => $tipoAp,
        };
    }

    private static function fechaVista(string $fechaCo, string $fechaOp): string
    {
        foreach ([$fechaCo, $fechaOp] as $raw) {
            $iso = ComprobanteProveedorAnitaImportClaveSupport::fechaIsoDesdeAnita($raw);
            if ($iso !== '') {
                $parts = explode('-', $iso);

                return sprintf('%02d/%02d/%04d', (int) ($parts[2] ?? 0), (int) ($parts[1] ?? 0), (int) ($parts[0] ?? 0));
            }
        }

        return '';
    }

    private static function monedaAbrev(int $codAnita): string
    {
        return match ($codAnita) {
            2 => 'USD',
            3 => 'EUR',
            default => $codAnita > 1 ? (string) $codAnita : 'ARS',
        };
    }
}
