<?php

namespace App\Support\Contable;

use App\ApiAnita;
use App\Models\Caja\AperturaGastoEmpresa;
use App\Models\Caja\Flash\FlashCaja;
use App\Models\Caja\RendicionMaquina;
use App\Support\Caja\CotizacionTesoreriaConsultaSupport;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaTurno;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaValoresCuentacajaSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Totales diarios del cierre máquinas (réplica p-vtamaquina.c un_dia / genera_asiento).
 */
final class CierreRendicionMaquinaTotalesSupport
{
    /**
     * @param  Collection<int, RendicionMaquina>  $rendiciones
     * @return array<string, mixed>
     */
    public static function calcular(Collection $rendiciones, int $empresaId, string $fechaDia): array
    {
        $rendicionesCierre = $rendiciones->filter(
            fn (RendicionMaquina $r) => (string) ($r->turno ?? '') === RendicionMaquinaTurno::COMPLETO,
        );

        if ($rendicionesCierre->isEmpty()) {
            return self::estructuraVacia($empresaId, $fechaDia);
        }

        $rendicionesCierre->loadMissing([
            'valores.cuentacaja.monedas',
            'valores.cuentacaja.cuentacontables',
            'gastos.aperturaGasto',
        ]);

        $tot = self::inicializarAcumuladores();
        $gastosApertura = [];

        foreach ($rendicionesCierre as $rendicion) {
            self::acumularDesdeRendicionCierre($rendicion, $tot, $gastosApertura, $empresaId);
        }

        self::acumularTurnosNoCierre($tot, $empresaId, $fechaDia);
        $tot['impuesto_qr'] = self::impuestoQrDiaSiguiente($empresaId, $fechaDia);
        $tot['impuesto_esp'] = round(
            $tot['impuesto_drop'] + $tot['impuesto_venta'] + $tot['impuesto_qr'],
            2,
        );

        $flash = FlashCaja::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', Carbon::parse($fechaDia)->toDateString())
            ->first(['win_ol_slot', 'win_ol_rul']);

        $tot['maquinas_online'] = round((float) ($flash?->win_ol_slot ?? 0), 2);
        $tot['ruletas_online'] = round((float) ($flash?->win_ol_rul ?? 0), 2);
        $tot['resultado_online'] = round($tot['maquinas_online'] + $tot['ruletas_online'], 2);
        $tot['fsl_monto'] = $tot['resultado_online'];
        $tot['gastos_apertura'] = array_values($gastosApertura);

        $resultadoReal = round($tot['maquinas_real'] + $tot['ruletas_real'], 2);
        $tot['resultado_real'] = $resultadoReal;
        $tot['diferencia_real_online'] = round($resultadoReal - $tot['resultado_online'], 2);
        $tot['tot_caja_trans'] = round(
            $tot['deposito']
            + $tot['variacion_ff']
            - $resultadoReal
            - $tot['impuesto_esp']
            - $tot['sobrante_caja'],
            2,
        );
        $tot['efectivo_euro'] = round($tot['efectivo'] + $tot['euros_en_pesos'], 2);

        // Cotizaciones del día (listado Anita imprime cot aunque no haya ME en el día).
        if ($tot['cot_dolar'] <= 0.0001) {
            $tot['cot_dolar'] = round(
                CotizacionTesoreriaConsultaSupport::calculaVenta($fechaDia, 2, $empresaId),
                4,
            );
        }
        if ($tot['cot_euro'] <= 0.0001) {
            $tot['cot_euro'] = round(
                CotizacionTesoreriaConsultaSupport::calculaVenta($fechaDia, 3, $empresaId),
                4,
            );
        }

        $tot['valores_cuenta'] = array_values($tot['valores_cuenta']);

        return $tot;
    }

    /**
     * Resultado rodillo de un_dia (p-vtamaquina.c): drop bruto − impuesto + QR − salidas.
     */
    public static function resultadoRodilloUnDia(RendicionMaquina $rendicion): float
    {
        $dropBruto = self::dropBilleteBruto($rendicion);
        $ingreso = self::input($rendicion, 'venta_ficha')
            + $dropBruto
            - self::input($rendicion, 'impuesto_drop')
            + self::dropemRodillo($rendicion)
            + self::input($rendicion, 'dropqr_rodillo');
        $salida = self::input($rendicion, 'pago_manual')
            + self::input($rendicion, 'tito')
            + self::input($rendicion, 'hopper');

        return round($ingreso - $salida, 2);
    }

