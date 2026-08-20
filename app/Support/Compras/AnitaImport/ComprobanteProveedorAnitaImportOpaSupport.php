<?php

namespace App\Support\Compras\AnitaImport;

/**
 * Adelantos Anita (OPA) en promov: crédito de CC si queda saldo sin aplicar.
 *
 * @phpstan-type Adelanto array{
 *   clave: string,
 *   tipo: string,
 *   letra: string,
 *   sucursal: int,
 *   numero: int,
 *   etiqueta: string,
 *   fecha: string,
 *   fechavencimiento: string,
 *   pendiente: float,
 *   monto: float,
 *   pagado: float,
 *   moneda_anita: int,
 *   cotizacion: float,
 *   empresa_codigo: int
 * }
 */
final class ComprobanteProveedorAnitaImportOpaSupport
{
    public static function esTipoAdelanto(string $tipo): bool
    {
        return ComprobanteProveedorAnitaImportClaveSupport::tipo($tipo) === 'OPA';
    }

    public static function pendiente(array|object $promov): float
    {
        $f = (array) $promov;
        $monto = abs((float) ($f['prov_monto'] ?? 0));
        $pagado = abs((float) ($f['prov_t_pagado'] ?? 0));

        return round(max(0, $monto - $pagado), 4);
    }

    /**
     * Agrupa promov OPA por clave+empresa y deja solo los que tienen saldo.
     *
     * @param  list<array<string, mixed>|object>  $promovs
     * @return list<Adelanto>
     */
    public static function adelantosPendientes(array $promovs): array
    {
        $agrupados = [];
        foreach ($promovs as $promov) {
            $f = (array) $promov;
            if (! self::esTipoAdelanto((string) ($f['prov_tipo'] ?? ''))) {
                continue;
            }

            $pendiente = self::pendiente($f);
            if ($pendiente < 0.009) {
                continue;
            }

            $clave = ComprobanteProveedorAnitaImportClaveSupport::claveDesdePromov($f);
            $empresa = (int) ($f['prov_empresa'] ?? 0);
            $grupo = $clave.'|'.$empresa;
            $fecha = ComprobanteProveedorAnitaImportClaveSupport::fechaIsoDesdeAnita($f['prov_fecha'] ?? '');
            $vto = ComprobanteProveedorAnitaImportClaveSupport::fechaIsoDesdeAnita($f['prov_fecha_vto'] ?? '') ?: $fecha;
            $monto = abs((float) ($f['prov_monto'] ?? 0));
            $pagado = abs((float) ($f['prov_t_pagado'] ?? 0));
            $tipo = ComprobanteProveedorAnitaImportClaveSupport::tipo((string) ($f['prov_tipo'] ?? ''));
            $letra = ComprobanteProveedorAnitaImportClaveSupport::letra((string) ($f['prov_letra'] ?? ''));
            $sucursal = (int) ($f['prov_sucursal'] ?? 0);
            $numero = (int) ($f['prov_nro'] ?? 0);

            if (! isset($agrupados[$grupo])) {
                $agrupados[$grupo] = [
                    'clave' => $clave,
                    'tipo' => $tipo,
                    'letra' => $letra,
                    'sucursal' => $sucursal,
                    'numero' => $numero,
                    'etiqueta' => ComprobanteProveedorAnitaImportClaveSupport::etiqueta($tipo, $letra, $sucursal, $numero),
                    'fecha' => $fecha,
                    'fechavencimiento' => $vto,
                    'pendiente' => 0.0,
                    'monto' => 0.0,
                    'pagado' => 0.0,
                    'moneda_anita' => (int) ($f['prov_cod_mon'] ?? 1) ?: 1,
                    'cotizacion' => (float) ($f['prov_cotizacion'] ?? 1) ?: 1.0,
                    'empresa_codigo' => $empresa,
                ];
            }

            $agrupados[$grupo]['pendiente'] = round($agrupados[$grupo]['pendiente'] + $pendiente, 4);
            $agrupados[$grupo]['monto'] = round($agrupados[$grupo]['monto'] + $monto, 4);
            $agrupados[$grupo]['pagado'] = round($agrupados[$grupo]['pagado'] + $pagado, 4);
            if ($fecha !== '' && ($agrupados[$grupo]['fecha'] === '' || $fecha < $agrupados[$grupo]['fecha'])) {
                $agrupados[$grupo]['fecha'] = $fecha;
            }
            if ($vto !== '' && $vto > $agrupados[$grupo]['fechavencimiento']) {
                $agrupados[$grupo]['fechavencimiento'] = $vto;
            }
        }

        $out = array_values(array_filter(
            $agrupados,
            static fn (array $a) => $a['pendiente'] >= 0.009 && $a['fecha'] !== '' && $a['numero'] > 0
        ));
        usort($out, static fn (array $a, array $b) => [$a['fecha'], $a['clave']] <=> [$b['fecha'], $b['clave']]);

        return $out;
    }
}
