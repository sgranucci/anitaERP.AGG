<?php

namespace App\Support\Contable\MayorPlanoCuenta;

use App\ApiAnita;
use Illuminate\Support\Facades\Log;

/**
 * Carga ctamov y subdiario vía bridge Anita para el mayor plano por cuenta.
 */
class MayorPlanoCuentaAnitaBridgeReader
{
    /**
     * `*_desc_mov` va último: el bridge parte el CSV por `|` sin respetar el escape, así que una
     * descripción con `|` corre los campos siguientes (sistema, cotización, moneda, balancea).
     */
    private const CTAMOV_CAMPOS = 'ctav_empresa,ctav_nro_asiento,ctav_nro_linea,ctav_d_h,ctav_cuenta,ctav_fecha,'
        .'ctav_tipo,ctav_letra,ctav_sucursal,ctav_nro,ctav_importe,ctav_cotizacion,ctav_cod_mon,'
        .'ctav_sistema,ctav_tipo_asiento,ctav_ccosto,ctav_balancea,ctav_o_compra,ctav_asi_mon_ref,ctav_desc_mov';

    private const SUBDIARIO_CAMPOS = 'subd_empresa,subd_sistema,subd_fecha,subd_tipo,subd_letra,subd_sucursal,subd_nro,'
        .'subd_emisor,subd_tipo_mov,subd_cuenta,subd_contrapartida,subd_nro_operacion,subd_ref_tipo,subd_ref_letra,'
        .'subd_ref_sucursal,subd_ref_nro,subd_importe,subd_cod_mon,subd_cotizacion,subd_nro_asiento,'
        .'subd_nro_interno,subd_ccosto_cta,subd_ccosto_con,subd_desc_mov';

    private const AUXPAG_CAMPOS = 'axp_pro,axp_fecha,axp_rec,axp_tipo,axp_nro,axp_tipo_ap,axp_monto_ap,axp_cod_mon_co,'
        .'axp_sucursal,axp_empresa,axp_letra_comp,axp_nro_interno,axp_banco,axp_concepto';

    public function __construct(
        private readonly ApiAnita $api = new ApiAnita(),
    ) {
    }

    /**
     * Resuelve desde qué fecha cargar movimientos de saldo inicial.
     * Si hay APE en el ejercicio del período → desde comienzo de ese ejercicio;
     * si no → ejercicio anterior (l-mayor.c + regla Biyemas desde 01/01/26).
     *
     * @param  list<int>  $empresaIds
     */
    public function resolverFechaSaldoDesde(
        array $empresaIds,
        int $fechaDesde,
        int $fechaComienzoEjercicioAjustada,
    ): int {
        $diag = $this->diagnosticarSaldoInicial($empresaIds, $fechaDesde, $fechaComienzoEjercicioAjustada);

        return (int) ($diag['fecha_saldo_desde'] ?? MayorPlanoCuentaSupport::SALDO_ORIGEN_MINIMO_YMD);
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array<string, mixed>
     */
    public function diagnosticarSaldoInicial(
        array $empresaIds,
        int $fechaDesde,
        int $fechaComienzoEjercicioAjustada,
    ): array {
        $porEmpresa = [];
        $fechas = [];

        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }

            $apeEnEjercicio = $this->existeAsientoAperturaEnRango(
                $empresaId,
                $fechaComienzoEjercicioAjustada,
                $fechaDesde,
            );
            $fechaSaldo = $apeEnEjercicio
                ? $fechaComienzoEjercicioAjustada
                : MayorPlanoCuentaSupport::ejercicioAnterior($fechaComienzoEjercicioAjustada);
            $fechaSaldo = max(MayorPlanoCuentaSupport::SALDO_ORIGEN_MINIMO_YMD, $fechaSaldo);
            $fechas[] = $fechaSaldo;

            $porEmpresa[$empresaId] = [
                'ape_en_ejercicio_actual' => $apeEnEjercicio,
                'fecha_saldo_desde' => $fechaSaldo,
            ];
        }

