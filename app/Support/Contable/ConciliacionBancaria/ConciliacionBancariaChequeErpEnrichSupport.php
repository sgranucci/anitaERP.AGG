<?php

declare(strict_types=1);

namespace App\Support\Contable\ConciliacionBancaria;

use App\Models\Caja\Cheque;
use Illuminate\Support\Facades\Schema;

/**
 * Completa datos faltantes de CHP (proveedor/entregado_a, nro OP, fechas) desde el ERP.
 *
 * Casos típicos: filas nacidas solo de semilla Excel/snapshot sin cpromae ni
 * posicion_bancaria_cheque, donde entregado_a / nro_op quedan vacíos.
 */
final class ConciliacionBancariaChequeErpEnrichSupport
{
    /** Tips cuyo “Numero” es nro de cheque propio (incl. anulación ACP). */
    public const TIPS_CHEQUE = ['CHP', 'CHD', 'CHQ', 'CHE', 'ACP', 'ANULA'];

    /**
     * @param  list<array<string, mixed>>  $filas  Filas con numero_cheque o numero
     * @return list<array<string, mixed>>
     */
    public static function enriquecerPendientes(array $filas, int $empresaId = 0, int $cuentacajaId = 0): array
    {
        if ($filas === [] || ! Schema::hasTable('cheque')) {
            return $filas;
        }

        $nros = [];
        foreach ($filas as $f) {
            if (! self::necesitaEnrich($f)) {
                continue;
            }
            $n = self::nroCheque($f);
            if ($n !== '') {
                $nros[$n] = true;
            }
        }
        if ($nros === []) {
            return $filas;
        }

        $map = self::mapaDesdeCheque(array_keys($nros), $empresaId, $cuentacajaId);
        if ($map === []) {
            return $filas;
        }

        foreach ($filas as &$f) {
            $n = self::nroCheque($f);
            if ($n === '' || ! isset($map[$n])) {
                continue;
            }
            $f = self::aplicar($f, $map[$n]);
        }
        unset($f);

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $filas  Filas del reporte sin conciliar
     * @return list<array<string, mixed>>
     */
    public static function enriquecerFilasReporte(array $filas, int $empresaId = 0, int $cuentacajaId = 0): array
    {
        if ($filas === [] || ! Schema::hasTable('cheque')) {
            return $filas;
        }

        $nros = [];
        foreach ($filas as $f) {
            $tip = strtoupper(trim((string) ($f['tip'] ?? '')));
            if (! in_array($tip, self::TIPS_CHEQUE, true)) {
                continue;
            }
            if (! self::necesitaEnrichReporte($f)) {
                continue;
            }
            $n = self::nroNorm((string) ($f['numero'] ?? ''));
            if ($n !== '') {
                $nros[$n] = true;
            }
        }
        if ($nros === []) {
            return $filas;
        }

        $map = self::mapaDesdeCheque(array_keys($nros), $empresaId, $cuentacajaId);
        if ($map === []) {
            return $filas;
        }

        foreach ($filas as &$f) {
            $tip = strtoupper(trim((string) ($f['tip'] ?? '')));
            if (! in_array($tip, self::TIPS_CHEQUE, true)) {
                continue;
            }
            $n = self::nroNorm((string) ($f['numero'] ?? ''));
            if ($n === '' || ! isset($map[$n])) {
                continue;
            }
            $src = $map[$n];
            if (trim((string) ($f['detalle'] ?? '')) === '' && $src['entregado_a'] !== '') {
                $f['detalle'] = $src['entregado_a'];
            }
            if (trim((string) ($f['n_orig'] ?? '')) === '' && $src['nro_op'] !== '') {
                $f['n_orig'] = $src['nro_op'];
            }
            if (empty($f['cheque_id']) && $src['cheque_id'] > 0) {
                $f['cheque_id'] = $src['cheque_id'];
            }
            if (empty($f['pagoproveedor_id']) && $src['pagoproveedor_id'] > 0) {
                $f['pagoproveedor_id'] = $src['pagoproveedor_id'];
            }
            if (($f['f_entr'] ?? '---') === '---' && $src['fecha_entrega'] !== null) {
                try {
                    $f['f_entr'] = \Carbon\Carbon::parse($src['fecha_entrega'])->format('m/d/Y');
                } catch (\Throwable) {
                    // keep ---
                }
            }
            $sinImporte = abs((float) ($f['debito'] ?? 0)) < 0.005
                && abs((float) ($f['credito'] ?? 0)) < 0.005;
            if ($sinImporte) {
                $debeSrc = $src['debe'] ?? null;
                $haberSrc = $src['haber'] ?? null;
                if ($debeSrc !== null && abs((float) $debeSrc) > 0.005) {
                    $f['debito'] = round((float) $debeSrc, 2);
                    $f['credito'] = 0.0;
                } elseif ($haberSrc !== null && abs((float) $haberSrc) > 0.005) {
                    $f['debito'] = 0.0;
                    $f['credito'] = round((float) $haberSrc, 2);
                } elseif (abs((float) ($src['importe'] ?? 0)) > 0.005) {
                    $impSigned = (float) $src['importe'];
                    $imp = round(abs($impSigned), 2);
                    // Misma regla que desdeCheque: ANULA o importe (−) → débitos.
                    if (strcasecmp($tip, 'ANULA') === 0 || $impSigned < -0.005) {
                        $f['debito'] = $imp;
                        $f['credito'] = 0.0;
                    } else {
                        $f['debito'] = 0.0;
                        $f['credito'] = $imp;
                    }
                }
            }
        }
        unset($f);

        return $filas;
    }

    /**
     * @param  array<string, mixed>  $f
     */
    private static function necesitaEnrich(array $f): bool
    {
        return trim((string) ($f['entregado_a'] ?? '')) === ''
            || trim((string) ($f['nro_op'] ?? '')) === ''
            || trim((string) ($f['proveedor_codigo'] ?? '')) === ''
            || empty($f['fecha_entrega'])
            || abs((float) ($f['importe'] ?? 0)) < 0.005;
    }

    /**
     * @param  array<string, mixed>  $f
     */
    private static function necesitaEnrichReporte(array $f): bool
    {
        $sinImporte = abs((float) ($f['debito'] ?? 0)) < 0.005
            && abs((float) ($f['credito'] ?? 0)) < 0.005;

        return trim((string) ($f['detalle'] ?? '')) === ''
            || trim((string) ($f['n_orig'] ?? '')) === ''
            || empty($f['cheque_id'])
            || empty($f['pagoproveedor_id'])
            || ($f['f_entr'] ?? '---') === '---'
            || $sinImporte;
    }

    /**
     * @param  array<string, mixed>  $f
     */
    private static function nroCheque(array $f): string
    {
        $raw = (string) ($f['numero_cheque'] ?? $f['numero'] ?? '');

        return self::nroNorm($raw);
    }

    private static function nroNorm(string $raw): string
    {
        $n = ltrim((string) preg_replace('/\D/', '', $raw), '0');

        return ($n === '' || $n === '0') ? '' : $n;
    }

    /**
     * @param  list<string>  $nros
     * @return array<string, array{
     *   entregado_a: string,
     *   proveedor_codigo: string,
     *   nro_op: string,
     *   fecha_entrega: ?string,
     *   cheque_id: int,
     *   pagoproveedor_id: int,
     *   importe: float
     * }>
     */
    private static function mapaDesdeCheque(array $nros, int $empresaId, int $cuentacajaId): array
    {
        $q = Cheque::query()
            ->with([
                'proveedores:id,codigo,nombre',
                'pagoproveedores:id,numerotransaccion',
            ])
            ->whereIn('numerocheque', $nros);

        if ($empresaId > 0 && Schema::hasColumn('cheque', 'empresa_id')) {
            $q->where('empresa_id', $empresaId);
        }
        if ($cuentacajaId > 0 && Schema::hasColumn('cheque', 'cuentacaja_id')) {
            $q->where('cuentacaja_id', $cuentacajaId);
        }

        $map = [];
        foreach ($q->get([
            'id',
            'numerocheque',
            'anombrede',
            'proveedor_id',
            'pagoproveedor_id',
            'fecha_entrega',
            'monto',
        ]) as $ch) {
            $key = self::nroNorm((string) $ch->numerocheque);
            if ($key === '') {
                continue;
            }
            $nombreProv = trim((string) ($ch->proveedores->nombre ?? ''));
            $anombre = trim((string) ($ch->anombrede ?? ''));
            $entregado = $nombreProv !== '' ? $nombreProv : $anombre;
            $codigo = ltrim((string) ($ch->proveedores->codigo ?? ''), '0');
            $nroOp = '';
            if ($ch->pagoproveedores) {
                $nroOp = ltrim((string) preg_replace('/\D/', '', (string) $ch->pagoproveedores->numerotransaccion), '0');
            }
            $fechaEntrega = null;
            if ($ch->fecha_entrega) {
                $fechaEntrega = $ch->fecha_entrega instanceof \DateTimeInterface
                    ? $ch->fecha_entrega->format('Y-m-d')
                    : substr((string) $ch->fecha_entrega, 0, 10);
            }

            // Preferir fila con más datos si hay duplicados de número.
            $cand = [
                'entregado_a' => $entregado,
                'proveedor_codigo' => $codigo,
                'nro_op' => $nroOp,
                'fecha_entrega' => $fechaEntrega,
                'cheque_id' => (int) $ch->id,
                'pagoproveedor_id' => (int) ($ch->pagoproveedor_id ?? 0),
                'importe' => abs((float) ($ch->monto ?? 0)),
            ];
            if (! isset($map[$key]) || self::score($cand) > self::score($map[$key])) {
                $map[$key] = $cand;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function score(array $row): int
    {
        return (trim((string) ($row['entregado_a'] ?? '')) !== '' ? 4 : 0)
            + (trim((string) ($row['nro_op'] ?? '')) !== '' ? 2 : 0)
            + (trim((string) ($row['proveedor_codigo'] ?? '')) !== '' ? 1 : 0)
            + (! empty($row['fecha_entrega']) ? 1 : 0)
            + (abs((float) ($row['importe'] ?? 0)) > 0.005 ? 2 : 0);
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array{
     *   entregado_a: string,
     *   proveedor_codigo: string,
     *   nro_op: string,
     *   fecha_entrega: ?string,
     *   cheque_id: int,
     *   pagoproveedor_id: int,
     *   importe: float
     * }  $src
     * @return array<string, mixed>
     */
    private static function aplicar(array $fila, array $src): array
    {
        if (trim((string) ($fila['entregado_a'] ?? '')) === '' && $src['entregado_a'] !== '') {
            $fila['entregado_a'] = $src['entregado_a'];
        }
        if (trim((string) ($fila['proveedor_codigo'] ?? '')) === '' && $src['proveedor_codigo'] !== '') {
            $fila['proveedor_codigo'] = $src['proveedor_codigo'];
        }
        if (trim((string) ($fila['nro_op'] ?? '')) === '' && $src['nro_op'] !== '') {
            $fila['nro_op'] = $src['nro_op'];
        }
        if (empty($fila['fecha_entrega']) && $src['fecha_entrega'] !== null) {
            $fila['fecha_entrega'] = $src['fecha_entrega'];
        }
        if (abs((float) ($fila['importe'] ?? 0)) < 0.005 && abs((float) ($src['importe'] ?? 0)) > 0.005) {
            $fila['importe'] = round(abs((float) $src['importe']), 2);
        }

        $origen = is_array($fila['origen_json'] ?? null) ? $fila['origen_json'] : [];
        if (empty($origen['id']) && $src['cheque_id'] > 0) {
            $origen['cheque_id'] = $src['cheque_id'];
        }
        if ($src['pagoproveedor_id'] > 0) {
            $origen['pagoproveedor_id'] = $src['pagoproveedor_id'];
        }
        $origen['enrich_erp'] = true;
        $fila['origen_json'] = $origen;
        if (empty($fila['cheque_id']) && $src['cheque_id'] > 0) {
            $fila['cheque_id'] = $src['cheque_id'];
        }

        return $fila;
    }
}