    /**
     * Resultado ruleta de un_dia (p-vtamaquina.c). Usa drop del día, no drop_rul_ant.
     */
    public static function resultadoRuletaUnDia(RendicionMaquina $rendicion): float
    {
        $ingreso = self::input($rendicion, 'venta_ruleta')
            + self::input($rendicion, 'drop_ruleta')
            + self::dropemRuleta($rendicion)
            + self::input($rendicion, 'dropqr_ruleta');
        $salida = self::input($rendicion, 'salida_ruleta')
            + self::input($rendicion, 'tito_ruleta');

        return round($ingreso - $salida, 2);
    }

    /**
     * Impuesto QR del Completo del día siguiente (lee_impuesto_dia_siguiente).
     */
    public static function impuestoQrDiaSiguiente(int $empresaId, string $fechaDia): float
    {
        $siguiente = Carbon::parse($fechaDia)->addDay()->toDateString();
        $rendicion = RendicionMaquina::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', $siguiente)
            ->where('turno', RendicionMaquinaTurno::COMPLETO)
            ->where('estado', '!=', RendicionMaquina::ESTADO_ANULADA)
            ->orderBy('id')
            ->first();

        if ($rendicion !== null) {
            return self::input($rendicion, 'impuesto_qr');
        }

        return self::impuestoQrDiaSiguienteAnita($empresaId, $siguiente);
    }

    /**
     * Fallback Anita cuando el Completo del día siguiente aún no está en ERP.
     */
    private static function impuestoQrDiaSiguienteAnita(int $empresaId, string $fechaYmd): float
    {
        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        $fechaEntera = (int) str_replace('-', '', $fechaYmd);
        if ($empresaAnita <= 0 || $fechaEntera <= 0) {
            return 0.0;
        }

        try {
            $raw = (new ApiAnita)->apiCall([
                'acc' => 'list',
                'sistema' => (string) config('rendicion_maquina_anita.sistema', 'caja'),
                'tabla' => (string) config('rendicion_maquina_anita.tabla_cabecera', 'rendmaquina'),
                'campos' => 'rendm_nro_oper,rendm_turno,rendm_impuesto_qr',
                'whereArmado' => ' WHERE rendm_empresa='.$empresaAnita
                    .' AND rendm_fecha='.$fechaEntera
                    ." AND (rendm_turno='C' OR rendm_turno='3')",
            ]);
            $filas = ApiAnita::decodificarListaFilas(is_string($raw) ? $raw : json_encode($raw));
        } catch (Throwable $e) {
            Log::warning('Cierre máquinas: no se pudo leer impuesto QR día siguiente en Anita', [
                'empresa_id' => $empresaId,
                'fecha' => $fechaYmd,
                'error' => $e->getMessage(),
            ]);

            return 0.0;
        }

        $total = 0.0;
        foreach ($filas as $fila) {
            $total += (float) ($fila->rendm_impuesto_qr ?? 0);
        }

        return round($total, 2);
    }

    /**
     * @param  array<string, mixed>  $tot
     * @param  array<string, array<string, mixed>>  $gastosApertura
     */
    private static function acumularDesdeRendicionCierre(
        RendicionMaquina $rendicion,
        array &$tot,
        array &$gastosApertura,
        int $empresaId,
    ): void {
        $tot['vales'] = round($tot['vales'] + self::input($rendicion, 'vales'), 2);
        $tot['reintegros'] = round($tot['reintegros'] + self::input($rendicion, 'reintegros'), 2);
        $tot['sobrantes'] = round($tot['sobrantes'] + self::input($rendicion, 'sobrantes'), 2);
        $tot['variacion_ff'] = round($tot['variacion_ff'] + self::input($rendicion, 'variacion_ff'), 2);
        $tot['vta_ant_gastro'] = round($tot['vta_ant_gastro'] + self::input($rendicion, 'vta_ant_gastro'), 2);
        $tot['ticket_prom'] = round($tot['ticket_prom'] + self::input($rendicion, 'ticket_prom'), 2);

        $impDrop = self::input($rendicion, 'impuesto_drop');
        $impVenta = self::input($rendicion, 'impuesto_venta');
        $impPago = self::input($rendicion, 'impuesto_pago');
        $tot['impuesto_drop'] = round($tot['impuesto_drop'] + $impDrop, 2);
        $tot['impuesto_venta'] = round($tot['impuesto_venta'] + $impVenta, 2);
        $tot['impuesto_pago'] = round($tot['impuesto_pago'] + $impPago, 2);
        $tot['ticket_gastro'] = round($tot['ticket_gastro'] + $impPago, 2);

        $deposito = self::calc($rendicion, 'deposito');
        if (abs($deposito) <= 0.0001) {
            $deposito = self::input($rendicion, 'deposito');
        }
        $tot['deposito'] = round($tot['deposito'] + $deposito, 2);

        $difCaja = round((float) ($rendicion->dif_caja ?? 0), 2);
        if (abs($difCaja) <= 0.0001) {
            $difCaja = self::calc($rendicion, 'dif_caja');
        }
        $tot['dif_caja'] = round($tot['dif_caja'] + $difCaja, 2);

        $tot['maquinas_real'] = round($tot['maquinas_real'] + self::resultadoRodilloUnDia($rendicion), 2);
        $tot['ruletas_real'] = round($tot['ruletas_real'] + self::resultadoRuletaUnDia($rendicion), 2);

        self::acumularValores($rendicion, $tot, $empresaId);
        self::acumularGastosApertura($rendicion, $gastosApertura, $empresaId);
    }

