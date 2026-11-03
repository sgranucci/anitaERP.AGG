<?php

namespace App\Services\Sueldos;

use App\ApiAnita;
use App\Models\Sueldos\Acumulador_Sueldos;
use App\Models\Sueldos\Categoria_Base_Sueldos;
use App\Models\Sueldos\Categoria_Sueldos;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Models\Sueldos\Empleado_Familiar_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Acumulador_Sueldos;
use App\Models\Sueldos\Liquidacion_Recibo_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Models\Sueldos\Nombrebase_Sueldos;
use App\Repositories\Sueldos\Antiguedad_Tabla_SueldosRepositoryInterface;
use App\Repositories\Sueldos\Art_SueldosRepositoryInterface;
use App\Repositories\Sueldos\Agrupamiento_SueldosRepositoryInterface;
use App\Repositories\Sueldos\Categoria_SueldosRepositoryInterface;
use App\Repositories\Sueldos\Concepto_SueldosRepositoryInterface;
use App\Repositories\Sueldos\Empleado_SueldosRepositoryInterface;
use App\Repositories\Sueldos\Grupo_Concepto_SueldosRepositoryInterface;
use App\Repositories\Sueldos\Lugartrabajo_SueldosRepositoryInterface;
use App\Repositories\Sueldos\Motivoegreso_SueldosRepositoryInterface;
use App\Repositories\Sueldos\Nombrebase_SueldosRepositoryInterface;
use App\Repositories\Sueldos\Novedad_SueldosRepositoryInterface;
use App\Repositories\Sueldos\Obrasocial_SueldosRepositoryInterface;
use App\Repositories\Sueldos\Sindicato_SueldosRepositoryInterface;
use App\Support\Sueldos\AnitaAuxLiquidacionSupport;
use App\Support\Sueldos\CategoriaOrigenBases;
use App\Support\Sueldos\LiquidacionDetalleTotalesSupport;
use App\Support\Sueldos\NovedadSueldosCatalogo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cierra en el ERP un mes ya liquidado en Anita (cabeceras, nómina, bases,
 * ganancias y novedades pendientes) para poder abrir el mes siguiente.
 */
class AnitaCierreMesImportService
{
    /** nro_interno Anita 2026 → línea de entrada del plan ERP. */
    private const MOVIMIENTO_LINEA = [
        1 => 'SUJETO_APORTES',
        22 => 'PREMIO_DESEMP',
        27 => 'FALLO_CAJA',
        2 => 'INGRESO_OE',
        8 => 'INGRESO_OE_CONC',
        130 => 'AGUINALDO_PAGO',
        37 => 'AGUINALDO_PRORR',
        36 => 'AGUINALDO_2',
        132 => 'GRATIF_SUJETA',
        104 => 'APORTE_RNSS',
        136 => 'LEY_19032',
        107 => 'OBRA_SOCIAL',
        3 => 'OBRA_SOCIAL',
        4 => 'OBRA_SOCIAL',
        118 => 'GASTOS_INDUMENTARIA',
        35 => 'GASTOS_EDUCATIVOS',
        119 => 'HONORARIOS_MEDICOS',
        121 => 'ALQUILERES',
        120 => 'INTERESES_HIPOT',
        115 => 'DONACIONES',
        117 => 'CUOTA_SINDICAL',
        141 => 'RET_OE',
    ];

    /** El plan suma estas entradas: Anita las guarda en positivo. */
    private const LINEAS_NEGATIVAS = [
        'APORTE_RNSS' => true,
        'LEY_19032' => true,
        'OBRA_SOCIAL' => true,
        'GASTOS_INDUMENTARIA' => true,
        'GASTOS_EDUCATIVOS' => true,
        'HONORARIOS_MEDICOS' => true,
        'ALQUILERES' => true,
        'INTERESES_HIPOT' => true,
        'DONACIONES' => true,
        'CUOTA_SINDICAL' => true,
        'RET_OE' => true,
    ];

    /** nro_interno de cantidades (cónyuge / hijos) del plan 2026. */
    private const CANTIDAD_LINEA = [
        127 => 'CONYUGE',
        5 => 'HIJOS',
        128 => 'HIJOS_50',
        23 => 'HIJO_INCAP',
    ];

    /** deducmae Anita → código de ganancia_deduccion_valor_sueldos. */
    private const DEDUCCION_CODIGO = [
        1 => 'CONYUGE',
        2 => 'HIJO',
        4 => 'DE_ESP2',
        11 => 'GNI',
        102 => 'HIJO_INCAP',
    ];

