<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Caja\InterbankingArchivoPagoAnitaReader;

/**
 * Arma filas sábana desde pago + auxpag Anita (l-movim.c lee_auxpag), en memoria.
 */
final class PagosSabanaAnitaArmadoSupport
{
    /** @var list<string> */
    private const MEDIOS_EFECTIVO = ['EFE', 'EPY'];

    /** @var list<string> */
    private const MEDIOS_TRANSFERENCIA = [
        'ATE', 'TMB', 'TMK', 'TMR', 'GPB', 'MEP', 'TC1', 'TCM',
        'BBB', 'CO1', 'CO2', 'CO3', 'CQR', 'CTG',
    ];

    /** @var list<string> */
    private const INTERCOMPANY = ['ITC', 'KAN', 'REB', 'BIY'];

    /**
     * @param  list<object>  $pagos
     * @param  list<object>  $auxpags
     * @param  array<string, string>  $tesmae
     * @param  array<int, array{id: int, codigo: string, nombre: string}>  $empresasPorAnita
     * @param  array<string, array{id: int, codigo: string, nombre: string}>  $proveedoresPorCodigo
     * @return list<array<string, mixed>>
     */
    public static function armarFilas(
        array $pagos,
        array $auxpags,
        array $tesmae,
        array $empresasPorAnita,
        array $proveedoresPorCodigo,
    ): array {
        $auxPorClave = [];
        foreach ($auxpags as $axp) {
            $clave = self::clavePago(
                (int) ($axp->axp_empresa ?? 0),
                (string) ($axp->axp_tipo ?? ''),
                (int) ($axp->axp_rec ?? 0),
            );
            $auxPorClave[$clave][] = $axp;
        }

        $filas = [];
        foreach ($pagos as $pago) {
            $empresaAnita = (int) ($pago->pag_empresa ?? 0);
            $tip = strtoupper(trim((string) ($pago->pag_tipo ?? '')));
            $nro = (int) ($pago->pag_rec ?? 0);
            if ($empresaAnita <= 0 || $tip === '' || $nro <= 0) {
                continue;
            }

            $clave = self::clavePago($empresaAnita, $tip, $nro);
            $lineas = $auxPorClave[$clave] ?? [];
            $desglose = self::desglosar($lineas, $tesmae);

            $codPro = InterbankingArchivoPagoAnitaReader::padProveedor($pago->pag_pro ?? '');
            $prov = $proveedoresPorCodigo[$codPro] ?? null;
            $emp = $empresasPorAnita[$empresaAnita] ?? null;

            $fechaYmd = (string) ($pago->pag_fecha ?? '');
            $fecha = strlen($fechaYmd) === 8
                ? substr($fechaYmd, 0, 4).'-'.substr($fechaYmd, 4, 2).'-'.substr($fechaYmd, 6, 2)
                : $fechaYmd;

            $totalMedios = $desglose['efectivo'] + $desglose['transferencia'] + $desglose['ch_propios']
                + $desglose['ch_terceros'] + $desglose['doc_propios'] + $desglose['doc_terceros']
                + $desglose['varios'] + $desglose['intercompany'] + $desglose['creditos'] + $desglose['adelantos']
                + $desglose['retencion_iva'] + $desglose['retencion_gan'] + $desglose['retencion_ibr']
                + $desglose['retencion_suss'];
            $totalCab = abs((float) ($pago->pag_trec ?? 0));
            $totalPago = $totalMedios > 0.005 ? round($totalMedios, 2) : round($totalCab, 2);

            $filas[] = [
                'tipo_fila' => 'detalle',
                'origen' => 'anita',
                'pk_id' => 0,
                'pagoproveedor_id' => null,
                'caja_movimiento_id' => null,
                'solicitudpago_id' => null,
                'proveedor_id' => $prov['id'] ?? null,
                'proveedor_codigo' => $prov['codigo'] ?? ltrim($codPro, '0') ?: $codPro,
                'proveedor_nombre' => $prov['nombre'] ?? trim((string) ($pago->pag_entregado_a ?? '')),
                'tip' => $tip,
                'numero_op' => (string) $nro,
                'fecha' => $fecha,
                'tipo_medio' => self::resolverTipoMedio($desglose),
                'total_pago' => $totalPago,
                'efectivo' => $desglose['efectivo'],
                'transferencia' => $desglose['transferencia'],
                'ch_propios' => $desglose['ch_propios'],
                'ch_terceros' => $desglose['ch_terceros'],
                'doc_propios' => $desglose['doc_propios'],
                'doc_terceros' => $desglose['doc_terceros'],
                'retencion_iva' => $desglose['retencion_iva'],
                'retencion_gan' => $desglose['retencion_gan'],
                'retencion_ibr' => $desglose['retencion_ibr'],
                'retencion_suss' => $desglose['retencion_suss'],
                'creditos' => $desglose['creditos'],
                'adelantos' => $desglose['adelantos'],
                'varios' => $desglose['varios'],
                'intercompany' => $desglose['intercompany'],
                'comprobantes' => implode(' | ', array_column($desglose['comprobantes_refs'], 'etiqueta')),
                'comprobantes_links' => [],
                'comprobantes_refs' => $desglose['comprobantes_refs'],
                'ch_prop_emi' => implode(' ', $desglose['ch_prop_emi']),
                'banco' => implode(' | ', array_values($desglose['bancos'])),
                'ch_terc_ent' => implode(' ', $desglose['ch_terc_ent']),
                'doc_prop_emit' => implode(' ', $desglose['doc_prop_emit']),
                'doc_terc_entr' => implode(' ', $desglose['doc_terc_entr']),
                'centros_costo' => '',
                'ordenes_compra' => '',
                'ordenes_compra_links' => [],
                'detalle' => trim((string) ($pago->pag_leyenda ?? '')),
                'empresa_id' => $emp['id'] ?? $empresaAnita,
                'empresa' => $emp['codigo'] ?? (string) $empresaAnita,
                'empresa_nombre' => $emp['nombre'] ?? (string) $empresaAnita,
                'nombreempresa' => $emp['nombre'] ?? (string) $empresaAnita,
                'anita_empresa' => $empresaAnita,
                'anita_clave' => $clave,
            ];
        }

        usort($filas, static function (array $a, array $b): int {
            return [$a['fecha'], $a['numero_op'], $a['empresa_id']]
                <=> [$b['fecha'], $b['numero_op'], $b['empresa_id']];
        });

        return $filas;
    }

