<?php

namespace App\Support\Caja;

use App\ApiAnita;
use App\Support\Caja\AnitaSync\CobranzaAnitaCheBanEsquemaSupport;
use App\Support\Compras\PagoproveedorAnitaAuditoriaCompareSupport;

/**
 * Lecturas Anita (pago / tesmov / ctamov / cpromae) para el control diario de I/E.
 */
final class IngresoEgresoImputacionDiariaAnitaReader
{
    public function __construct(
        private readonly ApiAnita $api = new ApiAnita(),
    ) {}

    /**
     * @param  list<array{empresa_anita:int, tipo:string, numero:int}>  $claves
     * @return array<string, array{ars: float, lineas: int, encontrado: bool}>
     */
    public function tesmovPorComprobante(array $claves): array
    {
        $out = [];
        $porEmpresaTipo = [];
        foreach ($claves as $clave) {
            $empresa = (int) ($clave['empresa_anita'] ?? 0);
            $tipo = IngresoEgresoImputacionDiariaSupport::tipoDesdeAbreviatura((string) ($clave['tipo'] ?? ''));
            $nro = (int) ($clave['numero'] ?? 0);
            if ($empresa <= 0 || $nro <= 0 || $tipo === '') {
                continue;
            }
            $key = self::claveComprobante($empresa, $tipo, $nro);
            $out[$key] = ['ars' => 0.0, 'lineas' => 0, 'encontrado' => false];
            $porEmpresaTipo[$empresa.'|'.$tipo][$nro] = true;
        }

        foreach ($porEmpresaTipo as $grupo => $nrosMap) {
            [$empresaRaw, $tipo] = explode('|', $grupo, 2);
            $empresa = (int) $empresaRaw;
            $nros = array_map('intval', array_keys($nrosMap));
            if (IngresoEgresoImputacionDiariaSupport::esTransferencia($tipo)) {
                $this->sumarTesmovTra($out, $empresa, $nros);
                continue;
            }
            foreach (array_chunk($nros, 40) as $lote) {
                foreach ($this->listar(
                    IngresoEgresoAnitaTesmovSupport::sistema(),
                    'tesmov',
                    'tesv_tipo,tesv_nro,tesv_cuenta,tesv_importe,tesv_cotizacion',
                    ' WHERE tesv_tipo = '.$this->esc($tipo)
                        .' AND tesv_nro IN ('.implode(',', $lote).')'
                        .CobranzaAnitaCheBanEsquemaSupport::andFiltroEmpresaTesmov($empresa)
                ) as $fila) {
                    $nro = (int) ($fila->tesv_nro ?? 0);
                    $key = self::claveComprobante($empresa, $tipo, $nro);
                    if (! isset($out[$key])) {
                        continue;
                    }
                    $out[$key]['ars'] = round($out[$key]['ars'] + abs((float) ($fila->tesv_importe ?? 0)), 2);
                    $out[$key]['lineas']++;
                    $out[$key]['encontrado'] = true;
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<array{empresa_anita:int, tipo:string, numero:int}>  $claves
     * @return array<string, bool>
     */
    public function pagoExistePorComprobante(array $claves): array
    {
        $out = [];
        $porEmpresaTipo = [];
        foreach ($claves as $clave) {
            $empresa = (int) ($clave['empresa_anita'] ?? 0);
            $tipo = IngresoEgresoImputacionDiariaSupport::tipoDesdeAbreviatura((string) ($clave['tipo'] ?? ''));
            $nro = (int) ($clave['numero'] ?? 0);
            if ($empresa <= 0 || $nro <= 0) {
                continue;
            }
            $key = self::claveComprobante($empresa, $tipo, $nro);
            $out[$key] = false;
            $porEmpresaTipo[$empresa.'|'.$tipo][$nro] = true;
        }

        foreach ($porEmpresaTipo as $grupo => $nrosMap) {
            [$empresaRaw, $tipo] = explode('|', $grupo, 2);
            $empresa = (int) $empresaRaw;
            $nros = array_map('intval', array_keys($nrosMap));
            foreach (array_chunk($nros, 40) as $lote) {
                foreach ($this->listar(
                    IngresoEgresoAnitaTesmovSupport::sistema(),
                    'pago',
                    'pag_tipo,pag_rec,pag_empresa',
                    ' WHERE pag_tipo = '.$this->esc($tipo)
                        .' AND pag_rec IN ('.implode(',', $lote).')'
                        .' AND pag_empresa = '.$empresa
                ) as $fila) {
                    $nro = (int) ($fila->pag_rec ?? 0);
                    $key = self::claveComprobante($empresa, $tipo, $nro);
                    if (isset($out[$key])) {
                        $out[$key] = true;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<array{empresa_anita:int, numeroasiento:int}>  $claves
     * @return array<string, array{total_debe: float, total_haber: float, lineas_con_importe: int, balanceado: bool, encontrado: bool}>
     */
    public function ctamovPorAsiento(array $claves): array
    {
        $out = [];
        $porEmpresa = [];
        foreach ($claves as $clave) {
            $empresa = (int) ($clave['empresa_anita'] ?? 0);
            $nro = (int) ($clave['numeroasiento'] ?? 0);
            if ($empresa <= 0 || $nro <= 0) {
                continue;
            }
            $key = $empresa.':'.$nro;
            $out[$key] = [
                'total_debe' => 0.0,
                'total_haber' => 0.0,
                'lineas_con_importe' => 0,
                'balanceado' => true,
                'encontrado' => false,
            ];
            $porEmpresa[$empresa][$nro] = true;
        }

        foreach ($porEmpresa as $empresa => $nrosMap) {
            $nros = array_map('intval', array_keys($nrosMap));
            foreach (array_chunk($nros, 40) as $lote) {
                $filas = $this->listar(
                    'contab',
                    'ctamov',
                    'ctav_empresa,ctav_nro_asiento,ctav_d_h,ctav_importe',
                    ' WHERE ctav_empresa = '.$empresa
                        .' AND ctav_nro_asiento IN ('.implode(',', $lote).')'
                );
                $porAsiento = [];
                foreach ($filas as $fila) {
                    $nro = (int) ($fila->ctav_nro_asiento ?? 0);
                    $porAsiento[$nro][] = $fila;
                }
                foreach ($porAsiento as $nro => $lineas) {
                    $key = $empresa.':'.$nro;
                    if (! isset($out[$key])) {
                        continue;
                    }
                    $totales = PagoproveedorAnitaAuditoriaCompareSupport::totalesDesdeCtamov($lineas);
                    $out[$key] = array_merge($totales, ['encontrado' => $totales['lineas_con_importe'] > 0]);
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<array{cuenta:string, nro_cheque:int, empresa_anita:int}>  $cheques
     * @return array<string, array{cpromae: bool, tesmov_chp: bool}>
     */
    public function chequesPropios(array $cheques): array
    {
        $out = [];
        foreach ($cheques as $ch) {
            $cuenta = str_pad(ltrim((string) ($ch['cuenta'] ?? ''), '0') ?: '0', 8, '0', STR_PAD_LEFT);
            $nro = (int) ($ch['nro_cheque'] ?? 0);
            $empresa = (int) ($ch['empresa_anita'] ?? 0);
            if ($nro <= 0) {
                continue;
            }
            $key = self::claveCheque($cuenta, $nro);
            $out[$key] = ['cpromae' => false, 'tesmov_chp' => false];

            $cpro = $this->listar(
                IngresoEgresoAnitaTesmovSupport::sistema(),
                'cpromae',
                'cpro_cuenta,cpro_nro_cheque',
                ' WHERE cpro_cuenta = '.$this->esc($cuenta)
                    .' AND cpro_nro_cheque = '.$nro
            );
            if ($cpro !== []) {
                $out[$key]['cpromae'] = true;
            }

            $tes = $this->listar(
                IngresoEgresoAnitaTesmovSupport::sistema(),
                'tesmov',
                'tesv_tipo,tesv_nro,tesv_cuenta',
                " WHERE tesv_tipo = 'CHP' AND tesv_nro = ".$nro
                    .' AND tesv_cuenta = '.$this->esc($cuenta)
                    .CobranzaAnitaCheBanEsquemaSupport::andFiltroEmpresaTesmov($empresa)
            );
            if ($tes !== []) {
                $out[$key]['tesmov_chp'] = true;
            }
        }

        return $out;
    }

    public static function claveComprobante(int $empresaAnita, string $tipo, int $numero): string
    {
        return $empresaAnita.':'.IngresoEgresoImputacionDiariaSupport::tipoDesdeAbreviatura($tipo).':'.$numero;
    }

    public static function claveCheque(string $cuenta, int $nroCheque): string
    {
        return str_pad(ltrim($cuenta, '0') ?: '0', 8, '0', STR_PAD_LEFT).':'.$nroCheque;
    }

    /**
     * @param  array<string, array{ars: float, lineas: int, encontrado: bool}>  $out
     * @param  list<int>  $nrosTra
     */
    private function sumarTesmovTra(array &$out, int $empresa, array $nrosTra): void
    {
        foreach (array_chunk($nrosTra, 40) as $lote) {
            $aux = $this->listar(
                IngresoEgresoAnitaTesmovSupport::sistema(),
                'auxpag',
                'axp_tipo,axp_rec,axp_tipo_ap,axp_nro,axp_banco,axp_sucursal',
                " WHERE axp_tipo = 'TRA' AND axp_rec IN (".implode(',', $lote).')'
                    .CobranzaAnitaCheBanEsquemaSupport::andFiltroEmpresaAuxpag($empresa)
            );
            $porTra = [];
            foreach ($aux as $fila) {
                $tipoAp = strtoupper(substr(trim((string) ($fila->axp_tipo_ap ?? '')), 0, 3));
                if ($tipoAp === 'CHP') {
                    continue;
                }
                $nroTra = (int) ($fila->axp_rec ?? 0);
                $nroTes = (int) ($fila->axp_nro ?? 0);
                $cuenta = trim((string) ($fila->axp_banco ?? ''));
                if ($nroTra <= 0 || $nroTes <= 0) {
                    continue;
                }
                $porTra[$nroTra][] = [
                    'tipos' => IngresoEgresoImputacionDiariaSupport::tiposTesmovTraDesdeSucursalAxp(
                        (int) ($fila->axp_sucursal ?? -1)
                    ),
                    'nro' => $nroTes,
                    'cuenta' => $cuenta,
                ];
            }

            foreach ($porTra as $nroTra => $legs) {
                $key = self::claveComprobante($empresa, 'TRA', (int) $nroTra);
                if (! isset($out[$key])) {
                    continue;
                }
                foreach ($legs as $leg) {
                    foreach ($leg['tipos'] as $tipoTes) {
                        $tes = $this->listar(
                            IngresoEgresoAnitaTesmovSupport::sistema(),
                            'tesmov',
                            'tesv_tipo,tesv_nro,tesv_importe',
                            ' WHERE tesv_tipo = '.$this->esc($tipoTes)
                                .' AND tesv_nro = '.(int) $leg['nro']
                                .($leg['cuenta'] !== '' ? ' AND tesv_cuenta = '.$this->esc($leg['cuenta']) : '')
                                .CobranzaAnitaCheBanEsquemaSupport::andFiltroEmpresaTesmov($empresa)
                        );
                        foreach ($tes as $fila) {
                            $out[$key]['ars'] = round($out[$key]['ars'] + abs((float) ($fila->tesv_importe ?? 0)), 2);
                            $out[$key]['lineas']++;
                            $out[$key]['encontrado'] = true;
                        }
                    }
                }
            }

            $directo = $this->listar(
                IngresoEgresoAnitaTesmovSupport::sistema(),
                'tesmov',
                'tesv_tipo,tesv_nro,tesv_importe',
                " WHERE tesv_tipo = 'TRA' AND tesv_nro IN (".implode(',', $lote).')'
                    .CobranzaAnitaCheBanEsquemaSupport::andFiltroEmpresaTesmov($empresa)
            );
            foreach ($directo as $fila) {
                $key = self::claveComprobante($empresa, 'TRA', (int) ($fila->tesv_nro ?? 0));
                if (! isset($out[$key]) || $out[$key]['encontrado']) {
                    continue;
                }
                $out[$key]['ars'] = round($out[$key]['ars'] + abs((float) ($fila->tesv_importe ?? 0)), 2);
                $out[$key]['lineas']++;
                $out[$key]['encontrado'] = true;
            }
        }
    }

    /**
     * @return list<object>
     */
    private function listar(string $sistema, string $tabla, string $campos, string $where): array
    {
        $payload = [
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $where,
        ];
        $intentos = max(1, (int) config('caja.ingresoegreso_imputacion_diaria.anita_reintentos_bridge', 3));
        for ($i = 1; $i <= $intentos; $i++) {
            $raw = $this->api->apiCall($payload);
            if (ApiAnita::extraerMensajeError($raw) === null) {
                $filas = ApiAnita::decodificarListaFilas($raw);
                if ($filas !== [] || $i === $intentos) {
                    return $filas;
                }
            } elseif ($i === $intentos) {
                return [];
            }
            usleep(200000);
        }

        return [];
    }

    private function esc(string $valor): string
    {
        return "'".str_replace("'", "''", $valor)."'";
    }
}