    public function __construct(
        private Nombrebase_SueldosRepositoryInterface $nombresBase,
        private Categoria_SueldosRepositoryInterface $categorias,
        private Obrasocial_SueldosRepositoryInterface $obrasSociales,
        private Sindicato_SueldosRepositoryInterface $sindicatos,
        private Art_SueldosRepositoryInterface $arts,
        private Motivoegreso_SueldosRepositoryInterface $motivos,
        private Lugartrabajo_SueldosRepositoryInterface $lugares,
        private Agrupamiento_SueldosRepositoryInterface $agrupamientos,
        private Grupo_Concepto_SueldosRepositoryInterface $grupos,
        private Antiguedad_Tabla_SueldosRepositoryInterface $antiguedad,
        private Concepto_SueldosRepositoryInterface $conceptos,
        private Empleado_SueldosRepositoryInterface $empleados,
        private AnitaLiquidacionNovedadSyncService $liquidacionesAnita,
        private Novedad_SueldosRepositoryInterface $novedades,
        private AnitaAuxLiquidacionSupport $aux,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function importar(int $anio, int $mes, ?callable $log = null): array
    {
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '-1');

        $log = $log ?? static function (): void {};
        $periodo = $anio * 100 + $mes;
        $desde = $periodo * 100 + 1;
        $hasta = (int) Carbon::create($anio, $mes, 1)->endOfMonth()->format('Ymd');
        $periodoSiguiente = (int) Carbon::create($anio, $mes, 1)->addMonth()->format('Ym');
        $res = ['periodo' => $periodo, 'errores' => []];

        $log('Maestros faltantes…');
        $res['maestros'] = $this->maestrosFaltantes($log);

        $log('Bases de categoría…');
        $res['bases_categoria'] = $this->actualizarBasesCategoria($periodoSiguiente);

        $log('Empleados…');
        $res['empleados'] = $this->empleados->sincronizarConAnita();
        if (! empty($res['empleados']['errores'])) {
            $res['errores'] = array_merge($res['errores'], $res['empleados']['errores']);
        }

        $log('Familiares para ganancias…');
        $res['familiares'] = $this->importarFamiliares();

        $empresas = $this->empresasAlineadas();
        $res['empresas'] = array_keys($empresas);
        $numeros = [];
        $res['liquidaciones'] = [];
        $res['novedades'] = [];
        foreach ($empresas as $empresaId) {
            $log("Liquidaciones y novedades empresa {$empresaId}…");
            $sync = $this->liquidacionesAnita->sincronizarEmpresaDesdeFechaLiq($empresaId, $desde, $hasta);
            $res['liquidaciones'][$empresaId] = $sync['liquidaciones'];
            $res['novedades'][$empresaId] = $sync['novedades'];
            foreach ($sync['liquidaciones']['numeros'] as $n) {
                $numeros[$empresaId][] = (int) $n;
            }
            $res['errores'] = array_merge(
                $res['errores'],
                $sync['liquidaciones']['errores'] ?? [],
                $sync['novedades']['errores'] ?? []
            );

            $log("Novedades pendientes empresa {$empresaId}…");
            $pend = $this->novedades->sincronizarConAnita([
                'empresa_id' => $empresaId,
                'numeros_liquidacion' => [0],
            ]);
            $res['novedades_pendientes'][$empresaId] = $pend;
            $res['errores'] = array_merge($res['errores'], $pend['errores'] ?? []);
        }

        $asignadas = DB::table('novedad_sueldos')
            ->where('origen', NovedadSueldosCatalogo::ORIGEN_SYNC_ANITA)
            ->whereNull('liquidacion_id')
            ->where('estado', NovedadSueldosCatalogo::ESTADO_PENDIENTE)
            ->where('observacion', 'Sync Anita liq 0')
            ->where(function ($q) {
                $q->whereNull('periodo')->orWhere('periodo', 0);
            })
            ->update(['periodo' => $periodoSiguiente, 'updated_at' => now()]);
        $res['novedades_periodo_siguiente'] = $asignadas;

        $log('Tablas de ganancias…');
        $res['ganancias_tablas'] = $this->actualizarTablasGanancias($anio);
        $log('Movimientos de ganancias…');
        $res['ganancias_movimientos'] = $this->importarMovimientosGanancias($anio, $mes);

        $res['nomina'] = [];
        foreach ($numeros as $empresaId => $lista) {
            foreach ($lista as $numero) {
                $log("Nómina {$empresaId}/{$numero}…");
                $liq = Liquidacion_Sueldos::query()
                    ->where('empresa_id', $empresaId)
                    ->where('numero', $numero)
                    ->first();
                if (! $liq) {
                    $res['nomina'][] = ['empresa' => $empresaId, 'numero' => $numero, 'error' => 'sin cabecera ERP'];

                    continue;
                }
                $res['nomina'][] = $this->importarNomina($liq, $empresaId, $numero);
            }
        }

        $borradas = $this->retirarAcumuladoresBorrador($periodo, $numeros);
        $res['acumuladores_borrador_retirados'] = $borradas;

        return $res;
    }

