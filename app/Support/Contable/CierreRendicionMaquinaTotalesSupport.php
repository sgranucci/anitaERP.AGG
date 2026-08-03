<?php

namespace App\Support\Contable;

use App\Models\Caja\AperturaGastoEmpresa;
use App\Models\Caja\Flash\FlashCaja;
use App\Models\Caja\RendicionMaquina;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaTurno;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Totales diarios del cierre máquinas (réplica p-vtamaquina.c un_dia / genera_asiento).
 */
final class CierreRendicionMaquinaTotalesSupport
{
    /** @var list<int> */
    private const CODIGOS_TOTALCOIN = [25, 76, 100];

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
            self::acumularDesdeRendicion($rendicion, $tot, $gastosApertura, $empresaId);
        }

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
        $tot['tot_caja_trans'] = round(
            $tot['deposito']
            + $tot['variacion_ff']
            - $resultadoReal
            - $tot['impuesto_esp']
            - $tot['sobrante_caja'],
            2,
        );
        $tot['valores_cuenta'] = array_values($tot['valores_cuenta']);

        return $tot;
    }

    /**
     * @param  array<string, mixed>  $tot
     * @param  array<string, array<string, mixed>>  $gastosApertura
     */
    private static function acumularDesdeRendicion(
        RendicionMaquina $rendicion,
        array &$tot,
        array &$gastosApertura,
        int $empresaId,
    ): void {
        $tot['vales'] = round($tot['vales'] + self::input($rendicion, 'vales'), 2);
        $tot['reintegros'] = round($tot['reintegros'] + self::input($rendicion, 'reintegros'), 2);
        $tot['sobrantes'] = round($tot['sobrantes'] + self::input($rendicion, 'sobrantes'), 2);
        $tot['variacion_ff'] = round($tot['variacion_ff'] + self::input($rendicion, 'variacion_ff'), 2);
        $tot['pago_diferido'] = round($tot['pago_diferido'] + self::input($rendicion, 'pago_diferido'), 2);
        $tot['vta_ant_gastro'] = round($tot['vta_ant_gastro'] + self::input($rendicion, 'vta_ant_gastro'), 2);
        $tot['ticket_prom'] = round($tot['ticket_prom'] + self::input($rendicion, 'ticket_prom'), 2);

        $impDrop = self::input($rendicion, 'impuesto_drop');
        $impVenta = self::input($rendicion, 'impuesto_venta');
        $impQr = self::input($rendicion, 'impuesto_qr');
        $impPago = self::input($rendicion, 'impuesto_pago');
        $tot['impuesto_drop'] = round($tot['impuesto_drop'] + $impDrop, 2);
        $tot['impuesto_venta'] = round($tot['impuesto_venta'] + $impVenta, 2);
        $tot['impuesto_qr'] = round($tot['impuesto_qr'] + $impQr, 2);
        $tot['impuesto_pago'] = round($tot['impuesto_pago'] + $impPago, 2);
        $tot['impuesto_esp'] = round($tot['impuesto_esp'] + $impDrop + $impVenta + $impQr, 2);
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

        $rodillo = self::calc($rendicion, 'resultado_rodillo');
        $ruleta = self::calc($rendicion, 'resultado_ruleta');
        $tot['maquinas_real'] = round($tot['maquinas_real'] + $rodillo, 2);
        $tot['ruletas_real'] = round($tot['ruletas_real'] + $ruleta, 2);

        self::acumularValores($rendicion, $tot, $empresaId);
        self::acumularGastosApertura($rendicion, $gastosApertura, $empresaId);
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
            $codigoValormae = (int) ($valor->codigo_valormae ?? 0);
            $cotizacion = (float) ($valor->cotizacion ?? 1);
            if ($cotizacion <= 0) {
                $cotizacion = 1.0;
            }

            $nombreCodigo = strtolower(trim(
                (string) ($caja?->codigo ?? '').' '
                .(string) ($caja?->nombre ?? '').' '
                .(string) ($caja?->descripcion_operaciones ?? ''),
            ));

            if (self::esTotalcoin($nombreCodigo, $codigoValormae)) {
                $tot['totalcoin'] = round($tot['totalcoin'] + $monto, 2);

                continue;
            }

            if (self::contieneAlguno($nombreCodigo, ['visa', 'master', 'electron', 'maestro'])) {
                $tot['tarjetas'] = round($tot['tarjetas'] + $monto, 2);

                continue;
            }

            if (self::contieneAlguno($nombreCodigo, ['mep'])) {
                $tot['mep'] = round($tot['mep'] + $monto, 2);

                continue;
            }

            $moneda = strtoupper(trim((string) ($caja?->monedas?->codigo ?? $caja?->monedas?->nombre ?? '')));
            if (self::contieneAlguno($moneda, ['USD', 'DOL', 'U$S', 'DOLAR'])) {
                $tot['dolares_en_pesos'] = round($tot['dolares_en_pesos'] + ($monto * $cotizacion), 2);

                continue;
            }
            if (self::contieneAlguno($moneda, ['EUR', 'EURO'])) {
                $tot['euros_en_pesos'] = round($tot['euros_en_pesos'] + ($monto * $cotizacion), 2);

                continue;
            }
            if (self::contieneAlguno($nombreCodigo, ['cripto', 'crypto', 'bitcoin', 'btc'])) {
                $tot['cripto_en_pesos'] = round($tot['cripto_en_pesos'] + ($monto * $cotizacion), 2);

                continue;
            }

            if (self::esValorCuentaFinanciera($codigoValormae, $nombreCodigo)) {
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
                        $tot['valores_cuenta'][$key]['monto'] + $monto,
                        2,
                    );
                }

                continue;
            }

            $tot['efectivo'] = round($tot['efectivo'] + $monto, 2);
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

    private static function esTotalcoin(string $nombreCodigo, int $codigoValormae): bool
    {
        if (in_array($codigoValormae, self::CODIGOS_TOTALCOIN, true)) {
            return true;
        }

        return self::contieneAlguno($nombreCodigo, ['totalcoin', 'total coin']);
    }

    private static function esValorCuentaFinanciera(int $codigoValormae, string $nombreCodigo): bool
    {
        if (in_array($codigoValormae, self::CODIGOS_TOTALCOIN, true)) {
            return false;
        }

        return self::contieneAlguno($nombreCodigo, ['ticket', 'varios', ' qr', 'qr '])
            || in_array((string) $codigoValormae, ['3', '4', '5', '6', '7', '9'], true);
    }

    /**
     * @param  list<string>  $terminos
     */
    private static function contieneAlguno(string $texto, array $terminos): bool
    {
        foreach ($terminos as $termino) {
            if ($termino !== '' && str_contains($texto, strtolower($termino))) {
                return true;
            }
        }

        return false;
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
            'tarjetas' => 0.0,
            'mep' => 0.0,
            'totalcoin' => 0.0,
            'dolares_en_pesos' => 0.0,
            'euros_en_pesos' => 0.0,
            'cripto_en_pesos' => 0.0,
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
