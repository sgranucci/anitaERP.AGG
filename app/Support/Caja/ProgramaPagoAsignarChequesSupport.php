<?php

namespace App\Support\Caja;

use App\Models\Caja\Cheque;
use App\Models\Compras\ProgramaPago;
use App\Models\Compras\ProgramaPagoCheque;
use App\Models\Compras\ProgramaPagoLinea;
use App\Support\Compras\ProgramaPagoMesesSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Asigna CHT diferidos en cartera a montos programados (tolerancia ±30%).
 */
final class ProgramaPagoAsignarChequesSupport
{
    public const TOLERANCIA = 0.30;

    /**
     * @return array{
     *   asignados: list<array<string, mixed>>,
     *   discrepancias: list<array<string, mixed>>,
     *   resumen: array{asignados: int, discrepancias: int, monto_asignado: float, monto_sin_cubrir: float}
     * }
     */
    public static function asignar(ProgramaPago $programa, ?int $lineaId = null): array
    {
        $columnas = ProgramaPagoMesesSupport::columnas(
            (string) $programa->anio_mes_inicio,
            (int) $programa->cantidad_meses,
            (bool) $programa->incluye_transf
        );
        $clavesMes = [];
        foreach ($columnas as $col) {
            if (($col['anio_mes'] ?? null) !== null) {
                $clavesMes[$col['clave']] = true;
            }
        }

        $lineasQuery = ProgramaPagoLinea::query()
            ->where('programa_pago_id', $programa->id)
            ->with(['asignaciones', 'proveedores:id,codigo,nombre']);
        if ($lineaId !== null && $lineaId > 0) {
            $lineasQuery->where('id', $lineaId);
        }
        /** @var Collection<int, ProgramaPagoLinea> $lineas */
        $lineas = $lineasQuery->orderBy('orden')->orderBy('id')->get();

        $demandas = [];
        foreach ($lineas as $linea) {
            foreach ($linea->asignaciones as $asig) {
                $clave = (string) $asig->clave;
                if (! isset($clavesMes[$clave])) {
                    continue;
                }
                $monto = round((float) $asig->monto, 2);
                if ($monto < 0.01) {
                    continue;
                }
                $demandas[] = [
                    'linea_id' => (int) $linea->id,
                    'proveedor_id' => (int) $linea->proveedor_id,
                    'codigo' => (string) ($linea->proveedores->codigo ?? ''),
                    'nombre' => (string) ($linea->proveedores->nombre ?? ''),
                    'clave' => $clave,
                    'monto_programado' => $monto,
                ];
            }
        }

        if ($demandas === []) {
            return [
                'asignados' => [],
                'discrepancias' => [[
                    'tipo' => 'sin_programacion',
                    'proveedor' => '',
                    'clave' => '',
                    'monto_programado' => 0.0,
                    'monto_asignado' => 0.0,
                    'detalle' => $lineaId
                        ? 'El proveedor no tiene montos programados en meses (solo TRANSF o vacío).'
                        : 'No hay montos programados en meses para asignar cheques.',
                ]],
                'resumen' => [
                    'asignados' => 0,
                    'discrepancias' => 1,
                    'monto_asignado' => 0.0,
                    'monto_sin_cubrir' => 0.0,
                ],
            ];
        }

        $lineaIds = array_values(array_unique(array_map(static fn ($d) => $d['linea_id'], $demandas)));

        return DB::transaction(function () use ($programa, $demandas, $lineaIds, $clavesMes, $lineaId) {
            if ($lineaId !== null && $lineaId > 0) {
                EloquentAuditDeleteSupport::each(
                    ProgramaPagoCheque::query()
                        ->where('programa_pago_linea_id', $lineaId)
                );
            } else {
                EloquentAuditDeleteSupport::each(
                    ProgramaPagoCheque::query()
                        ->whereHas('linea', static fn ($q) => $q->where('programa_pago_id', $programa->id))
                );
            }

            $idsYaEnPrograma = ProgramaPagoCheque::query()
                ->whereHas('linea', static function ($q) use ($programa) {
                    $q->where('programa_pago_id', $programa->id);
                })
                ->pluck('cheque_id')
                ->map(static fn ($id) => (int) $id)
                ->all();

            $poolPorMes = self::cargarPoolCheques(
                (int) $programa->empresa_id,
                array_keys($clavesMes),
                $idsYaEnPrograma
            );

            $asignados = [];
            $discrepancias = [];
            $montoAsignado = 0.0;
            $montoSinCubrir = 0.0;

            usort($demandas, static function ($a, $b) {
                $cmp = $b['monto_programado'] <=> $a['monto_programado'];
                if ($cmp !== 0) {
                    return $cmp;
                }

                return [$a['clave'], $a['linea_id']] <=> [$b['clave'], $b['linea_id']];
            });

            foreach ($demandas as $demanda) {
                $clave = $demanda['clave'];
                $target = $demanda['monto_programado'];
                $pool = $poolPorMes[$clave] ?? [];
                $elegidos = self::seleccionarCheques($target, $pool);
                $suma = round(array_sum(array_map(static fn ($c) => $c['monto'], $elegidos)), 2);

                if ($elegidos === [] || ! self::dentroTolerancia($suma, $target)) {
                    $discrepancias[] = [
                        'tipo' => $elegidos === [] ? 'sin_cheques' : 'fuera_tolerancia',
                        'proveedor' => trim($demanda['codigo'].' '.$demanda['nombre']),
                        'linea_id' => $demanda['linea_id'],
                        'clave' => $clave,
                        'monto_programado' => $target,
                        'monto_asignado' => $suma,
                        'detalle' => self::mensajeDiscrepancia($elegidos === [] ? 'sin_cheques' : 'fuera_tolerancia', $target, $suma, count($pool)),
                    ];
                    $montoSinCubrir = round($montoSinCubrir + $target, 2);
                    continue;
                }

                $idsUsados = [];
                foreach ($elegidos as $ch) {
                    ProgramaPagoCheque::query()->create([
                        'programa_pago_linea_id' => $demanda['linea_id'],
                        'clave' => $clave,
                        'cheque_id' => $ch['id'],
                        'monto_cheque' => $ch['monto'],
                        'monto_programado' => $target,
                    ]);
                    $asignados[] = [
                        'cheque_id' => $ch['id'],
                        'numerocheque' => $ch['numerocheque'],
                        'nro_interno_anita' => $ch['nro_interno_anita'],
                        'fechapago' => $ch['fechapago'],
                        'monto' => $ch['monto'],
                        'proveedor' => trim($demanda['codigo'].' '.$demanda['nombre']),
                        'linea_id' => $demanda['linea_id'],
                        'clave' => $clave,
                        'monto_programado' => $target,
                    ];
                    $idsUsados[] = $ch['id'];
                    $montoAsignado = round($montoAsignado + $ch['monto'], 2);
                }

                $poolPorMes[$clave] = array_values(array_filter(
                    $pool,
                    static fn ($c) => ! in_array($c['id'], $idsUsados, true)
                ));
            }

            return [
                'asignados' => $asignados,
                'discrepancias' => $discrepancias,
                'resumen' => [
                    'asignados' => count($asignados),
                    'discrepancias' => count($discrepancias),
                    'monto_asignado' => $montoAsignado,
                    'monto_sin_cubrir' => $montoSinCubrir,
                ],
            ];
        });
    }

