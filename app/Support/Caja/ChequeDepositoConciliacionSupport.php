<?php

namespace App\Support\Caja;

use App\Models\Caja\Cheque;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * Tablero de cheques depositados: en tránsito / acreditados / rechazados post-depósito.
 */
final class ChequeDepositoConciliacionSupport
{
    /** @var list<string> */
    public const ESTADOS = ['transito', 'acreditado', 'rechazado'];

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   buckets: array<string, array{label:string, cantidad:int, monto:float}>,
     *   total_cantidad:int,
     *   total_monto:float,
     *   boletas: list<array{boleta:string, cantidad:int, monto:float}>
     * }
     */
    public static function resumir(array $filtros = []): array
    {
        $q = Cheque::query()
            ->with([
                'bancos:id,nombre',
                'clientes:id,codigo,nombre',
                'monedas:id,abreviatura',
                'empresas:id,nombre',
                'cuentacajaDeposito:id,codigo,nombre',
            ])
            ->where('origen', 'R')
            ->where(function ($w) {
                $w->whereNotNull('fecha_deposito')
                    ->orWhere('estado', '*')
                    ->orWhere(function ($r) {
                        $r->where('estado', 'R')->whereNotNull('fecha_deposito');
                    });
            });

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $q->where('empresa_id', $empresaId);
        }

        $cuentaId = (int) ($filtros['cuentacaja_id'] ?? 0);
        if ($cuentaId > 0) {
            $q->where('cuentacaja_deposito_id', $cuentaId);
        }

        $boleta = trim((string) ($filtros['boleta'] ?? ''));
        if ($boleta !== '') {
            $q->where('nro_boleta_deposito', 'like', '%'.$boleta.'%');
        }

        $desde = (string) ($filtros['desde'] ?? '');
        if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $q->whereDate('fecha_deposito', '>=', $desde);
        }
        $hasta = (string) ($filtros['hasta'] ?? '');
        if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $q->whereDate('fecha_deposito', '<=', $hasta);
        }

        $texto = trim((string) ($filtros['texto'] ?? ''));
        if ($texto !== '') {
            $like = '%'.$texto.'%';
            $q->where(function ($w) use ($like) {
                $w->where('numerocheque', 'like', $like)
                    ->orWhere('nro_boleta_deposito', 'like', $like)
                    ->orWhere('nro_interno_anita', 'like', $like)
                    ->orWhereHas('clientes', fn ($c) => $c->where('nombre', 'like', $like)->orWhere('codigo', 'like', $like))
                    ->orWhereHas('bancos', fn ($b) => $b->where('nombre', 'like', $like));
            });
        }

        $cheques = $q->orderByDesc('fecha_deposito')->orderByDesc('id')->get();

        $buckets = [
            'transito' => ['label' => 'En tránsito', 'cantidad' => 0, 'monto' => 0.],
            'acreditado' => ['label' => 'Acreditados', 'cantidad' => 0, 'monto' => 0.],
            'rechazado' => ['label' => 'Rechazados', 'cantidad' => 0, 'monto' => 0.],
        ];

        $boletasMap = [];
        $filas = [];
        $totalMonto = 0.;
        $estadoFiltro = (string) ($filtros['estado'] ?? '');

        foreach ($cheques as $c) {
            $estadoKey = self::estadoKey($c);
            $monto = round((float) $c->monto, 2);
            $buckets[$estadoKey]['cantidad']++;
            $buckets[$estadoKey]['monto'] = round($buckets[$estadoKey]['monto'] + $monto, 2);

            $boletaKey = trim((string) ($c->nro_boleta_deposito ?? ''));
            if ($boletaKey === '') {
                $boletaKey = '(sin boleta)';
            }
            if (! isset($boletasMap[$boletaKey])) {
                $boletasMap[$boletaKey] = ['boleta' => $boletaKey, 'cantidad' => 0, 'monto' => 0.];
            }
            $boletasMap[$boletaKey]['cantidad']++;
            $boletasMap[$boletaKey]['monto'] = round($boletasMap[$boletaKey]['monto'] + $monto, 2);

            if ($estadoFiltro !== '' && $estadoFiltro !== $estadoKey) {
                continue;
            }

            $totalMonto = round($totalMonto + $monto, 2);
            $filas[] = [
                'id' => (int) $c->id,
                'numerocheque' => (string) ($c->numerocheque ?? ''),
                'nro_interno_anita' => $c->nro_interno_anita,
                'fecha_deposito' => (string) ($c->fecha_deposito ?? ''),
                'fecha_acreditacion' => (string) ($c->fecha_acreditacion ?? ''),
                'nro_boleta' => (string) ($c->nro_boleta_deposito ?? ''),
                'monto' => $monto,
                'moneda' => (string) ($c->monedas->abreviatura ?? ''),
                'banco' => (string) ($c->bancos->nombre ?? ''),
                'cliente' => (string) ($c->clientes->nombre ?? ''),
                'cliente_id' => $c->cliente_id ? (int) $c->cliente_id : null,
                'empresa' => (string) ($c->empresas->nombre ?? ''),
                'nombreempresa' => (string) ($c->empresas->nombre ?? ''),
                'cuenta' => trim(((string) ($c->cuentacajaDeposito->codigo ?? '')).' '.((string) ($c->cuentacajaDeposito->nombre ?? ''))),
                'estado' => $estadoKey,
                'estado_label' => $buckets[$estadoKey]['label'],
                'puede_acreditar' => $estadoKey === 'transito',
            ];
        }

        usort($boletasMap, static fn ($a, $b) => $b['monto'] <=> $a['monto']);

        return [
            'filas' => $filas,
            'buckets' => $buckets,
            'total_cantidad' => count($filas),
            'total_monto' => $totalMonto,
            'boletas' => array_values($boletasMap),
            'total_cantidad_tablero' => array_sum(array_column($buckets, 'cantidad')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{paginator: LengthAwarePaginator, filas: list<array<string, mixed>>, buckets: array, total_cantidad:int, total_monto:float, boletas: list}
     */
    public static function resumirPaginado(array $filtros, int $page = 1, int $perPage = 25): array
    {
        $resumen = self::resumir($filtros);
        $page = max(1, $page);
        $slice = array_slice($resumen['filas'], ($page - 1) * $perPage, $perPage);
        $paginator = new LengthAwarePaginator(
            $slice,
            count($resumen['filas']),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'query' => request()->query()]
        );

        return array_merge($resumen, [
            'paginator' => $paginator,
            'filas_pagina' => $slice,
        ]);
    }

    public static function estadoKey(Cheque $c): string
    {
        $estado = (string) ($c->estado ?? ' ');
        if ($estado === 'R') {
            return 'rechazado';
        }
        if (! empty($c->fecha_acreditacion)) {
            return 'acreditado';
        }

        return 'transito';
    }
}
