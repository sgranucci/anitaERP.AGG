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
            if (! array_key_exists('fondo_inicial', $inputs) && ! array_key_exists('inputs.fondo_inicial', $inputs)) {
                $contexto['inputs.fondo_inicial'] = round((float) ($previas['fondo_inicial'] ?? 0), 2);
            }
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
        $noDivisa = 0.0;

        foreach ($lineasValor as $linea) {
            $monto = round((float) ($linea['monto'] ?? 0), 2);
            if (abs($monto) < 0.00001) {
                continue;
            }

            $total += $monto;
            $tipo = trim((string) ($linea['tipo_valormae'] ?? $linea['valm_tipo'] ?? ''));

            // valormae: 0 pesos, 1 u$s, 2 €, 5 QR, 7 dep.QR/transf, 8 cripto, 9 TotalCoin
            if (in_array($tipo, ['1', '2', '8'], true)) {
                $divisa += $monto;
            } elseif (in_array($tipo, ['5', '9'], true)) {
                $qr += $monto;
            } elseif ($tipo === '0' || $tipo === '7' || $tipo === '') {
                $efectivo += $monto;
            } else {
                $efectivo += $monto;
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
}
