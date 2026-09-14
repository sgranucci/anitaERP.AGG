<?php

namespace App\Support\Caja;

use App\Models\Caja\Cheque;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * Aging / antigüedad de cheques de terceros en cartera.
 */
final class ChequeCarteraAgingSupport
{
    /** @var list<string> */
    public const BUCKETS = ['vencido', '0_7', '8_15', '16_30', '31_60', '61_mas'];

    /**
     * @param  array{empresa_id?:int|null, bucket?:string|null, texto?:string|null, hasta?:string|null}  $filtros
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   buckets: array<string, array{label:string, cantidad:int, monto:float}>,
     *   total_cantidad:int,
     *   total_monto:float,
     *   hasta:string
     * }
     */
    public static function resumir(?int $empresaId = null, ?string $hastaFecha = null, array $filtros = []): array
    {
        if ($empresaId === null && array_key_exists('empresa_id', $filtros)) {
            $empresaId = ($filtros['empresa_id'] ?? null) ? (int) $filtros['empresa_id'] : null;
        }
        if ($hastaFecha === null && isset($filtros['hasta'])) {
            $hastaFecha = $filtros['hasta'] !== '' ? (string) $filtros['hasta'] : null;
        }

        $hasta = $hastaFecha && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hastaFecha)
            ? $hastaFecha
            : date('Y-m-d');

        $q = Cheque::query()
            ->with(['bancos:id,nombre', 'clientes:id,codigo,nombre', 'monedas:id,abreviatura', 'empresas:id,nombre'])
            ->where('origen', 'R')
            ->whereNull('pagoproveedor_id')
            ->whereNull('fecha_deposito')
            ->where(function ($w) {
                $w->whereNull('estado')
                    ->orWhereIn('estado', [' ', 'N', '']);
            })
            ->where(function ($w) {
                $w->whereNull('nro_caucion')
                    ->orWhere('nro_caucion', '')
                    ->orWhere('nro_caucion', '0');
            });

        if ($empresaId && $empresaId > 0) {
            $q->where('empresa_id', $empresaId);
        }

        $cheques = $q->orderBy('fechapago')->orderBy('id')->get();

        $buckets = [
            'vencido' => ['label' => 'Vencidos', 'cantidad' => 0, 'monto' => 0.],
            '0_7' => ['label' => '0–7 días', 'cantidad' => 0, 'monto' => 0.],
            '8_15' => ['label' => '8–15 días', 'cantidad' => 0, 'monto' => 0.],
            '16_30' => ['label' => '16–30 días', 'cantidad' => 0, 'monto' => 0.],
            '31_60' => ['label' => '31–60 días', 'cantidad' => 0, 'monto' => 0.],
            '61_mas' => ['label' => '+60 días', 'cantidad' => 0, 'monto' => 0.],
        ];

        $filas = [];
        $totalMonto = 0.;
        foreach ($cheques as $c) {
            $pago = (string) ($c->fechapago ?? '');
            $dias = self::diasHastaPago($pago, $hasta);
            $bucket = self::bucketKey($dias);
            $monto = round((float) $c->monto, 2);
            $buckets[$bucket]['cantidad']++;
            $buckets[$bucket]['monto'] = round($buckets[$bucket]['monto'] + $monto, 2);
            $totalMonto = round($totalMonto + $monto, 2);

            $filas[] = [
                'id' => (int) $c->id,
                'numerocheque' => (string) ($c->numerocheque ?? ''),
                'nro_interno_anita' => $c->nro_interno_anita,
                'fechapago' => $pago,
                'dias' => $dias,
                'bucket' => $bucket,
                'bucket_label' => $buckets[$bucket]['label'],
                'monto' => $monto,
                'moneda' => (string) ($c->monedas->abreviatura ?? ''),
                'banco' => (string) ($c->bancos->nombre ?? ''),
                'banco_id' => $c->banco_id ? (int) $c->banco_id : null,
                'cliente' => (string) ($c->clientes->nombre ?? ''),
                'cliente_id' => $c->cliente_id ? (int) $c->cliente_id : null,
                'empresa' => (string) ($c->empresas->nombre ?? ''),
                'empresa_id' => $c->empresa_id ? (int) $c->empresa_id : null,
                'nombreempresa' => (string) ($c->empresas->nombre ?? ''),
                // fechapago ≤ hasta y sin depósito/caución/OP (query ya filtró cartera)
                'puede_depositar' => $dias <= 0 && empty($c->fecha_deposito),
            ];
        }

