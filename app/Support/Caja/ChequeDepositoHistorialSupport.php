<?php

namespace App\Support\Caja;

use App\Models\Caja\Cheque;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * Historial de boletas de depósito CHT: una fila por fecha + cuenta + nro. boleta.
 */
final class ChequeDepositoHistorialSupport
{
    /** @var list<string> */
    public const ESTADOS = ['transito', 'acreditado', 'rechazado', 'mixto'];

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   grupos: list<array<string, mixed>>,
     *   buckets: array<string, array{label:string, cantidad:int, monto:float}>,
     *   total_grupos:int,
     *   total_cheques:int,
     *   total_monto:float,
     *   totales_moneda: list<array{moneda:string, monto:float, cantidad:int}>
     * }
     */
    public static function resumir(array $filtros = [], ?EmpresaRepositoryInterface $empresaRepository = null): array
    {
        $empresaRepository ??= app(EmpresaRepositoryInterface::class);

        $q = Cheque::query()
            ->with([
                'bancos:id,nombre',
                'clientes:id,codigo,nombre',
                'monedas:id,abreviatura',
                'empresas:id,nombre',
                'cuentacajaDeposito:id,codigo,nombre',
            ])
            ->where('origen', 'R')
            ->whereNotNull('fecha_deposito');

        $empresaRepository->aplicarFiltroEmpresasAsignadas($q, 'empresa_id');

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

        $map = [];
        foreach ($cheques as $c) {
            $fecha = self::fechaYmd($c->fecha_deposito);
            $cuentaIdRow = (int) ($c->cuentacaja_deposito_id ?? 0);
            $boletaKey = trim((string) ($c->nro_boleta_deposito ?? ''));
            $key = $fecha.'|'.$cuentaIdRow.'|'.$boletaKey;

            if (! isset($map[$key])) {
                $cc = $c->cuentacajaDeposito;
                $cuentaLabel = '';
                if ($cc) {
                    $cuentaLabel = trim((string) ($cc->codigo ?? '').' — '.(string) ($cc->nombre ?? ''), ' —');
                }
                $map[$key] = [
                    'key' => $key,
                    'fecha_deposito' => $fecha,
                    'fecha_deposito_dmy' => ChequeDepositoComprobanteSupport::fechaDmy($fecha),
                    'nro_boleta' => $boletaKey,
                    'cuentacaja_id' => $cuentaIdRow > 0 ? $cuentaIdRow : null,
                    'cuenta' => $cuentaLabel,
                    'empresa_ids' => [],
                    'empresas' => [],
                    'nombreempresa' => '',
                    'cantidad' => 0,
                    'monto' => 0.0,
                    'totales_moneda' => [],
                    'estado_counts' => [
                        'transito' => 0,
                        'acreditado' => 0,
                        'rechazado' => 0,
                    ],
                    'cheque_ids' => [],
                    'cheques' => [],
                ];
            }

            $estadoKey = ChequeDepositoConciliacionSupport::estadoKey($c);
            $monto = round((float) $c->monto, 2);
            $moneda = trim((string) ($c->monedas->abreviatura ?? ''));
            if ($moneda === '') {
                $moneda = '$';
            }

            $map[$key]['cantidad']++;
            $map[$key]['monto'] = round($map[$key]['monto'] + $monto, 2);
            $map[$key]['estado_counts'][$estadoKey]++;
            $map[$key]['cheque_ids'][] = (int) $c->id;

            if (! isset($map[$key]['totales_moneda'][$moneda])) {
                $map[$key]['totales_moneda'][$moneda] = ['moneda' => $moneda, 'monto' => 0.0, 'cantidad' => 0];
            }
            $map[$key]['totales_moneda'][$moneda]['monto'] = round($map[$key]['totales_moneda'][$moneda]['monto'] + $monto, 2);
            $map[$key]['totales_moneda'][$moneda]['cantidad']++;

            $empId = (int) ($c->empresa_id ?? 0);
            $empNombre = trim((string) ($c->empresas->nombre ?? ''));
            if ($empId > 0 && ! in_array($empId, $map[$key]['empresa_ids'], true)) {
                $map[$key]['empresa_ids'][] = $empId;
                if ($empNombre !== '') {
                    $map[$key]['empresas'][] = $empNombre;
                }
            }

            $map[$key]['cheques'][] = [
                'id' => (int) $c->id,
                'numerocheque' => (string) ($c->numerocheque ?? ''),
                'nro_interno_anita' => $c->nro_interno_anita,
                'monto' => $monto,
                'moneda' => $moneda,
                'banco' => (string) ($c->bancos->nombre ?? ''),
                'cliente' => (string) ($c->clientes->nombre ?? ''),
                'cliente_id' => $c->cliente_id ? (int) $c->cliente_id : null,
                'estado' => $estadoKey,
                'estado_label' => match ($estadoKey) {
                    'acreditado' => 'Acreditado',
                    'rechazado' => 'Rechazado',
                    default => 'En tránsito',
                },
                'fecha_acreditacion' => self::fechaYmd($c->fecha_acreditacion),
            ];
        }

        $buckets = [
            'transito' => ['label' => 'En tránsito', 'cantidad' => 0, 'monto' => 0.],
            'acreditado' => ['label' => 'Acreditados', 'cantidad' => 0, 'monto' => 0.],
            'rechazado' => ['label' => 'Con rechazos', 'cantidad' => 0, 'monto' => 0.],
            'mixto' => ['label' => 'Mixtos', 'cantidad' => 0, 'monto' => 0.],
        ];

        $estadoFiltro = (string) ($filtros['estado'] ?? '');
        $grupos = [];
        $totalCheques = 0;
        $totalMonto = 0.;
        $totalesMonedaGlobal = [];

        foreach ($map as $g) {
            $g['estado'] = self::estadoGrupo($g['estado_counts']);
            $g['estado_label'] = $buckets[$g['estado']]['label'];
            $g['totales_moneda'] = array_values($g['totales_moneda']);
            $g['empresa'] = implode(' / ', $g['empresas']);
            $g['nombreempresa'] = $g['empresas'][0] ?? '';
            $g['url_comprobante_pdf'] = ChequeDepositoComprobanteSupport::url($g['cheque_ids']);
            $g['url_conciliacion'] = route('conciliacion_deposito_cheque', array_filter([
                'boleta' => $g['nro_boleta'] !== '' ? $g['nro_boleta'] : null,
                'cuentacaja_id' => $g['cuentacaja_id'],
                'desde' => $g['fecha_deposito'] !== '' ? $g['fecha_deposito'] : null,
                'hasta' => $g['fecha_deposito'] !== '' ? $g['fecha_deposito'] : null,
            ], static fn ($v) => $v !== null && $v !== ''));

            $buckets[$g['estado']]['cantidad']++;
            $buckets[$g['estado']]['monto'] = round($buckets[$g['estado']]['monto'] + $g['monto'], 2);

            if ($estadoFiltro !== '' && $estadoFiltro !== $g['estado']) {
                continue;
            }

            $totalCheques += $g['cantidad'];
            $totalMonto = round($totalMonto + $g['monto'], 2);
            foreach ($g['totales_moneda'] as $tm) {
                $m = $tm['moneda'];
                if (! isset($totalesMonedaGlobal[$m])) {
                    $totalesMonedaGlobal[$m] = ['moneda' => $m, 'monto' => 0.0, 'cantidad' => 0];
                }
                $totalesMonedaGlobal[$m]['monto'] = round($totalesMonedaGlobal[$m]['monto'] + $tm['monto'], 2);
                $totalesMonedaGlobal[$m]['cantidad'] += $tm['cantidad'];
            }

            $grupos[] = $g;
        }

        usort($grupos, static function (array $a, array $b): int {
            $cmp = strcmp((string) $b['fecha_deposito'], (string) $a['fecha_deposito']);
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmpB = strcmp((string) $b['nro_boleta'], (string) $a['nro_boleta']);
            if ($cmpB !== 0) {
                return $cmpB;
            }

            return ((int) $b['cuentacaja_id']) <=> ((int) $a['cuentacaja_id']);
        });

        return [
            'grupos' => $grupos,
            'buckets' => $buckets,
            'total_grupos' => count($grupos),
            'total_cheques' => $totalCheques,
            'total_monto' => $totalMonto,
            'totales_moneda' => array_values($totalesMonedaGlobal),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   paginator: LengthAwarePaginator,
     *   grupos: list<array<string, mixed>>,
     *   grupos_pagina: list<array<string, mixed>>,
     *   buckets: array,
     *   total_grupos:int,
     *   total_cheques:int,
     *   total_monto:float,
     *   totales_moneda: list
     * }
     */
    public static function resumirPaginado(
        array $filtros,
        int $page = 1,
        int $perPage = 25,
        ?EmpresaRepositoryInterface $empresaRepository = null
    ): array {
        $resumen = self::resumir($filtros, $empresaRepository);
        $page = max(1, $page);
        $slice = array_slice($resumen['grupos'], ($page - 1) * $perPage, $perPage);
        $paginator = new LengthAwarePaginator(
            $slice,
            count($resumen['grupos']),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'query' => request()->query()]
        );

        return array_merge($resumen, [
            'paginator' => $paginator,
            'grupos_pagina' => $slice,
        ]);
    }

    /**
     * @param  array{transito:int, acreditado:int, rechazado:int}  $counts
     */
    public static function estadoGrupo(array $counts): string
    {
        $t = (int) ($counts['transito'] ?? 0);
        $a = (int) ($counts['acreditado'] ?? 0);
        $r = (int) ($counts['rechazado'] ?? 0);
        $activos = ($t > 0 ? 1 : 0) + ($a > 0 ? 1 : 0) + ($r > 0 ? 1 : 0);

        if ($r > 0 && $t === 0 && $a === 0) {
            return 'rechazado';
        }
        if ($r > 0) {
            return 'rechazado';
        }
        if ($activos > 1) {
            return 'mixto';
        }
        if ($a > 0) {
            return 'acreditado';
        }

        return 'transito';
    }

    public static function fechaYmd(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }
        $f = trim((string) $fecha);
        if ($f === '') {
            return '';
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $f, $m)) {
            return $m[1];
        }

        return $f;
    }
}
