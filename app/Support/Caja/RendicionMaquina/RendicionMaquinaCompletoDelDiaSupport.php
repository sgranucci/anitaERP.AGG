<?php

declare(strict_types=1);

namespace App\Support\Caja\RendicionMaquina;

use App\ApiAnita;
use App\Models\Caja\AperturaGasto;
use App\Models\Caja\Cuentacaja;
use App\Models\Caja\RendicionMaquina;
use App\Models\Caja\Usocuentacaja;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Paridad Anita `lee_rendiciones_del_dia` + cierre de `calcula_rendicion_turno_completo`.
 *
 * El turno Completo no se carga a mano: consolida M/T/N del día (inputs, valores, gastos)
 * y aplica el desfase de jornada del drop/impuestos.
 *
 * Contrato de lectura (igual que previas): ERP primero → Anita si no hay turnos ERP.
 */
final class RendicionMaquinaCompletoDelDiaSupport
{
    /** Campos de inputs que se suman de M/T/N (y de otros C distintos al actual). */
    private const CAMPOS_SUMA = [
        'venta_ficha',
        'venta_ruleta',
        'tito',
        'tito_ruleta',
        'salida_ruleta',
        'pago_manual',
        'hopper',
        'impuesto_venta',
        'impuesto_qr',
        'impuesto_pago',
        'variacion_ff',
        'dropem_rodillo',
        'dropem_ruleta',
        'vales',
        'reintegros',
        'vta_ant_gastro',
        'pago_diferido',
    ];

    /**
     * @return array{
     *   inputs: array<string, float>,
     *   valores: list<array<string, mixed>>,
     *   gastos: list<array<string, mixed>>,
     *   orquestador: array{
     *     comprobante: float,
     *     vale_rep_fondo: float,
     *     fondo_cierre: float,
     *     resultado_turno: float,
     *     transferencia: float
     *   },
     *   meta: array<string, mixed>
     * }
     */
    public static function consolidar(int $empresaId, string $fechaYmd, ?int $exceptoId = null): array
    {
        $inputs = self::inputsEnCero();
        $orquestador = [
            'comprobante' => 0.0,
            'vale_rep_fondo' => 0.0,
            'fondo_cierre' => 0.0,
            'resultado_turno' => 0.0,
            'transferencia' => 0.0,
        ];
        $meta = [
            'origen' => 'ninguno',
            'turnos_erp' => [],
            'origen_impuesto_drop' => 'ninguno',
            'origen_drop_ant' => 'ninguno',
            'origen_billem' => 'ninguno',
            'origen_valores' => 'ninguno',
            'origen_gastos' => 'ninguno',
            'origen_noche' => 'ninguno',
            'origen_transferencia' => 'ninguno',
        ];

        if ($empresaId <= 0 || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaYmd)) {
            return [
                'inputs' => $inputs,
                'valores' => self::catalogoValoresVacios($empresaId),
                'gastos' => self::catalogoGastosVacios($empresaId),
                'orquestador' => $orquestador,
                'meta' => $meta,
            ];
        }

        // Solo M/T/N: el Completo se arma desde los parciales (no se carga a mano).
        $turnosErp = self::cargarTurnosErp($empresaId, $fechaYmd, $exceptoId, soloParciales: true);
        if ($turnosErp !== []) {
            self::aplicarDesdeErp($inputs, $meta, $turnosErp, $orquestador);
            $valores = self::sumarValoresErp($empresaId, $turnosErp, $meta);
            $gastos = self::sumarGastosErp($empresaId, $turnosErp, $meta);
        } else {
            self::aplicarDesdeAnita($inputs, $meta, $empresaId, $fechaYmd, $exceptoId, $orquestador);
            $valores = self::catalogoValoresVacios($empresaId);
            $gastos = self::catalogoGastosVacios($empresaId);
            if (($meta['nro_opers_anita'] ?? []) !== []) {
                $valores = self::sumarValoresAnita($empresaId, $meta['nro_opers_anita'], $valores, $meta);
                $gastos = self::sumarGastosAnita($empresaId, $meta['nro_opers_anita'], $gastos, $meta);
            }
        }

        // Fondo cierre / resultado del Completo Anita = Noche Anita (lee_rendiciones_del_dia).
        // Si hay Noche Anita, prevalece sobre ERP para paridad con el Completo histórico.
        self::aplicarNocheAnitaSiCorresponde($orquestador, $meta, $empresaId, $fechaYmd);

