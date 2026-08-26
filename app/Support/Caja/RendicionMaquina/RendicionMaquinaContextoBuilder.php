<?php

namespace App\Support\Caja\RendicionMaquina;

/**
 * Arma el contexto del motor de cálculo desde el payload JSON de la pantalla.
 */
final class RendicionMaquinaContextoBuilder
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, float|int|string>
     */
    public static function desdePayload(array $payload): array
    {
        $empresaId = (int) ($payload['empresa_id'] ?? 0);
        $fecha = (string) ($payload['fecha'] ?? date('Y-m-d'));
        $turno = RendicionMaquinaTurno::normalizar((string) ($payload['turno'] ?? RendicionMaquinaTurno::MANIANA));

        $contexto = RendicionMaquinaVariables::defaultsVacios($turno);

        $inputs = is_array($payload['inputs'] ?? null) ? $payload['inputs'] : [];
        foreach ($inputs as $clave => $valor) {
            $ruta = str_starts_with((string) $clave, 'inputs.')
                ? (string) $clave
                : 'inputs.'.(string) $clave;
            $contexto[$ruta] = is_numeric($valor) ? (float) $valor : $valor;
        }

        $valoresTotales = self::armarValoresTotales(
            is_array($payload['valores'] ?? null) ? $payload['valores'] : []
        );
        foreach ($valoresTotales as $ruta => $valor) {
            $contexto[$ruta] = $valor;
        }

        $contexto['gastos.total'] = self::armarGastosTotal(
            is_array($payload['gastos'] ?? null) ? $payload['gastos'] : []
        );

        $orquestador = is_array($payload['calc_orquestador'] ?? null) ? $payload['calc_orquestador'] : [];
        $contexto['calc.comprobante'] = round((float) ($orquestador['comprobante'] ?? 0), 2);
        $contexto['calc.vale_rep_fondo'] = round((float) ($orquestador['vale_rep_fondo'] ?? 0), 2);
        // Completo: semillas Noche / suma M+T+N; D30 deja depósito efectivo en 0.
        if (array_key_exists('fondo_cierre', $orquestador)) {
            $contexto['calc.fondo_cierre'] = round((float) $orquestador['fondo_cierre'], 2);
        }
        if (array_key_exists('resultado_turno', $orquestador)) {
            $contexto['calc.resultado_turno'] = round((float) $orquestador['resultado_turno'], 2);
        }
        if (array_key_exists('transferencia', $orquestador)) {
            $contexto['calc.transferencia'] = round((float) $orquestador['transferencia'], 2);
        }

        $contexto['meta.empresa_id'] = $empresaId;
        $contexto['meta.fecha_ymd'] = str_replace('-', '', $fecha);
        $contexto['meta.turno'] = $turno;

        $previas = is_array($payload['previas'] ?? null) ? $payload['previas'] : [];
        if ($previas !== []) {
            $contexto['prev.fondo_cierre'] = round((float) ($previas['prev_fondo_cierre'] ?? 0), 2);
            $contexto['prev.transferencia'] = round((float) ($previas['prev_transferencia'] ?? 0), 2);
            $contexto['prev.impuesto_drop_dia_ant'] = round((float) ($previas['impuesto_drop_dia_ant'] ?? 0), 2);
            if (! isset($orquestador['comprobante'])) {
                $contexto['calc.comprobante'] = round((float) ($previas['comprobante'] ?? $contexto['calc.comprobante']), 2);
            }
            if (! isset($orquestador['vale_rep_fondo'])) {
                $contexto['calc.vale_rep_fondo'] = round((float) ($previas['vale_rep_fondo'] ?? $contexto['calc.vale_rep_fondo']), 2);
            }
            $fondoActual = (float) ($contexto['inputs.fondo_inicial'] ?? 0);
            if (abs($fondoActual) < 0.00001 && abs((float) ($previas['fondo_inicial'] ?? 0)) > 0.00001) {
                $contexto['inputs.fondo_inicial'] = round((float) $previas['fondo_inicial'], 2);
            }
            // No rellenar impuesto_drop desde previas acá: 0 manual en T/N (o M) debe respetarse.
        }

        return $contexto;
    }

    /**
     * @param  list<array<string, mixed>>  $lineasValor
     * @return array<string, float>
     */
    public static function armarValoresTotales(array $lineasValor): array
    {
        $total = 0.0;
        $efectivo = 0.0;
        $qr = 0.0;
        $divisa = 0.0;

        foreach ($lineasValor as $linea) {
            $monto = round((float) ($linea['monto'] ?? 0), 2);
            if (abs($monto) < 0.00001) {
                continue;
            }

            $monedaId = (int) ($linea['moneda_id'] ?? 1);
            $cotizacion = (float) ($linea['cotizacion'] ?? 0);
            $montoPesos = RendicionMaquinaValoresCuentacajaSupport::montoEnPesos(
                $monedaId,
                $monto,
                $cotizacion
            );

            $total += $montoPesos;
            if (RendicionMaquinaValoresCuentacajaSupport::esMonedaExtranjera($monedaId)) {
                $divisa += $montoPesos;
                continue;
            }

            if (self::esValorQrCuentacaja($linea)) {
                $qr += $montoPesos;
            } else {
                $efectivo += $montoPesos;
            }
        }

        $noDivisa = $total - $divisa;

        return [
            'valores.total' => round($total, 2),
            'valores.total_efectivo' => round($efectivo, 2),
            'valores.total_qr' => round($qr, 2),
            'valores.total_divisa' => round($divisa, 2),
            'valores.total_no_divisa' => round($noDivisa, 2),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lineasGasto
     */
    public static function armarGastosTotal(array $lineasGasto): float
    {
        $total = 0.0;
        foreach ($lineasGasto as $linea) {
            $total += (float) ($linea['monto'] ?? 0);
        }

        return round($total, 2);
    }

    /**
     * QR / TotalCoin se detecta por la cuenta de caja, no por valormae.
     *
     * @param  array<string, mixed>  $linea
     */
    private static function esValorQrCuentacaja(array $linea): bool
    {
        $texto = mb_strtolower(trim(implode(' ', [
            (string) ($linea['codigo'] ?? ''),
            (string) ($linea['nombre'] ?? ''),
            (string) ($linea['descripcion_operaciones'] ?? ''),
            (string) ($linea['nombre_maestro'] ?? ''),
        ])));
        if ($texto === '') {
            return false;
        }

        return str_contains($texto, 'qr')
            || str_contains($texto, 'totalcoin')
            || str_contains($texto, 'total coin');
    }
}