    /**
     * @param  list<string>  $clavesMes
     * @param  list<int>  $excluirChequeIds
     * @return array<string, list<array{id:int,monto:float,numerocheque:string,nro_interno_anita:?int,fechapago:string}>>
     */
    public static function cargarPoolCheques(int $empresaId, array $clavesMes, array $excluirChequeIds = []): array
    {
        $porMes = [];
        foreach ($clavesMes as $clave) {
            $porMes[$clave] = [];
        }
        if ($clavesMes === []) {
            return $porMes;
        }

        sort($clavesMes);
        $desde = Carbon::createFromFormat('Y-m', $clavesMes[0])->startOfMonth()->toDateString();
        $hasta = Carbon::createFromFormat('Y-m', $clavesMes[count($clavesMes) - 1])->endOfMonth()->toDateString();

        $query = self::queryCarteraDisponible($empresaId)
            ->whereBetween('fechapago', [$desde, $hasta])
            ->when($excluirChequeIds !== [], static fn (Builder $q) => $q->whereNotIn('id', $excluirChequeIds));

        foreach ($query->get(['id', 'monto', 'numerocheque', 'nro_interno_anita', 'fechapago']) as $cheque) {
            $clave = Carbon::parse((string) $cheque->fechapago)->format('Y-m');
            if (! array_key_exists($clave, $porMes)) {
                continue;
            }
            $porMes[$clave][] = [
                'id' => (int) $cheque->id,
                'monto' => round((float) $cheque->monto, 2),
                'numerocheque' => (string) ($cheque->numerocheque ?? ''),
                'nro_interno_anita' => $cheque->nro_interno_anita !== null ? (int) $cheque->nro_interno_anita : null,
                'fechapago' => (string) $cheque->fechapago,
            ];
        }

        return $porMes;
    }

    /**
     * @return Builder<Cheque>
     */
    public static function queryCarteraDisponible(int $empresaId): Builder
    {
        $query = Cheque::query()
            ->where('origen', 'R')
            ->whereNull('pagoproveedor_id')
            ->where(function (Builder $e) {
                $e->whereNull('estado')->orWhereIn('estado', [' ', 'N', '']);
            })
            ->where(function (Builder $c) {
                $c->whereNull('nro_caucion')->orWhere('nro_caucion', '')->orWhere('nro_caucion', '0');
            })
            ->whereNull('fecha_deposito');

        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query;
    }