        // Impuesto drop del Completo = M del día siguiente (desfase jornada Anita).
        self::aplicarImpuestoDropDiaSiguiente($inputs, $meta, $empresaId, $fechaYmd, $exceptoId);

        // Bill emergencia anterior = dropem M/T/N del día anterior.
        self::aplicarBillemAnterior($inputs, $meta, $empresaId, $fechaYmd, $exceptoId);

        // Cierre: fondo/comprobante/vale siempre 0 (calcula_rendicion_turno_completo).
        // fondo_cierre / resultado_turno vienen de la Noche; transferencia = suma M+T+N.
        $inputs['fondo_inicial'] = 0.0;

        return [
            'inputs' => $inputs,
            'valores' => $valores,
            'gastos' => $gastos,
            'orquestador' => $orquestador,
            'meta' => $meta,
        ];
    }

    /**
     * @param  array<string, float>  $inputs
     * @param  array<string, mixed>  $meta
     * @param  array<string, float>  $orquestador
     * @param  list<RendicionMaquina>  $turnos
     */
    private static function aplicarDesdeErp(
        array &$inputs,
        array &$meta,
        array $turnos,
        array &$orquestador
    ): void {
        $meta['origen'] = 'erp';
        $sumaSobrantes = 0.0;
        $sumaTransfer = 0.0;

        foreach ($turnos as $rend) {
            $turno = RendicionMaquinaTurno::normalizar((string) $rend->turno);
            $meta['turnos_erp'][] = $turno.'#'.$rend->id;
            $raw = is_array($rend->inputs_json) ? $rend->inputs_json : [];

            foreach (self::CAMPOS_SUMA as $campo) {
                $inputs[$campo] = round($inputs[$campo] + self::leerInput($raw, $campo), 2);
            }

            // Sobrantes + transferencia: solo parciales (Anita excluye C).
            if ($turno !== RendicionMaquinaTurno::COMPLETO) {
                $sumaSobrantes += self::leerInput($raw, 'sobrantes');
                $sumaTransfer += (float) $rend->transferencia;
            }

            if ($turno === RendicionMaquinaTurno::MANIANA) {
                $calc = is_array($rend->calc_json) ? $rend->calc_json : [];
                $vars = is_array($calc['variables'] ?? null) ? $calc['variables'] : [];
                if (isset($vars['calc.drop_bill_rodillo'])) {
                    $dr = (float) $vars['calc.drop_bill_rodillo'];
                } elseif (isset($raw['drop_billete_bruto'])) {
                    $dr = (float) ($raw['drop_billete'] ?? 0);
                } else {
                    $dr = (float) ($raw['drop_billete'] ?? 0)
                        - (float) ($raw['impuesto_drop'] ?? 0);
                }
                $inputs['drop_bill_ant'] = round($dr, 2);
                $inputs['drop_rul_ant'] = round(self::leerInput($raw, 'drop_ruleta'), 2);
                // Anita: RENDM_vale_anterior ← COMPROBANTE del M
                if (isset($vars['calc.comprobante'])) {
                    $comp = (float) $vars['calc.comprobante'];
                } else {
                    $orq = is_array($calc['orquestador'] ?? null) ? $calc['orquestador'] : [];
                    $comp = (float) ($orq['comprobante'] ?? 0);
                }
                $inputs['vale_anterior'] = round($comp, 2);
                $meta['origen_drop_ant'] = 'erp_m#'.$rend->id;
            }

            // lee_rendiciones_del_dia: fondo_cierre y resultado_turno del turno Noche.
            if ($turno === RendicionMaquinaTurno::NOCHE) {
                $calc = is_array($rend->calc_json) ? $rend->calc_json : [];
                $vars = is_array($calc['variables'] ?? null) ? $calc['variables'] : [];
                $orquestador['fondo_cierre'] = round(
                    (float) ($vars['calc.fondo_cierre'] ?? $rend->fondo_cierre ?? 0),
                    2
                );
                $orquestador['resultado_turno'] = round(
                    (float) ($vars['calc.resultado_turno'] ?? $rend->resultado_turno ?? 0),
                    2
                );
                $meta['origen_noche'] = 'erp_n#'.$rend->id;
            }
        }

        $inputs['sobrantes'] = round($sumaSobrantes, 2);
        $orquestador['transferencia'] = round($sumaTransfer, 2);
        $meta['origen_transferencia'] = 'erp_suma_mtn';
    }

    /**
     * @param  array<string, float>  $inputs
     * @param  array<string, mixed>  $meta
     * @param  array<string, float>  $orquestador
     */
    private static function aplicarDesdeAnita(
        array &$inputs,
        array &$meta,
        int $empresaId,
        string $fechaYmd,
        ?int $exceptoId,
        array &$orquestador
    ): void {
        $filas = self::listarCabecerasAnita($empresaId, $fechaYmd);
        if ($filas === []) {
            return;
        }

        $meta['origen'] = 'anita';
        $nroOpers = [];
        $sumaSobrantes = 0.0;
        $sumaTransfer = 0.0;
        $exceptoNro = null;
        if ($exceptoId) {
            $exceptoNro = (int) (RendicionMaquina::query()->whereKey($exceptoId)->value('nro_oper_anita') ?? 0) ?: null;
        }

        foreach ($filas as $fila) {
            $turnoAnita = strtoupper(trim((string) ($fila->rendm_turno ?? '')));
            $turno = RendicionMaquinaTurno::ANITA_A_LETRA[$turnoAnita]
                ?? (isset(RendicionMaquinaTurno::LETRA_A_ANITA[$turnoAnita]) ? $turnoAnita : null);
            if ($turno === null) {
                continue;
            }
            $nro = (int) ($fila->rendm_nro_oper ?? 0);
            if ($exceptoNro && $nro === $exceptoNro) {
                continue;
            }

            // Solo parciales (M/T/N). Un C previo no debe inflar el consolidado.
            if ($turno === RendicionMaquinaTurno::COMPLETO) {
                continue;
            }

            $nroOpers[] = $nro;
            $meta['turnos_erp'][] = 'anita_'.$turno.'#'.$nro;

            $inputs['venta_ficha'] = round($inputs['venta_ficha'] + (float) ($fila->rendm_venta_ficha ?? 0), 2);
            $inputs['venta_ruleta'] = round($inputs['venta_ruleta'] + (float) ($fila->rendm_venta_ruleta ?? 0), 2);
            $inputs['tito'] = round($inputs['tito'] + (float) ($fila->rendm_tito ?? 0), 2);
            $inputs['tito_ruleta'] = round($inputs['tito_ruleta'] + (float) ($fila->rendm_tito_ruleta ?? 0), 2);
            $inputs['salida_ruleta'] = round($inputs['salida_ruleta'] + (float) ($fila->rendm_salida_rul ?? 0), 2);
            $inputs['pago_manual'] = round($inputs['pago_manual'] + (float) ($fila->rendm_pago_manual ?? 0), 2);
            $inputs['hopper'] = round($inputs['hopper'] + (float) ($fila->rendm_hopper ?? 0), 2);
            $inputs['impuesto_venta'] = round($inputs['impuesto_venta'] + (float) ($fila->rendm_imp_venta ?? 0), 2);
            $inputs['impuesto_qr'] = round($inputs['impuesto_qr'] + (float) ($fila->rendm_impuesto_qr ?? 0), 2);
            $inputs['variacion_ff'] = round($inputs['variacion_ff'] + (float) ($fila->rendm_variacion_ff ?? 0), 2);

            $sumaSobrantes += (float) ($fila->rendm_sobrantes ?? 0);
            $sumaTransfer += (float) ($fila->rendm_transfer ?? 0);

            if ($turno === RendicionMaquinaTurno::MANIANA) {
                $inputs['drop_bill_ant'] = round((float) ($fila->rendm_dr_bill_rod ?? 0), 2);
                $inputs['drop_rul_ant'] = round((float) ($fila->rendm_drop_ruleta ?? 0), 2);
                $inputs['vale_anterior'] = round((float) ($fila->rendm_comprobante ?? 0), 2);
                $meta['origen_drop_ant'] = 'anita_m#'.$nro;
            }

            if ($turno === RendicionMaquinaTurno::NOCHE) {
                $orquestador['fondo_cierre'] = round((float) ($fila->rendm_fondo_cierre ?? 0), 2);
                $orquestador['resultado_turno'] = round((float) ($fila->rendm_resul_turno ?? 0), 2);
                $meta['origen_noche'] = 'anita_n#'.$nro;
            }
        }

        $inputs['sobrantes'] = round($sumaSobrantes, 2);
        $orquestador['transferencia'] = round($sumaTransfer, 2);
        $meta['origen_transferencia'] = 'anita_suma_mtn';
        $meta['nro_opers_anita'] = $nroOpers;
    }

    /**
     * @param  array<string, float>  $orquestador
     * @param  array<string, mixed>  $meta
     */
    private static function aplicarNocheAnitaSiCorresponde(
        array &$orquestador,
        array &$meta,
        int $empresaId,
        string $fechaYmd
    ): void {
        foreach (self::listarCabecerasAnita($empresaId, $fechaYmd) as $fila) {
            $t = strtoupper(trim((string) ($fila->rendm_turno ?? '')));
            $turno = RendicionMaquinaTurno::ANITA_A_LETRA[$t] ?? $t;
            if ($turno !== RendicionMaquinaTurno::NOCHE) {
                continue;
            }
            $fondo = round((float) ($fila->rendm_fondo_cierre ?? 0), 2);
            $resultado = round((float) ($fila->rendm_resul_turno ?? 0), 2);
            if (abs($fondo) < 0.00001 && abs($resultado) < 0.00001) {
                return;
            }
            $orquestador['fondo_cierre'] = $fondo;
            $orquestador['resultado_turno'] = $resultado;
            $meta['origen_noche'] = 'anita_n#'.(string) ($fila->rendm_nro_oper ?? '');

            return;
        }
    }

    /**
     * @param  array<string, float>  $inputs
     * @param  array<string, mixed>  $meta
     */
    private static function aplicarImpuestoDropDiaSiguiente(
        array &$inputs,
        array &$meta,
        int $empresaId,
        string $fechaYmd,
        ?int $exceptoId
    ): void {
        $fechaSig = Carbon::parse($fechaYmd)->addDay()->format('Y-m-d');
        $maniana = self::queryBase($empresaId, $exceptoId)
            ->whereDate('fecha', $fechaSig)
            ->where('turno', RendicionMaquinaTurno::MANIANA)
            ->orderByDesc('id')
            ->first();

        if ($maniana) {
            $raw = is_array($maniana->inputs_json) ? $maniana->inputs_json : [];
            $inputs['impuesto_drop'] = round(self::leerInput($raw, 'impuesto_drop'), 2);
            $meta['origen_impuesto_drop'] = 'erp_m_sig#'.$maniana->id;

            return;
        }

        $filas = self::listarCabecerasAnita($empresaId, $fechaSig);
        foreach ($filas as $fila) {
            $t = strtoupper(trim((string) ($fila->rendm_turno ?? '')));
            $turno = RendicionMaquinaTurno::ANITA_A_LETRA[$t] ?? $t;
            if ($turno !== RendicionMaquinaTurno::MANIANA) {
                continue;
            }
            $inputs['impuesto_drop'] = round((float) ($fila->rendm_imp_drop ?? 0), 2);
            $meta['origen_impuesto_drop'] = 'anita_m_sig#'.(string) ($fila->rendm_nro_oper ?? '');

            return;
        }
    }

    /**
     * @param  array<string, float>  $inputs
     * @param  array<string, mixed>  $meta
     */
    private static function aplicarBillemAnterior(
        array &$inputs,
        array &$meta,
        int $empresaId,
        string $fechaYmd,
        ?int $exceptoId
    ): void {
        $fechaAnt = Carbon::parse($fechaYmd)->subDay()->format('Y-m-d');
        $turnos = self::cargarTurnosErp($empresaId, $fechaAnt, $exceptoId, soloParciales: true);
        $rod = 0.0;
        $rul = 0.0;

        if ($turnos !== []) {
            foreach ($turnos as $rend) {
                $raw = is_array($rend->inputs_json) ? $rend->inputs_json : [];
                $rod += self::leerInput($raw, 'dropem_rodillo');
                $rul += self::leerInput($raw, 'dropem_ruleta');
            }
            $meta['origen_billem'] = 'erp_dia_ant';
        } else {
            foreach (self::listarCabecerasAnita($empresaId, $fechaAnt) as $fila) {
                $t = strtoupper(trim((string) ($fila->rendm_turno ?? '')));
                $turno = RendicionMaquinaTurno::ANITA_A_LETRA[$t] ?? $t;
                if (! in_array($turno, [
                    RendicionMaquinaTurno::MANIANA,
                    RendicionMaquinaTurno::TARDE,
                    RendicionMaquinaTurno::NOCHE,
                ], true)) {
                    continue;
                }
                $rod += (float) ($fila->rendm_billem_rod ?? 0);
                $rul += (float) ($fila->rendm_billem_rul ?? 0);
            }
            if (abs($rod) > 0.00001 || abs($rul) > 0.00001) {
                $meta['origen_billem'] = 'anita_dia_ant';
            }
        }

        $inputs['billem_rodillo'] = round($rod, 2);
        $inputs['billem_ruleta'] = round($rul, 2);
    }

    /**
     * @param  list<RendicionMaquina>  $turnos
     * @param  array<string, mixed>  $meta
     * @return list<array<string, mixed>>
     */
    private static function sumarValoresErp(int $empresaId, array $turnos, array &$meta): array
    {
        $montos = [];
        foreach ($turnos as $rend) {
            $rend->loadMissing('valores');
            foreach ($rend->valores as $valor) {
                $id = (int) $valor->cuentacaja_id;
                if ($id <= 0) {
                    continue;
                }
                if (! isset($montos[$id])) {
                    $montos[$id] = [
                        'monto' => 0.0,
                        'cotizacion' => $valor->cotizacion !== null ? (float) $valor->cotizacion : null,
                        'codigo_valormae' => $valor->codigo_valormae,
                    ];
                }
                $montos[$id]['monto'] = round($montos[$id]['monto'] + (float) $valor->monto, 2);
                if ($montos[$id]['cotizacion'] === null && $valor->cotizacion !== null) {
                    $montos[$id]['cotizacion'] = (float) $valor->cotizacion;
                }
            }
        }
        $meta['origen_valores'] = $montos === [] ? 'catalogo_cero' : 'erp';

        return self::catalogoValoresConMontos($empresaId, $montos);
    }

    /**
     * @param  list<RendicionMaquina>  $turnos
     * @param  array<string, mixed>  $meta
     * @return list<array<string, mixed>>
     */
    private static function sumarGastosErp(int $empresaId, array $turnos, array &$meta): array
    {
        $montos = [];
        foreach ($turnos as $rend) {
            $rend->loadMissing('gastos');
            foreach ($rend->gastos as $gasto) {
                $id = (int) $gasto->apertura_gasto_id;
                if ($id <= 0) {
                    continue;
                }
                $montos[$id] = round(($montos[$id] ?? 0.0) + (float) $gasto->monto, 2);
            }
        }
        $meta['origen_gastos'] = $montos === [] ? 'catalogo_cero' : 'erp';

        return self::catalogoGastosConMontos($empresaId, $montos);
    }

    /**
     * @param  list<int>  $nroOpers
     * @param  list<array<string, mixed>>  $base
     * @param  array<string, mixed>  $meta
     * @return list<array<string, mixed>>
     */
    private static function sumarValoresAnita(int $empresaId, array $nroOpers, array $base, array &$meta): array
    {
        $nroOpers = array_values(array_filter(array_map('intval', $nroOpers), static fn (int $n) => $n > 0));
        if ($nroOpers === []) {
            return $base;
        }

        $tipo = (string) config('rendicion_maquina_anita.tipo_oper', 'F');
        $tabla = (string) config('rendicion_maquina_anita.tabla_valor', 'rendvalor');
        $in = implode(',', $nroOpers);

        try {
            $raw = (new ApiAnita)->apiCall([
                'acc' => 'list',
                'sistema' => (string) config('rendicion_maquina_anita.sistema', 'caja'),
                'tabla' => $tabla,
                'campos' => 'rendv_nro_oper,rendv_codigo,rendv_total,rendv_cotizacion',
                'whereArmado' => " WHERE rendv_tipo_oper='{$tipo}' AND rendv_nro_oper IN ({$in})",
            ]);
            $filas = ApiAnita::decodificarListaFilas(is_string($raw) ? $raw : json_encode($raw));
        } catch (Throwable $e) {
            Log::warning('RendicionMaquina completo valores Anita: '.$e->getMessage());

            return $base;
        }

        /** @var array<int, array{monto: float, cotizacion: ?float, codigo_valormae: ?int}> $porCodigo */
        $porCodigo = [];
        foreach ($filas as $fila) {
            $obj = is_object($fila) ? $fila : (object) $fila;
            $codigo = (int) ($obj->rendv_codigo ?? 0);
            if ($codigo <= 0) {
                continue;
            }
            if (! isset($porCodigo[$codigo])) {
                $porCodigo[$codigo] = [
                    'monto' => 0.0,
                    'cotizacion' => null,
                    'codigo_valormae' => $codigo,
                ];
            }
            $porCodigo[$codigo]['monto'] = round($porCodigo[$codigo]['monto'] + (float) ($obj->rendv_total ?? 0), 2);
            if ($porCodigo[$codigo]['cotizacion'] === null && isset($obj->rendv_cotizacion)) {
                $porCodigo[$codigo]['cotizacion'] = (float) $obj->rendv_cotizacion;
            }
        }

        if ($porCodigo === []) {
            return $base;
        }

        $montosPorCuenta = [];
        foreach ($base as $linea) {
            $codigoMae = (int) ($linea['codigo'] ?? 0);
            // Match por código de cuentacaja (suele coincidir con valormae en este módulo)
            if ($codigoMae > 0 && isset($porCodigo[$codigoMae])) {
                $montosPorCuenta[(int) $linea['cuentacaja_id']] = $porCodigo[$codigoMae];
            }
        }

        // También por codigo_valormae si alguna línea lo trae
        foreach ($base as $linea) {
            $id = (int) ($linea['cuentacaja_id'] ?? 0);
            $codigoMae = (int) ($linea['codigo_valormae'] ?? 0);
            if ($id > 0 && $codigoMae > 0 && isset($porCodigo[$codigoMae]) && ! isset($montosPorCuenta[$id])) {
                $montosPorCuenta[$id] = $porCodigo[$codigoMae];
            }
        }

        $meta['origen_valores'] = $montosPorCuenta === [] ? 'anita_sin_match' : 'anita';

        return self::catalogoValoresConMontos($empresaId, $montosPorCuenta);
    }

    /**
     * @param  list<int>  $nroOpers
     * @param  list<array<string, mixed>>  $base
     * @param  array<string, mixed>  $meta
     * @return list<array<string, mixed>>
     */
    private static function sumarGastosAnita(int $empresaId, array $nroOpers, array $base, array &$meta): array
    {
        $nroOpers = array_values(array_filter(array_map('intval', $nroOpers), static fn (int $n) => $n > 0));
        if ($nroOpers === []) {
            return $base;
        }

        $tabla = (string) config('rendicion_maquina_anita.tabla_gasto', 'rendmapgasto');
        $colNro = (string) config('rendicion_maquina_anita.gasto_col_nro_oper', 'renmap_nro_oper');
        $colCodigo = (string) config('rendicion_maquina_anita.gasto_col_codigo', 'renmap_codigo');
        $colImporte = (string) config('rendicion_maquina_anita.gasto_col_importe', 'renmap_importe');
        $in = implode(',', $nroOpers);

        try {
            $raw = (new ApiAnita)->apiCall([
                'acc' => 'list',
                'sistema' => (string) config('rendicion_maquina_anita.sistema', 'caja'),
                'tabla' => $tabla,
                'campos' => "{$colNro},{$colCodigo},{$colImporte}",
                'whereArmado' => " WHERE {$colNro} IN ({$in})",
            ]);
            $filas = ApiAnita::decodificarListaFilas(is_string($raw) ? $raw : json_encode($raw));
        } catch (Throwable $e) {
            Log::warning('RendicionMaquina completo gastos Anita: '.$e->getMessage());

            return $base;
        }

        /** @var array<int, float> $porCodigo */
        $porCodigo = [];
        foreach ($filas as $fila) {
            $obj = is_object($fila) ? $fila : (object) $fila;
            $codigo = (int) ($obj->{$colCodigo} ?? 0);
            if ($codigo <= 0) {
                continue;
            }
            $porCodigo[$codigo] = round(($porCodigo[$codigo] ?? 0.0) + (float) ($obj->{$colImporte} ?? 0), 2);
        }

        if ($porCodigo === []) {
            return $base;
        }

        $montos = [];
        foreach ($base as $linea) {
            $codigo = (int) ($linea['codigo'] ?? 0);
            $id = (int) ($linea['apertura_gasto_id'] ?? 0);
            if ($id > 0 && $codigo > 0 && isset($porCodigo[$codigo])) {
                $montos[$id] = $porCodigo[$codigo];
            }
        }
        $meta['origen_gastos'] = $montos === [] ? 'anita_sin_match' : 'anita';

        return self::catalogoGastosConMontos($empresaId, $montos);
    }

    /**
     * @return list<RendicionMaquina>
     */
    private static function cargarTurnosErp(
        int $empresaId,
        string $fechaYmd,
        ?int $exceptoId,
        bool $soloParciales = false
    ): array {
        $q = self::queryBase($empresaId, $exceptoId)->whereDate('fecha', $fechaYmd);
        if ($soloParciales) {
            $q->whereIn('turno', [
                RendicionMaquinaTurno::MANIANA,
                RendicionMaquinaTurno::TARDE,
                RendicionMaquinaTurno::NOCHE,
            ]);
        } else {
            // Paridad Anita: suma todos los turnos del día salvo el C que se está editando.
            $q->whereIn('turno', [
                RendicionMaquinaTurno::MANIANA,
                RendicionMaquinaTurno::TARDE,
                RendicionMaquinaTurno::NOCHE,
                RendicionMaquinaTurno::COMPLETO,
            ]);
        }

        return $q->orderBy('id')->get()->all();
    }

    /**
     * @return list<object>
     */
    private static function listarCabecerasAnita(int $empresaId, string $fechaYmd): array
    {
        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        if ($empresaAnita <= 0) {
            return [];
        }
        $fechaEntera = (int) str_replace('-', '', $fechaYmd);

        try {
            $raw = (new ApiAnita)->apiCall([
                'acc' => 'list',
                'sistema' => (string) config('rendicion_maquina_anita.sistema', 'caja'),
                'tabla' => (string) config('rendicion_maquina_anita.tabla_cabecera', 'rendmaquina'),
                'campos' => 'rendm_nro_oper,rendm_turno,rendm_venta_ficha,rendm_venta_ruleta,'
                    .'rendm_tito,rendm_tito_ruleta,rendm_salida_rul,rendm_pago_manual,rendm_hopper,'
                    .'rendm_imp_drop,rendm_imp_venta,rendm_impuesto_qr,rendm_variacion_ff,'
                    .'rendm_sobrantes,rendm_dr_bill_rod,rendm_drop_ruleta,rendm_comprobante,'
                    .'rendm_billem_rod,rendm_billem_rul,rendm_fondo_cierre,rendm_resul_turno,rendm_transfer',
                'whereArmado' => ' WHERE rendm_empresa='.$empresaAnita
                    .' AND rendm_fecha='.$fechaEntera,
            ]);
            $filas = ApiAnita::decodificarListaFilas(is_string($raw) ? $raw : json_encode($raw));
        } catch (Throwable $e) {
            Log::warning('RendicionMaquina completo Anita: '.$e->getMessage(), [
                'empresa_id' => $empresaId,
                'fecha' => $fechaYmd,
            ]);

            return [];
        }

        $out = [];
        foreach ($filas as $fila) {
            $out[] = is_object($fila) ? $fila : (object) $fila;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function leerInput(array $raw, string $campo): float
    {
        if (array_key_exists($campo, $raw) && is_numeric($raw[$campo])) {
            return (float) $raw[$campo];
        }
        $ruta = 'inputs.'.$campo;
        if (array_key_exists($ruta, $raw) && is_numeric($raw[$ruta])) {
            return (float) $raw[$ruta];
        }

        return 0.0;
    }

    /**
     * @return array<string, float>
     */
    private static function inputsEnCero(): array
    {
        $out = [];
        foreach (RendicionMaquinaVariables::INPUTS as $ruta) {
            $clave = str_starts_with($ruta, 'inputs.') ? substr($ruta, 7) : $ruta;
            $out[$clave] = 0.0;
        }

        return $out;
    }

    /**
     * @param  array<int, array{monto: float, cotizacion: ?float, codigo_valormae: ?int}>  $montos
     * @return list<array<string, mixed>>
     */
    private static function catalogoValoresConMontos(int $empresaId, array $montos): array
    {
        $lineas = self::catalogoValoresVacios($empresaId);
        foreach ($lineas as &$linea) {
            $id = (int) $linea['cuentacaja_id'];
            if (isset($montos[$id])) {
                $linea['monto'] = round((float) $montos[$id]['monto'], 2);
                $linea['cotizacion'] = $montos[$id]['cotizacion'];
                if ($montos[$id]['codigo_valormae'] !== null) {
                    $linea['codigo_valormae'] = $montos[$id]['codigo_valormae'];
                }
            }
        }
        unset($linea);

        return $lineas;
    }

    /**
     * @param  array<int, float>  $montos
     * @return list<array<string, mixed>>
     */
    private static function catalogoGastosConMontos(int $empresaId, array $montos): array
    {
        $lineas = self::catalogoGastosVacios($empresaId);
        foreach ($lineas as &$linea) {
            $id = (int) $linea['apertura_gasto_id'];
            if (isset($montos[$id])) {
                $linea['monto'] = round((float) $montos[$id], 2);
            }
        }
        unset($linea);

        return $lineas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function catalogoValoresVacios(int $empresaId): array
    {
        $usoId = (int) (Usocuentacaja::query()
            ->where('nombre', RendicionMaquinaVariables::USO_CUENTACAJA_NOMBRE)
            ->value('id') ?? 0);
        if ($usoId <= 0 || $empresaId <= 0) {
            return [];
        }

        $cuentas = Cuentacaja::query()
            ->paraEmpresa($empresaId)
            ->whereHas('usocuentacajas', fn ($q) => $q->where('usocuentacaja.id', $usoId))
            ->get(['id', 'codigo', 'nombre', 'descripcion_operaciones']);

        $lineas = [];
        foreach ($cuentas as $cuenta) {
            $lineas[] = [
                'cuentacaja_id' => (int) $cuenta->id,
                'codigo' => (string) $cuenta->codigo,
                'nombre' => $cuenta->etiquetaOperaciones(),
                'descripcion_operaciones' => trim((string) ($cuenta->descripcion_operaciones ?? '')),
                'nombre_maestro' => (string) $cuenta->nombre,
                'monto' => 0.0,
                'cotizacion' => null,
                'codigo_valormae' => ctype_digit((string) $cuenta->codigo) ? (int) $cuenta->codigo : null,
                'tipo_valormae' => null,
            ];
        }

        return self::ordenarPorCodigo($lineas);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function catalogoGastosVacios(int $empresaId): array
    {
        if ($empresaId <= 0) {
            return [];
        }

        $conceptos = AperturaGasto::query()
            ->where('estado', AperturaGasto::ESTADO_ACTIVO)
            ->whereHas('empresas', fn ($q) => $q->where('empresa_id', $empresaId))
            ->get(['id', 'codigo', 'nombre']);

        $lineas = [];
        foreach ($conceptos as $concepto) {
            $lineas[] = [
                'apertura_gasto_id' => (int) $concepto->id,
                'codigo' => (int) $concepto->codigo,
                'nombre' => (string) $concepto->nombre,
                'monto' => 0.0,
            ];
        }

        return self::ordenarPorCodigo($lineas);
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private static function ordenarPorCodigo(array $lineas): array
    {
        usort($lineas, static function (array $a, array $b): int {
            $ca = trim((string) ($a['codigo'] ?? ''));
            $cb = trim((string) ($b['codigo'] ?? ''));
            $na = ctype_digit($ca) ? (int) $ca : null;
            $nb = ctype_digit($cb) ? (int) $cb : null;
            if ($na !== null && $nb !== null && $na !== $nb) {
                return $na <=> $nb;
            }
            if ($na !== null && $nb === null) {
                return -1;
            }
            if ($na === null && $nb !== null) {
                return 1;
            }

            return strnatcasecmp($ca, $cb);
        });

        return $lineas;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<RendicionMaquina>
     */
    private static function queryBase(int $empresaId, ?int $exceptoId)
    {
        $q = RendicionMaquina::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', '!=', RendicionMaquina::ESTADO_ANULADA);

        if ($exceptoId) {
            $q->where('id', '!=', $exceptoId);
        }

        return $q;
    }
}
