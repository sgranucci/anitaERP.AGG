<?php

declare(strict_types=1);

namespace App\Support\Contable\CcVsMayorAnita;

use App\Support\Contable\Anita\AnitaSubdiarioMayorSupport;

/**
 * Cruza climov/aplmov (CC Anita) con imputaciones de subdiario a la cuenta
 * (subd_cuenta + subd_contrapartida vía subd_tipo_mov).
 */
final class CcVsMayorAnitaProcesador
{
    /** @var list<string> */
    private const TIPOS_HABER_CC = ['NCD', 'NCK', 'NCE', 'NCP', 'REC', 'COB', 'COA', 'ANT', 'RBO', 'AJU'];

    /** @var list<string> */
    private const TIPOS_CRUCE = [
        'FAC', 'FAK', 'FAB', 'FAS', 'NDD', 'NDE', 'DEB', 'APA',
        'NCD', 'NCK', 'NCE', 'NCP', 'COB', 'COA', 'REC', 'ANT',
    ];

    public function __construct(
        private readonly CcVsMayorAnitaBridgeReader $bridgeReader = new CcVsMayorAnitaBridgeReader(),
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generar(array $filtros): array
    {
        $fechaYmd = CcVsMayorAnitaListadoFiltros::fechaYmd($filtros);
        $cuenta = (int) ($filtros['cuenta_codigo'] ?? 0);
        $sistemaSub = (string) ($filtros['sistema_subdiario'] ?? config('anita.subdiario_sistema', 'ventas'));
        $tolerancia = (float) ($filtros['tolerancia'] ?? 0.05);
        $soloDiff = ! empty($filtros['solo_diferencias']);

        $climov = $this->bridgeReader->listarClimov($fechaYmd);
        $aplmov = $this->bridgeReader->listarAplmov($fechaYmd);
        $subPack = $this->bridgeReader->listarSubdiarioConMeta($sistemaSub, $fechaYmd, $cuenta);
        $subdiario = $subPack['filas'];
        $errorBridge = $subPack['error'];

        $ccPorComp = $this->agregarClimov($climov);
        $mayorPorComp = $this->agregarMayorSubdiario($subdiario, $cuenta);

        $filas = $this->cruzar($ccPorComp, $mayorPorComp, $tolerancia);
        $filas = $this->intentarMatchFlexible($filas, $tolerancia);

        $resumen = $this->armarResumen($climov, $aplmov, $subdiario, $mayorPorComp, $ccPorComp, $filas, $cuenta);
        $resumen['error_bridge'] = $errorBridge;

        if ($soloDiff) {
            $filas = array_values(array_filter(
                $filas,
                static fn (array $f): bool => ($f['estado'] ?? '') !== 'OK',
            ));
        }

        usort($filas, static function (array $a, array $b): int {
            $prio = ['DIFF' => 0, 'SOLO_CC' => 1, 'SOLO_MAYOR' => 2, 'MATCH_FLEX' => 3, 'OK' => 4];
            $pa = $prio[$a['estado'] ?? ''] ?? 9;
            $pb = $prio[$b['estado'] ?? ''] ?? 9;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return abs((float) ($b['diff'] ?? 0)) <=> abs((float) ($a['diff'] ?? 0));
        });

        return [
            'filtros' => $filtros,
            'cuenta_codigo' => $cuenta,
            'sistema_subdiario' => $sistemaSub,
            'filas' => $filas,
            'resumen' => $resumen,
            'conteos' => [
                'climov' => count($climov),
                'aplmov' => count($aplmov),
                'subdiario' => count($subdiario),
            ],
            'errores_bridge' => array_values(array_filter([$errorBridge])),
        ];
    }

    /**
     * @param  list<object>  $climov
     * @return array<string, array<string, mixed>>
     */
    private function agregarClimov(array $climov): array
    {
        $out = [];
        foreach ($climov as $f) {
            $tipo = strtoupper(trim((string) ($f->cliv_tipo ?? '')));
            $clave = self::claveComp($tipo, (string) ($f->cliv_letra ?? ''), $f->cliv_sucursal ?? 0, $f->cliv_nro ?? 0);
            if (! isset($out[$clave])) {
                $out[$clave] = [
                    'clave' => $clave,
                    'tipo' => $tipo,
                    'letra' => strtoupper(trim((string) ($f->cliv_letra ?? ''))),
                    'sucursal' => (int) ($f->cliv_sucursal ?? 0),
                    'nro' => (int) ($f->cliv_nro ?? 0),
                    'cliente' => trim((string) ($f->cliv_cliente ?? '')),
                    'monto' => 0.0,
                    'cobrado' => 0.0,
                    'n' => 0,
                    'estado_climov' => (string) ($f->cliv_estado ?? ''),
                ];
            }
            $out[$clave]['monto'] += (float) ($f->cliv_monto ?? 0);
            $out[$clave]['cobrado'] += (float) ($f->cliv_t_cobrado ?? 0);
            $out[$clave]['n']++;
            if ($out[$clave]['cliente'] === '') {
                $out[$clave]['cliente'] = trim((string) ($f->cliv_cliente ?? ''));
            }
        }

        return $out;
    }

    /**
     * Expande subdiario con {@see AnitaSubdiarioMayorSupport}: subd_tipo_mov define el lado
     * de subd_cuenta; subd_contrapartida va al lado opuesto.
     *
     * @param  list<object>  $subdiario
     * @return array<string, array<string, mixed>>
     */
    private function agregarMayorSubdiario(array $subdiario, int $cuenta): array
    {
        $out = [];
        foreach ($subdiario as $f) {
            $tipo = strtoupper(trim((string) ($f->subd_tipo ?? '')));
            $clave = self::claveComp($tipo, (string) ($f->subd_letra ?? ''), $f->subd_sucursal ?? 0, $f->subd_nro ?? 0);

            foreach (AnitaSubdiarioMayorSupport::imputacionesLineaSubdiario($f) as $imp) {
                if ((int) $imp['cuenta'] !== $cuenta) {
                    continue;
                }
                $dh = AnitaSubdiarioMayorSupport::debeHaberDesdeDh((string) $imp['dh'], (float) $imp['importe']);
                if (! isset($out[$clave])) {
                    $lado = ((int) ($f->subd_cuenta ?? 0) === $cuenta) ? 'subd_cuenta' : 'subd_contrapartida';
                    $out[$clave] = [
                        'clave' => $clave,
                        'tipo' => $tipo,
                        'letra' => strtoupper(trim((string) ($f->subd_letra ?? ''))),
                        'sucursal' => (int) ($f->subd_sucursal ?? 0),
                        'nro' => (int) ($f->subd_nro ?? 0),
                        'debe' => 0.0,
                        'haber' => 0.0,
                        'n' => 0,
                        'lado_predominante' => $lado,
                        'tipo_mov' => strtoupper(trim((string) ($f->subd_tipo_mov ?? ''))),
                        'desc' => trim((string) ($f->subd_desc_mov ?? '')),
                    ];
                }
                $out[$clave]['debe'] += $dh['debe'];
                $out[$clave]['haber'] += $dh['haber'];
                $out[$clave]['n']++;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, mixed>>  $ccPorComp
     * @param  array<string, array<string, mixed>>  $mayorPorComp
     * @return list<array<string, mixed>>
     */
    private function cruzar(array $ccPorComp, array $mayorPorComp, float $tolerancia): array
    {
        $filas = [];
        $claves = array_unique(array_merge(array_keys($ccPorComp), array_keys($mayorPorComp)));

        foreach ($claves as $clave) {
            $cc = $ccPorComp[$clave] ?? null;
            $my = $mayorPorComp[$clave] ?? null;
            $tipo = (string) ($cc['tipo'] ?? $my['tipo'] ?? '');
            if (! in_array($tipo, self::TIPOS_CRUCE, true)) {
                continue;
            }

            $netoCc = $cc ? $this->netoCc($tipo, (float) $cc['monto']) : 0.0;
            $netoMayor = $my ? ((float) $my['debe'] - (float) $my['haber']) : 0.0;

            if ($cc === null) {
                $filas[] = $this->fila('SOLO_MAYOR', $clave, $tipo, null, $my, 0.0, $netoMayor, -$netoMayor);
                continue;
            }
            if ($my === null) {
                $filas[] = $this->fila('SOLO_CC', $clave, $tipo, $cc, null, $netoCc, 0.0, $netoCc);
                continue;
            }

            $diff = round($netoCc - $netoMayor, 2);
            $estado = abs($diff) > $tolerancia ? 'DIFF' : 'OK';
            $filas[] = $this->fila($estado, $clave, $tipo, $cc, $my, $netoCc, $netoMayor, $diff);
        }

        return $filas;
    }

    /**
     * Reapareja SOLO_CC con SOLO_MAYOR del mismo tipo+nro e importe (caso típico: distinto PV).
     *
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function intentarMatchFlexible(array $filas, float $tolerancia): array
    {
        $soloCc = [];
        $soloMayor = [];
        $resto = [];

        foreach ($filas as $i => $f) {
            if (($f['estado'] ?? '') === 'SOLO_CC') {
                $soloCc[$i] = $f;
            } elseif (($f['estado'] ?? '') === 'SOLO_MAYOR') {
                $soloMayor[$i] = $f;
            } else {
                $resto[] = $f;
            }
        }

        $usadosMayor = [];
        foreach ($soloCc as $iCc => $cc) {
            $matchIdx = null;
            foreach ($soloMayor as $iMy => $my) {
                if (isset($usadosMayor[$iMy])) {
                    continue;
                }
                if ((string) ($cc['tipo'] ?? '') !== (string) ($my['tipo'] ?? '')) {
                    continue;
                }
                if ((int) ($cc['nro'] ?? 0) !== (int) ($my['nro'] ?? 0)) {
                    continue;
                }
                if (abs(abs((float) $cc['neto_cc']) - abs((float) $my['neto_mayor'])) > $tolerancia) {
                    continue;
                }
                $matchIdx = $iMy;
                break;
            }

            if ($matchIdx === null) {
                $resto[] = $cc;
                continue;
            }

            $usadosMayor[$matchIdx] = true;
            $my = $soloMayor[$matchIdx];
            $diff = round((float) $cc['neto_cc'] - (float) $my['neto_mayor'], 2);
            $resto[] = [
                'estado' => abs($diff) > $tolerancia ? 'DIFF' : 'MATCH_FLEX',
                'clave_cc' => (string) ($cc['clave'] ?? ''),
                'clave_mayor' => (string) ($my['clave'] ?? ''),
                'clave' => (string) ($cc['clave'] ?? '').' ↔ '.(string) ($my['clave'] ?? ''),
                'tipo' => (string) ($cc['tipo'] ?? ''),
                'letra' => (string) ($cc['letra'] ?? ''),
                'sucursal_cc' => (int) ($cc['sucursal'] ?? 0),
                'sucursal_mayor' => (int) ($my['sucursal'] ?? 0),
                'nro' => (int) ($cc['nro'] ?? 0),
                'cliente' => (string) ($cc['cliente'] ?? ''),
                'neto_cc' => (float) $cc['neto_cc'],
                'neto_mayor' => (float) $my['neto_mayor'],
                'diff' => $diff,
                'mayor_debe' => (float) ($my['mayor_debe'] ?? 0),
                'mayor_haber' => (float) ($my['mayor_haber'] ?? 0),
                'cc_monto' => (float) ($cc['cc_monto'] ?? 0),
                'lado' => (string) ($my['lado'] ?? ''),
                'tipo_mov' => (string) ($my['tipo_mov'] ?? ''),
                'desc' => 'Match flexible (mismo tipo/nro/importe; distinto PV/sucursal). '
                    .(string) ($my['desc'] ?? ''),
                'aviso' => 'Posible PV distinto entre climov y subdiario',
            ];
        }

        foreach ($soloMayor as $iMy => $my) {
            if (! isset($usadosMayor[$iMy])) {
                $resto[] = $my;
            }
        }

        return $resto;
    }

    /**
     * @param  array<string, mixed>|null  $cc
     * @param  array<string, mixed>|null  $my
     * @return array<string, mixed>
     */
    private function fila(
        string $estado,
        string $clave,
        string $tipo,
        ?array $cc,
        ?array $my,
        float $netoCc,
        float $netoMayor,
        float $diff,
    ): array {
        return [
            'estado' => $estado,
            'clave' => $clave,
            'clave_cc' => $cc['clave'] ?? $clave,
            'clave_mayor' => $my['clave'] ?? $clave,
            'tipo' => $tipo,
            'letra' => (string) ($cc['letra'] ?? $my['letra'] ?? ''),
            'sucursal' => (int) ($cc['sucursal'] ?? $my['sucursal'] ?? 0),
            'sucursal_cc' => (int) ($cc['sucursal'] ?? 0),
            'sucursal_mayor' => (int) ($my['sucursal'] ?? 0),
            'nro' => (int) ($cc['nro'] ?? $my['nro'] ?? 0),
            'cliente' => (string) ($cc['cliente'] ?? ''),
            'neto_cc' => $netoCc,
            'neto_mayor' => $netoMayor,
            'diff' => $diff,
            'mayor_debe' => (float) ($my['debe'] ?? 0),
            'mayor_haber' => (float) ($my['haber'] ?? 0),
            'cc_monto' => (float) ($cc['monto'] ?? 0),
            'lado' => (string) ($my['lado_predominante'] ?? ''),
            'tipo_mov' => (string) ($my['tipo_mov'] ?? ''),
            'desc' => (string) ($my['desc'] ?? ''),
            'aviso' => '',
        ];
    }

    private function netoCc(string $tipo, float $monto): float
    {
        return in_array($tipo, self::TIPOS_HABER_CC, true) ? -$monto : $monto;
    }

    /**
     * @param  list<object>  $climov
     * @param  list<object>  $aplmov
     * @param  list<object>  $subdiario
     * @param  array<string, array<string, mixed>>  $mayorPorComp
     * @param  array<string, array<string, mixed>>  $ccPorComp
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    private function armarResumen(
        array $climov,
        array $aplmov,
        array $subdiario,
        array $mayorPorComp,
        array $ccPorComp,
        array $filas,
        int $cuenta,
    ): array {
        $mayorDebe = 0.0;
        $mayorHaber = 0.0;
        foreach ($mayorPorComp as $m) {
            $mayorDebe += (float) $m['debe'];
            $mayorHaber += (float) $m['haber'];
        }

        $ccDebe = 0.0;
        $ccHaber = 0.0;
        foreach ($ccPorComp as $c) {
            $tipo = (string) $c['tipo'];
            $monto = (float) $c['monto'];
            if (in_array($tipo, self::TIPOS_HABER_CC, true)) {
                $ccHaber += $monto;
            } else {
                $ccDebe += $monto;
            }
        }

        $sumaApl = 0.0;
        foreach ($aplmov as $a) {
            $sumaApl += (float) ($a->aplv_monto ?? 0);
        }

        $porEstado = [];
        $sumaAbsDiff = 0.0;
        $sumaDiff = 0.0;
        foreach ($filas as $f) {
            $e = (string) ($f['estado'] ?? '');
            $porEstado[$e] = ($porEstado[$e] ?? 0) + 1;
            if (in_array($e, ['DIFF', 'SOLO_CC', 'SOLO_MAYOR'], true)) {
                $sumaAbsDiff += abs((float) ($f['diff'] ?? 0));
                $sumaDiff += (float) ($f['diff'] ?? 0);
            }
        }

        return [
            'cuenta_codigo' => $cuenta,
            'climov_filas' => count($climov),
            'aplmov_filas' => count($aplmov),
            'subdiario_filas' => count($subdiario),
            'cc_debe' => round($ccDebe, 2),
            'cc_haber' => round($ccHaber, 2),
            'cc_neto' => round($ccDebe - $ccHaber, 2),
            'mayor_debe' => round($mayorDebe, 2),
            'mayor_haber' => round($mayorHaber, 2),
            'mayor_neto' => round($mayorDebe - $mayorHaber, 2),
            'diff_neto' => round(($ccDebe - $ccHaber) - ($mayorDebe - $mayorHaber), 2),
            'aplmov_suma' => round($sumaApl, 2),
            'por_estado' => $porEstado,
            'suma_abs_diff' => round($sumaAbsDiff, 2),
            'suma_diff' => round($sumaDiff, 2),
            'filas_problema' => ($porEstado['DIFF'] ?? 0) + ($porEstado['SOLO_CC'] ?? 0) + ($porEstado['SOLO_MAYOR'] ?? 0),
            'filas_match_flex' => $porEstado['MATCH_FLEX'] ?? 0,
        ];
    }

    public static function claveComp(string $tipo, string $letra, $sucursal, $nro): string
    {
        return strtoupper(trim($tipo)).'|'
            .strtoupper(trim($letra)).'|'
            .(int) $sucursal.'|'
            .(int) $nro;
    }
}