    /**
     * @param  list<array{id:int,monto:float,numerocheque:string,nro_interno_anita:?int,fechapago:string}>  $pool
     * @return list<array{id:int,monto:float,numerocheque:string,nro_interno_anita:?int,fechapago:string}>
     */
    public static function seleccionarCheques(float $target, array $pool): array
    {
        if ($target < 0.01 || $pool === []) {
            return [];
        }

        $min = round($target * (1 - self::TOLERANCIA), 2);
        $max = round($target * (1 + self::TOLERANCIA), 2);

        $candidatos = [];

        foreach ($pool as $ch) {
            if ($ch['monto'] + 0.005 < $min || $ch['monto'] - 0.005 > $max) {
                continue;
            }
            $candidatos[] = [
                'cheques' => [$ch],
                'suma' => $ch['monto'],
                'diff' => abs($ch['monto'] - $target),
                'cant' => 1,
            ];
        }

        foreach (['desc', 'asc'] as $orden) {
            $ordenados = $pool;
            usort(
                $ordenados,
                $orden === 'desc'
                    ? static fn ($a, $b) => $b['monto'] <=> $a['monto']
                    : static fn ($a, $b) => $a['monto'] <=> $b['monto']
            );
            $seleccion = [];
            $suma = 0.0;
            foreach ($ordenados as $ch) {
                if ($suma + $ch['monto'] > $max + 0.005) {
                    continue;
                }
                $seleccion[] = $ch;
                $suma = round($suma + $ch['monto'], 2);
                if ($suma >= $target - 0.005) {
                    break;
                }
            }
            if ($seleccion !== [] && self::dentroTolerancia($suma, $target)) {
                $candidatos[] = [
                    'cheques' => $seleccion,
                    'suma' => $suma,
                    'diff' => abs($suma - $target),
                    'cant' => count($seleccion),
                ];
            }
        }

        if ($candidatos === []) {
            return [];
        }

        usort($candidatos, static function ($a, $b) {
            $cmp = $a['diff'] <=> $b['diff'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return $a['cant'] <=> $b['cant'];
        });

        return $candidatos[0]['cheques'];
    }

    public static function dentroTolerancia(float $asignado, float $programado): bool
    {
        if ($programado < 0.01) {
            return abs($asignado) < 0.01;
        }
        $ratio = abs($asignado - $programado) / $programado;

        return $ratio <= self::TOLERANCIA + 0.0001;
    }

    private static function mensajeDiscrepancia(string $tipo, float $target, float $suma, int $disponibles): string
    {
        $tolPct = (int) round(self::TOLERANCIA * 100);
        if ($tipo === 'sin_cheques') {
            if ($disponibles <= 0) {
                return "Sin cheques disponibles en el mes para cubrir {$target} (±{$tolPct}%).";
            }

            return "No se pudo armar un paquete de cheques dentro de ±{$tolPct}% del programado ({$target}). Disponibles en el mes: {$disponibles}.";
        }

        return "Suma asignable {$suma} fuera de ±{$tolPct}% respecto de {$target}.";
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listarAsignados(ProgramaPago $programa): array
    {
        $filas = ProgramaPagoCheque::query()
            ->whereHas('linea', static fn ($q) => $q->where('programa_pago_id', $programa->id))
            ->with([
                'cheque:id,numerocheque,nro_interno_anita,fechapago,monto,banco_id',
                'cheque.bancos:id,codigo,nombre',
                'linea:id,proveedor_id',
                'linea.proveedores:id,codigo,nombre',
            ])
            ->orderBy('clave')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($filas as $fila) {
            $ch = $fila->cheque;
            $linea = $fila->linea;
            $banco = $ch?->bancos;
            $out[] = [
                'id' => (int) $fila->id,
                'linea_id' => (int) $fila->programa_pago_linea_id,
                'clave' => (string) $fila->clave,
                'cheque_id' => (int) $fila->cheque_id,
                'numerocheque' => (string) ($ch->numerocheque ?? ''),
                'nro_interno_anita' => $ch && $ch->nro_interno_anita !== null ? (int) $ch->nro_interno_anita : null,
                'fechapago' => $ch && $ch->fechapago ? (string) $ch->fechapago : '',
                'monto' => round((float) $fila->monto_cheque, 2),
                'monto_programado' => round((float) $fila->monto_programado, 2),
                'banco' => $banco ? trim(($banco->codigo ?? '').' '.($banco->nombre ?? '')) : '',
                'proveedor' => $linea && $linea->proveedores
                    ? trim(($linea->proveedores->codigo ?? '').' '.($linea->proveedores->nombre ?? ''))
                    : '',
            ];
        }

        return $out;
    }
}
