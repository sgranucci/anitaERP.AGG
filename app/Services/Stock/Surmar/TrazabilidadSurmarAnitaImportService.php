<?php

namespace App\Services\Stock\Surmar;

use App\ApiAnita;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Articulo;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Repositories\Stock\Articulo_Saldo_DepositoRepositoryInterface;
use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use App\Support\Stock\RecepcionProveedorDepositoAnitaSupport;
use App\Support\Stock\Surmar\TrazabilidadSurmarAnitaBridgeSupport as Bridge;
use App\Support\Stock\SurmarSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pipeline import trazabilidad Surmar desde Anita.
 * Una sola lectura por tabla (t_comp, recepaper, stkmov, stkvaper, apcom).
 * Inserta movimientostock/articulo_movimiento vía DB (sin observer) y reconstruye
 * saldos solo en depósitos de la empresa Surmar.
 */
class TrazabilidadSurmarAnitaImportService
{
    /** @var array<string, int|null> */
    private array $cacheArticulo = [];

    /** @var array<string, int|null> */
    private array $cacheDeposito = [];

    /** @var array<string, int> abreviatura => id */
    private array $cacheTipo = [];

    /** @var array<int, int> signo DB por tipo id */
    private array $cacheTipoSigno = [];

    public function __construct(
        private readonly Articulo_Saldo_DepositoRepositoryInterface $saldoRepo,
    ) {}

    /**
     * @param  list<string>|null  $pasos  null = todos
     * @return array<string, mixed>
     */
    public function ejecutar(?int $usuarioId = null, bool $dryRun = false, ?array $pasos = null): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $this->assertEntornoSurmar();
        RecepcionProveedorDepositoAnitaSupport::reiniciarCache();

        $usuarioId = $usuarioId ?? (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);
        $empresaId = (int) config('trazabilidad_anita_surmar.empresa_id', SurmarSupport::EMPRESA_ID);
        $todos = ['tipos', 'etiquetas', 'movimientos', 'vinculos', 'consumos', 'saldos'];
        $pasos = $pasos === null || $pasos === [] ? $todos : array_values(array_intersect($todos, $pasos));

        $out = [
            'dry_run' => $dryRun,
            'path' => Bridge::pathSistema(),
            'fecha_desde' => Bridge::fechaDesde(),
            'fecha_hasta' => Bridge::fechaHasta(),
            'pasos' => $pasos,
            'tipos' => null,
            'etiquetas' => null,
            'movimientos' => null,
            'vinculos' => null,
            'consumos' => null,
            'saldos' => null,
        ];

        if (in_array('tipos', $pasos, true)) {
            $out['tipos'] = $this->importarTipos($dryRun);
        }

        $this->cargarCacheTipos();

        $stkmov = null;
        $stkvaper = null;
        $apcom = null;
        $recepaper = null;

        $necesitaAnita = array_intersect($pasos, ['etiquetas', 'movimientos', 'vinculos', 'consumos']);
        if ($necesitaAnita !== []) {
            if (in_array('etiquetas', $pasos, true) || in_array('vinculos', $pasos, true) || in_array('consumos', $pasos, true)) {
                // etiquetas necesita recepaper; vinculos/consumos no
            }
        }

        if (in_array('etiquetas', $pasos, true)) {
            $recepaper = $this->listarRecepaperUnaLectura();
            $out['etiquetas'] = $this->importarEtiquetas($recepaper, $empresaId, $usuarioId, $dryRun);
        }

        if (in_array('movimientos', $pasos, true)) {
            $stkmov = $this->listarStkmovUnaLectura();
            $out['movimientos'] = $this->importarMovimientos($stkmov, $empresaId, $usuarioId, $dryRun);
        }

        if (in_array('vinculos', $pasos, true)) {
            $stkvaper = $this->listarStkvaperUnaLectura();
            $out['vinculos'] = $this->importarVinculosStkvaper($stkvaper, $empresaId, $dryRun);
        }

        if (in_array('consumos', $pasos, true)) {
            $apcom = $this->listarApcomUnaLectura();
            $out['consumos'] = $this->importarConsumosApcom($apcom, $empresaId, $dryRun);
        }

