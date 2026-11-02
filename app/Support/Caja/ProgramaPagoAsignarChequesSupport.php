<?php

namespace App\Support\Caja;

use App\Models\Caja\Cheque;
use App\Models\Compras\ProgramaPago;
use App\Models\Compras\ProgramaPagoAsignacion;
use App\Models\Compras\ProgramaPagoCheque;
use App\Models\Compras\ProgramaPagoLinea;
use App\Support\Compras\ProgramaPagoMesesSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Asigna CHT diferidos en cartera a montos programados (tolerancia ±10%).
 * Si el mes no entra, busca en todos los meses posteriores y deja la propuesta para que el usuario la acepte.
 */
final class ProgramaPagoAsignarChequesSupport
{
    public const TOLERANCIA = 0.10;

    /**
     * @return array{
     *   asignados: list<array<string, mixed>>,
     *   discrepancias: list<array<string, mixed>>,
     *   propuestas: list<array<string, mixed>>,
     *   ajustes: list<array<string, mixed>>,
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
                'propuestas' => [],
                'ajustes' => [],
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

            $clavesOrdenadas = array_keys($clavesMes);
            sort($clavesOrdenadas);
            $desdePrograma = Carbon::createFromFormat('Y-m', $clavesOrdenadas[0])->startOfMonth()->toDateString();

            $poolPorMes = self::cargarPoolCheques(
                (int) $programa->empresa_id,
                $clavesOrdenadas,
                $idsYaEnPrograma
            );
            $poolDesde = self::cargarChequesDesde(
                (int) $programa->empresa_id,
                $desdePrograma,
                $idsYaEnPrograma
            );

            $asignados = [];
            $discrepancias = [];
            $propuestas = [];
            $montoAsignado = 0.0;
            $montoSinCubrir = 0.0;
            $pendientes = [];

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
                    $hasta = self::seleccionarHasta($target, $pool);
                    $sumaHasta = round(array_sum(array_map(static fn ($c) => $c['monto'], $hasta)), 2);
                    if ($hasta !== [] && self::dentroTolerancia($sumaHasta, $target)) {
                        $elegidos = $hasta;
                        $suma = $sumaHasta;
                    }
                }

                if ($elegidos === [] || ! self::dentroTolerancia($suma, $target)) {
                    $pendientes[] = $demanda + [
                        'tipo' => $elegidos === [] ? 'sin_cheques' : 'fuera_tolerancia',
                        'disponibles_mes' => count($pool),
                        'suma_mes' => $suma,
                    ];
                    continue;
                }

                $idsUsados = self::grabarElegidos($demanda, $elegidos, $target, $asignados, $montoAsignado);
                self::quitarIdsDePools($poolPorMes, $poolDesde, $idsUsados);
            }

            usort($pendientes, static function ($a, $b) {
                $cmp = $a['clave'] <=> $b['clave'];
                if ($cmp !== 0) {
                    return $cmp;
                }

                return $b['monto_programado'] <=> $a['monto_programado'];
            });

            foreach ($pendientes as $demanda) {
                $clave = $demanda['clave'];
                $target = $demanda['monto_programado'];
                $limite = Carbon::createFromFormat('Y-m', $clave)->addMonth()->startOfMonth();
                $poolAdelante = array_values(array_filter(
                    $poolDesde,
                    static fn ($c) => Carbon::parse((string) $c['fechapago'])->greaterThanOrEqualTo($limite)
                ));
                $propuesta = self::proponerCobertura($target, $poolPorMes[$clave] ?? [], $poolAdelante);
                $detalle = self::mensajeDiscrepancia(
                    (string) $demanda['tipo'],
                    $target,
                    (float) $demanda['suma_mes'],
                    (int) $demanda['disponibles_mes']
                );
                $filaPropuesta = null;
                if ($propuesta['cheques'] !== []) {
                    $detalle .= ' Propuesta sin grabar: del mes '
                        .number_format($propuesta['suma_mes'], 2, ',', '.')
                        .' + posteriores '
                        .number_format($propuesta['suma_adelante'], 2, ',', '.')
                        .'.';
                    if (! $propuesta['dentro_tolerancia']) {
                        $detalle .= ' Queda fuera de ±'.((int) round(self::TOLERANCIA * 100)).'%: se puede aceptar igual.';
                    }
                    $filaPropuesta = [
                        'linea_id' => $demanda['linea_id'],
                        'clave' => $clave,
                        'proveedor' => trim($demanda['codigo'].' '.$demanda['nombre']),
                        'monto_programado' => $target,
                        'suma' => $propuesta['suma'],
                        'suma_mes' => $propuesta['suma_mes'],
                        'suma_adelante' => $propuesta['suma_adelante'],
                        'dentro_tolerancia' => $propuesta['dentro_tolerancia'],
                        'cheques' => $propuesta['cheques'],
                    ];
                    $propuestas[] = $filaPropuesta;
                    self::quitarIdsDePools(
                        $poolPorMes,
                        $poolDesde,
                        array_map(static fn ($c) => (int) $c['id'], $propuesta['cheques'])
                    );
                } else {
                    $detalle .= ' No hay cheques posteriores que acerquen el faltante.';
                }

                $discrepancias[] = [
                    'tipo' => $demanda['tipo'],
                    'proveedor' => trim($demanda['codigo'].' '.$demanda['nombre']),
                    'linea_id' => $demanda['linea_id'],
                    'clave' => $clave,
                    'monto_programado' => $target,
                    'monto_asignado' => 0.0,
                    'monto_propuesto' => $filaPropuesta['suma'] ?? 0.0,
                    'detalle' => $detalle,
                ];
                $montoSinCubrir = round($montoSinCubrir + $target, 2);
            }

            $excluir = [];
            foreach ($propuestas as $prop) {
                $excluir[(int) $prop['linea_id']][] = (string) $prop['clave'];
            }
            $ajustes = self::alinearProgramado(
                $programa,
                array_values(array_unique(array_map(static fn ($d) => (int) $d['linea_id'], $demandas))),
                $excluir
            );

            return [
                'asignados' => $asignados,
                'discrepancias' => $discrepancias,
                'propuestas' => $propuestas,
                'ajustes' => $ajustes,
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
     * @param  array<string, mixed>  $demanda
     * @param  list<array{id:int,monto:float,numerocheque:string,nro_interno_anita:?int,fechapago:string}>  $elegidos
     * @param  list<array<string, mixed>>  $asignados
     * @return list<int>
     */
    private static function grabarElegidos(array $demanda, array $elegidos, float $target, array &$asignados, float &$montoAsignado): array
    {
        $idsUsados = [];
        foreach ($elegidos as $ch) {
            ProgramaPagoCheque::query()->create([
                'programa_pago_linea_id' => $demanda['linea_id'],
                'clave' => $demanda['clave'],
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
                'clave' => $demanda['clave'],
                'monto_programado' => $target,
            ];
            $idsUsados[] = (int) $ch['id'];
            $montoAsignado = round($montoAsignado + $ch['monto'], 2);
        }

        return $idsUsados;
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
     * Cheques en cartera con fecha de pago desde $fechaDesde (inclusive), ordenados por fecha.
     *
     * @param  list<int>  $excluirChequeIds
     * @return list<array{id:int,monto:float,numerocheque:string,nro_interno_anita:?int,fechapago:string}>
     */
    public static function cargarChequesDesde(int $empresaId, string $fechaDesde, array $excluirChequeIds = []): array
    {
        $query = self::queryCarteraDisponible($empresaId)
            ->whereDate('fechapago', '>=', $fechaDesde)
            ->orderBy('fechapago')
            ->orderBy('id')
            ->when($excluirChequeIds !== [], static fn (Builder $q) => $q->whereNotIn('id', $excluirChequeIds));

        $out = [];
        foreach ($query->get(['id', 'monto', 'numerocheque', 'nro_interno_anita', 'fechapago']) as $cheque) {
            $out[] = self::filaCheque($cheque);
        }

        return $out;
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

    /**
     * Arma cheques del mes sin pasarse del programado y completa el faltante con cheques posteriores.
     * No graba nada.
     *
     * @param  list<array{id:int,monto:float,numerocheque:string,nro_interno_anita:?int,fechapago:string}>  $poolMes
     * @param  list<array{id:int,monto:float,numerocheque:string,nro_interno_anita:?int,fechapago:string}>  $poolAdelante
     * @return array{
     *   cheques: list<array{id:int,monto:float,numerocheque:string,nro_interno_anita:?int,fechapago:string,adelantado:bool}>,
     *   suma: float,
     *   suma_mes: float,
     *   suma_adelante: float,
     *   dentro_tolerancia: bool
     * }
     */
    public static function proponerCobertura(float $target, array $poolMes, array $poolAdelante): array
    {
        $vacio = [
            'cheques' => [],
            'suma' => 0.0,
            'suma_mes' => 0.0,
            'suma_adelante' => 0.0,
            'dentro_tolerancia' => false,
        ];
        if ($target < 0.01) {
            return $vacio;
        }

        $delMes = self::seleccionarHasta($target, $poolMes);
        $sumaMes = round(array_sum(array_map(static fn ($c) => $c['monto'], $delMes)), 2);
        $falta = round($target - $sumaMes, 2);
        $adelante = [];
        if ($falta > 0.009 && $poolAdelante !== []) {
            $adelante = self::seleccionarCheques($falta, $poolAdelante);
            if ($adelante === []) {
                $adelante = self::seleccionarMasCercano($falta, $poolAdelante);
            }
        }

        $cheques = [];
        foreach ($delMes as $ch) {
            $ch['adelantado'] = false;
            $cheques[] = $ch;
        }
        $idsMes = array_map(static fn ($c) => (int) $c['id'], $cheques);
        foreach ($adelante as $ch) {
            if (in_array((int) $ch['id'], $idsMes, true)) {
                continue;
            }
            $ch['adelantado'] = true;
            $cheques[] = $ch;
        }

        if ($cheques === [] && $poolMes !== []) {
            $cheques = [];
            foreach (self::seleccionarMasCercano($target, $poolMes) as $ch) {
                $ch['adelantado'] = false;
                $cheques[] = $ch;
            }
            $sumaMes = round(array_sum(array_map(static fn ($c) => $c['monto'], $cheques)), 2);
        }

        $completo = [];
        if ($poolAdelante !== []) {
            $completo = self::seleccionarCheques($target, $poolAdelante);
            if ($completo === []) {
                $completo = self::seleccionarMasCercano($target, $poolAdelante);
            }
        }
        $sumaActual = round(array_sum(array_map(static fn ($c) => $c['monto'], $cheques)), 2);
        $sumaCompleto = round(array_sum(array_map(static fn ($c) => $c['monto'], $completo)), 2);
        $diffActual = $cheques === [] ? INF : abs($sumaActual - $target);
        $diffCompleto = $completo === [] ? INF : abs($sumaCompleto - $target);
        if ($diffCompleto + 0.005 < $diffActual) {
            $cheques = [];
            foreach ($completo as $ch) {
                $ch['adelantado'] = true;
                $cheques[] = $ch;
            }
            $sumaMes = 0.0;
        }

        if ($cheques === []) {
            return $vacio;
        }

        $suma = round(array_sum(array_map(static fn ($c) => $c['monto'], $cheques)), 2);

        return [
            'cheques' => $cheques,
            'suma' => $suma,
            'suma_mes' => $sumaMes,
            'suma_adelante' => round($suma - $sumaMes, 2),
            'dentro_tolerancia' => self::dentroTolerancia($suma, $target),
        ];
    }

    /**
     * @param  list<array{id:int,monto:float}>  $pool
     * @return list<array{id:int,monto:float}>
     */
    public static function seleccionarHasta(float $target, array $pool): array
    {
        if ($target < 0.01 || $pool === []) {
            return [];
        }

        $ordenados = $pool;
        usort($ordenados, static fn ($a, $b) => $b['monto'] <=> $a['monto']);
        $seleccion = [];
        $suma = 0.0;
        foreach ($ordenados as $ch) {
            if ($suma + $ch['monto'] > $target + 0.005) {
                continue;
            }
            $seleccion[] = $ch;
            $suma = round($suma + $ch['monto'], 2);
            if ($suma >= $target - 0.005) {
                break;
            }
        }

        return $seleccion;
    }

    /**
     * Paquete más cercano al objetivo en todo el pool, aunque quede fuera de la tolerancia.
     * Recorre todas las fechas: no se queda con el primer cheque que achica la diferencia.
     *
     * @param  list<array{id:int,monto:float,fechapago?:string}>  $pool
     * @return list<array{id:int,monto:float,fechapago?:string}>
     */
    public static function seleccionarMasCercano(float $target, array $pool): array
    {
        if ($target < 0.01 || $pool === []) {
            return [];
        }

        $mejor = [];
        $mejorDiff = INF;
        foreach ($pool as $ch) {
            $diff = abs((float) $ch['monto'] - $target);
            if ($diff + 0.005 < $mejorDiff) {
                $mejor = [$ch];
                $mejorDiff = $diff;
            }
        }

        $pack = array_slice(self::seleccionarHasta($target, $pool), 0, 8);
        $sumaPack = round(array_sum(array_map(static fn ($c) => (float) $c['monto'], $pack)), 2);
        if ($pack !== [] && abs($sumaPack - $target) + 0.005 < $mejorDiff) {
            return $pack;
        }

        return $mejor;
    }

    /**
     * @param  array<string, list<array{id:int}>>  $poolPorMes
     * @param  list<array{id:int}>  $poolDesde
     * @param  list<int>  $ids
     */
    private static function quitarIdsDePools(array &$poolPorMes, array &$poolDesde, array $ids): void
    {
        if ($ids === []) {
            return;
        }
        $set = array_fill_keys($ids, true);
        foreach ($poolPorMes as $mes => $lista) {
            $poolPorMes[$mes] = array_values(array_filter(
                $lista,
                static fn ($c) => ! isset($set[(int) $c['id']])
            ));
        }
        $poolDesde = array_values(array_filter(
            $poolDesde,
            static fn ($c) => ! isset($set[(int) $c['id']])
        ));
    }

    /**
     * @return array{id:int,monto:float,numerocheque:string,nro_interno_anita:?int,fechapago:string}
     */
    private static function filaCheque(Cheque $cheque): array
    {
        return [
            'id' => (int) $cheque->id,
            'monto' => round((float) $cheque->monto, 2),
            'numerocheque' => (string) ($cheque->numerocheque ?? ''),
            'nro_interno_anita' => $cheque->nro_interno_anita !== null ? (int) $cheque->nro_interno_anita : null,
            'fechapago' => Carbon::parse((string) $cheque->fechapago)->toDateString(),
        ];
    }

    /**
     * Mueve el programado de cada mes al total de cheques de ese mes.
     * Lo que los cheques no cubren queda en TRANSF. El total del proveedor no cambia.
     * Las claves de $excluirPorLinea se dejan como están (propuestas todavía no aceptadas).
     *
     * @param  list<int>  $lineaIds
     * @param  array<int, list<string>>  $excluirPorLinea
     * @return list<array{linea_id:int,proveedor:string,transf:float,antes:array<string,float>,despues:array<string,float>}>
     */
    public static function alinearProgramado(ProgramaPago $programa, array $lineaIds, array $excluirPorLinea = []): array
    {
        $lineaIds = array_values(array_unique(array_filter(array_map('intval', $lineaIds))));
        if ($lineaIds === []) {
            return [];
        }

        $lineas = ProgramaPagoLinea::query()
            ->where('programa_pago_id', $programa->id)
            ->whereIn('id', $lineaIds)
            ->with(['asignaciones', 'chequesAsignados', 'proveedores:id,codigo,nombre'])
            ->get();

        $ajustes = [];
        foreach ($lineas as $linea) {
            $ajuste = self::alinearLinea(
                $linea,
                (bool) $programa->incluye_transf,
                $excluirPorLinea[(int) $linea->id] ?? []
            );
            if ($ajuste !== null) {
                $ajustes[] = $ajuste;
            }
        }

        return $ajustes;
    }

    /**
     * @param  list<string>  $clavesExcluidas
     * @return array{linea_id:int,proveedor:string,transf:float,antes:array<string,float>,despues:array<string,float>}|null
     */
    private static function alinearLinea(ProgramaPagoLinea $linea, bool $incluyeTransf, array $clavesExcluidas): ?array
    {
        $excluir = [];
        foreach ($clavesExcluidas as $clave) {
            $excluir[(string) $clave] = true;
        }

        $actual = [];
        foreach ($linea->asignaciones as $asig) {
            $monto = round((float) $asig->monto, 2);
            if ($monto < 0.005) {
                continue;
            }
            $actual[(string) $asig->clave] = $monto;
        }
        $total = round(array_sum($actual), 2);
        if ($total < 0.01) {
            return null;
        }

        $cheques = [];
        foreach ($linea->chequesAsignados as $fila) {
            $clave = (string) $fila->clave;
            if (isset($excluir[$clave])) {
                continue;
            }
            $cheques[$clave] = round(($cheques[$clave] ?? 0) + (float) $fila->monto_cheque, 2);
        }
        if ($cheques === []) {
            return null;
        }

        $bloqueado = 0.0;
        foreach (array_keys($excluir) as $clave) {
            $bloqueado += $actual[$clave] ?? 0;
        }
        $bloqueado = round($bloqueado, 2);
        $movable = round($total - $bloqueado, 2);
        if ($movable < 0.01) {
            return null;
        }

        $nuevos = [];
        foreach (array_keys($excluir) as $clave) {
            if (($actual[$clave] ?? 0) > 0.009) {
                $nuevos[$clave] = $actual[$clave];
            }
        }

        $sumaCheques = round(array_sum($cheques), 2);
        $claveTransf = ProgramaPagoMesesSupport::CLAVE_TRANSF;
        if ($sumaCheques <= $movable + 0.009) {
            foreach ($cheques as $clave => $monto) {
                if ($monto > 0.009) {
                    $nuevos[$clave] = $monto;
                }
            }
            $resto = round($movable - $sumaCheques, 2);
            if ($resto > 0.009) {
                if ($incluyeTransf && ! isset($excluir[$claveTransf])) {
                    $nuevos[$claveTransf] = round(($nuevos[$claveTransf] ?? 0) + $resto, 2);
                } else {
                    $ultima = array_key_last($cheques);
                    $nuevos[$ultima] = round(($nuevos[$ultima] ?? 0) + $resto, 2);
                }
            }
        } else {
            $restante = $movable;
            ksort($cheques);
            foreach ($cheques as $clave => $monto) {
                if ($restante < 0.01) {
                    break;
                }
                $tomar = round(min($monto, $restante), 2);
                if ($tomar > 0.009) {
                    $nuevos[$clave] = round(($nuevos[$clave] ?? 0) + $tomar, 2);
                }
                $restante = round($restante - $tomar, 2);
            }
        }

        ksort($actual);
        ksort($nuevos);
        if ($actual == $nuevos) {
            return null;
        }

        $clavesConservar = [];
        foreach ($nuevos as $clave => $monto) {
            $monto = round((float) $monto, 2);
            if ($monto < 0.005) {
                continue;
            }
            $clavesConservar[] = (string) $clave;
            ProgramaPagoAsignacion::query()->updateOrCreate(
                [
                    'programa_pago_linea_id' => $linea->id,
                    'clave' => $clave,
                ],
                ['monto' => $monto]
            );
        }
        $borrar = ProgramaPagoAsignacion::query()->where('programa_pago_linea_id', $linea->id);
        if ($clavesConservar !== []) {
            $borrar->whereNotIn('clave', $clavesConservar);
        }
        EloquentAuditDeleteSupport::each($borrar);

        foreach ($linea->chequesAsignados as $fila) {
            $clave = (string) $fila->clave;
            if (! isset($nuevos[$clave])) {
                continue;
            }
            $nuevoProg = round((float) $nuevos[$clave], 2);
            if (abs((float) $fila->monto_programado - $nuevoProg) > 0.009) {
                $fila->monto_programado = $nuevoProg;
                $fila->save();
            }
        }

        return [
            'linea_id' => (int) $linea->id,
            'proveedor' => trim((string) (($linea->proveedores->codigo ?? '').' '.($linea->proveedores->nombre ?? ''))),
            'transf' => round((float) ($nuevos[$claveTransf] ?? 0), 2),
            'antes' => $actual,
            'despues' => $nuevos,
        ];
    }

    public static function desasignar(
        ProgramaPago $programa,
        string $alcance,
        ?int $lineaId,
        ?string $clave,
        ?int $asignacionId
    ): int {
        $query = ProgramaPagoCheque::query()
            ->whereHas('linea', static fn ($q) => $q->where('programa_pago_id', $programa->id));

        if ($alcance === 'uno') {
            if ($asignacionId === null || $asignacionId <= 0) {
                throw new RuntimeException('No se indicó el cheque a quitar.');
            }
            $query->where('id', $asignacionId);
        } elseif ($alcance === 'celda') {
            if ($lineaId === null || $lineaId <= 0 || $clave === null || $clave === '') {
                throw new RuntimeException('No se indicó el mes a desasignar.');
            }
            $query->where('programa_pago_linea_id', $lineaId)->where('clave', $clave);
        } elseif ($alcance === 'linea') {
            if ($lineaId === null || $lineaId <= 0) {
                throw new RuntimeException('No se indicó el proveedor.');
            }
            $query->where('programa_pago_linea_id', $lineaId);
        } elseif ($alcance !== 'todos') {
            throw new RuntimeException('No se indicó qué cheques quitar.');
        }

        return EloquentAuditDeleteSupport::each($query);
    }

    /**
     * Graba propuestas ya aceptadas por el usuario. Cada propuesta se toma completa.
     *
     * @param  list<array<string, mixed>>  $propuestas
     * @return array{grabados: int, errores: list<string>, claves_ok: list<string>}
     */
    public static function persistirPropuestas(ProgramaPago $programa, array $propuestas): array
    {
        $grabados = 0;
        $errores = [];
        $clavesOk = [];

        DB::transaction(function () use ($programa, $propuestas, &$grabados, &$errores, &$clavesOk) {
            foreach ($propuestas as $prop) {
                $lineaId = (int) ($prop['linea_id'] ?? 0);
                $clave = (string) ($prop['clave'] ?? '');
                $target = round((float) ($prop['monto_programado'] ?? 0), 2);
                $cheques = is_array($prop['cheques'] ?? null) ? $prop['cheques'] : [];
                $proveedor = trim((string) ($prop['proveedor'] ?? ''));
                $etiqueta = trim($proveedor.' '.$clave);

                if ($lineaId <= 0 || ! preg_match('/^\d{4}-\d{2}$/', $clave) || $cheques === []) {
                    $errores[] = $etiqueta.': la propuesta no es válida.';
                    continue;
                }

                $linea = ProgramaPagoLinea::query()
                    ->with('asignaciones')
                    ->where('programa_pago_id', $programa->id)
                    ->where('id', $lineaId)
                    ->first();
                if ($linea === null) {
                    $errores[] = $etiqueta.': el proveedor no está en este programa.';
                    continue;
                }

                $montoActual = 0.0;
                foreach ($linea->asignaciones as $asig) {
                    if ((string) $asig->clave === $clave) {
                        $montoActual = round((float) $asig->monto, 2);
                    }
                }
                if (abs($montoActual - $target) > 0.05) {
                    $errores[] = $etiqueta.': el monto programado cambió. Volvé a asignar cheques.';
                    continue;
                }

                $modelos = [];
                $rota = false;
                $idsVistos = [];
                foreach ($cheques as $ch) {
                    $chequeId = (int) ($ch['id'] ?? 0);
                    if ($chequeId <= 0 || isset($idsVistos[$chequeId])) {
                        $errores[] = $etiqueta.': cheque inválido en la propuesta.';
                        $rota = true;
                        break;
                    }
                    $idsVistos[$chequeId] = true;
                    $disponible = self::queryCarteraDisponible((int) $programa->empresa_id)
                        ->where('id', $chequeId)
                        ->lockForUpdate()
                        ->first();
                    $usado = ProgramaPagoCheque::query()
                        ->where('cheque_id', $chequeId)
                        ->whereHas('linea', static fn ($q) => $q->where('programa_pago_id', $programa->id))
                        ->exists();
                    if ($disponible === null || $usado) {
                        $errores[] = $etiqueta.': el cheque '.(int) ($ch['nro_interno_anita'] ?? $chequeId).' ya no está disponible.';
                        $rota = true;
                        break;
                    }
                    $modelos[] = $disponible;
                }
                if ($rota) {
                    continue;
                }

                foreach ($modelos as $disponible) {
                    ProgramaPagoCheque::query()->create([
                        'programa_pago_linea_id' => $lineaId,
                        'clave' => $clave,
                        'cheque_id' => (int) $disponible->id,
                        'monto_cheque' => round((float) $disponible->monto, 2),
                        'monto_programado' => $target,
                    ]);
                    $grabados++;
                }
                $clavesOk[] = $lineaId.'|'.$clave;
            }
        });

        return ['grabados' => $grabados, 'errores' => $errores, 'claves_ok' => $clavesOk];
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
