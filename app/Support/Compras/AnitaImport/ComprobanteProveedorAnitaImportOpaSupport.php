<?php

namespace App\Support\Compras\AnitaImport;

/**
 * Créditos Anita en promov sin fila en `compra` (OPA, EGR, IEV, NCJ, …):
 * pagoproveedor + CC negativa si queda saldo sin aplicar.
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
    /** @var list<string> */
    private const TIPOS_CREDITO_DEFAULT = ['OPA', 'EGR', 'IEV', 'NCJ'];

    /** Compat / tests: mismos tipos default que config tipos_credito_sin_compra. */
    public static function esTipoAdelanto(string $tipo): bool
    {
        $ab = ComprobanteProveedorAnitaImportClaveSupport::tipo($tipo);

        return $ab !== '' && in_array($ab, self::TIPOS_CREDITO_DEFAULT, true);
    }

    public static function pendiente(array|object $promov): float
    {
        $f = (array) $promov;
        $monto = abs((float) ($f['prov_monto'] ?? 0));
        $pagado = abs((float) ($f['prov_t_pagado'] ?? 0));

        return round(max(0, $monto - $pagado), 4);
    }

    /**
     * Agrupa promov de crédito-sin-compra por clave+empresa y deja solo saldo abierto.
     * El caller suele pasar solo tipos_credito_sin_compra; si no, se filtra por $tiposPermitidos
     * o por el default OPA/EGR/IEV/NCJ.
     *
     * @param  list<array<string, mixed>|object>  $promovs
     * @param  list<string>|null  $tiposPermitidos
     * @return list<Adelanto>
     */
    public static function adelantosPendientes(array $promovs, ?array $tiposPermitidos = null): array
    {
        $tiposFuente = $tiposPermitidos ?? self::TIPOS_CREDITO_DEFAULT;
        $tiposOk = [];
        foreach ($tiposFuente as $t) {
            $ab = ComprobanteProveedorAnitaImportClaveSupport::tipo((string) $t);
            if ($ab !== '') {
                $tiposOk[$ab] = true;
            }
        }

        $agrupados = [];
        foreach ($promovs as $promov) {
            $f = (array) $promov;
            $tipo = ComprobanteProveedorAnitaImportClaveSupport::tipo((string) ($f['prov_tipo'] ?? ''));
            if ($tipo === '' || ! isset($tiposOk[$tipo])) {
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