        $filasFiltradas = self::filtrarFilas($filas, $filtros);

        return [
            'filas' => $filasFiltradas,
            'filas_todas' => $filas,
            'buckets' => $buckets,
            'total_cantidad' => count($filasFiltradas),
            'total_monto' => round(array_sum(array_column($filasFiltradas, 'monto')), 2),
            'total_cantidad_cartera' => count($filas),
            'total_monto_cartera' => $totalMonto,
            'hasta' => $hasta,
        ];
    }

    /**
     * @param  array{empresa_id?:int|null, bucket?:string|null, texto?:string|null, hasta?:string|null}  $filtros
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   buckets: array<string, array{label:string, cantidad:int, monto:float}>,
     *   total_cantidad:int,
     *   total_monto:float,
     *   hasta:string,
     *   paginator: LengthAwarePaginator
     * }
     */
    public static function resumirPaginado(array $filtros, int $page = 1, int $perPage = 25): array
    {
        $resumen = self::resumir(
            ($filtros['empresa_id'] ?? null) ? (int) $filtros['empresa_id'] : null,
            $filtros['hasta'] ?? null,
            $filtros
        );
        $resumen['paginator'] = self::paginar($resumen, $page, $perPage);

        return $resumen;
    }

    /**
     * @param  array{filas: list<array<string, mixed>>}  $resumen
     */
    public static function paginar(array $resumen, int $page, int $perPage = 25): LengthAwarePaginator
    {
        $filas = $resumen['filas'] ?? [];
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $total = count($filas);
        $slice = array_slice($filas, ($page - 1) * $perPage, $perPage);

        return new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array{bucket?:string|null, texto?:string|null}  $filtros
     * @return list<array<string, mixed>>
     */
    private static function filtrarFilas(array $filas, array $filtros): array
    {
        $bucket = trim((string) ($filtros['bucket'] ?? ''));
        $texto = mb_strtolower(trim((string) ($filtros['texto'] ?? '')));
        $paraDepositar = ! empty($filtros['para_depositar']);

        if ($bucket === '' && $texto === '' && ! $paraDepositar) {
            return $filas;
        }

        $out = [];
        foreach ($filas as $fila) {
            if ($paraDepositar && empty($fila['puede_depositar'])) {
                continue;
            }
            if ($bucket !== '' && in_array($bucket, self::BUCKETS, true) && ($fila['bucket'] ?? '') !== $bucket) {
                continue;
            }
            if ($texto !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    (string) ($fila['numerocheque'] ?? ''),
                    (string) ($fila['cliente'] ?? ''),
                    (string) ($fila['banco'] ?? ''),
                ]));
                if (! str_contains($haystack, $texto)) {
                    continue;
                }
            }
            $out[] = $fila;
        }

        return $out;
    }

    private static function diasHastaPago(string $fechapago, string $hasta): int
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechapago)) {
            return 0;
        }
        $pagoTs = strtotime($fechapago.' 00:00:00');
        $hastaTs = strtotime($hasta.' 00:00:00');
        if ($pagoTs === false || $hastaTs === false) {
            return 0;
        }

        return (int) floor(($pagoTs - $hastaTs) / 86400);
    }

    private static function bucketKey(int $dias): string
    {
        if ($dias < 0) {
            return 'vencido';
        }
        if ($dias <= 7) {
            return '0_7';
        }
        if ($dias <= 15) {
            return '8_15';
        }
        if ($dias <= 30) {
            return '16_30';
        }
        if ($dias <= 60) {
            return '31_60';
        }

        return '61_mas';
    }
}
