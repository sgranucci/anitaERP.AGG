<?php

namespace App\Support\Contable\MayorPlanoCuenta;

use App\ApiAnita;

/**
 * Carga ctamov y subdiario vía bridge Anita para el mayor plano por cuenta.
 */
class MayorPlanoCuentaAnitaBridgeReader
{
    private const CTAMOV_CAMPOS = 'ctav_empresa,ctav_nro_asiento,ctav_nro_linea,ctav_d_h,ctav_cuenta,ctav_fecha,'
        .'ctav_tipo,ctav_letra,ctav_sucursal,ctav_nro,ctav_importe,ctav_desc_mov,ctav_cotizacion,ctav_cod_mon,'
        .'ctav_sistema,ctav_tipo_asiento,ctav_ccosto,ctav_balancea,ctav_o_compra,ctav_asi_mon_ref';

    private const SUBDIARIO_CAMPOS = 'subd_empresa,subd_sistema,subd_fecha,subd_tipo,subd_letra,subd_sucursal,subd_nro,'
        .'subd_emisor,subd_tipo_mov,subd_cuenta,subd_contrapartida,subd_nro_operacion,subd_ref_tipo,subd_ref_letra,'
        .'subd_ref_sucursal,subd_ref_nro,subd_importe,subd_cod_mon,subd_cotizacion,subd_desc_mov,subd_nro_asiento,'
        .'subd_nro_interno,subd_ccosto_cta,subd_ccosto_con';

    private const AUXPAG_CAMPOS = 'axp_pro,axp_fecha,axp_rec,axp_tipo,axp_nro,axp_tipo_ap,axp_monto_ap,axp_cod_mon_co,'
        .'axp_sucursal,axp_empresa,axp_letra_comp,axp_nro_interno,axp_banco,axp_concepto';

    public function __construct(
        private readonly ApiAnita $api = new ApiAnita(),
    ) {
    }

    /**
     * Resuelve desde qué fecha cargar movimientos de saldo inicial.
     * Si hay APE en el ejercicio del período → desde comienzo de ese ejercicio;
     * si no → ejercicio anterior (l-mayor.c + regla Biyemas desde 01/01/25).
     *
     * @param  list<int>  $empresaIds
     */
    public function resolverFechaSaldoDesde(
        array $empresaIds,
        int $fechaDesde,
        int $fechaComienzoEjercicioAjustada,
    ): int {
        $fechas = [];

        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }

            $fechaSaldo = $fechaComienzoEjercicioAjustada;
            if (! $this->existeAsientoAperturaEnRango($empresaId, $fechaComienzoEjercicioAjustada, $fechaDesde)) {
                $fechaSaldo = MayorPlanoCuentaSupport::ejercicioAnterior($fechaComienzoEjercicioAjustada);
            }

            $fechas[] = max(MayorPlanoCuentaSupport::SALDO_ORIGEN_MINIMO_YMD, $fechaSaldo);
        }

        return MayorPlanoCuentaSupport::consolidarFechaSaldoDesde($fechas);
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

            $porEmpresa[$empresaId] = [
                'ape_en_ejercicio_actual' => $apeEnEjercicio,
                'fecha_saldo_desde' => $fechaSaldo,
            ];
        }

        return [
            'fecha_comienzo_ejercicio' => MayorPlanoCuentaSupport::inicioEjercicio($fechaDesde),
            'fecha_comienzo_ajustada' => $fechaComienzoEjercicioAjustada,
            'fecha_saldo_desde' => $this->resolverFechaSaldoDesde(
                $empresaIds,
                $fechaDesde,
                $fechaComienzoEjercicioAjustada,
            ),
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
     * @param  list<int>  $empresaIds
     * @return array{
     *   ctamov: list<object>,
     *   subdiario: list<object>,
     *   pago: list<object>,
     *   auxpag: list<object>,
     *   errores: list<string>
     * }
     */
    public function cargarPeriodo(
        array $empresaIds,
        int $fechaDesde,
        int $fechaHasta,
        int $fechaSaldoDesde,
        bool $incluyeSubdiario,
    ): array {
        $errores = [];
        $ctamov = [];
        $subdiario = [];
        $pago = [];
        $auxpag = [];

        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }

            $fechaSaldoHasta = $this->fechaAnterior($fechaDesde);
            if ($fechaSaldoHasta >= $fechaSaldoDesde) {
                $ctamov = array_merge(
                    $ctamov,
                    $this->listar(
                        'contab',
                        'ctamov',
                        self::CTAMOV_CAMPOS,
                        ' WHERE ctav_empresa='.$empresaId
                        .' AND ctav_fecha BETWEEN '.$fechaSaldoDesde.' AND '.$fechaSaldoHasta,
                        $errores,
                        'ctamov-saldo-empresa-'.$empresaId,
                    ),
                );

                $pago = array_merge(
                    $pago,
                    $this->listarPago($empresaId, $fechaSaldoDesde, $fechaSaldoHasta, $errores),
                );
            }

            $ctamov = array_merge(
                $ctamov,
                $this->listar(
                    'contab',
                    'ctamov',
                    self::CTAMOV_CAMPOS,
                    ' WHERE ctav_empresa='.$empresaId
                    .' AND ctav_fecha BETWEEN '.$fechaDesde.' AND '.$fechaHasta,
                    $errores,
                    'ctamov-periodo-empresa-'.$empresaId,
                ),
            );

            $pago = array_merge(
                $pago,
                $this->listarPago($empresaId, $fechaDesde, $fechaHasta, $errores),
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
                            .' AND subd_fecha BETWEEN '.$fechaSaldoDesde.' AND '.$fechaSaldoHasta,
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
                        .' AND subd_fecha BETWEEN '.$fechaDesde.' AND '.$fechaHasta,
                        $errores,
                        'subdiario-periodo-empresa-'.$empresaId,
                    ),
                );
            }

            $auxpag = array_merge(
                $auxpag,
                $this->listar(
                    'che_ban',
                    'auxpag',
                    self::AUXPAG_CAMPOS,
                    ' WHERE axp_empresa='.$empresaId
                    .' AND axp_fecha BETWEEN '.$fechaSaldoDesde.' AND '.$fechaHasta,
                    $errores,
                    'auxpag-empresa-'.$empresaId,
                ),
            );
        }

        return [
            'ctamov' => $ctamov,
            'subdiario' => $subdiario,
            'pago' => $pago,
            'auxpag' => $auxpag,
            'errores' => $errores,
        ];
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

            return [];
        }

        return ApiAnita::decodificarListaFilas($raw);
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
