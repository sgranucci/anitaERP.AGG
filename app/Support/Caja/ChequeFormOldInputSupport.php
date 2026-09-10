<?php

namespace App\Support\Caja;

/**
 * Rearma filas de cheques del formulario desde old() tras un error de grabación.
 */
final class ChequeFormOldInputSupport
{
    /**
     * @param  array<string, mixed>  $old
     */
    public static function at(array $old, string $campo, int|string $i, mixed $default = ''): mixed
    {
        $arr = $old[$campo] ?? null;
        if (is_array($arr) && array_key_exists($i, $arr)) {
            return $arr[$i];
        }

        return $default;
    }

    /**
     * @param  array<string, mixed>  $old
     */
    public static function mapearFilaEmitido(array $old, int|string $i, ?object $cuenta = null, ?object $chequera = null): ?object
    {
        $nro = trim((string) self::at($old, 'numerocheque_emitidos', $i, ''));
        $monto = (float) self::at($old, 'montocheque_emitidos', $i, 0);
        $cuentaId = (int) self::at($old, 'cuentacaja_emitido_ids', $i, 0);
        $codigo = trim((string) self::at($old, 'codigo_emitido', $i, ''));
        if ($nro === '' && $monto <= 0 && $cuentaId <= 0 && $codigo === '') {
            return null;
        }

        if ($cuenta === null && ($cuentaId > 0 || $codigo !== '')) {
            $cuenta = (object) [
                'codigo' => $codigo,
                'nombre' => '',
            ];
        }

        return (object) [
            'id' => self::at($old, 'cheque_emitido_ids', $i, ''),
            'cuentacaja_id' => $cuentaId,
            'chequera_id' => self::at($old, 'chequera_emitido_ids', $i, ''),
            'numerocheque' => $nro,
            'fechapago' => self::at($old, 'fechapago_emitidos', $i, ''),
            'caracter' => self::at($old, 'caracter_emitidos', $i, 'O') ?: 'O',
            'anombrede' => (string) self::at($old, 'anombrede_emitidos', $i, ''),
            'moneda_id' => (int) self::at($old, 'moneda_emitido_ids', $i, 1) ?: 1,
            'monto' => $monto,
            'cotizacion' => self::at($old, 'cotizacioncheque_emitidos', $i, 1) ?: 1,
            'cuentacajas' => $cuenta,
            'chequeras' => $chequera,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @return \Illuminate\Support\Collection<int, object>
     */
    public static function emitidosDesdeOld(?array $old = null): \Illuminate\Support\Collection
    {
        $old = $old ?? (array) old();
        $indices = array_keys((array) ($old['numerocheque_emitidos'] ?? []));
        if ($indices === []) {
            $indices = array_keys((array) ($old['cuentacaja_emitido_ids'] ?? []));
        }
        $ids = [];
        $chequeraIds = [];
        foreach ($indices as $i) {
            $cid = (int) self::at($old, 'cuentacaja_emitido_ids', $i, 0);
            if ($cid > 0) {
                $ids[] = $cid;
            }
            $chid = (int) self::at($old, 'chequera_emitido_ids', $i, 0);
            if ($chid > 0) {
                $chequeraIds[] = $chid;
            }
        }
        $cuentas = $ids === []
            ? collect()
            : \App\Models\Caja\Cuentacaja::query()->whereIn('id', array_values(array_unique($ids)))->get()->keyBy('id');
        $chequeras = $chequeraIds === []
            ? collect()
            : \App\Models\Caja\Chequera::query()->whereIn('id', array_values(array_unique($chequeraIds)))->get()->keyBy('id');

        $out = [];
        foreach ($indices as $i) {
            $cid = (int) self::at($old, 'cuentacaja_emitido_ids', $i, 0);
            $chid = (int) self::at($old, 'chequera_emitido_ids', $i, 0);
            $fila = self::mapearFilaEmitido(
                $old,
                $i,
                $cid > 0 ? $cuentas->get($cid) : null,
                $chid > 0 ? $chequeras->get($chid) : null
            );
            if ($fila !== null) {
                $out[] = $fila;
            }
        }

        return collect($out);
    }

    /**
     * @param  array<string, mixed>  $old
     */
    public static function mapearFilaRecibido(array $old, int|string $i, ?object $banco = null): ?object
    {
        $nro = trim((string) self::at($old, 'numerocheque_recibidos', $i, ''));
        $monto = (float) self::at($old, 'montocheque_recibidos', $i, 0);
        $bancoId = (int) self::at($old, 'banco_recibido_ids', $i, 0);
        if ($nro === '' && $monto <= 0 && $bancoId <= 0) {
            return null;
        }

        return (object) [
            'id' => self::at($old, 'cheque_recibido_ids', $i, ''),
            'banco_id' => $bancoId,
            'numerocheque' => $nro,
            'fechapago' => self::at($old, 'fechapago_recibidos', $i, ''),
            'sucursalpago' => self::at($old, 'sucursalpago_recibidos', $i, ''),
            'cuentalibradora' => self::at($old, 'cuentalibradora_recibidos', $i, ''),
            'moneda_id' => (int) self::at($old, 'monedacheque_recibido_ids', $i, 1) ?: 1,
            'monto' => $monto,
            'cotizacion' => self::at($old, 'cotizacioncheque_recibidos', $i, 1) ?: 1,
            'bancos' => $banco,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @return \Illuminate\Support\Collection<int, object>
     */
    public static function recibidosDesdeOld(?array $old = null): \Illuminate\Support\Collection
    {
        $old = $old ?? (array) old();
        $indices = array_keys((array) ($old['numerocheque_recibidos'] ?? []));
        $ids = [];
        foreach ($indices as $i) {
            $bid = (int) self::at($old, 'banco_recibido_ids', $i, 0);
            if ($bid > 0) {
                $ids[] = $bid;
            }
        }
        $bancos = $ids === []
            ? collect()
            : \App\Models\Caja\Banco::query()->whereIn('id', array_values(array_unique($ids)))->get()->keyBy('id');

        $out = [];
        foreach ($indices as $i) {
            $bid = (int) self::at($old, 'banco_recibido_ids', $i, 0);
            $fila = self::mapearFilaRecibido($old, $i, $bid > 0 ? $bancos->get($bid) : null);
            if ($fila !== null) {
                $out[] = $fila;
            }
        }

        return collect($out);
    }
}