    public static function clavePago(int $empresa, string $tipo, int $rec): string
    {
        return $empresa.'|'.strtoupper(trim($tipo)).'|'.$rec;
    }

    /**
     * @param  list<object>  $lineas
     * @param  array<string, string>  $tesmae
     * @return array<string, mixed>
     */
    private static function desglosar(array $lineas, array $tesmae): array
    {
        $out = [
            'efectivo' => 0.0,
            'transferencia' => 0.0,
            'ch_propios' => 0.0,
            'ch_terceros' => 0.0,
            'doc_propios' => 0.0,
            'doc_terceros' => 0.0,
            'creditos' => 0.0,
            'adelantos' => 0.0,
            'varios' => 0.0,
            'intercompany' => 0.0,
            'retencion_iva' => 0.0,
            'retencion_gan' => 0.0,
            'retencion_ibr' => 0.0,
            'retencion_suss' => 0.0,
            'comprobantes' => [],
            'comprobantes_refs' => [],
            'ch_prop_emi' => [],
            'ch_terc_ent' => [],
            'doc_prop_emit' => [],
            'doc_terc_entr' => [],
            'bancos' => [],
        ];

        foreach ($lineas as $axp) {
            $tipoAp = strtoupper(trim((string) ($axp->axp_tipo_ap ?? '')));
            $monto = abs((float) ($axp->axp_monto_ap ?? 0));
            $nro = trim((string) ($axp->axp_nro ?? ''));
            $bancoCod = strtoupper(trim((string) ($axp->axp_banco ?? '')));
            $bancoNom = $tesmae[$bancoCod] ?? $bancoCod;

            if ($tipoAp === '' || $tipoAp === 'FIN') {
                continue;
            }

            if (in_array($tipoAp, ['CHP', 'CPC', 'CPA'], true)) {
                $out['ch_propios'] += $monto;
                if ($nro !== '' && $nro !== '0') {
                    $txt = $nro.($bancoNom !== '' ? '/'.mb_substr($bancoNom, 0, 4).'.' : '');
                    $out['ch_prop_emi'][$txt] = $txt;
                }
                if ($bancoNom !== '') {
                    $out['bancos'][$bancoNom] = $bancoNom;
                }
                continue;
            }

            if (in_array($tipoAp, ['CHT', 'CTC', 'CTA'], true)) {
                $out['ch_terceros'] += $monto;
                if ($nro !== '' && $nro !== '0') {
                    $out['ch_terc_ent'][$nro] = $nro;
                }
                continue;
            }

            if (in_array($tipoAp, ['DOP', 'DPC'], true)) {
                $out['doc_propios'] += $monto;
                if ($nro !== '' && $nro !== '0') {
                    $out['doc_prop_emit'][$nro] = $nro;
                }
                continue;
            }

            if (in_array($tipoAp, ['DOT'], true)) {
                $out['doc_terceros'] += $monto;
                if ($nro !== '' && $nro !== '0') {
                    $out['doc_terc_entr'][$nro] = $nro;
                }
                continue;
            }

            if ($tipoAp === 'DTC') {
                $out['creditos'] += $monto;
                continue;
            }

            if ($tipoAp === 'APA') {
                $out['adelantos'] += $monto;
                continue;
            }

            if (in_array($tipoAp, self::INTERCOMPANY, true)) {
                $out['intercompany'] += $monto;
                continue;
            }

            if ($tipoAp === 'RIP' || $tipoAp === 'RIV' || $tipoAp === 'IVA' || preg_match('/^V\d+$/', $tipoAp) === 1) {
                $out['retencion_iva'] += $monto;
                continue;
            }
            if ($tipoAp === 'RGP' || $tipoAp === 'GAN' || preg_match('/^G\d+$/', $tipoAp) === 1) {
                $out['retencion_gan'] += $monto;
                continue;
            }
            if ($tipoAp === 'RTP' || $tipoAp === 'SIR' || preg_match('/^T\d+$/', $tipoAp) === 1) {
                $out['retencion_ibr'] += $monto;
                continue;
            }
            if ($tipoAp === 'RSP' || $tipoAp === 'RSU' || $tipoAp === 'SUSS' || preg_match('/^S\d+$/', $tipoAp) === 1) {
                $out['retencion_suss'] += $monto;
                continue;
            }

            if (in_array($tipoAp, self::MEDIOS_EFECTIVO, true)) {
                $out['efectivo'] += $monto;
                continue;
            }

            if (in_array($tipoAp, self::MEDIOS_TRANSFERENCIA, true) || $tipoAp === 'IBP') {
                $out['transferencia'] += $monto;
                if ($bancoNom !== '' && $bancoNom !== '00000000') {
                    $out['bancos'][$bancoNom] = $bancoNom;
                }
                continue;
            }

            // Facturas / débitos aplicados → solo texto Comprobantes (no suman al total de medios).
            if (
                str_starts_with($tipoAp, 'F')
                || in_array($tipoAp, ['ADP', 'ADT'], true)
                || ((int) ($axp->axp_nro_interno ?? 0) > 0 && ! in_array($tipoAp, self::MEDIOS_TRANSFERENCIA, true))
            ) {
                if ($nro !== '' && $nro !== '0') {
                    $etiqueta = trim($tipoAp.' '.$nro);
                    $out['comprobantes'][$etiqueta] = $etiqueta;
                    $out['comprobantes_refs'][$etiqueta] = [
                        'tipo' => $tipoAp,
                        'numero' => preg_replace('/\D+/', '', $nro) ?: $nro,
                        'letra' => strtoupper(trim((string) ($axp->axp_letra_comp ?? 'A'))) ?: 'A',
                        'sucursal' => (int) ($axp->axp_sucursal ?? 0),
                        'nro_interno' => (int) ($axp->axp_nro_interno ?? 0),
                        'etiqueta' => $etiqueta,
                    ];
                }
                continue;
            }

            // Resto de claves tctes / varios
            if ($bancoCod !== '' && $bancoCod !== '00000000' && isset($tesmae[$bancoCod])) {
                $out['transferencia'] += $monto;
                $out['bancos'][$bancoNom] = $bancoNom;
            } else {
                $out['varios'] += $monto;
            }
        }

        foreach ([
            'efectivo', 'transferencia', 'ch_propios', 'ch_terceros', 'doc_propios', 'doc_terceros',
            'creditos', 'adelantos', 'varios', 'intercompany',
            'retencion_iva', 'retencion_gan', 'retencion_ibr', 'retencion_suss',
        ] as $k) {
            $out[$k] = round($out[$k], 2);
        }
        $out['comprobantes'] = array_values($out['comprobantes']);
        $out['comprobantes_refs'] = array_values($out['comprobantes_refs']);
        $out['ch_prop_emi'] = array_values($out['ch_prop_emi']);
        $out['ch_terc_ent'] = array_values($out['ch_terc_ent']);
        $out['doc_prop_emit'] = array_values($out['doc_prop_emit']);
        $out['doc_terc_entr'] = array_values($out['doc_terc_entr']);

        return $out;
    }

    /**
     * @param  array<string, mixed>  $desglose
     */
    private static function resolverTipoMedio(array $desglose): string
    {
        if (abs((float) ($desglose['ch_propios'] ?? 0)) >= 0.005
            || abs((float) ($desglose['ch_terceros'] ?? 0)) >= 0.005) {
            return 'CHQ';
        }
        if (abs((float) ($desglose['efectivo'] ?? 0)) >= 0.005
            && abs((float) ($desglose['transferencia'] ?? 0)) < 0.005) {
            return 'EFE';
        }

        return 'TRF';
    }
}