    /**
     * p-vtamaquina.c: pago_diferido y sobrante_caja salen de turnos ≠ Completo.
     *
     * @param  array<string, mixed>  $tot
     */
    private static function acumularTurnosNoCierre(array &$tot, int $empresaId, string $fechaDia): void
    {
        $otras = RendicionMaquina::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', Carbon::parse($fechaDia)->toDateString())
            ->where('turno', '!=', RendicionMaquinaTurno::COMPLETO)
            ->where('estado', '!=', RendicionMaquina::ESTADO_ANULADA)
            ->get();

        foreach ($otras as $rendicion) {
            $tot['sobrante_caja'] = round($tot['sobrante_caja'] + self::input($rendicion, 'sobrantes'), 2);
            $tot['pago_diferido'] = round($tot['pago_diferido'] + self::input($rendicion, 'pago_diferido'), 2);
        }
    }

    /**
     * @param  array<string, mixed>  $tot
     */
    private static function acumularValores(RendicionMaquina $rendicion, array &$tot, int $empresaId): void
    {
        foreach ($rendicion->valores as $valor) {
            $monto = round((float) ($valor->monto ?? 0), 2);
            if (abs($monto) <= 0.0001) {
                continue;
            }

            $caja = $valor->cuentacaja;
            $mae = CierreRendicionMaquinaValormaeSupport::resolver(
                (int) ($valor->codigo_valormae ?? 0),
                $caja,
            );
            $codigoMae = (int) $mae['codigo'];
            $tipo = (string) $mae['tipo'];

            $monedaId = (int) ($caja?->moneda_id ?? 1);
            $cotizacion = (float) ($valor->cotizacion ?? 0);
            if (! RendicionMaquinaValoresCuentacajaSupport::cotizacionUsable($cotizacion, $monedaId)) {
                $fechaYmd = $rendicion->fecha?->format('Y-m-d') ?? date('Y-m-d');
                $cotizacion = CotizacionTesoreriaConsultaSupport::calculaVenta(
                    $fechaYmd,
                    $monedaId,
                    $empresaId
                );
            }
            $montoPesos = RendicionMaquinaValoresCuentacajaSupport::montoEnPesos($monedaId, $monto, $cotizacion);

            if (CierreRendicionMaquinaValormaeSupport::esPlayuzu($codigoMae)) {
                $tot['playuzu'] = round($tot['playuzu'] + $montoPesos, 2);
            }

            // Códigos 25/76/100: signo invertido + contrapartida caja pesos (Anita).
            // La cuenta sale de la cuentacaja del valor (no del slot fijo), p.ej. 113010011.
            if (CierreRendicionMaquinaValormaeSupport::esTotalcoinSlot($codigoMae)) {
                $tot['totalcoin'] = round($tot['totalcoin'] + $montoPesos, 2);
                if ($caja !== null) {
                    $cuentaTc = (int) (CuentacajaCuentacontableResolverSupport::resolverIdParaEmpresa($caja, $empresaId) ?? 0);
                    if ($cuentaTc > 0) {
                        $tot['totalcoin_cuenta_id'] = $cuentaTc;
                    }
                }

                continue;
            }

            if ($tipo === CierreRendicionMaquinaValormaeSupport::TIPO_TOTALCOIN_QR) {
                // Columna «Total coin» del listado Anita (tot_totalcoin_caja / VALM_TOTALCOIN_QR).
                $tot['totalcoin_caja'] = round($tot['totalcoin_caja'] + $montoPesos, 2);
            }

            // Asiento: todos los valores (pesos, ME, MEP, tarjetas, Macro, Totalcoin 21/22, etc.)
            // usan la cuentacontable de la cuentacaja — no slots fijos de impcont.
            $cuentaId = 0;
            if ($caja !== null) {
                $cuentaId = (int) (CuentacajaCuentacontableResolverSupport::resolverIdParaEmpresa($caja, $empresaId) ?? 0);
            }
            if ($cuentaId > 0) {
                $key = (string) $cuentaId;
                if (! isset($tot['valores_cuenta'][$key])) {
                    $tot['valores_cuenta'][$key] = [
                        'cuentacontable_id' => $cuentaId,
                        'concepto' => trim((string) ($caja?->etiquetaOperaciones() ?? 'Valor cuenta')),
                        'monto' => 0.0,
                    ];
                }
                $tot['valores_cuenta'][$key]['monto'] = round(
                    $tot['valores_cuenta'][$key]['monto'] + $montoPesos,
                    2,
                );
            }

            // Buckets informativos (listado / diagnóstico); el asiento no los postea.
            $textoCaja = CierreRendicionMaquinaValormaeSupport::textoCaja($caja);
            switch ($tipo) {
                case CierreRendicionMaquinaValormaeSupport::TIPO_TARJETA:
                    $tot['tarjetas'] = round($tot['tarjetas'] + $montoPesos, 2);
                    if (CierreRendicionMaquinaValormaeSupport::esVisaTexto($textoCaja)) {
                        $tot['visa'] = round($tot['visa'] + $montoPesos, 2);
                    } elseif (CierreRendicionMaquinaValormaeSupport::esMasterTexto($textoCaja)) {
                        $tot['master'] = round($tot['master'] + $montoPesos, 2);
                    }
                    break;
                case CierreRendicionMaquinaValormaeSupport::TIPO_MEP:
                    $tot['mep'] = round($tot['mep'] + $montoPesos, 2);
                    break;
                case CierreRendicionMaquinaValormaeSupport::TIPO_EFE_DOLAR:
                    $tot['dolares'] = round($tot['dolares'] + $monto, 2);
                    $tot['dolares_en_pesos'] = round($tot['dolares_en_pesos'] + $montoPesos, 2);
                    if ($cotizacion > 0.0001) {
                        $tot['cot_dolar'] = $cotizacion;
                    }
                    break;
                case CierreRendicionMaquinaValormaeSupport::TIPO_EFE_EURO:
                    $tot['euros'] = round($tot['euros'] + $monto, 2);
                    $tot['euros_en_pesos'] = round($tot['euros_en_pesos'] + $montoPesos, 2);
                    if ($cotizacion > 0.0001) {
                        $tot['cot_euro'] = $cotizacion;
                    }
                    break;
                case CierreRendicionMaquinaValormaeSupport::TIPO_EFE_CRIPTO:
                    $tot['cripto_en_pesos'] = round($tot['cripto_en_pesos'] + $montoPesos, 2);
                    break;
                case CierreRendicionMaquinaValormaeSupport::TIPO_EFE_PESOS:
                    $tot['efectivo'] = round($tot['efectivo'] + $monto, 2);
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $gastosApertura
     */
    private static function acumularGastosApertura(
        RendicionMaquina $rendicion,
        array &$gastosApertura,
        int $empresaId,
    ): void {
        if ($rendicion->gastos->isEmpty()) {
            return;
        }

        $aperturaIds = $rendicion->gastos
            ->pluck('apertura_gasto_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($aperturaIds === []) {
            return;
        }

        $configs = AperturaGastoEmpresa::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('apertura_gasto_id', $aperturaIds)
            ->get()
            ->keyBy('apertura_gasto_id');

        foreach ($rendicion->gastos as $linea) {
            $monto = round((float) ($linea->monto ?? 0), 2);
            if (abs($monto) <= 0.0001) {
                continue;
            }

            $aperturaId = (int) ($linea->apertura_gasto_id ?? 0);
            $cfg = $configs->get($aperturaId);
            $cuentaId = (int) ($cfg?->cuentacontable_id ?? 0);
            $contrapartidaId = (int) ($cfg?->cuentacontable_contrapartida_id ?? 0);
            $centrocostoId = (int) ($cfg?->centrocosto_id ?? 0);
            $codigoGasto = (int) ($linea->aperturaGasto?->codigo ?? 0);
            $key = $cuentaId.'|'.$contrapartidaId.'|'.$centrocostoId;

            if (! isset($gastosApertura[$key])) {
                $gastosApertura[$key] = [
                    'apertura_gasto_id' => $aperturaId,
                    'codigo_gasto' => $codigoGasto,
                    'descripcion' => trim((string) ($linea->aperturaGasto?->nombre ?? 'Gasto apertura')),
                    'cuentacontable_id' => $cuentaId,
                    'contrapartida_id' => $contrapartidaId,
                    'centrocosto_id' => $centrocostoId > 0 ? $centrocostoId : null,
                    'monto' => 0.0,
                ];
            }

            $gastosApertura[$key]['monto'] = round($gastosApertura[$key]['monto'] + $monto, 2);
        }
    }

    private static function dropBilleteBruto(RendicionMaquina $rendicion): float
    {
        $bruto = self::input($rendicion, 'drop_billete_bruto');
        if (abs($bruto) > 0.0001) {
            return $bruto;
        }

        return round(self::input($rendicion, 'drop_billete') + self::input($rendicion, 'impuesto_drop'), 2);
    }

    private static function dropemRodillo(RendicionMaquina $rendicion): float
    {
        $v = self::input($rendicion, 'dropem_rodillo');

        return abs($v) > 0.0001 ? $v : self::input($rendicion, 'billem_rodillo');
    }

    private static function dropemRuleta(RendicionMaquina $rendicion): float
    {
        $v = self::input($rendicion, 'dropem_ruleta');

        return abs($v) > 0.0001 ? $v : self::input($rendicion, 'billem_ruleta');
    }

    private static function input(RendicionMaquina $rendicion, string $clave): float
    {
        $inputs = is_array($rendicion->inputs_json) ? $rendicion->inputs_json : [];
        if (array_key_exists($clave, $inputs)) {
            return round((float) $inputs[$clave], 2);
        }
        $ruta = 'inputs.'.$clave;
        if (array_key_exists($ruta, $inputs)) {
            return round((float) $inputs[$ruta], 2);
        }

        return 0.0;
    }

    private static function calc(RendicionMaquina $rendicion, string $clave): float
    {
        $calcVars = is_array($rendicion->calc_json['variables'] ?? null)
            ? $rendicion->calc_json['variables']
            : [];
        if (array_key_exists($clave, $calcVars)) {
            return round((float) $calcVars[$clave], 2);
        }
        $ruta = 'calc.'.$clave;
        if (array_key_exists($ruta, $calcVars)) {
            return round((float) $calcVars[$ruta], 2);
        }

        return 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    private static function inicializarAcumuladores(): array
    {
        return [
            'vales' => 0.0,
            'reintegros' => 0.0,
            'sobrantes' => 0.0,
            'sobrante_caja' => 0.0,
            'variacion_ff' => 0.0,
            'pago_diferido' => 0.0,
            'impuesto_drop' => 0.0,
            'impuesto_venta' => 0.0,
            'impuesto_qr' => 0.0,
            'impuesto_pago' => 0.0,
            'impuesto_esp' => 0.0,
            'vta_ant_gastro' => 0.0,
            'ticket_prom' => 0.0,
            'ticket_gastro' => 0.0,
            'deposito' => 0.0,
            'dif_caja' => 0.0,
            'maquinas_real' => 0.0,
            'ruletas_real' => 0.0,
            'resultado_real' => 0.0,
            'maquinas_online' => 0.0,
            'ruletas_online' => 0.0,
            'resultado_online' => 0.0,
            'fsl_monto' => 0.0,
            'efectivo' => 0.0,
            'efectivo_euro' => 0.0,
            'tarjetas' => 0.0,
            'visa' => 0.0,
            'master' => 0.0,
            'mep' => 0.0,
            'totalcoin' => 0.0,
            'totalcoin_caja' => 0.0,
            'totalcoin_cuenta_id' => 0,
            'playuzu' => 0.0,
            'dolares' => 0.0,
            'dolares_en_pesos' => 0.0,
            'cot_dolar' => 0.0,
            'euros' => 0.0,
            'euros_en_pesos' => 0.0,
            'cot_euro' => 0.0,
            'cripto_en_pesos' => 0.0,
            'diferencia_real_online' => 0.0,
            'valores_cuenta' => [],
            'gastos_apertura' => [],
            'tot_caja_trans' => 0.0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function estructuraVacia(int $empresaId, string $fechaDia): array
    {
        $tot = self::inicializarAcumuladores();
        $flash = FlashCaja::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', Carbon::parse($fechaDia)->toDateString())
            ->first(['win_ol_slot', 'win_ol_rul']);
        $tot['maquinas_online'] = round((float) ($flash?->win_ol_slot ?? 0), 2);
        $tot['ruletas_online'] = round((float) ($flash?->win_ol_rul ?? 0), 2);
        $tot['resultado_online'] = round($tot['maquinas_online'] + $tot['ruletas_online'], 2);
        $tot['fsl_monto'] = $tot['resultado_online'];

        return $tot;
    }
}