    /**
     * Empresas cuyo id ERP coincide con el código Anita (1, 2, 3).
     *
     * @return array<int, int>
     */
    private function empresasAlineadas(): array
    {
        $out = [];
        foreach (DB::table('empresa')->select('id', 'codigo')->orderBy('id')->get() as $e) {
            $codigo = trim((string) $e->codigo);
            if ($codigo !== '' && ctype_digit($codigo) && (int) $codigo === (int) $e->id) {
                $out[(int) $e->id] = (int) $e->id;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function maestrosFaltantes(callable $log): array
    {
        $pasos = [
            'nombres_base' => fn () => $this->nombresBase->sincronizarConAnita(),
            'categorias' => fn () => $this->categorias->sincronizarConAnita(),
            'obras_sociales' => fn () => $this->obrasSociales->sincronizarConAnita(),
            'sindicatos' => fn () => $this->sindicatos->sincronizarConAnita(),
            'art' => fn () => $this->arts->sincronizarConAnita(),
            'motivos_egreso' => fn () => $this->motivos->sincronizarConAnita(),
            'lugares' => fn () => $this->lugares->sincronizarConAnita(),
            'agrupamientos' => fn () => $this->agrupamientos->sincronizarConAnita(),
            'grupos' => fn () => $this->grupos->sincronizarConAnita(),
            'antiguedad' => fn () => $this->antiguedad->sincronizarConAnita(),
            'conceptos' => fn () => $this->conceptos->sincronizarConAnita(),
        ];
        $out = [];
        foreach ($pasos as $nombre => $fn) {
            $log('  '.$nombre);
            try {
                $out[$nombre] = $fn();
            } catch (\Throwable $e) {
                $out[$nombre] = ['error' => $e->getMessage()];
            }
        }

        return $out;
    }

    /**
     * @return array{insertadas: int, iguales: int}
     */
    private function actualizarBasesCategoria(int $periodoVigencia): array
    {
        $fecha = Carbon::createFromFormat('Ymd', $periodoVigencia.'01')->toDateString();
        $api = new ApiAnita();
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => 'categoria',
            'campos' => 'cat_numero, cat_desc, cat_tabla, cat_sueldo, cat_diario, cat_hora, cat_base4, cat_base5, cat_base6',
            'orderBy' => 'cat_numero',
        ]));
        if (! empty($parsed['error_lectura'])) {
            return ['insertadas' => 0, 'iguales' => 0, 'error' => (string) $parsed['error_lectura']];
        }

        $nombrebase = Nombrebase_Sueldos::query()->pluck('id', 'codigo')->all();
        $mapa = [
            'cat_sueldo' => 1, 'cat_diario' => 2, 'cat_hora' => 3,
            'cat_base4' => 4, 'cat_base5' => 5, 'cat_base6' => 6,
        ];
        $insertadas = 0;
        $iguales = 0;

        Categoria_Sueldos::withoutAuditing(function () use ($parsed, $nombrebase, $mapa, $fecha, &$insertadas, &$iguales) {
            Categoria_Base_Sueldos::withoutAuditing(function () use ($parsed, $nombrebase, $mapa, $fecha, &$insertadas, &$iguales) {
                foreach ($parsed['filas'] as $row) {
                    $codigo = (int) ($row->cat_numero ?? 0);
                    $cat = Categoria_Sueldos::query()->where('codigo', $codigo)->first();
                    if (! $cat || ! CategoriaOrigenBases::usaTablaCategoria($cat->origen_bases)) {
                        continue;
                    }
                    $vigentes = [];
                    foreach (DB::table('categoria_base_sueldos')
                        ->where('categoria_id', $cat->id)
                        ->orderBy('fecha_vigencia')
                        ->get(['nombrebase_id', 'valor']) as $b) {
                        $vigentes[(int) $b->nombrebase_id] = (float) $b->valor;
                    }
                    foreach ($mapa as $col => $codBase) {
                        $valor = round((float) ($row->{$col} ?? 0), 4);
                        if ($valor == 0.0) {
                            continue;
                        }
                        $nbId = $nombrebase[$codBase] ?? $nombrebase[(string) $codBase] ?? null;
                        if ($nbId === null) {
                            continue;
                        }
                        $actual = $vigentes[(int) $nbId] ?? null;
                        if ($actual !== null && abs($actual - $valor) < 0.0001) {
                            $iguales++;

                            continue;
                        }
                        Categoria_Base_Sueldos::query()->create([
                            'categoria_id' => $cat->id,
                            'nombrebase_id' => (int) $nbId,
                            'valor' => $valor,
                            'fecha_vigencia' => $fecha,
                            'valor_anterior' => $actual,
                            'usuario_id' => null,
                        ]);
                        $vigentes[(int) $nbId] = $valor;
                        $insertadas++;
                    }
                }
            });
        });

        return ['insertadas' => $insertadas, 'iguales' => $iguales, 'vigencia' => $fecha];
    }

