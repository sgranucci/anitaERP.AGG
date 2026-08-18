<?php

namespace App\Services\Stock\Surmar;

use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\CertificadoSenasaSurmar;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Stock_Etiqueta;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Services\Stock\Articulo_MovimientoService;
use App\Support\Stock\Surmar\SurmarEtiquetaLookupSupport;
use App\Support\Stock\SurmarEtiquetaZplSupport;
use App\Support\Stock\SurmarSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Piqueo Surmar en movimientos AP/DES/TRA (espejo a-stkmov.c: tapcom→apcom + etiqueta hija).
 */
class MovimientoStockSurmarEtiquetaService
{
    /** @var list<string> */
    public const TIPOS_CON_PIQUEO = ['AP', 'DES', 'TRA'];

    public function __construct(
        private readonly Articulo_MovimientoService $articuloMovimientoService,
    ) {}

    public function tipoRequierePiqueo(?Tipotransaccion_Stock $tipo): bool
    {
        if ($tipo === null) {
            return false;
        }
        $abrev = strtoupper(trim((string) ($tipo->abreviatura ?? '')));

        return in_array($abrev, self::TIPOS_CON_PIQUEO, true);
    }

    public function debeProcesar(int $empresaId, ?Tipotransaccion_Stock $tipo): bool
    {
        return SurmarSupport::esEmpresaSurmar($empresaId) && $this->tipoRequierePiqueo($tipo);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolverEscaneo(string $raw, ?int $empresaId = null, bool $soloDisponible = true): array
    {
        return SurmarEtiquetaLookupSupport::resolver($raw, $empresaId, $soloDisponible)['payload'];
    }

    public function zplParaEtiqueta(int $etiquetaId): string
    {
        $eti = Stock_Etiqueta::query()
            ->with(['articulos', 'unidadesmedida', 'separaUnidadmedida'])
            ->where('empresa_id', SurmarSupport::EMPRESA_ID)
            ->whereKey($etiquetaId)
            ->firstOrFail();

        return SurmarEtiquetaZplSupport::generar([
            'id' => (int) $eti->id,
            'codigo_articulo' => (string) ($eti->articulos->sku ?? ''),
            'descripcion' => (string) ($eti->descripcion_snapshot ?: ($eti->articulos->descripcion ?? '')),
            'peso_bruto' => (float) $eti->peso_bruto,
            'peso_neto' => (float) $eti->peso_neto,
            'cant_pieza' => (float) $eti->cant_pieza,
            'umd_separa' => (string) (
                \App\Support\Stock\Surmar\SurmarUnidadmedidaSeparaSupport::abreviatura((int) ($eti->separa_unidadmedida_id ?? 0))
            ),
            'cant_unid_separa' => (int) ($eti->cant_unid_separa ?? 1),
            'nro_apertura' => (int) ($eti->anita_nro_apertura ?? 1),
            'lote' => (string) ($eti->lote_proveedor ?? ''),
            'fecha' => optional($eti->fecha_emision)->format('d/m/Y'),
            'fecha_vto' => optional($eti->fecha_vto)->format('d/m/Y'),
        ]);
    }

    /**
     * @param  list<int>  $etiquetaIds
     * @return list<array{id:int,zpl:string}>
     */
    public function zplsParaIds(array $etiquetaIds): array
    {
        $out = [];
        foreach ($etiquetaIds as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $out[] = ['id' => $id, 'zpl' => $this->zplParaEtiqueta($id)];
        }

        return $out;
    }

    /**
     * Revierte piqueo Surmar de uno o más movimientos: reactiva etiquetas consumidas,
     * anula hijas generadas y borra consumos/movimientos de etiqueta.
     * Falla si alguna hija ya fue consumida o figura en un certificado SENASA no anulado.
     *
     * @param  list<int>  $movimientoIds
     * @return array{reactivadas:int, hijas_anuladas:int, consumos_borrados:int}
     */
    public function revertirEtiquetasPorMovimientos(array $movimientoIds): array
    {
        $stats = ['reactivadas' => 0, 'hijas_anuladas' => 0, 'consumos_borrados' => 0];
        // AGG / no-Bierzo: no tocar tablas Surmar (id 3 = Rebisco; stock_etiqueta puede no existir).
        if (! SurmarSupport::esEmpresaSurmar(SurmarSupport::EMPRESA_ID)) {
            return $stats;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $movimientoIds), fn (int $id) => $id > 0)));
        if ($ids === []) {
            return $stats;
        }

        $hijas = Stock_Etiqueta::query()
            ->where('empresa_id', SurmarSupport::EMPRESA_ID)
            ->whereIn('origen_id', $ids)
            ->whereIn('origen_tipo', [SurmarSupport::ORIGEN_AP, SurmarSupport::ORIGEN_DES, SurmarSupport::ORIGEN_TRA])
            ->lockForUpdate()
            ->get();

        if ($hijas->isNotEmpty()) {
            $hijaIds = $hijas->pluck('id')->map(fn ($v) => (int) $v)->all();
            $consumidas = $hijas->filter(fn (Stock_Etiqueta $e) => $e->estado === SurmarSupport::ESTADO_CONSUMIDA);
            if ($consumidas->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'etiquetas' => 'No se puede anular/revertir: etiqueta(s) hija ya consumida(s): #'
                        .$consumidas->pluck('id')->implode(', #').'.',
                ]);
            }
            $otrosConsumos = DB::table('stock_etiqueta_consumo')
                ->whereIn('etiqueta_id', $hijaIds)
                ->whereNotIn('movimientostock_id', $ids)
                ->pluck('etiqueta_id');
            if ($otrosConsumos->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'etiquetas' => 'No se puede anular/revertir: etiqueta(s) hija con consumo en otro movimiento: #'
                        .$otrosConsumos->unique()->implode(', #').'.',
                ]);
            }
            $enCert = DB::table('certificado_senasa_surmar_etiqueta as ce')
                ->join('certificado_senasa_surmar as c', 'c.id', '=', 'ce.certificado_senasa_surmar_id')
                ->whereIn('ce.etiqueta_id', $hijaIds)
                ->where('c.estado', '!=', CertificadoSenasaSurmar::ESTADO_ANULADO)
                ->pluck('ce.etiqueta_id');
            if ($enCert->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'etiquetas' => 'No se puede anular/revertir: etiqueta(s) hija en certificado SENASA activo: #'
                        .$enCert->unique()->implode(', #').'.',
                ]);
            }
        }

        $amIds = Articulo_Movimiento::query()
            ->whereIn('movimientostock_id', $ids)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $consumos = DB::table('stock_etiqueta_consumo')
            ->whereIn('movimientostock_id', $ids)
            ->get();
        $etiquetaConsumidaIds = $consumos->pluck('etiqueta_id')->map(fn ($v) => (int) $v)->unique()->values()->all();

        if ($amIds !== []) {
            DB::table('stock_etiqueta_movimiento')->whereIn('articulo_movimiento_id', $amIds)->delete();
        }

        if ($consumos->isNotEmpty()) {
            DB::table('stock_etiqueta_consumo')->whereIn('movimientostock_id', $ids)->delete();
            $stats['consumos_borrados'] = $consumos->count();
        }

        if ($etiquetaConsumidaIds !== []) {
            $quedan = DB::table('stock_etiqueta_consumo')
                ->whereIn('etiqueta_id', $etiquetaConsumidaIds)
                ->pluck('etiqueta_id')
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->all();
            $reactivar = array_values(array_diff($etiquetaConsumidaIds, $quedan));
            if ($reactivar !== []) {
                $n = Stock_Etiqueta::query()
                    ->where('empresa_id', SurmarSupport::EMPRESA_ID)
                    ->whereIn('id', $reactivar)
                    ->where('estado', SurmarSupport::ESTADO_CONSUMIDA)
                    ->update(['estado' => SurmarSupport::ESTADO_DISPONIBLE, 'updated_at' => now()]);
                $stats['reactivadas'] = (int) $n;
            }
        }

        foreach ($hijas as $hija) {
            if ($hija->estado === SurmarSupport::ESTADO_ANULADA) {
                continue;
            }
            $hija->estado = SurmarSupport::ESTADO_ANULADA;
            $hija->save();
            $stats['hijas_anuladas']++;
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{consumos:int, etiquetas_hijas:int, salidas_des:int, hijas_ids:list<int>}
     */
    public function procesarDespuesDeGrabar(
        MovimientoStock $movimiento,
        Tipotransaccion_Stock $tipo,
        array $data,
        string $funcion,
    ): array {
        $stats = ['consumos' => 0, 'etiquetas_hijas' => 0, 'salidas_des' => 0, 'hijas_ids' => []];
        if (! in_array($funcion, ['create', 'update'], true)) {
            return $stats;
        }
        if (! empty($data['omitir_surmar_etiquetas'])) {
            return $stats;
        }

        $empresaId = (int) ($data['empresa_id'] ?? 0);
        if ($empresaId <= 0 && ! empty($data['deposito_id'])) {
            $empresaId = (int) (DB::table('depmae')->where('id', (int) $data['deposito_id'])->value('empresa_id') ?? 0);
        }
        if (! $this->debeProcesar($empresaId, $tipo)) {
            return $stats;
        }

        $abrev = strtoupper(trim((string) $tipo->abreviatura));
        if ($abrev === 'TRA') {
            // TRA va por flujo transferencia
            return $stats;
        }

        $porLinea = $this->etiquetasPorLineaProductoDesdeRequest($data);
        $lineasProducto = $this->lineasProductoSurmar((int) $movimiento->id);
        if ($lineasProducto->isEmpty()) {
            throw ValidationException::withMessages([
                'articulos_id' => 'El movimiento no tiene líneas de producto.',
            ]);
        }

        $this->assertEtiquetasPorLineaCompletas($porLinea, $lineasProducto->count(), $abrev);

        $depositoForm = (int) ($data['deposito_id'] ?? $lineasProducto->first()->deposito_id ?? 0);
        $loteCab = $this->resolverLote($data, (int) $movimiento->id);
        // Fecha de movimiento para consumos / DES; la etiqueta impresa usa fecha del día.
        $fecha = (string) ($movimiento->fecha ?? $data['fecha'] ?? now()->toDateString());
        $fechaEmisionEtiqueta = now()->toDateString();

        foreach ($lineasProducto->values() as $idx => $linea) {
            $ids = $porLinea[$idx] ?? [];
            $this->consumirEtiquetas(
                $ids,
                $empresaId,
                (int) $movimiento->id,
                (int) $linea->id,
                $depositoForm,
                'SALIDA',
                $depositoForm,
                null,
                $stats,
                $abrev === 'DES' ? $movimiento : null,
                $abrev === 'DES' ? $tipo : null,
                $fecha,
                $loteCab,
            );
        }

        $origenTipo = $abrev === 'DES' ? SurmarSupport::ORIGEN_DES : SurmarSupport::ORIGEN_AP;
        $this->crearEtiquetasHijas(
            $lineasProducto,
            $empresaId,
            (int) $movimiento->id,
            $depositoForm,
            $origenTipo,
            $loteCab,
            $fechaEmisionEtiqueta,
            $stats,
        );

        return $stats;
    }

    /**
     * Tras transferencia Surmar tipo TRA confirmada (con movimientos salida/entrada).
     *
     * @param  array<string, mixed>  $data
     * @return array{consumos:int, etiquetas_hijas:int, salidas_des:int, hijas_ids:list<int>}
     */
    public function procesarDespuesDeTransferencia(
        Transferencia_Mercaderia $transferencia,
        Tipotransaccion_Stock $tipo,
        array $data,
    ): array {
        $stats = ['consumos' => 0, 'etiquetas_hijas' => 0, 'salidas_des' => 0, 'hijas_ids' => []];
        $empresaId = (int) ($transferencia->empresa_id ?? $data['empresa_id'] ?? 0);
        if (! $this->debeProcesar($empresaId, $tipo)) {
            return $stats;
        }

        $porLinea = $this->etiquetasPorLineaProductoDesdeRequest($data);

        $salidaId = (int) ($transferencia->movimientostock_salida_id ?? 0);
        $entradaId = (int) ($transferencia->movimientostock_entrada_id ?? 0);
        if ($salidaId <= 0 || $entradaId <= 0) {
            throw ValidationException::withMessages([
                'etiquetas_consumo_id' => 'TRA Surmar requiere transferencia confirmada (sin pendiente de aprobación). Usá tipo TRA sin aprobación.',
            ]);
        }

        $lineasSalida = $this->lineasProductoSurmar($salidaId);
        $lineasEntrada = $this->lineasProductoSurmar($entradaId);
        if ($lineasSalida->isEmpty() || $lineasEntrada->isEmpty()) {
            throw ValidationException::withMessages([
                'articulos_id' => 'Transferencia sin líneas de stock para vincular etiquetas.',
            ]);
        }

        $this->assertEtiquetasPorLineaCompletas($porLinea, $lineasSalida->count(), 'TRA');

        $depOrigen = (int) ($transferencia->deposito_origen_id ?? $data['deposito_salida_id'] ?? 0);
        $depDestino = (int) ($transferencia->deposito_destino_id ?? $data['deposito_entrada_id'] ?? 0);
        $loteCab = $this->resolverLote($data, $entradaId);
        $fecha = (string) ($data['fecha'] ?? now()->toDateString());
        $fechaEmisionEtiqueta = now()->toDateString();

        foreach ($lineasSalida->values() as $idx => $lineaSalida) {
            $ids = $porLinea[$idx] ?? [];
            $this->consumirEtiquetas(
                $ids,
                $empresaId,
                $salidaId,
                (int) $lineaSalida->id,
                $depOrigen,
                'TRANSFERENCIA',
                $depOrigen,
                $depDestino,
                $stats,
                null,
                null,
                $fecha,
                $loteCab,
            );
        }

        $this->crearEtiquetasHijas(
            $lineasEntrada,
            $empresaId,
            $entradaId,
            $depDestino,
            SurmarSupport::ORIGEN_TRA,
            $loteCab,
            $fechaEmisionEtiqueta,
            $stats,
        );

        return $stats;
    }

    /**
     * @param  list<int>  $ids
     * @param  array{consumos:int, etiquetas_hijas:int, salidas_des:int, hijas_ids:list<int>}  $stats
     */
    private function consumirEtiquetas(
        array $ids,
        int $empresaId,
        int $movimientostockId,
        int $articuloMovimientoId,
        int $depositoForm,
        string $rol,
        ?int $depOrigen,
        ?int $depDestino,
        array &$stats,
        ?MovimientoStock $movDes,
        ?Tipotransaccion_Stock $tipoDes,
        string $fecha,
        string $loteCab,
    ): void {
        $now = now();
        $etiquetas = Stock_Etiqueta::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('id', $ids)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($ids as $etiquetaId) {
            $eti = $etiquetas->get($etiquetaId);
            if (! $eti) {
                throw ValidationException::withMessages([
                    'etiquetas_consumo_id' => 'Etiqueta #'.$etiquetaId.' no encontrada.',
                ]);
            }
            if ($eti->estado !== SurmarSupport::ESTADO_DISPONIBLE) {
                throw ValidationException::withMessages([
                    'etiquetas_consumo_id' => 'Etiqueta #'.$etiquetaId.' ya no está DISPONIBLE.',
                ]);
            }

            DB::table('stock_etiqueta_consumo')->insert([
                'empresa_id' => $empresaId,
                'movimientostock_id' => $movimientostockId,
                'articulo_movimiento_id' => $articuloMovimientoId,
                'etiqueta_id' => $eti->id,
                'articulo_id' => $eti->articulo_id,
                'cant_pieza' => $eti->cant_pieza,
                'peso_bruto' => $eti->peso_bruto,
                'peso_neto' => $eti->peso_neto,
                'unidadmedida_id' => $eti->unidadmedida_id,
                'lote_proveedor' => $eti->lote_proveedor,
                'fecha_vto' => $eti->fecha_vto,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('stock_etiqueta_movimiento')->insert([
                'etiqueta_id' => $eti->id,
                'articulo_movimiento_id' => $articuloMovimientoId,
                'rol' => $rol,
                'deposito_origen_id' => $depOrigen ?: ($eti->deposito_id ?: null),
                'deposito_destino_id' => $depDestino,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $eti->update(['estado' => SurmarSupport::ESTADO_CONSUMIDA]);
            $stats['consumos']++;

            if ($movDes && $tipoDes) {
                $this->grabarSalidaDesEtiqueta($movDes, $tipoDes, $eti, $fecha, $depositoForm, $loteCab);
                $stats['salidas_des']++;
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Articulo_Movimiento>  $lineasProducto
     * @param  array{consumos:int, etiquetas_hijas:int, salidas_des:int, hijas_ids:list<int>}  $stats
     */
    private function crearEtiquetasHijas(
        $lineasProducto,
        int $empresaId,
        int $movimientoId,
        int $depositoForm,
        string $origenTipo,
        string $loteCab,
        string $fecha,
        array &$stats,
    ): void {
        $now = now();
        $usuarioId = (int) (Auth::id() ?: 0);

        foreach ($lineasProducto as $linea) {
            $peso = abs((float) $linea->cantidad);
            $piezas = abs((float) ($linea->pieza ?? 0));
            if ($piezas <= 0) {
                $piezas = 1;
            }
            $depositoHija = (int) ($linea->deposito_id ?: $depositoForm);
            $hija = Stock_Etiqueta::create([
                'empresa_id' => $empresaId,
                'articulo_id' => (int) $linea->articulo_id,
                'deposito_id' => $depositoHija > 0 ? $depositoHija : null,
                'unidadmedida_id' => null,
                'estado' => SurmarSupport::ESTADO_DISPONIBLE,
                'origen_tipo' => $origenTipo,
                'origen_id' => $movimientoId,
                'origen_linea_id' => $linea->id,
                'articulo_movimiento_id' => $linea->id,
                'etiqueta_origen_id' => null,
                'lote_proveedor' => mb_substr($loteCab, 0, 30),
                'fecha_vto' => null,
                'fecha_emision' => $fecha,
                'hora_emision' => $now->format('H:i'),
                'cant_pieza' => $piezas,
                'peso_bruto' => $peso,
                'peso_neto' => $peso,
                'descripcion_snapshot' => null,
                'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
            ]);

            DB::table('stock_etiqueta_movimiento')->insert([
                'etiqueta_id' => $hija->id,
                'articulo_movimiento_id' => $linea->id,
                'rol' => 'ENTRADA',
                'deposito_origen_id' => null,
                'deposito_destino_id' => $depositoHija > 0 ? $depositoHija : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $stats['etiquetas_hijas']++;
            $stats['hijas_ids'][] = (int) $hija->id;
        }
    }

    /**
     * Etiquetas por índice de renglón de producto (solo filas con artículo/cantidad, mismo orden que el grabado).
     *
     * @param  array<string, mixed>  $data
     * @return array<int, list<int>>
     */
    public function etiquetasPorLineaProductoDesdeRequest(array $data): array
    {
        $raw = $this->etiquetasPorLineaDesdeRequest($data);
        $articulos = array_values((array) ($data['articulos_id'] ?? []));
        $cantidades = array_values((array) ($data['cantidades'] ?? []));
        $n = max(count($articulos), count($cantidades), $raw === [] ? 0 : (max(array_keys($raw)) + 1));

        $out = [];
        $j = 0;
        for ($i = 0; $i < $n; $i++) {
            $articuloId = (int) ($articulos[$i] ?? 0);
            $cant = (float) ($cantidades[$i] ?? 0);
            if ($articuloId <= 0 && abs($cant) < 1e-9) {
                continue;
            }
            $out[$j] = $raw[$i] ?? [];
            $j++;
        }

        // Sin filas de artículo en el request (caso raro / legacy pool): devolver raw
        if ($j === 0 && $raw !== []) {
            return $raw;
        }

        return $out;
    }

    /**
     * Etiquetas piqueadas por índice de renglón de producto (orden del formulario).
     *
     * @param  array<string, mixed>  $data
     * @return array<int, list<int>>
     */
    public function etiquetasPorLineaDesdeRequest(array $data): array
    {
        $rawLinea = $data['etiquetas_consumo_linea'] ?? null;
        $porLinea = [];
        $vistos = [];

        if (is_array($rawLinea) && $rawLinea !== []) {
            foreach ($rawLinea as $idx => $idsRaw) {
                $idx = (int) $idx;
                if (! is_array($idsRaw)) {
                    $idsRaw = [$idsRaw];
                }
                $ids = [];
                foreach ($idsRaw as $v) {
                    $id = (int) $v;
                    if ($id <= 0) {
                        continue;
                    }
                    if (isset($vistos[$id])) {
                        throw ValidationException::withMessages([
                            'etiquetas_consumo_linea' => 'Etiqueta #'.$id.' repetida en más de un renglón.',
                        ]);
                    }
                    $vistos[$id] = true;
                    $ids[$id] = $id;
                }
                $porLinea[$idx] = array_values($ids);
            }

            return $porLinea;
        }

        // Compat: pool único → todo en renglón 0
        $flat = [];
        $raw = $data['etiquetas_consumo_id'] ?? [];
        if (! is_array($raw)) {
            $raw = [$raw];
        }
        foreach ($raw as $v) {
            $id = (int) $v;
            if ($id > 0) {
                $flat[$id] = $id;
            }
        }
        if ($flat !== []) {
            $porLinea[0] = array_values($flat);
        }

        return $porLinea;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    public function idsEtiquetasDesdeRequest(array $data): array
    {
        $ids = [];
        foreach ($this->etiquetasPorLineaDesdeRequest($data) as $lista) {
            foreach ($lista as $id) {
                $ids[(int) $id] = (int) $id;
            }
        }

        return array_values($ids);
    }

    /**
     * Líneas de producto del movimiento (excluye salidas automáticas DES por etiqueta).
     *
     * @return \Illuminate\Support\Collection<int, Articulo_Movimiento>
     */
    public function lineasProductoSurmar(int $movimientoId): \Illuminate\Support\Collection
    {
        return Articulo_Movimiento::query()
            ->where('movimientostock_id', $movimientoId)
            ->where(function ($q) {
                $q->whereNull('concepto')
                    ->orWhere('concepto', 'not like', 'DES etiqueta #%');
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * Precarga para editar / old input: lista por índice de renglón con payload de consulta.
     *
     * @param  array<int, mixed>|null  $idsPorLineaOld
     * @return list<list<array<string, mixed>>>
     */
    public function consumosPayloadPorLineaProducto(int $movimientoId, ?array $idsPorLineaOld = null): array
    {
        if (is_array($idsPorLineaOld) && $idsPorLineaOld !== []) {
            return $this->hidratarPayloadsPorLinea($idsPorLineaOld);
        }

        $lineas = $this->lineasProductoSurmar($movimientoId);
        if ($lineas->isEmpty()) {
            return [];
        }

        $lineaIds = $lineas->pluck('id')->map(fn ($v) => (int) $v)->all();
        $consumos = DB::table('stock_etiqueta_consumo')
            ->where('movimientostock_id', $movimientoId)
            ->whereIn('articulo_movimiento_id', $lineaIds)
            ->orderBy('id')
            ->get();

        $etiquetaIds = $consumos->pluck('etiqueta_id')->map(fn ($v) => (int) $v)->unique()->values()->all();
        $etiquetas = $etiquetaIds === []
            ? collect()
            : Stock_Etiqueta::query()
                ->with(['articulos:id,sku,descripcion,grupocarne,tipocarne', 'depositos:id,codigo,nombre', 'unidadesmedida:id,abreviatura,nombre'])
                ->whereIn('id', $etiquetaIds)
                ->get()
                ->keyBy('id');

        $porAm = [];
        foreach ($consumos as $c) {
            $amId = (int) ($c->articulo_movimiento_id ?? 0);
            $etiId = (int) ($c->etiqueta_id ?? 0);
            $eti = $etiquetas->get($etiId);
            if (! $eti) {
                continue;
            }
            $porAm[$amId][] = SurmarEtiquetaLookupSupport::payload($eti);
        }

        $out = [];
        foreach ($lineas->values() as $idx => $linea) {
            $out[$idx] = $porAm[(int) $linea->id] ?? [];
        }

        $tieneAlgo = false;
        foreach ($out as $lista) {
            if ($lista !== []) {
                $tieneAlgo = true;
                break;
            }
        }

        // Legacy: pool único colgado de un AM que no matcheó / vacío → renglón 0
        if (! $tieneAlgo) {
            $todos = DB::table('stock_etiqueta_consumo')
                ->where('movimientostock_id', $movimientoId)
                ->orderBy('id')
                ->pluck('etiqueta_id')
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->values()
                ->all();
            if ($todos !== []) {
                return $this->hidratarPayloadsPorLinea([0 => $todos]);
            }
        }

        return array_values($out);
    }

    /**
     * @param  array<int, mixed>  $idsPorLinea
     * @return list<list<array<string, mixed>>>
     */
    public function hidratarPayloadsPorLinea(array $idsPorLinea): array
    {
        $idsFlat = [];
        foreach ($idsPorLinea as $lista) {
            if (! is_array($lista)) {
                $lista = [$lista];
            }
            foreach ($lista as $v) {
                if (is_array($v) && isset($v['etiqueta_id'])) {
                    $id = (int) $v['etiqueta_id'];
                } else {
                    $id = (int) $v;
                }
                if ($id > 0) {
                    $idsFlat[$id] = $id;
                }
            }
        }

        $etiquetas = $idsFlat === []
            ? collect()
            : Stock_Etiqueta::query()
                ->with(['articulos:id,sku,descripcion,grupocarne,tipocarne', 'depositos:id,codigo,nombre', 'unidadesmedida:id,abreviatura,nombre'])
                ->where('empresa_id', SurmarSupport::EMPRESA_ID)
                ->whereIn('id', array_values($idsFlat))
                ->get()
                ->keyBy('id');

        $out = [];
        $maxIdx = -1;
        foreach ($idsPorLinea as $idx => $lista) {
            $idx = (int) $idx;
            $maxIdx = max($maxIdx, $idx);
            if (! is_array($lista)) {
                $lista = [$lista];
            }
            $payloads = [];
            foreach ($lista as $v) {
                if (is_array($v) && isset($v['etiqueta_id']) && isset($v['sku'])) {
                    $payloads[] = $v;
                    continue;
                }
                $id = is_array($v) ? (int) ($v['etiqueta_id'] ?? 0) : (int) $v;
                $eti = $etiquetas->get($id);
                if ($eti) {
                    $payloads[] = SurmarEtiquetaLookupSupport::payload($eti);
                }
            }
            $out[$idx] = $payloads;
        }

        $ordenado = [];
        for ($i = 0; $i <= $maxIdx; $i++) {
            $ordenado[$i] = $out[$i] ?? [];
        }

        return array_values($ordenado);
    }

    /**
     * @param  array<int, list<int>>  $porLinea
     */
    private function assertEtiquetasPorLineaCompletas(array $porLinea, int $cantLineas, string $abrev): void
    {
        if ($cantLineas <= 0) {
            throw ValidationException::withMessages([
                'etiquetas_consumo_linea' => 'Debe piquear etiquetas disponibles para '.$abrev.'.',
            ]);
        }

        $faltan = [];
        for ($i = 0; $i < $cantLineas; $i++) {
            if (($porLinea[$i] ?? []) === []) {
                $faltan[] = (string) ($i + 1);
            }
        }
        if ($faltan !== []) {
            throw ValidationException::withMessages([
                'etiquetas_consumo_linea' => 'Surmar '.$abrev.': cada ítem debe tener al menos una etiqueta. Faltan renglón(es): '.implode(', ', $faltan).'.',
            ]);
        }
    }

    public static function esLineaSalidaDesEtiqueta(?string $concepto): bool
    {
        return is_string($concepto) && preg_match('/^DES etiqueta #\d+/', $concepto) === 1;
    }

    /** @param  array<string, mixed>  $data */
    private function resolverLote(array $data, int $refId): string
    {
        $loteCab = trim((string) ($data['lote'] ?? ''));
        if ($loteCab === '' || strtoupper($loteCab) === 'LOTE DE ALTA') {
            return now()->format('Ymd').'-'.$refId;
        }

        return $loteCab;
    }

    private function grabarSalidaDesEtiqueta(
        MovimientoStock $movimiento,
        Tipotransaccion_Stock $tipo,
        Stock_Etiqueta $eti,
        string $fecha,
        int $depositoForm,
        string $lote,
    ): void {
        $depositoId = (int) ($eti->deposito_id ?: $depositoForm);
        if ($depositoId <= 0) {
            throw ValidationException::withMessages([
                'etiquetas_consumo_id' => 'Etiqueta #'.$eti->id.' sin depósito para salida DES.',
            ]);
        }
        $peso = abs((float) $eti->peso_neto);
        if ($peso <= 0) {
            $peso = abs((float) $eti->cant_pieza);
        }
        if ($peso <= 0) {
            return;
        }

        $this->articuloMovimientoService->guardaArticuloMovimiento('create', [
            'fecha' => $fecha,
            'fechajornada' => $fecha,
            'tipotransaccion_stock_id' => $tipo->id,
            'movimientostock_id' => $movimiento->id,
            'deposito_id' => $depositoId,
            'lote' => $lote,
            'articulo_id' => $eti->articulo_id,
            'concepto' => 'DES etiqueta #'.$eti->id,
            'cantidad' => -$peso,
            'cantidad_ya_firmada' => true,
            'precio' => 0,
            'costo' => 0,
            'descuento' => 0,
            'pieza' => abs((float) $eti->cant_pieza),
            'caja' => 0,
            'listaprecio_id' => null,
            'moneda_id' => null,
            'incluyeimpuesto' => null,
            'loteimportacion_id' => null,
        ], []);
    }
}
