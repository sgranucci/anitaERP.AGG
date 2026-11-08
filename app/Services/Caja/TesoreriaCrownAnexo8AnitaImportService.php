<?php

namespace App\Services\Caja;

use App\ApiAnita;
use App\Models\Caja\TesoreriaCrownBovedaStockDia;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownMonedaExtranjera;
use App\Models\Caja\TesoreriaCrownMonedaExtranjeraMov;
use App\Models\Caja\TesoreriaCrownPropinaDia;
use App\Models\Caja\TesoreriaCrownResumenFinalDia;
use App\Models\Caja\TesoreriaCrownValor;
use App\Models\Configuracion\Sala;
use App\Support\Caja\TesoreriaCrownAnexo8Calculo;
use App\Support\Caja\TesoreriaCrownBovedaStockDiaSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Ventas\GastronomiaSalaSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Trae al ERP lo que el Anexo VIII todavía leía en vivo de Anita:
 * arqueo A, resumen final diario, propina del día y ledger monextr/monemov.
 */
class TesoreriaCrownAnexo8AnitaImportService
{
    /** @var array<string, string> */
    private const MONEDA = ['1' => 'ARS', '2' => 'USD', '3' => 'EUR'];

    /** @var array<int, string> */
    private const SIGNO_RESUMEN = [
        21 => '0', 22 => '1',
        25 => '0', 30 => '1',
        31 => '0', 32 => '1',
        33 => '0', 34 => '1',
        35 => '0', 36 => '1',
        6 => '0', 40 => '1',
        99 => '0', 98 => '1',
    ];

    /** @var array<int, int> */
    private const GRUPO_RESUMEN = [
        21 => 1, 22 => 1,
        25 => 3, 30 => 3,
        31 => 4, 32 => 4,
        33 => 5, 34 => 5,
        35 => 6, 36 => 6,
        6 => 7, 40 => 7,
        99 => 99, 98 => 99,
    ];

    /**
     * @return array{
     *     stock: array{crear: int, actualizar: int, omitir: int},
     *     resumen: array{crear: int, actualizar: int, omitir: int},
     *     propina: array{crear: int, actualizar: int, omitir: int},
     *     monextr: array{crear: int, actualizar: int, omitir: int, movs: int},
     *     filas: list<array<string, mixed>>,
     *     jornadas_invalidar: list<int>
     * }
     */
    public function plan(string $desdeYmd, string $hastaYmd): array
    {
        $this->assertCrown();
        $this->assertTablas();
        [$desde, $hasta] = $this->rango($desdeYmd, $hastaYmd);
        $sala = $this->salaCrown();
        $filas = [];
        $jornadasInvalidar = [];

        $stock = $this->planStock($sala, $desde, $hasta, $filas, $jornadasInvalidar);
        $resumen = $this->planResumen($sala, $desde, $hasta, $filas, $jornadasInvalidar);
        $propina = $this->planPropina($sala, $desde, $hasta, $filas);
        $monextr = $this->planMonextr($hasta, $filas);

        return compact('stock', 'resumen', 'propina', 'monextr', 'filas') + [
            'jornadas_invalidar' => array_values(array_unique($jornadasInvalidar)),
        ];
    }