    /**
     * @return array{importados: int, ya_existian: int, omitidos: int}
     */
    private function importarFamiliares(): array
    {
        $api = new ApiAnita();
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => 'familiar',
            'campos' => 'fam_empresa, fam_legajo, fam_linea, fam_nombre, fam_parentezco, fam_nro_documento, fam_fecha_nac, fam_cod_ganancia, fam_mes_desde, fam_mes_hasta',
            'whereArmado' => ' WHERE fam_cod_ganancia > 0',
            'orderBy' => 'fam_empresa, fam_legajo, fam_linea',
            'curl_timeout' => 180,
        ]));
        if (! empty($parsed['error_lectura'])) {
            return ['importados' => 0, 'ya_existian' => 0, 'omitidos' => 0, 'error' => (string) $parsed['error_lectura']];
        }

        $empleados = [];
        foreach (DB::table('empleado_sueldos')->select('id', 'empresa_id', 'legajo')->get() as $e) {
            $empleados[$e->empresa_id.':'.$e->legajo] = (int) $e->id;
        }
        $importados = 0;
        $ya = 0;
        $omitidos = 0;

        Empleado_Familiar_Sueldos::withoutAuditing(function () use ($parsed, $empleados, &$importados, &$ya, &$omitidos) {
        foreach ($parsed['filas'] as $f) {
            $empresa = (int) ($f->fam_empresa ?? 0);
            $legajo = (int) ($f->fam_legajo ?? 0);
            $empleadoId = $empleados[$empresa.':'.$legajo] ?? null;
            if ($empleadoId === null) {
                $omitidos++;

                continue;
            }
            $tipo = $this->tipoFamiliar((string) ($f->fam_parentezco ?? ''));
            if ($tipo === null) {
                $omitidos++;

                continue;
            }
            $doc = trim((string) ($f->fam_nro_documento ?? ''));
            $nombre = trim((string) ($f->fam_nombre ?? ''));
            $existe = Empleado_Familiar_Sueldos::query()
                ->where('empleado_id', $empleadoId)
                ->where('tipo', $tipo)
                ->where('apellido', mb_substr($nombre, 0, 60))
                ->exists();
            if ($existe) {
                $ya++;

                continue;
            }
            $nac = $this->fechaYmd((int) ($f->fam_fecha_nac ?? 0));
            Empleado_Familiar_Sueldos::query()->create([
                'empleado_id' => $empleadoId,
                'tipo' => $tipo,
                'apellido' => mb_substr($nombre, 0, 60),
                'nombre' => null,
                'documento' => $doc !== '' ? mb_substr($doc, 0, 20) : null,
                'fecha_nacimiento' => $nac,
                'porcentaje_deduccion' => $tipo === 'HIJOS_50' ? 50 : 100,
                'vigente_desde' => $nac ?? '2026-01-01',
                'vigente_hasta' => null,
                'activo' => true,
                'observacion' => 'Sync Anita familiar',
            ]);
            $importados++;
        }
        });

        return ['importados' => $importados, 'ya_existian' => $ya, 'omitidos' => $omitidos, 'leidos' => count($parsed['filas'])];
    }

    private function tipoFamiliar(string $parentezco): ?string
    {
        $p = mb_strtoupper(trim($parentezco));
        if (str_contains($p, 'CONY')) {
            return 'CONYUGE';
        }
        if (str_contains($p, 'INCAP')) {
            return 'HIJO_INCAP';
        }
        if (str_contains($p, 'HIJO')) {
            return 'HIJOS';
        }

        return null;
    }

    /**
     * @return array<string, int|string>
     */
    private function actualizarTablasGanancias(int $anio): array
    {
        $api = new ApiAnita();
        $escala = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => 'art90',
            'campos' => 'art90_periodo, art90_linea, art90_mas_de, art90_hasta, art90_valor, art90_mas_porc, art90_excedente',
            'whereArmado' => ' WHERE art90_periodo >= '.($anio * 100 + 1).' AND art90_periodo <= '.($anio * 100 + 12),
            'orderBy' => 'art90_periodo, art90_linea',
            'curl_timeout' => 120,
        ]));
        $ded = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => 'deducval',
            'campos' => 'dedv_deduccion, dedv_anio, dedv_mes, dedv_valor',
            'whereArmado' => ' WHERE dedv_anio = '.$anio,
            'orderBy' => 'dedv_deduccion, dedv_mes',
            'curl_timeout' => 120,
        ]));

        $tramos = 0;
        $meses = [];
        if (empty($escala['error_lectura'])) {
            $porMes = [];
            foreach ($escala['filas'] as $f) {
                $periodo = (int) ($f->art90_periodo ?? 0);
                $mes = $periodo % 100;
                if ($mes < 1 || $mes > 12) {
                    continue;
                }
                $hasta = (float) ($f->art90_hasta ?? 0);
                $porMes[$mes][] = [
                    'anio' => $anio,
                    'mes' => $mes,
                    'desde' => round((float) ($f->art90_mas_de ?? 0), 2),
                    'hasta' => $hasta >= 9999999 ? null : round($hasta, 2),
                    'fijo' => round((float) ($f->art90_valor ?? 0), 2),
                    'alicuota' => round((float) ($f->art90_mas_porc ?? 0), 4),
                    'excedente' => round((float) ($f->art90_excedente ?? 0), 2),
                    'nro_tramo' => (int) ($f->art90_linea ?? 0),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            foreach ($porMes as $mes => $filas) {
                DB::table('ganancia_escala_tramo_sueldos')->where('anio', $anio)->where('mes', $mes)->delete();
                DB::table('ganancia_escala_tramo_sueldos')->insert($filas);
                $tramos += count($filas);
                $meses[] = $mes;
            }
        }

        $dedActualizadas = 0;
        if (empty($ded['error_lectura'])) {
            foreach ($ded['filas'] as $f) {
                $codigo = self::DEDUCCION_CODIGO[(int) ($f->dedv_deduccion ?? 0)] ?? null;
                $mes = (int) ($f->dedv_mes ?? 0);
                if ($codigo === null || $mes < 1 || $mes > 12) {
                    continue;
                }
                $valor = round((float) ($f->dedv_valor ?? 0), 2);
                $actual = DB::table('ganancia_deduccion_valor_sueldos')
                    ->where('codigo', $codigo)
                    ->where('anio', $anio)
                    ->where('mes', $mes)
                    ->first();
                if ($actual && abs((float) $actual->valor_acumulado - $valor) < 0.01) {
                    continue;
                }
                if ($actual) {
                    DB::table('ganancia_deduccion_valor_sueldos')->where('id', $actual->id)->update([
                        'valor_acumulado' => $valor,
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('ganancia_deduccion_valor_sueldos')->insert([
                        'codigo' => $codigo,
                        'anio' => $anio,
                        'mes' => $mes,
                        'valor_acumulado' => $valor,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $dedActualizadas++;
            }
        }

        return [
            'tramos' => $tramos,
            'meses_escala' => $meses,
            'deducciones_actualizadas' => $dedActualizadas,
            'error_escala' => $escala['error_lectura'] ?? null,
            'error_deduccion' => $ded['error_lectura'] ?? null,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function importarMovimientosGanancias(int $anio, int $hastaMes): array
    {
        $empleados = [];
        foreach (DB::table('empleado_sueldos')->select('id', 'empresa_id', 'legajo')->get() as $e) {
            $empleados[(int) $e->empresa_id.':'.(int) $e->legajo] = [(int) $e->id, (int) $e->empresa_id];
        }

        $acum = [];
        $leidos = 0;
        $omitidos = 0;
        $api = new ApiAnita();
        for ($mes = 1; $mes <= $hastaMes; $mes++) {
            $periodo = $anio * 100 + $mes;
            $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
                'acc' => 'list',
                'sistema' => 'sueldos',
                'tabla' => 'concgmov',
                'campos' => 'congv_empresa, congv_legajo, congv_periodo, congv_nro_interno, congv_valor, congv_cant',
                'whereArmado' => ' WHERE congv_periodo = '.$periodo,
                'orderBy' => 'congv_empresa, congv_legajo',
                'curl_timeout' => 180,
            ]));
            if (! empty($parsed['error_lectura'])) {
                return ['leidos' => $leidos, 'insertados' => 0, 'omitidos' => $omitidos, 'error' => (string) $parsed['error_lectura']];
            }
            foreach ($parsed['filas'] as $f) {
                $leidos++;
                $interno = (int) ($f->congv_nro_interno ?? 0);
                $linea = self::MOVIMIENTO_LINEA[$interno] ?? self::CANTIDAD_LINEA[$interno] ?? null;
                $empKey = (int) ($f->congv_empresa ?? 0).':'.(int) ($f->congv_legajo ?? 0);
                if ($linea === null || ! isset($empleados[$empKey])) {
                    $omitidos++;

                    continue;
                }
                [$empleadoId, $empresaId] = $empleados[$empKey];
                $clave = $empleadoId.'|'.$periodo.'|'.$linea;
                if (! isset($acum[$clave])) {
                    $acum[$clave] = [
                        'empresa_id' => $empresaId,
                        'empleado_id' => $empleadoId,
                        'periodo' => $periodo,
                        'linea_codigo' => $linea,
                        'valor' => 0.0,
                        'cantidad' => 0.0,
                    ];
                }
                if (isset(self::CANTIDAD_LINEA[$interno])) {
                    $cant = (float) ($f->congv_cant ?? 0);
                    if (abs($cant) < 0.0001 && abs((float) ($f->congv_valor ?? 0)) > 0.0001) {
                        $cant = 1.0;
                    }
                    $acum[$clave]['cantidad'] += $cant;
                } else {
                    $valor = (float) ($f->congv_valor ?? 0);
                    if (isset(self::LINEAS_NEGATIVAS[$linea]) && $valor > 0) {
                        $valor = -$valor;
                    }
                    $acum[$clave]['valor'] += $valor;
                    $acum[$clave]['cantidad'] += (float) ($f->congv_cant ?? 0);
                }
            }
        }

        $codigos = array_values(array_unique(array_merge(
            array_values(self::MOVIMIENTO_LINEA),
            array_values(self::CANTIDAD_LINEA)
        )));
        DB::table('ganancia_movimiento_sueldos')
            ->whereBetween('periodo', [$anio * 100 + 1, $anio * 100 + $hastaMes])
            ->whereIn('linea_codigo', $codigos)
            ->delete();

        $ahora = now();
        $filas = [];
        foreach ($acum as $row) {
            if (abs($row['valor']) < 0.0001 && abs($row['cantidad']) < 0.0001) {
                continue;
            }
            $filas[] = [
                'empresa_id' => $row['empresa_id'],
                'empleado_id' => $row['empleado_id'],
                'periodo' => $row['periodo'],
                'linea_codigo' => $row['linea_codigo'],
                'valor' => round($row['valor'], 2),
                'cantidad' => round($row['cantidad'], 4),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }
        foreach (array_chunk($filas, 500) as $lote) {
            DB::table('ganancia_movimiento_sueldos')->insert($lote);
        }

        return ['leidos' => $leidos, 'insertados' => count($filas), 'omitidos' => $omitidos];
    }

    /**
     * @return array<string, mixed>
     */
    private function importarNomina(Liquidacion_Sueldos $liq, int $empresaAnita, int $numero): array
    {
        $ya = Liquidacion_Recibo_Sueldos::query()
            ->where('liquidacion_id', $liq->id)
            ->where('origen', Liquidacion_Recibo_Sueldos::ORIGEN_AUXHIST)
            ->count();
        if ($ya > 0) {
            return ['empresa' => $empresaAnita, 'numero' => $numero, 'omitida' => true, 'recibos' => $ya];
        }

        try {
            $filas = $this->aux->leerTablaNormalizada('auxhist', $empresaAnita, $numero);
        } catch (\Throwable $e) {
            return ['empresa' => $empresaAnita, 'numero' => $numero, 'error' => $e->getMessage()];
        }
        if ($filas === []) {
            return ['empresa' => $empresaAnita, 'numero' => $numero, 'filas' => 0, 'recibos' => 0];
        }

        $empleados = Empleado_Sueldos::query()
            ->where('empresa_id', $liq->empresa_id)
            ->get()
            ->keyBy(fn ($e) => (int) $e->legajo);
        $conceptos = Concepto_Sueldos::query()->get()->keyBy(fn ($c) => (int) $c->codigo);
        $defs = Acumulador_Sueldos::query()
            ->where('activo', true)
            ->where(function ($q) use ($liq) {
                $q->whereNull('empresa_id')->orWhere('empresa_id', $liq->empresa_id);
            })
            ->orderBy('orden')
            ->get();
        $overrides = [];
        foreach (DB::table('concepto_acumulador_sueldos as ca')
            ->join('acumulador_sueldos as a', 'a.id', '=', 'ca.acumulador_id')
            ->get(['ca.concepto_id', 'a.codigo', 'ca.signo', 'ca.excluir']) as $o) {
            $overrides[(int) $o->concepto_id][strtoupper((string) $o->codigo)] = [
                'signo' => (int) $o->signo,
                'excluir' => (bool) $o->excluir,
            ];
        }

        $porLegajo = [];
        $sinConcepto = [];
        foreach ($filas as $f) {
            $porLegajo[(int) $f['legajo']][] = $f;
        }

        $recibos = 0;
        $lineas = 0;
        $sinEmpleado = 0;
        $ahora = now();
        $snapshots = [];
        $periodo = (int) $liq->periodo;

        Liquidacion_Recibo_Sueldos::withoutAuditing(function () use (
            $porLegajo, $empleados, $conceptos, $defs, $overrides, $liq, $ahora, $periodo,
            &$recibos, &$lineas, &$sinEmpleado, &$sinConcepto, &$snapshots
        ) {
        foreach ($porLegajo as $legajo => $lineasAnita) {
            $emp = $empleados->get($legajo);
            if (! $emp) {
                $sinEmpleado++;

                continue;
            }
            if (Liquidacion_Recibo_Sueldos::query()
                ->where('liquidacion_id', $liq->id)
                ->where('empleado_id', $emp->id)
                ->exists()) {
                continue;
            }

            $dtos = [];
            foreach ($lineasAnita as $f) {
                $concepto = $conceptos->get((int) $f['codigo']);
                if (! $concepto) {
                    $sinConcepto[(int) $f['codigo']] = true;

                    continue;
                }
                $importe = abs((float) $f['haberes']) > 0.0001 ? (float) $f['haberes'] : (float) $f['deduc'];
                $tipo = (string) ($concepto->tipo ?? 'remunerativo');
                $dtos[] = [
                    'concepto_id' => $concepto->id,
                    'concepto_codigo' => (int) $concepto->codigo,
                    'concepto_descripcion' => $f['descripcion'] !== '' ? mb_substr($f['descripcion'], 0, 60) : (string) $concepto->descripcion,
                    'tipo' => $tipo,
                    'columna' => LiquidacionDetalleTotalesSupport::columnaParaTipo($tipo),
                    'cantidad' => (float) $f['cantidad'],
                    'valor' => (float) $f['valor'],
                    'importe' => round($importe, 2),
                    'remunerativo' => $tipo === 'remunerativo',
                    'va_recibo' => (bool) ($concepto->va_recibo ?? true),
                    'concepto_afip' => $concepto->concepto_afip ?? null,
                    'origen_tabla' => 'auxhist',
                    'origen_serial' => (int) $f['serial'],
                    'origen_nro_interno' => (int) $f['nro_interno'],
                ];
            }
            if ($dtos === []) {
                continue;
            }
            $tot = LiquidacionDetalleTotalesSupport::totalesRecibo($dtos);
            $recibo = Liquidacion_Recibo_Sueldos::query()->create([
                'liquidacion_id' => $liq->id,
                'empleado_id' => $emp->id,
                'legajo' => $emp->legajo,
                'numero_recibo' => 0,
                'apellido_nombre' => $emp->nombre,
                'cuil' => $emp->cuil,
                'categoria_id' => $emp->categoria_id,
                'categoria_desc' => optional($emp->categoria)->descripcion,
                'agrupamiento_id' => $emp->agrupamiento_id,
                'lugartrabajo_id' => $emp->lugartrabajo_id,
                'obrasocial_id' => $emp->obrasocial_id,
                'sindicato_id' => $emp->sindicato_id,
                'fecha_ingreso' => $emp->fecha_ingreso,
                'total_remunerativo' => $tot['rem'],
                'total_no_remunerativo' => $tot['norem'],
                'total_bruto' => $tot['bruto'],
                'total_descuentos' => $tot['desc'],
                'total_aportes' => $tot['aportes'],
                'total_contribuciones' => $tot['contrib'],
                'total_asignaciones' => $tot['asig'],
                'neto' => $tot['neto'],
                'redondeo' => 0,
                'neto_a_pagar' => $tot['neto'],
                'estado' => 'calculado',
                'origen' => Liquidacion_Recibo_Sueldos::ORIGEN_AUXHIST,
                'confidencial' => false,
                'observacion' => 'Import Anita auxhist',
            ]);
            $nro = 0;
            $lote = [];
            $acum = [];
            foreach ($defs as $def) {
                $acum[strtoupper((string) $def->codigo)] = 0.0;
            }
            foreach ($dtos as $l) {
                $nro++;
                $lote[] = [
                    'recibo_id' => $recibo->id,
                    'liquidacion_id' => $liq->id,
                    'empleado_id' => $emp->id,
                    'concepto_id' => $l['concepto_id'],
                    'concepto_codigo' => $l['concepto_codigo'],
                    'concepto_descripcion' => $l['concepto_descripcion'],
                    'tipo' => $l['tipo'],
                    'nro_linea' => $nro,
                    'columna' => $l['columna'],
                    'cantidad' => $l['cantidad'],
                    'valor' => $l['valor'],
                    'importe' => $l['importe'],
                    'remunerativo' => $l['remunerativo'],
                    'va_recibo' => $l['va_recibo'],
                    'concepto_afip' => $l['concepto_afip'],
                    'origen_tabla' => $l['origen_tabla'],
                    'origen_serial' => $l['origen_serial'],
                    'origen_nro_interno' => $l['origen_nro_interno'],
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
                $this->sumarAcumulador($acum, $defs, $overrides[(int) $l['concepto_id']] ?? [], (string) $l['tipo'], (float) $l['importe']);
            }
            foreach (array_chunk($lote, 400) as $chunk) {
                DB::table('liquidacion_detalle_sueldos')->insert($chunk);
            }
            foreach ($acum as $cod => $valor) {
                if (abs($valor) < 0.004) {
                    continue;
                }
                $snapshots[] = [
                    'empresa_id' => $liq->empresa_id,
                    'empleado_id' => $emp->id,
                    'liquidacion_id' => $liq->id,
                    'periodo' => $periodo,
                    'periodo_anio' => (int) $liq->periodo_anio,
                    'periodo_mes' => (int) $liq->periodo_mes,
                    'tipo_corrida' => $liq->tipo,
                    'codigo' => $cod,
                    'valor' => round($valor, 2),
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
            $recibos++;
            $lineas += $nro;
        }
        });

        foreach (array_chunk($snapshots, 500) as $lote) {
            Liquidacion_Acumulador_Sueldos::query()->insert($lote);
        }
        LiquidacionDetalleTotalesSupport::renumerarRecibos((int) $liq->id);
        LiquidacionDetalleTotalesSupport::recalcularCabecera($liq->fresh());

        return [
            'empresa' => $empresaAnita,
            'numero' => $numero,
            'filas' => count($filas),
            'recibos' => $recibos,
            'lineas' => $lineas,
            'sin_empleado' => $sinEmpleado,
            'conceptos_faltantes' => array_keys($sinConcepto),
        ];
    }

    /**
     * @param  array<string, float>  $acum
     * @param  \Illuminate\Support\Collection<int, Acumulador_Sueldos>  $defs
     * @param  array<string, array{signo: int, excluir: bool}>  $overrides
     */
    private function sumarAcumulador(array &$acum, $defs, array $overrides, string $tipo, float $importe): void
    {
        foreach ($defs as $def) {
            $cod = strtoupper((string) $def->codigo);
            if (array_key_exists($cod, $overrides)) {
                if ($overrides[$cod]['excluir']) {
                    continue;
                }
                $acum[$cod] = ($acum[$cod] ?? 0.0) + $overrides[$cod]['signo'] * $importe;

                continue;
            }
            $tipos = $def->tipos_incluye ?? [];
            if (in_array($tipo, $tipos, true)) {
                $acum[$cod] = ($acum[$cod] ?? 0.0) + ((int) $def->signo) * $importe;
            }
        }
    }

    /**
     * @param  array<int, list<int>>  $numerosOficiales
     */
    private function retirarAcumuladoresBorrador(int $periodo, array $numerosOficiales): int
    {
        $ids = [];
        foreach (Liquidacion_Sueldos::query()->where('periodo', (string) $periodo)->get() as $liq) {
            $oficiales = $numerosOficiales[(int) $liq->empresa_id] ?? [];
            if (! in_array((int) $liq->numero, $oficiales, true)) {
                $ids[] = (int) $liq->id;
            }
        }
        if ($ids === []) {
            return 0;
        }

        return Liquidacion_Acumulador_Sueldos::query()->whereIn('liquidacion_id', $ids)->delete();
    }

    private function fechaYmd(int $yyyymmdd): ?string
    {
        if ($yyyymmdd < 19000101) {
            return null;
        }
        $s = (string) $yyyymmdd;
        if (strlen($s) !== 8) {
            return null;
        }

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }
}