        if (in_array('saldos', $pasos, true) && ! $dryRun && (bool) config('trazabilidad_anita_surmar.reconstruir_saldos', true)) {
            $out['saldos'] = $this->reconstruirSaldosEmpresa($empresaId);
        } elseif (in_array('saldos', $pasos, true)) {
            $out['saldos'] = ['omitido' => true, 'motivo' => $dryRun ? 'dry-run' : 'config'];
        }

        return $out;
    }

    /**
     * @return array{en_anita:int, creados:int, actualizados:int, omitidos:int}
     */
    public function importarTipos(bool $dryRun): array
    {
        $api = new ApiAnita;
        $raw = $api->apiCall(array_merge(Bridge::parametrosVentas(), [
            'acc' => 'list',
            'tabla' => 't_comp',
            'campos' => Bridge::camposTcomp(),
            'whereArmado' => '',
        ]));
        $filas = ApiAnita::decodificarListaFilas($raw);

        $stats = ['en_anita' => count($filas), 'creados' => 0, 'actualizados' => 0, 'omitidos' => 0];

        foreach ($filas as $row) {
            $abrev = strtoupper(trim((string) ($row->tcomp_clave ?? '')));
            if ($abrev === '' || strlen($abrev) > 10) {
                $stats['omitidos']++;

                continue;
            }

            $mapa = Bridge::operacionDesdeOperStk($row->tcomp_oper_stk ?? 0);
            if ($mapa === null) {
                $stats['omitidos']++;

                continue;
            }

            $nombre = trim((string) ($row->tcomp_desc ?? $abrev));
            if ($nombre === '') {
                $nombre = $abrev;
            }
            $estadoAnita = strtoupper(trim((string) ($row->tcomp_estado ?? 'A')));
            $estado = $estadoAnita === 'I' ? 'S' : 'A';

            $existente = Tipotransaccion_Stock::withTrashed()
                ->where('abreviatura', $abrev)
                ->first();

            if ($existente) {
                if ($dryRun) {
                    $stats['actualizados']++;

                    continue;
                }
                // No pisar tipos AGG ya usados: solo completar nombre/estado si vacío/suspendido raro.
                $dirty = false;
                if (trim((string) $existente->nombre) === '') {
                    $existente->nombre = mb_substr($nombre, 0, 60);
                    $dirty = true;
                }
                if ($existente->trashed()) {
                    $existente->restore();
                    $dirty = true;
                }
                if ($dirty) {
                    $existente->save();
                    $stats['actualizados']++;
                } else {
                    $stats['omitidos']++;
                }

                continue;
            }

            if ($dryRun) {
                $stats['creados']++;

                continue;
            }

            Tipotransaccion_Stock::create([
                'nombre' => mb_substr($nombre, 0, 60),
                'operacion' => $mapa['operacion'],
                'abreviatura' => $abrev,
                'signo' => $mapa['signo'],
                'estado' => $estado,
                'requiere_aprobacion' => false,
                'aviso_opcional' => false,
                'maneja_contabilidad' => false,
            ]);
            $stats['creados']++;
        }

        return $stats;
    }

    /**
     * @param  list<object>  $filas
     * @return array<string, int>
     */
    public function importarEtiquetas(array $filas, int $empresaId, int $usuarioId, bool $dryRun): array
    {
        $stats = [
            'en_anita' => count($filas),
            'fisicas' => 0,
            'creadas' => 0,
            'omitidas' => 0,
            'sin_articulo' => 0,
            'enriquecidas' => 0,
        ];

        /** @var array<string, object> nint|nap => mejor fila (prioriza COM) */
        $porClave = [];
        foreach ($filas as $row) {
            $nint = (int) ($row->recap_nro_interno ?? 0);
            $nap = (int) ($row->recap_nro_apertura ?? 0);
            if ($nint <= 0 || $nap <= 0) {
                continue;
            }
            $k = $nint.'|'.$nap;
            $tipo = strtoupper(trim((string) ($row->recap_tipo ?? '')));
            if (! isset($porClave[$k])) {
                $porClave[$k] = $row;

                continue;
            }
            $prev = strtoupper(trim((string) ($porClave[$k]->recap_tipo ?? '')));
            if ($tipo === 'COM' && $prev !== 'COM') {
                $porClave[$k] = $row;
            }
        }
        $stats['fisicas'] = count($porClave);

        $existentes = DB::table('stock_etiqueta')
            ->where('empresa_id', $empresaId)
            ->whereNotNull('anita_nro_interno')
            ->whereNotNull('anita_nro_apertura')
            ->get(['id', 'anita_nro_interno', 'anita_nro_apertura', 'lote_proveedor', 'fecha_vto', 'nro_establecimiento', 'deposito_id', 'origen_id', 'origen_linea_id'])
            ->keyBy(fn ($r) => ((int) $r->anita_nro_interno).'|'.((int) $r->anita_nro_apertura));

        // Índice recepción línea: nint → [recepcion_id, linea_id, deposito_id]
        $lineasRecepcion = DB::table('recepcion_proveedor_articulo as a')
            ->join('recepcion_proveedor as r', 'r.id', '=', 'a.recepcion_proveedor_id')
            ->where('r.empresa_id', $empresaId)
            ->where('r.origen_carga', 'ANITA_IMPORT')
            ->whereNotNull('a.penvp_nro_interno')
            ->where('a.penvp_nro_interno', '>', 0)
            ->get(['r.id as recepcion_id', 'a.id as linea_id', 'a.penvp_nro_interno', 'a.deposito_id', 'r.deposito_id as deposito_cab'])
            ->groupBy(fn ($r) => (int) $r->penvp_nro_interno);

        $now = now();
        $batch = [];

        foreach ($porClave as $k => $row) {
            if (isset($existentes[$k])) {
                $ex = $existentes[$k];
                $updates = [];
                $cert = trim((string) ($row->recap_certificado ?? ''));
                if (($ex->lote_proveedor === null || $ex->lote_proveedor === '') && $cert !== '') {
                    $updates['lote_proveedor'] = mb_substr($cert, 0, 30);
                }
                $vto = (int) ($row->recap_fecha_vto ?? 0);
                if ($ex->fecha_vto === null && $vto > 0) {
                    $updates['fecha_vto'] = RecepcionProveedorAnitaImportSupport::fechaDesdeAnita($vto);
                }
                $est = (int) ($row->recap_nro_establ ?? 0);
                if (($ex->nro_establecimiento === null || (int) $ex->nro_establecimiento === 0) && $est > 0) {
                    $updates['nro_establecimiento'] = $est;
                }
                if ($updates !== []) {
                    if (! $dryRun) {
                        $updates['updated_at'] = $now;
                        DB::table('stock_etiqueta')->where('id', $ex->id)->update($updates);
                    }
                    $stats['enriquecidas']++;
                } else {
                    $stats['omitidas']++;
                }

                continue;
            }

            $sku = (string) ($row->recap_articulo ?? '');
            $articuloId = $this->resolverArticuloId($sku);
            if (! $articuloId) {
                $stats['sin_articulo']++;

                continue;
            }

            $nint = (int) $row->recap_nro_interno;
            $nap = (int) $row->recap_nro_apertura;
            $tipo = strtoupper(trim((string) ($row->recap_tipo ?? '')));
            $origenTipo = $tipo === 'COM' ? SurmarSupport::ORIGEN_COM : SurmarSupport::ORIGEN_IMPORT_ANITA;

            $origenId = null;
            $origenLineaId = null;
            $depositoId = null;
            $cands = $lineasRecepcion->get($nint);
            if ($cands && $cands->isNotEmpty()) {
                $lin = $cands->first();
                $origenId = (int) $lin->recepcion_id;
                $origenLineaId = (int) $lin->linea_id;
                $depositoId = (int) ($lin->deposito_id ?: $lin->deposito_cab ?: 0) ?: null;
                if ($tipo === 'COM') {
                    $origenTipo = SurmarSupport::ORIGEN_COM;
                }
            }

            $fechaEmi = (int) ($row->recap_fecha_emi ?? 0);
            $fechaEmiIso = $fechaEmi > 0
                ? RecepcionProveedorAnitaImportSupport::fechaDesdeAnita($fechaEmi)
                : null;
            $vto = (int) ($row->recap_fecha_vto ?? 0);
            $cert = trim((string) ($row->recap_certificado ?? ''));
            $umdAnita = (int) ($row->recap_cod_umd ?? 0);

            $batch[] = [
                'empresa_id' => $empresaId,
                'articulo_id' => $articuloId,
                'deposito_id' => $depositoId,
                'unidadmedida_id' => $umdAnita > 0 && $umdAnita <= 4 ? $umdAnita : 1,
                'estado' => SurmarSupport::ESTADO_DISPONIBLE,
                'origen_tipo' => $origenTipo,
                'origen_id' => $origenId,
                'origen_linea_id' => $origenLineaId,
                'articulo_movimiento_id' => null,
                'etiqueta_origen_id' => null,
                'lote_proveedor' => $cert !== '' ? mb_substr($cert, 0, 30) : null,
                'fecha_vto' => $vto > 0 ? RecepcionProveedorAnitaImportSupport::fechaDesdeAnita($vto) : null,
                'fecha_emision' => $fechaEmiIso,
                'hora_emision' => trim((string) ($row->recap_hora_emi ?? '')) ?: null,
                'cant_pieza' => (float) ($row->recap_cant_pieza ?? 0),
                'peso_bruto' => (float) ($row->recap_peso_bruto ?? 0),
                'peso_neto' => (float) ($row->recap_peso_neto ?? 0),
                'nro_establecimiento' => (int) ($row->recap_nro_establ ?? 0) ?: null,
                'descripcion_snapshot' => mb_substr(trim((string) ($row->recap_desc ?? '')), 0, 60) ?: null,
                'anita_proveedor' => trim((string) ($row->recap_proveedor ?? '')) ?: null,
                'anita_tipo' => $tipo !== '' ? $tipo : null,
                'anita_letra' => trim((string) ($row->recap_letra ?? '')) !== '' ? trim((string) $row->recap_letra) : null,
                'anita_sucursal' => (int) ($row->recap_sucursal ?? 0) ?: null,
                'anita_nro' => (int) ($row->recap_nro ?? 0) ?: null,
                'anita_orden' => (int) ($row->recap_orden ?? 0),
                'anita_nro_interno' => $nint,
                'anita_nro_apertura' => $nap,
                'usuario_id' => $usuarioId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $stats['creadas']++;

            if (! $dryRun && count($batch) >= 500) {
                DB::table('stock_etiqueta')->insert($batch);
                $batch = [];
            }
        }

        if (! $dryRun && $batch !== []) {
            DB::table('stock_etiqueta')->insert($batch);
        }

        return $stats;
    }

    /**
     * @param  list<object>  $filas
     * @return array<string, int>
     */
    public function importarMovimientos(array $filas, int $empresaId, int $usuarioId, bool $dryRun): array
    {
        $stats = [
            'en_anita' => count($filas),
            'cabeceras' => 0,
            'lineas' => 0,
            'omitidas_cab' => 0,
            'sin_articulo' => 0,
            'sin_tipo' => 0,
        ];

        /** @var array<string, list<object>> */
        $porCab = [];
        foreach ($filas as $row) {
            $tipo = strtoupper(trim((string) ($row->stkv_tipo ?? '')));
            $letra = (string) ($row->stkv_letra ?? '');
            $suc = (int) ($row->stkv_sucursal ?? 0);
            $nro = (int) ($row->stkv_nro ?? 0);
            if ($tipo === '' || $nro <= 0) {
                continue;
            }
            $clave = Bridge::claveMovimiento($tipo, $letra, $suc, $nro);
            $porCab[$clave][] = $row;
        }
        $stats['cabeceras'] = count($porCab);

        $prefix = (string) config('trazabilidad_anita_surmar.leyenda_prefix', 'ANITA_SURMAR');
        $ya = DB::table('movimientostock')
            ->where('leyenda', 'like', $prefix.'|%')
            ->pluck('id', 'leyenda');

        $now = now();

        foreach ($porCab as $leyenda => $lineas) {
            if ($ya->has($leyenda)) {
                $stats['omitidas_cab']++;

                continue;
            }

            $primera = $lineas[0];
            $tipo = strtoupper(trim((string) $primera->stkv_tipo));
            $tipoId = $this->cacheTipo[$tipo] ?? null;
            if (! $tipoId) {
                $stats['sin_tipo']++;

                continue;
            }

            $fechaAnita = (int) ($primera->stkv_fecha ?? 0);
            $fecha = $fechaAnita > 0
                ? RecepcionProveedorAnitaImportSupport::fechaDesdeAnita($fechaAnita)
                : $now->toDateString();

            $signoTipo = (int) ($this->cacheTipoSigno[$tipoId] ?? 1);
            $signo = Bridge::signoNumerico($tipo, $signoTipo);

            if ($dryRun) {
                $stats['lineas'] += count($lineas);

                continue;
            }

            $movId = (int) DB::table('movimientostock')->insertGetId([
                'fecha' => $fecha,
                'fechajornada' => $fecha,
                'tipotransaccion_stock_id' => $tipoId,
                'mventa_id' => null,
                'codigo' => mb_substr($leyenda, 0, 50),
                'leyenda' => $leyenda,
                'estado' => 'A',
                'usuario_id' => $usuarioId,
                'asiento_id' => null,
                'movimientostock_origen_id' => null,
                'movimientostock_revertido_por_id' => null,
                'centrocosto_destino_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('movimientostock')->where('id', $movId)->update(['codigo' => (string) $movId]);

            $batchLin = [];
            foreach ($lineas as $lin) {
                $articuloId = $this->resolverArticuloId((string) ($lin->stkv_articulo ?? ''));
                if (! $articuloId) {
                    $stats['sin_articulo']++;

                    continue;
                }
                $depCod = (int) ($lin->stkv_deposito ?? 0);
                $depositoId = $this->resolverDepositoId($depCod, $empresaId);
                $cant = abs((float) ($lin->stkv_cantidad ?? 0));
                if ($cant <= 0) {
                    continue;
                }
                $orden = (int) ($lin->stkv_nro_orden ?? 0);
                $letra = (string) ($lin->stkv_letra ?? '');
                $suc = (int) ($lin->stkv_sucursal ?? 0);
                $nro = (int) ($lin->stkv_nro ?? 0);

                $batchLin[] = [
                    'fecha' => $fecha,
                    'fechajornada' => $fecha,
                    'tipotransaccion_id' => null,
                    'tipotransaccion_stock_id' => $tipoId,
                    'venta_id' => null,
                    'venta_emision_id' => null,
                    'vianda_consumo_id' => null,
                    'movimientostock_id' => $movId,
                    'pedido_combinacion_id' => null,
                    'ordentrabajo_id' => null,
                    'lote' => $orden,
                    'articulo_id' => $articuloId,
                    'color_id' => null,
                    'talle_id' => null,
                    'numeroparte' => null,
                    'combinacion_id' => null,
                    'concepto' => Bridge::conceptoLinea($tipo, $letra, $suc, $nro, $orden),
                    'modulo_id' => null,
                    'cantidad' => $signo * $cant,
                    'pieza' => null,
                    'caja' => null,
                    'precio' => (float) ($lin->stkv_precio ?? 0),
                    'costo' => 0,
                    'listaprecio_id' => null,
                    'incluyeimpuesto' => null,
                    'moneda_id' => null,
                    'descuento' => 0,
                    'descuentointegrado' => 0,
                    'deposito_id' => $depositoId,
                    'bien_uso_id' => null,
                    'loteimportacion_id' => null,
                    'pedido_articulo_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $stats['lineas']++;
            }

            foreach (array_chunk($batchLin, 300) as $chunk) {
                DB::table('articulo_movimiento')->insert($chunk);
            }
        }

        return $stats;
    }

    /**
     * @param  list<object>  $filas
     * @return array<string, int>
     */
    public function importarVinculosStkvaper(array $filas, int $empresaId, bool $dryRun): array
    {
        $stats = [
            'en_anita' => count($filas),
            'vinculados' => 0,
            'sin_etiqueta' => 0,
            'sin_movimiento' => 0,
            'omitidos' => 0,
        ];

        $etiquetas = DB::table('stock_etiqueta')
            ->where('empresa_id', $empresaId)
            ->whereNotNull('anita_nro_interno')
            ->get(['id', 'anita_nro_interno', 'anita_nro_apertura', 'deposito_id'])
            ->keyBy(fn ($r) => ((int) $r->anita_nro_interno).'|'.((int) $r->anita_nro_apertura));

        $prefix = (string) config('trazabilidad_anita_surmar.leyenda_prefix', 'ANITA_SURMAR');
        $movs = DB::table('movimientostock')
            ->where('leyenda', 'like', $prefix.'|%')
            ->pluck('id', 'leyenda');

        // concepto → articulo_movimiento; también índice por mov+articulo
        $ams = DB::table('articulo_movimiento')
            ->where('concepto', 'like', $prefix.'|%')
            ->get(['id', 'concepto', 'deposito_id', 'movimientostock_id', 'articulo_id']);
        $amsPorConcepto = $ams->keyBy('concepto');
        /** @var array<string, object> */
        $amsPorMovArt = [];
        foreach ($ams as $am) {
            $amsPorMovArt[((int) $am->movimientostock_id).'|'.((int) $am->articulo_id)] = $am;
        }

        $ya = [];
        foreach (DB::table('stock_etiqueta_movimiento')->get(['etiqueta_id', 'articulo_movimiento_id', 'rol']) as $ex) {
            $ya[$ex->etiqueta_id.'|'.$ex->articulo_movimiento_id.'|'.$ex->rol] = 1;
        }

        $now = now();
        $batch = [];

        foreach ($filas as $row) {
            $nint = (int) ($row->stkvap_nro_interno ?? 0);
            $nap = (int) ($row->stkvap_nro_aper ?? 0);
            $ek = $nint.'|'.$nap;
            $eti = $etiquetas->get($ek);
            if (! $eti) {
                $stats['sin_etiqueta']++;

                continue;
            }

            $tipoAper = strtoupper(trim((string) ($row->stkvap_tipo ?? '')));
            $letra = (string) ($row->stkvap_letra ?? '');
            $suc = (int) ($row->stkvap_sucursal ?? 0);
            $nro = (int) ($row->stkvap_nro ?? 0);
            $orden = (int) ($row->stkvap_orden ?? 0);
            $articuloIdAper = $this->resolverArticuloId((string) ($row->stkvap_articulo ?? ''));

            $tiposMov = $tipoAper === 'TRA' ? ['TRS', 'TRE'] : [$tipoAper];
            $alguno = false;

            foreach ($tiposMov as $tipoMov) {
                $concepto = Bridge::conceptoLinea($tipoMov, $letra, $suc, $nro, $orden);
                $am = $amsPorConcepto->get($concepto);
                if (! $am) {
                    $ley = Bridge::claveMovimiento($tipoMov, $letra, $suc, $nro);
                    $movId = $movs->get($ley);
                    if (! $movId) {
                        continue;
                    }
                    if ($articuloIdAper) {
                        $am = $amsPorMovArt[((int) $movId).'|'.$articuloIdAper] ?? null;
                    }
                    if (! $am) {
                        $am = $ams->first(fn ($x) => (int) $x->movimientostock_id === (int) $movId);
                    }
                    if (! $am) {
                        continue;
                    }
                }

                $rol = Bridge::rolDesdeTipoStkmov($tipoMov);
                if ($tipoAper === 'TRA') {
                    $rol = 'TRANSFERENCIA';
                }

                $uk = $eti->id.'|'.$am->id.'|'.$rol;
                if (isset($ya[$uk])) {
                    $stats['omitidos']++;
                    $alguno = true;

                    continue;
                }

                $depOrigen = null;
                $depDestino = null;
                if ($rol === 'TRANSFERENCIA') {
                    if ($tipoMov === 'TRS') {
                        $depOrigen = (int) ($am->deposito_id ?: 0) ?: null;
                    } else {
                        $depDestino = (int) ($am->deposito_id ?: 0) ?: null;
                    }
                } elseif ($rol === 'ENTRADA') {
                    $depDestino = (int) ($am->deposito_id ?: $eti->deposito_id ?: 0) ?: null;
                } else {
                    $depOrigen = (int) ($am->deposito_id ?: $eti->deposito_id ?: 0) ?: null;
                }

                $batch[] = [
                    'etiqueta_id' => (int) $eti->id,
                    'articulo_movimiento_id' => (int) $am->id,
                    'rol' => $rol,
                    'deposito_origen_id' => $depOrigen,
                    'deposito_destino_id' => $depDestino,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $ya[$uk] = 1;
                $stats['vinculados']++;
                $alguno = true;

                // Actualizar deposito etiqueta en TRE/COM
                if (! $dryRun && in_array($tipoMov, ['TRE', 'COM', 'AP'], true) && $am->deposito_id) {
                    DB::table('stock_etiqueta')->where('id', $eti->id)->update([
                        'deposito_id' => (int) $am->deposito_id,
                        'updated_at' => $now,
                    ]);
                }
            }

            if (! $alguno) {
                $stats['sin_movimiento']++;
            }

            if (! $dryRun && count($batch) >= 500) {
                DB::table('stock_etiqueta_movimiento')->insert($batch);
                $batch = [];
            }
        }

        if (! $dryRun && $batch !== []) {
            DB::table('stock_etiqueta_movimiento')->insert($batch);
        }

        return $stats;
    }

    /**
     * @param  list<object>  $filas
     * @return array<string, int>
     */
    public function importarConsumosApcom(array $filas, int $empresaId, bool $dryRun): array
    {
        $stats = [
            'en_anita' => count($filas),
            'creados' => 0,
            'sin_etiqueta' => 0,
            'omitidos' => 0,
            'marcadas_consumidas' => 0,
        ];

        $etiquetas = DB::table('stock_etiqueta')
            ->where('empresa_id', $empresaId)
            ->whereNotNull('anita_nro_interno')
            ->get(['id', 'anita_nro_interno', 'anita_nro_apertura', 'articulo_id', 'unidadmedida_id', 'lote_proveedor', 'fecha_vto'])
            ->keyBy(fn ($r) => ((int) $r->anita_nro_interno).'|'.((int) $r->anita_nro_apertura));

        $prefix = (string) config('trazabilidad_anita_surmar.leyenda_prefix', 'ANITA_SURMAR');
        $movs = DB::table('movimientostock')
            ->where('leyenda', 'like', $prefix.'|%')
            ->pluck('id', 'leyenda');

        $yaEtiqueta = DB::table('stock_etiqueta_consumo')
            ->where('empresa_id', $empresaId)
            ->pluck('id', 'etiqueta_id');

        $now = now();
        $batch = [];
        $consumidas = [];

        foreach ($filas as $row) {
            $nint = (int) ($row->apcom_nro_interno ?? 0);
            $nap = (int) ($row->apcom_nro_apertura ?? 0);
            $eti = $etiquetas->get($nint.'|'.$nap);
            if (! $eti) {
                $stats['sin_etiqueta']++;

                continue;
            }

            if (isset($yaEtiqueta[$eti->id])) {
                $stats['omitidos']++;
                $consumidas[(int) $eti->id] = true;

                continue;
            }

            $tipo = strtoupper(trim((string) ($row->apcom_tipo ?? '')));
            $letra = (string) ($row->apcom_letra ?? '');
            $suc = (int) ($row->apcom_sucursal ?? 0);
            $nro = (int) ($row->apcom_nro ?? 0);

            $movId = null;
            foreach (($tipo === 'TRA' ? ['TRS', 'TRE', 'TRA'] : [$tipo]) as $tMov) {
                $ley = Bridge::claveMovimiento($tMov, $letra, $suc, $nro);
                if ($movs->has($ley)) {
                    $movId = (int) $movs->get($ley);
                    break;
                }
            }

            $cert = trim((string) ($row->apcom_certificado ?? ''));
            $vto = (int) ($row->apcom_fecha_vto ?? 0);

            $batch[] = [
                'empresa_id' => $empresaId,
                'movimientostock_id' => $movId,
                'articulo_movimiento_id' => null,
                'etiqueta_id' => (int) $eti->id,
                'articulo_id' => (int) $eti->articulo_id,
                'cant_pieza' => (float) ($row->apcom_cant_pieza ?? 0),
                'peso_bruto' => (float) ($row->apcom_peso_bruto ?? 0),
                'peso_neto' => (float) ($row->apcom_peso_neto ?? 0),
                'unidadmedida_id' => $eti->unidadmedida_id,
                'lote_proveedor' => $cert !== '' ? mb_substr($cert, 0, 30) : $eti->lote_proveedor,
                'fecha_vto' => $vto > 0
                    ? RecepcionProveedorAnitaImportSupport::fechaDesdeAnita($vto)
                    : $eti->fecha_vto,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $yaEtiqueta[$eti->id] = 1;
            $consumidas[(int) $eti->id] = true;
            $stats['creados']++;

            if (! $dryRun && count($batch) >= 500) {
                DB::table('stock_etiqueta_consumo')->insert($batch);
                $batch = [];
            }
        }

        if (! $dryRun && $batch !== []) {
            DB::table('stock_etiqueta_consumo')->insert($batch);
        }

        if (! $dryRun && $consumidas !== []) {
            $ids = array_keys($consumidas);
            foreach (array_chunk($ids, 1000) as $chunk) {
                $n = DB::table('stock_etiqueta')
                    ->whereIn('id', $chunk)
                    ->where('estado', '!=', SurmarSupport::ESTADO_ANULADA)
                    ->update([
                        'estado' => SurmarSupport::ESTADO_CONSUMIDA,
                        'updated_at' => $now,
                    ]);
                $stats['marcadas_consumidas'] += $n;
            }
        } else {
            $stats['marcadas_consumidas'] = count($consumidas);
        }

        return $stats;
    }

    /**
     * @return array{depositos:int, saldos:int}
     */
    public function reconstruirSaldosEmpresa(int $empresaId): array
    {
        $depIds = DB::table('depmae')->where('empresa_id', $empresaId)->pluck('id');
        $total = 0;
        foreach ($depIds as $depId) {
            $total += $this->saldoRepo->reconstruir((int) $depId);
        }

        return ['depositos' => $depIds->count(), 'saldos' => $total];
    }

    /** @return list<object> */
    public function listarRecepaperUnaLectura(): array
    {
        return $this->listarUna('compras', 'recepaper', Bridge::camposRecepaper(), Bridge::whereFecha('recap_fecha_emi'));
    }

    /** @return list<object> */
    public function listarStkmovUnaLectura(): array
    {
        return $this->listarUna('ventas', 'stkmov', Bridge::camposStkmov(), Bridge::whereFecha('stkv_fecha'));
    }

    /** @return list<object> */
    public function listarStkvaperUnaLectura(): array
    {
        return $this->listarUna('ventas', 'stkvaper', Bridge::camposStkvaper(), Bridge::whereFecha('stkvap_fecha_emi'));
    }

    /** @return list<object> */
    public function listarApcomUnaLectura(): array
    {
        return $this->listarUna('ventas', 'apcom', Bridge::camposApcom(), Bridge::whereFecha('apcom_fecha_emi'));
    }

    /**
     * @return list<object>
     */
    private function listarUna(string $ambito, string $tabla, string $campos, string $where): array
    {
        $api = new ApiAnita;
        $bridge = $ambito === 'compras' ? Bridge::parametrosCompras() : Bridge::parametrosVentas();
        Log::info('trazabilidad_surmar.listar', ['tabla' => $tabla, 'where' => $where, 'path' => $bridge['path_sistema']]);

        $raw = $api->apiCall(array_merge($bridge, [
            'acc' => 'list',
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $where,
        ]));
        $filas = ApiAnita::decodificarListaFilas($raw);
        Log::info('trazabilidad_surmar.listar.ok', ['tabla' => $tabla, 'filas' => count($filas)]);

        return $filas;
    }

    private function cargarCacheTipos(): void
    {
        $rows = DB::table('tipotransaccion_stock')
            ->whereNull('deleted_at')
            ->get(['id', 'abreviatura', 'signo']);
        foreach ($rows as $r) {
            $ab = strtoupper(trim((string) $r->abreviatura));
            $this->cacheTipo[$ab] = (int) $r->id;
            $this->cacheTipoSigno[(int) $r->id] = (int) $r->signo;
        }
    }

    private function resolverArticuloId(string $skuAnita): ?int
    {
        $sku = ltrim(trim($skuAnita), '0');
        if ($sku === '') {
            return null;
        }
        if (! array_key_exists($sku, $this->cacheArticulo)) {
            $this->cacheArticulo[$sku] = (int) (Articulo::query()->where('sku', $sku)->value('id') ?: 0) ?: null;
        }

        return $this->cacheArticulo[$sku];
    }

    private function resolverDepositoId(int $codigoAnita, int $empresaId): ?int
    {
        if ($codigoAnita <= 0) {
            return null;
        }
        $key = $empresaId.'-'.$codigoAnita;
        if (! array_key_exists($key, $this->cacheDeposito)) {
            $id = RecepcionProveedorDepositoAnitaSupport::resolverIdDesdeCodigoAnita($codigoAnita, $empresaId);
            $this->cacheDeposito[$key] = $id > 0 ? $id : null;
        }

        return $this->cacheDeposito[$key];
    }

    private function assertEntornoSurmar(): void
    {
        $empresaId = (int) config('trazabilidad_anita_surmar.empresa_id', SurmarSupport::EMPRESA_ID);
        if (! SurmarSupport::esEmpresaSurmar($empresaId)) {
            throw new \RuntimeException('Import trazabilidad Surmar solo en empresa SURMAR (id='.$empresaId.').');
        }
        if (strtoupper((string) config('app.empresa')) !== 'EL BIERZO') {
            throw new \RuntimeException('Import trazabilidad Surmar solo en entorno EL BIERZO.');
        }
    }
}