    /**
     * @param  array{filas: list<array<string, mixed>>, jornadas_invalidar: list<int>}  $plan
     * @return array{altas: int, cambios: int}
     */
    public function aplicar(array $plan): array
    {
        $altas = 0;
        $cambios = 0;
        DB::transaction(function () use ($plan, &$altas, &$cambios) {
            foreach ($plan['filas'] as $fila) {
                if (($fila['accion'] ?? '') === 'omitir') {
                    continue;
                }
                $tipo = $fila['tipo'] ?? '';
                if ($tipo === 'stock') {
                    $jornada = TesoreriaCrownJornada::query()->find((int) $fila['jornada_id']);
                    if ($jornada === null) {
                        continue;
                    }
                    TesoreriaCrownBovedaStockDiaSupport::persistir(
                        $jornada,
                        $fila['cantidades'] ?? [],
                        TesoreriaCrownBovedaStockDia::FUENTE_ANITA
                    );
                    if (($fila['accion'] ?? '') === 'crear') {
                        $altas++;
                    } else {
                        $cambios++;
                    }
                } elseif ($tipo === 'propina') {
                    $row = TesoreriaCrownPropinaDia::query()->updateOrCreate(
                        [
                            'sala_id' => (int) $fila['datos']['sala_id'],
                            'fecha' => $fila['datos']['fecha'],
                        ],
                        [
                            'monto' => $fila['datos']['monto'],
                            'fuente' => $fila['datos']['fuente'],
                        ]
                    );
                    if ($row->wasRecentlyCreated) {
                        $altas++;
                    } else {
                        $cambios++;
                    }
                } elseif ($tipo === 'resumen') {
                    $row = TesoreriaCrownResumenFinalDia::query()->updateOrCreate(
                        [
                            'sala_id' => (int) $fila['datos']['sala_id'],
                            'fecha' => $fila['datos']['fecha'],
                            'codigo' => (int) $fila['datos']['codigo'],
                        ],
                        [
                            'monto' => $fila['datos']['monto'],
                            'fuente' => $fila['datos']['fuente'],
                        ]
                    );
                    if ($row->wasRecentlyCreated) {
                        $altas++;
                    } else {
                        $cambios++;
                    }
                } elseif ($tipo === 'monextr') {
                    $partida = TesoreriaCrownMonedaExtranjera::query()->updateOrCreate(
                        ['anita_id' => (int) $fila['datos']['anita_id']],
                        [
                            'fecha' => $fila['datos']['fecha'],
                            'moneda' => $fila['datos']['moneda'],
                            'cotizacion' => $fila['datos']['cotizacion'],
                            'monto_orig' => $fila['datos']['monto_orig'],
                        ]
                    );
                    if ($partida->wasRecentlyCreated) {
                        $altas++;
                    } else {
                        $cambios++;
                    }
                    EloquentAuditDeleteSupport::each(
                        TesoreriaCrownMonedaExtranjeraMov::query()
                            ->where('moneda_extranjera_id', $partida->id)
                    );
                    foreach ($fila['movimientos'] ?? [] as $mov) {
                        TesoreriaCrownMonedaExtranjeraMov::query()->create([
                            'moneda_extranjera_id' => $partida->id,
                            'fecha' => $mov['fecha'],
                            'monto' => $mov['monto'],
                        ]);
                    }
                }
            }
        });

        foreach ($plan['jornadas_invalidar'] ?? [] as $jornadaId) {
            $jornada = TesoreriaCrownJornada::query()->find((int) $jornadaId);
            if ($jornada) {
                TesoreriaCrownAnexo8Calculo::invalidarCache($jornada);
            }
        }

        return compact('altas', 'cambios');
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<int>  $jornadasInvalidar
     * @return array{crear: int, actualizar: int, omitir: int}
     */
    private function planStock(
        Sala $sala,
        int $desde,
        int $hasta,
        array &$filas,
        array &$jornadasInvalidar
    ): array {
        $crear = $actualizar = $omitir = 0;
        $mapa = $this->mapaValorPorDenominacion((int) $sala->id);
        $raw = $this->consultar(
            'f,mon,den,cant',
            'SELECT renmb_fecha as f, renmb_cod_mon as mon, renmb_valor as den, SUM(renmb_cantidad) as cant'
            .' FROM rendmbill'
            .' WHERE renmb_fecha BETWEEN '.$desde.' AND '.$hasta
            ." AND renmb_origen = 'A'"
            .' GROUP BY renmb_fecha, renmb_cod_mon, renmb_valor'
            .' ORDER BY 1'
        );
        $porFecha = [];
        foreach ($raw as $fila) {
            $fechaInt = (int) ($fila->f ?? 0);
            $codigo = self::MONEDA[trim((string) ($fila->mon ?? ''))] ?? null;
            if ($codigo === null || $fechaInt <= 0) {
                continue;
            }
            $den = round((float) ($fila->den ?? 0), 2);
            $cant = (float) ($fila->cant ?? 0);
            if (abs($cant) < 0.0001) {
                continue;
            }
            $valorId = $mapa[$codigo][$den] ?? null;
            if ($valorId === null) {
                throw new RuntimeException(
                    'Arqueo A '.$this->ymd($fechaInt)." {$codigo} den {$den}: sin valor ERP."
                );
            }
            $porFecha[$fechaInt][$valorId] = ($porFecha[$fechaInt][$valorId] ?? 0.0) + $cant;
        }

        foreach ($porFecha as $fechaInt => $cantidades) {
            $ymd = $this->ymd((int) $fechaInt);
            $jornada = TesoreriaCrownJornada::query()
                ->where('sala_id', $sala->id)
                ->whereDate('fecha', $ymd)
                ->first();
            if ($jornada === null) {
                $filas[] = [
                    'grupo' => 'stock',
                    'tipo' => 'stock',
                    'accion' => 'omitir',
                    'etiqueta' => $ymd.' sin jornada ERP',
                ];
                $omitir++;

                continue;
            }
            $existente = TesoreriaCrownBovedaStockDia::query()
                ->where('jornada_id', $jornada->id)
                ->first();
            $accion = $existente ? 'actualizar' : 'crear';
            if ($accion === 'crear') {
                $crear++;
            } else {
                $actualizar++;
            }
            $jornadasInvalidar[] = (int) $jornada->id;
            $filas[] = [
                'grupo' => 'stock',
                'tipo' => 'stock',
                'accion' => $accion,
                'etiqueta' => $ymd.' ('.count($cantidades).' dens)',
                'jornada_id' => (int) $jornada->id,
                'cantidades' => $cantidades,
            ];
        }

        return compact('crear', 'actualizar', 'omitir');
    }

    /**
     * Snapshot acumulado ≤ fecha de los ítems de resumen final (como l-estboveda).
     *
     * @param  list<array<string, mixed>>  $filas
     * @param  list<int>  $jornadasInvalidar
     * @return array{crear: int, actualizar: int, omitir: int}
     */
    private function planResumen(
        Sala $sala,
        int $desde,
        int $hasta,
        array &$filas,
        array &$jornadasInvalidar
    ): array {
        $crear = $actualizar = $omitir = 0;
        $codigos = implode(',', array_keys(self::SIGNO_RESUMEN));
        $cursor = \Carbon\Carbon::createFromFormat('Ymd', (string) $desde)->startOfDay();
        $fin = \Carbon\Carbon::createFromFormat('Ymd', (string) $hasta)->startOfDay();
        for (; $cursor->lte($fin); $cursor->addDay()) {
            $ymd = $cursor->toDateString();
            $fechaInt = (int) $cursor->format('Ymd');
            $raw = $this->consultar(
                'cod,monto',
                'SELECT e.efeb_cod_trans as cod, SUM(r.renmb_cantidad * r.renmb_valor) as monto'
                .' FROM efeboveda e, rendmbill r'
                .' WHERE e.efeb_fecha <= '.$fechaInt
                .' AND e.efeb_nro_oper = r.renmb_nro_oper'
                ." AND r.renmb_origen = 'B' AND r.renmb_cod_mon = 1"
                .' AND e.efeb_cod_trans IN ('.$codigos.')'
                .' GROUP BY e.efeb_cod_trans'
            );
            $porGrupo = [];
            foreach ($raw as $fila) {
                $cod = (int) ($fila->cod ?? 0);
                $sr = self::SIGNO_RESUMEN[$cod] ?? null;
                if ($sr === null) {
                    continue;
                }
                $signo = $sr === '1' ? -1.0 : 1.0;
                $grupo = self::GRUPO_RESUMEN[$cod] ?? $cod;
                $porGrupo[$grupo] = ($porGrupo[$grupo] ?? 0.0) + ((float) ($fila->monto ?? 0) * $signo);
            }
            $jornada = TesoreriaCrownJornada::query()
                ->where('sala_id', $sala->id)
                ->whereDate('fecha', $ymd)
                ->first();
            if ($jornada) {
                $jornadasInvalidar[] = (int) $jornada->id;
            }
            foreach ($porGrupo as $codigo => $monto) {
                $monto = round($monto, 2);
                if (abs($monto) < 0.001) {
                    continue;
                }
                $existente = TesoreriaCrownResumenFinalDia::query()
                    ->where('sala_id', $sala->id)
                    ->whereDate('fecha', $ymd)
                    ->where('codigo', (int) $codigo)
                    ->first();
                $accion = 'crear';
                if ($existente) {
                    if (abs((float) $existente->monto - $monto) < 0.001
                        && $existente->fuente === TesoreriaCrownResumenFinalDia::FUENTE_ANITA) {
                        $accion = 'omitir';
                        $omitir++;
                    } else {
                        $accion = 'actualizar';
                        $actualizar++;
                    }
                } else {
                    $crear++;
                }
                $filas[] = [
                    'grupo' => 'resumen',
                    'tipo' => 'resumen',
                    'accion' => $accion,
                    'etiqueta' => $ymd.' cod '.$codigo.' = '.number_format($monto, 2, ',', '.'),
                    'datos' => [
                        'sala_id' => (int) $sala->id,
                        'fecha' => $ymd,
                        'codigo' => (int) $codigo,
                        'monto' => $monto,
                        'fuente' => TesoreriaCrownResumenFinalDia::FUENTE_ANITA,
                    ],
                ];
            }
        }

        return compact('crear', 'actualizar', 'omitir');
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{crear: int, actualizar: int, omitir: int}
     */
    private function planPropina(Sala $sala, int $desde, int $hasta, array &$filas): array
    {
        $crear = $actualizar = $omitir = 0;
        $raw = $this->consultar(
            'f,p',
            'SELECT prop_fecha as f, prop_propina as p FROM propina'
            .' WHERE prop_fecha BETWEEN '.$desde.' AND '.$hasta
            .' ORDER BY 1'
        );
        foreach ($raw as $fila) {
            $fechaInt = (int) ($fila->f ?? 0);
            if ($fechaInt <= 0) {
                continue;
            }
            $ymd = $this->ymd($fechaInt);
            $monto = round((float) ($fila->p ?? 0), 2);
            $existente = TesoreriaCrownPropinaDia::query()
                ->where('sala_id', $sala->id)
                ->whereDate('fecha', $ymd)
                ->first();
            $accion = 'crear';
            if ($existente) {
                if (abs((float) $existente->monto - $monto) < 0.001
                    && $existente->fuente === TesoreriaCrownPropinaDia::FUENTE_ANITA) {
                    $accion = 'omitir';
                    $omitir++;
                } else {
                    $accion = 'actualizar';
                    $actualizar++;
                }
            } else {
                $crear++;
            }
            $filas[] = [
                'grupo' => 'propina',
                'tipo' => 'propina',
                'accion' => $accion,
                'etiqueta' => $ymd.' = '.number_format($monto, 2, ',', '.'),
                'datos' => [
                    'sala_id' => (int) $sala->id,
                    'fecha' => $ymd,
                    'monto' => $monto,
                    'fuente' => TesoreriaCrownPropinaDia::FUENTE_ANITA,
                ],
            ];
        }

        return compact('crear', 'actualizar', 'omitir');
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{crear: int, actualizar: int, omitir: int, movs: int}
     */
    private function planMonextr(int $hasta, array &$filas): array
    {
        $crear = $actualizar = $omitir = 0;
        $movs = 0;
        $partidas = $this->consultar(
            'id,f,mon,cot,orig',
            'SELECT mone_id as id, mone_fecha as f, mone_cod_mon as mon,'
            .' mone_cotizacion as cot, mone_monto_orig as orig'
            .' FROM monextr'
            .' WHERE mone_fecha <= '.$hasta
            .' AND mone_monto_orig <> 0'
            .' ORDER BY 1'
        );
        if ($partidas === []) {
            return compact('crear', 'actualizar', 'omitir', 'movs');
        }
        $ids = array_map(fn ($p) => (int) ($p->id ?? 0), $partidas);
        $ids = array_values(array_filter($ids));
        $appsPorId = [];
        foreach (array_chunk($ids, 80) as $chunk) {
            $lista = implode(',', $chunk);
            $apps = $this->consultar(
                'id,f,monto',
                'SELECT monev_id_monextr as id, monev_fecha as f, monev_monto as monto'
                .' FROM monemov'
                .' WHERE monev_id_monextr IN ('.$lista.')'
                .' AND monev_fecha <= '.$hasta
                .' ORDER BY 1, 2'
            );
            foreach ($apps as $app) {
                $id = (int) ($app->id ?? 0);
                $appsPorId[$id][] = [
                    'fecha' => $this->ymd((int) ($app->f ?? 0)),
                    'monto' => round((float) ($app->monto ?? 0), 2),
                ];
            }
        }

        $existentes = TesoreriaCrownMonedaExtranjera::query()
            ->whereIn('anita_id', $ids)
            ->withCount('movimientos')
            ->get()
            ->keyBy('anita_id');

        foreach ($partidas as $partida) {
            $anitaId = (int) ($partida->id ?? 0);
            if ($anitaId <= 0) {
                continue;
            }
            $monCod = trim((string) ($partida->mon ?? ''));
            $moneda = self::MONEDA[$monCod] ?? null;
            if ($moneda === null || $moneda === 'ARS') {
                continue;
            }
            $fechaInt = (int) ($partida->f ?? 0);
            if ($fechaInt <= 0) {
                continue;
            }
            $datos = [
                'anita_id' => $anitaId,
                'fecha' => $this->ymd($fechaInt),
                'moneda' => $moneda,
                'cotizacion' => round((float) ($partida->cot ?? 0), 6),
                'monto_orig' => round((float) ($partida->orig ?? 0), 2),
            ];
            $movimientos = $appsPorId[$anitaId] ?? [];
            $movs += count($movimientos);
            $existente = $existentes->get($anitaId);
            $accion = 'crear';
            if ($existente) {
                $igual = abs((float) $existente->monto_orig - $datos['monto_orig']) < 0.001
                    && abs((float) $existente->cotizacion - $datos['cotizacion']) < 0.000001
                    && $existente->moneda === $datos['moneda']
                    && $existente->fecha->format('Y-m-d') === $datos['fecha']
                    && (int) ($existente->movimientos_count ?? 0) === count($movimientos);
                if ($igual) {
                    $accion = 'omitir';
                    $omitir++;
                } else {
                    $accion = 'actualizar';
                    $actualizar++;
                }
            } else {
                $crear++;
            }
            $filas[] = [
                'grupo' => 'monextr',
                'tipo' => 'monextr',
                'accion' => $accion,
                'etiqueta' => '#'.$anitaId.' '.$datos['moneda'].' '.$datos['fecha']
                    .' orig='.number_format($datos['monto_orig'], 2, ',', '.')
                    .' ('.count($movimientos).' movs)',
                'datos' => $datos,
                'movimientos' => $movimientos,
            ];
        }

        return compact('crear', 'actualizar', 'omitir', 'movs');
    }

    /**
     * @return array<string, array<float, int>>
     */
    private function mapaValorPorDenominacion(int $salaId): array
    {
        $mapa = ['ARS' => [], 'USD' => [], 'EUR' => []];
        $valores = TesoreriaCrownValor::query()
            ->with('tipoValor')
            ->where('sala_id', $salaId)
            ->get(['id', 'denominacion', 'tipo_valor_id']);
        foreach ($valores as $valor) {
            $codigo = (string) ($valor->tipoValor->codigo ?? '');
            if (! isset($mapa[$codigo])) {
                continue;
            }
            $mapa[$codigo][round((float) $valor->denominacion, 2)] = (int) $valor->id;
        }

        return $mapa;
    }

    private function salaCrown(): Sala
    {
        $codigoSala = strtoupper(trim((string) config('caja_anita.sala_codigo', 'CIP')));
        $salaId = GastronomiaSalaSupport::idPorCodigo($codigoSala);
        $sala = $salaId ? Sala::query()->find($salaId) : Sala::query()->orderBy('id')->first();
        if ($sala === null) {
            throw new RuntimeException('No hay sala Crown ('.$codigoSala.').');
        }

        return $sala;
    }

    private function assertCrown(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            throw new RuntimeException('La importación Anexo VIII solo corre en Crown.');
        }
    }

    private function assertTablas(): void
    {
        foreach ([
            'tesoreria_crown_boveda_stock_dia',
            'tesoreria_crown_boveda_stock_dia_linea',
            'tesoreria_crown_resumen_final_dia',
            'tesoreria_crown_propina_dia',
            'tesoreria_crown_moneda_extranjera',
            'tesoreria_crown_moneda_extranjera_mov',
        ] as $tabla) {
            if (! Schema::hasTable($tabla)) {
                throw new RuntimeException(
                    'Falta la tabla '.$tabla.'. Corré la migración 2026_11_07_230000_tesoreria_crown_anexo8_erp.'
                );
            }
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function rango(string $desdeYmd, string $hastaYmd): array
    {
        $desde = (int) str_replace('-', '', $desdeYmd);
        $hasta = (int) str_replace('-', '', $hastaYmd);
        if ($desde <= 0 || $hasta < $desde) {
            throw new RuntimeException('Rango de fechas inválido.');
        }

        return [$desde, $hasta];
    }

    private function ymd(int $fechaInt): string
    {
        return sprintf(
            '%04d-%02d-%02d',
            intdiv($fechaInt, 10000),
            intdiv($fechaInt % 10000, 100),
            $fechaInt % 100
        );
    }

    /**
     * @return list<object>
     */
    private function consultar(string $campos, string $sql): array
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'customSql',
            'campos' => $campos,
            'customSql' => $sql,
            'sistema' => config('caja_anita.sistema'),
        ];
        $ifx = trim((string) config('caja_anita.ifx_server', ''));
        if ($ifx !== '') {
            $payload['ifx_server'] = $ifx;
        }
        $path = rtrim(trim((string) config('caja_anita.path_sistema', '')), '/');
        if ($path !== '') {
            $payload['path_sistema'] = $path;
        }
        $raw = $api->apiCall($payload);
        $error = ApiAnita::extraerMensajeError(is_string($raw) ? $raw : null);
        if ($error !== null && $error !== '') {
            throw new RuntimeException($error);
        }
        $filas = json_decode(is_string($raw) ? $raw : '[]');

        return is_array($filas) ? $filas : [];
    }
}