        return [
            'fecha_comienzo_ejercicio' => MayorPlanoCuentaSupport::inicioEjercicio($fechaDesde),
            'fecha_comienzo_ajustada' => $fechaComienzoEjercicioAjustada,
            'fecha_saldo_desde' => MayorPlanoCuentaSupport::consolidarFechaSaldoDesde($fechas),
            'origen_minimo' => MayorPlanoCuentaSupport::SALDO_ORIGEN_MINIMO_YMD,
            'por_empresa' => $porEmpresa,
        ];
    }

    private function existeAsientoAperturaEnRango(int $empresaId, int $fechaDesdeRango, int $fechaHastaExclusiva): bool
    {
        $fechaHasta = $this->fechaAnterior($fechaHastaExclusiva);
        if ($fechaHasta < $fechaDesdeRango) {
            return false;
        }

        $errores = [];
        $filas = $this->listar(
            'contab',
            'ctamov',
            'ctav_tipo_asiento',
            ' WHERE ctav_empresa='.$empresaId
            .' AND ctav_tipo_asiento=\''.MayorPlanoCuentaSupport::TIPO_ASIENTO_APERTURA.'\''
            .' AND ctav_fecha BETWEEN '.$fechaDesdeRango.' AND '.$fechaHasta,
            $errores,
            'ape-detect-empresa-'.$empresaId,
        );

        return $filas !== [];
    }

    /**
     * Carga ctamov + subdiario filtrados por cuenta. No trae pago/auxpag
     * (se cargan bajo demanda solo si hay OP en el período consultado).
     *
     * @param  list<int>  $empresaIds
     * @param  list<int>  $cuentas
     * @return array{
     *   ctamov: list<object>,
     *   subdiario: list<object>,
     *   pago: list<object>,
     *   auxpag: list<object>,
     *   errores: list<string>,
     *   timings: array<string, float>
     * }
     */
    public function cargarPeriodo(
        array $empresaIds,
        int $fechaDesde,
        int $fechaHasta,
        int $fechaSaldoDesde,
        bool $incluyeSubdiario,
        int $cuentaDesde = 0,
        int $cuentaHasta = 0,
        array $cuentas = [],
        bool $soloMovimientosVentas = false,
    ): array {
        $t0 = microtime(true);
        $errores = [];
        $ctamov = [];
        $subdiario = [];
        $timings = [];

        $filtroCtamov = $this->filtroCuentasSql('ctav_cuenta', $cuentaDesde, $cuentaHasta, $cuentas);
        $condCuentaSubd = $this->condicionCuentas('subd_cuenta', $cuentaDesde, $cuentaHasta, $cuentas);
        $condContraSubd = $this->condicionCuentas('subd_contrapartida', $cuentaDesde, $cuentaHasta, $cuentas);
        $filtroSubdiario = $condCuentaSubd !== ''
            ? ' AND ('.$condCuentaSubd.' OR '.$condContraSubd.')'
            : '';
        if ($soloMovimientosVentas) {
            $filtroCtamov .= MayorPlanoCuentaVentasFiltroSupport::condicionSqlSistema('ctav_sistema');
            $filtroSubdiario .= MayorPlanoCuentaVentasFiltroSupport::condicionSqlSistema('subd_sistema');
        }

        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }

            $tEmp = microtime(true);
            $fechaSaldoHasta = $this->fechaAnterior($fechaDesde);

            if ($fechaSaldoHasta >= $fechaSaldoDesde) {
                $ctamov = array_merge(
                    $ctamov,
                    $this->listar(
                        'contab',
                        'ctamov',
                        self::CTAMOV_CAMPOS,
                        ' WHERE ctav_empresa='.$empresaId
                        .' AND ctav_fecha BETWEEN '.$fechaSaldoDesde.' AND '.$fechaSaldoHasta
                        .$filtroCtamov,
                        $errores,
                        'ctamov-saldo-empresa-'.$empresaId,
                    ),
                );
            }

            $ctamov = array_merge(
                $ctamov,
                $this->listar(
                    'contab',
                    'ctamov',
                    self::CTAMOV_CAMPOS,
                    ' WHERE ctav_empresa='.$empresaId
                    .' AND ctav_fecha BETWEEN '.$fechaDesde.' AND '.$fechaHasta
                    .$filtroCtamov,
                    $errores,
                    'ctamov-periodo-empresa-'.$empresaId,
                ),
            );

            if ($incluyeSubdiario) {
                if ($fechaSaldoHasta >= $fechaSaldoDesde) {
                    $subdiario = array_merge(
                        $subdiario,
                        $this->listar(
                            'contab',
                            'subdiario',
                            self::SUBDIARIO_CAMPOS,
                            ' WHERE subd_empresa='.$empresaId
                            .' AND subd_fecha BETWEEN '.$fechaSaldoDesde.' AND '.$fechaSaldoHasta
                            .$filtroSubdiario,
                            $errores,
                            'subdiario-saldo-empresa-'.$empresaId,
                        ),
                    );
                }

                $subdiario = array_merge(
                    $subdiario,
                    $this->listar(
                        'contab',
                        'subdiario',
                        self::SUBDIARIO_CAMPOS,
                        ' WHERE subd_empresa='.$empresaId
                        .' AND subd_fecha BETWEEN '.$fechaDesde.' AND '.$fechaHasta
                        .$filtroSubdiario,
                        $errores,
                        'subdiario-periodo-empresa-'.$empresaId,
                    ),
                );
            }

            $timings['empresa_'.$empresaId.'_ms'] = round((microtime(true) - $tEmp) * 1000, 1);
        }

        $timings['ctamov_subdiario_ms'] = round((microtime(true) - $t0) * 1000, 1);
        $timings['ctamov_filas'] = count($ctamov);
        $timings['subdiario_filas'] = count($subdiario);

        return [
            'ctamov' => $ctamov,
            'subdiario' => $subdiario,
            'pago' => [],
            'auxpag' => [],
            'errores' => $errores,
            'timings' => $timings,
        ];
    }

    /**
     * Carga pago + auxpag solo del período consultado (no del tramo de saldo inicial).
     *
     * @param  list<int>  $empresaIds
     * @param  list<string>  $errores
     * @return array{pago: list<object>, auxpag: list<object>, timings: array<string, float>}
     */
    public function cargarPagoYAuxpagPeriodo(
        array $empresaIds,
        int $fechaDesde,
        int $fechaHasta,
        array &$errores,
    ): array {
        $t0 = microtime(true);
        $pago = [];
        $auxpag = [];

        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }

            $pago = array_merge(
                $pago,
                $this->listarPago($empresaId, $fechaDesde, $fechaHasta, $errores),
            );

            $auxpag = array_merge(
                $auxpag,
                $this->listar(
                    'che_ban',
                    'auxpag',
                    self::AUXPAG_CAMPOS,
                    ' WHERE axp_empresa='.$empresaId
                    .' AND axp_fecha BETWEEN '.$fechaDesde.' AND '.$fechaHasta,
                    $errores,
                    'auxpag-empresa-'.$empresaId,
                ),
            );
        }

        return [
            'pago' => $pago,
            'auxpag' => $auxpag,
            'timings' => [
                'pago_auxpag_ms' => round((microtime(true) - $t0) * 1000, 1),
                'pago_filas' => count($pago),
                'auxpag_filas' => count($auxpag),
            ],
        ];
    }

    /**
     * @param  list<object>  $ctamov
     * @param  list<object>  $subdiario
     */
    public function hayOrdenesPagoEnMovimientos(array $ctamov, array $subdiario): bool
    {
        foreach ($ctamov as $linea) {
            if (MayorPlanoCuentaSupport::esTipoOrdenPago((string) ($linea->ctav_tipo ?? ''))) {
                return true;
            }
        }

        foreach ($subdiario as $linea) {
            $tipo = trim((string) ($linea->subd_ref_tipo ?? ''));
            if ($tipo === '') {
                $tipo = trim((string) ($linea->subd_tipo ?? ''));
            }
            if (MayorPlanoCuentaSupport::esTipoOrdenPago($tipo)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $errores
     * @return list<object>
     */
    private function listarPago(int $empresaId, int $fechaDesde, int $fechaHasta, array &$errores): array
    {
        return $this->listar(
            'che_ban',
            'pago',
            'pag_empresa,pag_fecha,pag_tipo,pag_rec,pag_sucursal,pag_leyenda',
            ' WHERE pag_empresa='.$empresaId
            .' AND pag_fecha BETWEEN '.$fechaDesde.' AND '.$fechaHasta,
            $errores,
            'pago-empresa-'.$empresaId.'-'.$fechaDesde.'-'.$fechaHasta,
        );
    }

    /**
     * @param  list<string>  $errores
     * @return list<object>
     */
    private function listar(
        string $sistema,
        string $tabla,
        string $campos,
        string $whereArmado,
        array &$errores,
        string $etiqueta,
    ): array {
        $t0 = microtime(true);
        $raw = $this->api->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $whereArmado,
        ]);

        $msg = ApiAnita::extraerMensajeError($raw);
        if ($msg !== null) {
            $errores[] = $etiqueta.': '.$msg;
            Log::info('mayor_plano_cuenta.bridge', [
                'etiqueta' => $etiqueta,
                'ms' => round((microtime(true) - $t0) * 1000, 1),
                'error' => $msg,
            ]);

            return [];
        }

        $filas = ApiAnita::decodificarListaFilas($raw);
        Log::info('mayor_plano_cuenta.bridge', [
            'etiqueta' => $etiqueta,
            'ms' => round((microtime(true) - $t0) * 1000, 1),
            'filas' => count($filas),
            'where' => mb_substr($whereArmado, 0, 220),
        ]);

        return $filas;
    }

    /**
     * Condición SQL por cuentas particulares (IN) y/o rango (BETWEEN).
     * Sin filtro → '' (todas las cuentas).
     *
     * @param  list<int>  $cuentas
     */
    private function condicionCuentas(string $columna, int $cuentaDesde, int $cuentaHasta, array $cuentas): string
    {
        $cuentas = array_values(array_unique(array_filter(array_map('intval', $cuentas), fn (int $c) => $c > 0)));
        sort($cuentas);

        $partes = [];
        if ($cuentas !== []) {
            $partes[] = $columna.' IN ('.implode(',', $cuentas).')';
        }

        $rango = $this->condicionRangoCuenta($columna, $cuentaDesde, $cuentaHasta);
        if ($rango !== '') {
            $partes[] = $rango;
        }

        if ($partes === []) {
            return '';
        }

        if (count($partes) === 1) {
            return $partes[0];
        }

        return '('.implode(' OR ', $partes).')';
    }

    /**
     * @param  list<int>  $cuentas
     */
    private function filtroCuentasSql(string $columna, int $cuentaDesde, int $cuentaHasta, array $cuentas): string
    {
        $cond = $this->condicionCuentas($columna, $cuentaDesde, $cuentaHasta, $cuentas);

        return $cond !== '' ? ' AND '.$cond : '';
    }

    private function condicionRangoCuenta(string $columna, int $cuentaDesde, int $cuentaHasta): string
    {
        if ($cuentaDesde <= 0 && $cuentaHasta <= 0) {
            return '';
        }

        if ($cuentaDesde > 0 && $cuentaHasta > 0) {
            return $columna.' BETWEEN '.$cuentaDesde.' AND '.$cuentaHasta;
        }

        if ($cuentaDesde > 0) {
            return $columna.'>='.$cuentaDesde;
        }

        return $columna.'<='.$cuentaHasta;
    }

    private function fechaAnterior(int $fechaYmd): int
    {
        $s = str_pad((string) $fechaYmd, 8, '0', STR_PAD_LEFT);
        $dt = \DateTime::createFromFormat('Ymd', $s);
        if ($dt === false) {
            return $fechaYmd;
        }
        $dt->modify('-1 day');

        return (int) $dt->format('Ymd');
    }
}
