<?php

declare(strict_types=1);

namespace App\Services\Stock;

use App\Mail\Stock\RecepcionProveedorCostoAuditorMail;
use App\Models\Stock\Articulo;
use App\Models\Stock\Recepcion_Proveedor;
use App\Services\Ventas\Gastronomia\GastronomiaRecetasReporteService;
use App\Support\Stock\ArticuloPrecioUltimaCompraSupport;
use App\Support\Stock\RecepcionProveedorCostoAuditorSupport as Reglas;
use App\Support\Stock\RecepcionProveedorDepositoSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use App\Support\Stock\TransferenciaMercaderiaEstados;
use App\Support\Ventas\GastronomiaRecetasReporteFiltros;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Auditor diario (solo lectura) de desfasajes de costo de compra que terminan en el costo de recetas:
 * precio de recepción vs OC e historia, UM y coeficientes, cambios de maestro y costo destino de TRA.
 */
final class RecepcionProveedorCostoAuditorService
{
    public const PRECIO_VS_OC = 'Precio vs OC';

    public const PRECIO_VS_HISTORIA = 'Precio vs compras anteriores';

    public const UM_SIN_CONVERSION = 'UM de línea sin conversión';

    public const PROVEEDOR_SIN_COEF = 'Proveedor sin coeficiente';

    public const MAESTRO_MODIFICADO = 'Maestro de conversión modificado';

    public const TRA_COSTO_DESTINO = 'Costo destino de transferencia';

    /** @var array<int, string> */
    private array $unidades = [];

    /** @var list<int> */
    private array $tiposExcluidos = [];

    public function __construct(
        private readonly GastronomiaRecetasReporteService $recetasService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(?string $desde = null, ?string $hasta = null, bool $enviarMail = true, ?bool $incluirRecetas = null): array
    {
        $config = (array) config('recepcion_proveedor.auditoria_costos_diaria', []);
        $ventana = max(1, (int) ($config['ventana_dias'] ?? 3));
        $hasta = $hasta ?: Carbon::today()->toDateString();
        $desde = $desde ?: Carbon::parse($hasta)->subDays($ventana - 1)->toDateString();
        $umbral = (float) ($config['umbral_ratio'] ?? 1.5);
        $incluirRecetas ??= (bool) ($config['incluir_recetas'] ?? true);

        $this->unidades = DB::table('unidadmedida')->pluck('abreviatura', 'id')->map(fn ($v) => (string) $v)->all();
        $nombresExcluidos = array_map('mb_strtoupper', (array) ($config['tipos_articulo_excluidos'] ?? []));
        $this->tiposExcluidos = $nombresExcluidos === [] ? [] : DB::table('tipoarticulo')
            ->whereIn(DB::raw('UPPER(nombre)'), $nombresExcluidos)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $informe = [
            'fecha_calendario' => $desde === $hasta ? $desde : $desde.' → '.$hasta,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'umbral_ratio' => $umbral,
            'lineas_auditadas' => 0,
            'transferencias_auditadas' => 0,
            'hallazgos' => [],
            'recetas' => [],
            'recetas_auditadas' => 0,
            'errores' => [],
            'requiere_alerta' => false,
            'mail_enviado' => false,
            'mail_destino' => null,
            'mail_error' => null,
        ];

        $skusConDesvio = [];
        $pasos = [
            'recepciones' => function () use (&$informe, &$skusConDesvio, $desde, $hasta, $umbral, $config): void {
                $this->auditarRecepciones($informe, $desde, $hasta, $umbral, $config, $skusConDesvio);
            },
            'maestros' => function () use (&$informe, &$skusConDesvio, $desde, $hasta): void {
                $this->auditarMaestros($informe, $desde, $hasta, $skusConDesvio);
            },
            'transferencias' => function () use (&$informe, &$skusConDesvio, $desde, $hasta, $umbral, $config): void {
                $this->auditarTransferencias($informe, $desde, $hasta, $umbral, $config, $skusConDesvio);
            },
        ];
        if ($incluirRecetas) {
            $pasos['recetas'] = function () use (&$informe, &$skusConDesvio, $config): void {
                $this->auditarRecetas($informe, $config, $skusConDesvio);
            };
        }

        foreach ($pasos as $paso => $fn) {
            try {
                $fn();
            } catch (Throwable $e) {
                $informe['errores'][] = ['paso' => $paso, 'mensaje' => $e->getMessage()];
                Log::warning('auditor_costos_compra.error', ['paso' => $paso, 'error' => $e->getMessage()]);
            }
        }

        $informe['requiere_alerta'] = $informe['hallazgos'] !== [] || $informe['recetas'] !== [] || $informe['errores'] !== [];

        if ($enviarMail) {
            $this->enviarMail($informe, $config);
        }

        return $informe;
    }

    /**
     * @param  array<string, mixed>  $informe
     * @param  array<string, mixed>  $config
     * @param  array<string, true>  $skusConDesvio
     */
    private function auditarRecepciones(array &$informe, string $desde, string $hasta, float $umbral, array $config, array &$skusConDesvio): void
    {
        $lineas = $this->lineasRecepcion()
            ->whereBetween('rp.fecha', [$desde, $hasta])
            ->orderBy('rp.fecha')
            ->orderBy('rpa.id')
            ->get();
        $informe['lineas_auditadas'] = $lineas->count();
        if ($lineas->isEmpty()) {
            return;
        }

        $articuloIds = $lineas->pluck('articulo_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $historiaDesde = Carbon::parse($desde)->subDays((int) ($config['historia_dias'] ?? 180))->toDateString();
        $historiaPorArticulo = $this->lineasRecepcion()
            ->whereIn('rpa.articulo_id', $articuloIds)
            ->whereBetween('rp.fecha', [$historiaDesde, $hasta])
            ->where('rpa.cantidad', '>', 0)
            ->where('rpa.precio', '>', 0)
            ->orderByDesc('rp.fecha')
            ->orderByDesc('rpa.id')
            ->get()
            ->groupBy('articulo_id');
        $proveedores = $this->articulosProveedor($articuloIds);
        $maxCompras = (int) ($config['historia_compras'] ?? 6);

        foreach ($lineas as $l) {
            if ((float) $l->cantidad <= 0) {
                continue;
            }
            $articuloId = (int) $l->articulo_id;
            $sku = (string) $l->sku;
            $umLinea = (int) ($l->um_linea ?? 0);
            $umArticulo = (int) ($l->um_articulo ?? 0);
            $coef = (float) ($l->coeficienteconversion ?? 1);
            $precio = (float) $l->precio;
            $envase = $this->unidadesEnvase($l, $proveedores[$articuloId] ?? collect());
            $documento = $this->documentoRecepcion($l);
            $base = [
                'fecha' => (string) $l->fecha,
                'documento' => $documento,
                'proveedor' => (string) ($l->proveedor ?? ''),
                'sku' => $sku,
                'descripcion' => (string) $l->descripcion,
                'url' => route('editar_recepcion_proveedor', ['id' => (int) $l->recepcion_id]),
            ];

            $desvioOc = Reglas::evaluarDesvio($precio, (float) ($l->precio_ordencompra ?? 0) ?: null, $umbral, $envase);
            if ($desvioOc !== null) {
                $this->agregar($informe, $skusConDesvio, self::PRECIO_VS_OC, 'Alta', $base + [
                    'detalle' => sprintf(
                        '%s %s a $ %s vs OC $ %s (×%s).',
                        $this->numero((float) $l->cantidad),
                        $this->um($umLinea),
                        $this->numero($precio),
                        $this->numero((float) $l->precio_ordencompra),
                        $this->numero($desvioOc['ratio'], 2),
                    ),
                    'pista' => $desvioOc['pista'],
                ]);
            }

            $unitario = $this->unitarioLocal($l);
            $referencias = $historiaPorArticulo->get($l->articulo_id, collect())
                ->filter(fn ($h) => (int) $h->rpa_id !== (int) $l->rpa_id
                    && ((string) $h->fecha < (string) $l->fecha
                        || ((string) $h->fecha === (string) $l->fecha && (int) $h->rpa_id < (int) $l->rpa_id)))
                ->take($maxCompras)
                ->map(fn ($h) => $this->unitarioLocal($h))
                ->values()
                ->all();
            $mediana = Reglas::mediana($referencias);
            $desvioHist = Reglas::evaluarDesvio($unitario, $mediana, $umbral, $envase);
            if ($desvioHist !== null) {
                $this->agregar($informe, $skusConDesvio, self::PRECIO_VS_HISTORIA, 'Alta', $base + [
                    'detalle' => sprintf(
                        'Unitario en UM de stock $ %s vs mediana de %d compras previas $ %s (×%s).',
                        $this->numero($unitario),
                        count($referencias),
                        $this->numero((float) $mediana),
                        $this->numero($desvioHist['ratio'], 2),
                    ),
                    'pista' => $desvioHist['pista'],
                ]);
            }

            if (Reglas::lineaSinConversion($umLinea, $umArticulo, $coef)) {
                $this->agregar($informe, $skusConDesvio, self::UM_SIN_CONVERSION, 'Alta', $base + [
                    'detalle' => sprintf('Línea en %s y artículo en %s con coeficiente 1.', $this->um($umLinea), $this->um($umArticulo)),
                    'pista' => 'El precio de la línea se toma como precio de la UM del artículo.',
                ]);
            }
        }

        $this->auditarMaestroProveedor($informe, $articuloIds, $lineas->keyBy('articulo_id'), $proveedores, $skusConDesvio);
    }

    /**
     * @param  array<string, mixed>  $informe
     * @param  list<int>  $articuloIds
     * @param  Collection<int|string, object>  $articulos
     * @param  array<int, Collection<int, object>>  $proveedores
     * @param  array<string, true>  $skusConDesvio
     */
    private function auditarMaestroProveedor(array &$informe, array $articuloIds, Collection $articulos, array $proveedores, array &$skusConDesvio): void
    {
        foreach ($articuloIds as $articuloId) {
            $art = $articulos->get($articuloId);
            if ($art === null) {
                continue;
            }
            foreach ($proveedores[$articuloId] ?? [] as $ap) {
                $umCompra = (int) ($ap->unidadmedida_compra_id ?? 0);
                $umArticulo = (int) ($art->um_articulo ?? 0);
                $coef = (float) ($ap->coeficiente_conversion ?? 0);
                if (! Reglas::faltaCoeficienteProveedor($umCompra, $umArticulo, $coef)) {
                    continue;
                }
                $declaradas = Reglas::unidadesDesdeDescripcion((string) ($ap->nombre_articulo_proveedor ?? ''));
                $this->agregar($informe, $skusConDesvio, self::PROVEEDOR_SIN_COEF, 'Alta', [
                    'fecha' => '',
                    'documento' => 'Artículo proveedor #'.(int) $ap->id,
                    'proveedor' => (string) ($ap->proveedor ?? ''),
                    'sku' => (string) $art->sku,
                    'descripcion' => (string) $art->descripcion,
                    'url' => null,
                    'detalle' => sprintf(
                        'Compra en %s, artículo en %s, coeficiente %s («%s»).',
                        $this->um($umCompra),
                        $this->um($umArticulo),
                        $this->numero($coef, 2),
                        (string) ($ap->nombre_articulo_proveedor ?? ''),
                    ),
                    'pista' => $declaradas !== null
                        ? sprintf('La descripción indica %s unidades por envase: coeficiente esperado %s.', $this->numero($declaradas, 0), $this->numero($declaradas, 0))
                        : 'Cargar el coeficiente de conversión del proveedor.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $informe
     * @param  array<string, true>  $skusConDesvio
     */
    private function auditarMaestros(array &$informe, string $desde, string $hasta, array &$skusConDesvio): void
    {
        $audits = DB::table('audits')
            ->where('created_at', '>=', $desde.' 00:00:00')
            ->where('created_at', '<=', $hasta.' 23:59:59')
            ->where('auditable_type', Articulo::class)
            ->where('event', 'updated')
            ->orderBy('id')
            ->get(['id', 'auditable_id', 'user_id', 'old_values', 'new_values', 'created_at']);

        $filas = [];
        foreach ($audits as $audit) {
            $cambios = Reglas::cambiosConversion(
                (array) (json_decode((string) $audit->old_values, true) ?: []),
                (array) (json_decode((string) $audit->new_values, true) ?: []),
            );
            if ($cambios !== []) {
                $filas[] = [$audit, $cambios];
            }
        }
        if ($filas === []) {
            return;
        }

        $articuloIds = array_values(array_unique(array_map(fn ($f) => (int) $f[0]->auditable_id, $filas)));
        $articulos = DB::table('articulo')->whereIn('id', $articuloIds)->get(['id', 'sku', 'descripcion'])->keyBy('id');
        $usuarios = DB::table('usuario')
            ->whereIn('id', array_values(array_unique(array_map(fn ($f) => (int) $f[0]->user_id, $filas))))
            ->pluck('nombre', 'id');
        $conCompras = DB::table('recepcion_proveedor_articulo')
            ->whereIn('articulo_id', $articuloIds)
            ->distinct()
            ->pluck('articulo_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        foreach ($filas as [$audit, $cambios]) {
            $articuloId = (int) $audit->auditable_id;
            if (! $conCompras->has($articuloId)) {
                continue;
            }
            $art = $articulos->get($articuloId);
            $texto = implode('; ', array_map(fn ($c) => sprintf(
                '%s: %s → %s',
                $c['campo'],
                $this->valorCampo($c['campo'], $c['antes']),
                $this->valorCampo($c['campo'], $c['despues']),
            ), $cambios));
            $this->agregar($informe, $skusConDesvio, self::MAESTRO_MODIFICADO, 'Media', [
                'fecha' => substr((string) $audit->created_at, 0, 16),
                'documento' => 'Usuario: '.(string) ($usuarios[(int) $audit->user_id] ?? '#'.(int) $audit->user_id),
                'proveedor' => '',
                'sku' => (string) ($art->sku ?? ''),
                'descripcion' => (string) ($art->descripcion ?? ''),
                'url' => null,
                'detalle' => $texto,
                'pista' => 'Verificar que las recepciones y el maestro proveedor sigan convirtiendo a la nueva UM.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $informe
     * @param  array<string, mixed>  $config
     * @param  array<string, true>  $skusConDesvio
     */
    private function auditarTransferencias(array &$informe, string $desde, string $hasta, float $umbral, array $config, array &$skusConDesvio): void
    {
        $base = fn () => $this->excluirTipos(DB::table('transferencia_mercaderia_articulo as tma')
            ->join('transferencia_mercaderia as tm', 'tm.id', '=', 'tma.transferencia_mercaderia_id')
            ->join('articulo as ad', 'ad.id', '=', 'tma.articulo_destino_id')
            ->join('articulo as ao', 'ao.id', '=', 'tma.articulo_origen_id')
            ->where('tm.estado', TransferenciaMercaderiaEstados::CONFIRMADA)
            ->where('tma.precio_costo_destino', '>', 0), 'ao.tipoarticulo_id');

        $lineas = $base()
            ->whereBetween('tm.fecha', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->orderBy('tm.fecha')
            ->orderBy('tma.id')
            ->get([
                'tma.id as tma_id', 'tm.id as tm_id', 'tm.fecha', 'tma.articulo_destino_id',
                'tma.cantidad_origen', 'tma.cantidad_destino', 'tma.coeficienteconversion',
                'tma.precio_costo_origen', 'tma.precio_costo_destino',
                'ad.sku as sku_destino', 'ad.descripcion as desc_destino', 'ao.sku as sku_origen',
            ]);
        $informe['transferencias_auditadas'] = $lineas->count();
        if ($lineas->isEmpty()) {
            return;
        }

        $historiaDesde = Carbon::parse($desde)->subDays((int) ($config['historia_dias'] ?? 180))->toDateString();
        $historia = $base()
            ->whereIn('tma.articulo_destino_id', $lineas->pluck('articulo_destino_id')->unique()->values()->all())
            ->where('tm.fecha', '>=', $historiaDesde.' 00:00:00')
            ->where('tm.fecha', '<=', $hasta.' 23:59:59')
            ->orderByDesc('tm.fecha')
            ->orderByDesc('tma.id')
            ->get(['tma.id as tma_id', 'tm.fecha', 'tma.articulo_destino_id', 'tma.precio_costo_destino'])
            ->groupBy('articulo_destino_id');
        $maxCompras = (int) ($config['historia_compras'] ?? 6);

        foreach ($lineas as $l) {
            $referencias = $historia->get($l->articulo_destino_id, collect())
                ->filter(fn ($h) => (string) $h->fecha < (string) $l->fecha
                    || ((string) $h->fecha === (string) $l->fecha && (int) $h->tma_id < (int) $l->tma_id))
                ->take($maxCompras)
                ->map(fn ($h) => (float) $h->precio_costo_destino)
                ->values()
                ->all();
            $mediana = Reglas::mediana($referencias);
            $desvio = Reglas::evaluarDesvio((float) $l->precio_costo_destino, $mediana, $umbral, (float) $l->coeficienteconversion);
            if ($desvio === null) {
                continue;
            }
            $this->agregar($informe, $skusConDesvio, self::TRA_COSTO_DESTINO, 'Alta', [
                'fecha' => substr((string) $l->fecha, 0, 10),
                'documento' => 'TRA #'.(int) $l->tm_id,
                'proveedor' => '',
                'sku' => (string) $l->sku_destino,
                'descripcion' => (string) $l->desc_destino,
                'url' => null,
                'detalle' => sprintf(
                    '%s %s → %s (coef. %s) · costo destino $ %s vs mediana previa $ %s (×%s).',
                    $this->numero((float) $l->cantidad_origen),
                    (string) $l->sku_origen,
                    $this->numero((float) $l->cantidad_destino),
                    $this->numero((float) $l->coeficienteconversion),
                    $this->numero((float) $l->precio_costo_destino),
                    $this->numero((float) $mediana),
                    $this->numero($desvio['ratio'], 2),
                ),
                'pista' => 'Costo origen $ '.$this->numero((float) $l->precio_costo_origen).' (última compra de '.$l->sku_origen.'). '.$desvio['pista'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $informe
     * @param  array<string, mixed>  $config
     * @param  array<string, true>  $skusConDesvio
     */
    private function auditarRecetas(array &$informe, array $config, array $skusConDesvio): void
    {
        $reporte = $this->recetasService->generar(GastronomiaRecetasReporteFiltros::porDefecto());
        $recetas = $reporte['recetas'] ?? [];
        $informe['recetas_auditadas'] = count($recetas);
        $precioMinimo = (float) ($config['receta_precio_minimo'] ?? 100);
        $excluidos = array_flip(array_map('strval', (array) ($config['receta_codigos_excluidos'] ?? [])));

        $lineasPorFormula = [];
        foreach ($reporte['lineas'] ?? [] as $linea) {
            if (($linea['entra_costo'] ?? '') === 'Sí') {
                $lineasPorFormula[(int) $linea['formula_id']][] = $linea;
            }
        }

        foreach ($recetas as $receta) {
            $codigo = (string) ($receta['codigo'] ?? '');
            if (isset($excluidos[$codigo])
                || ! Reglas::recetaBajoCosto((float) $receta['costo'], (float) $receta['precio'], $precioMinimo)) {
                continue;
            }
            $lineas = $lineasPorFormula[(int) $receta['formula_id']] ?? [];
            usort($lineas, fn ($a, $b) => ((float) $b['costo_linea']) <=> ((float) $a['costo_linea']));
            $principales = array_map(function ($ln) use ($skusConDesvio) {
                $sku = (string) $ln['codigo_linea'];
                $skusCompra = $this->skusCompraDeInsumo($sku);

                return [
                    'sku' => $sku,
                    'descripcion' => (string) $ln['descripcion'],
                    'costo_unitario' => (float) $ln['costo_unitario'],
                    'costo_linea' => (float) $ln['costo_linea'],
                    'skus_compra' => $skusCompra,
                    'con_desvio' => isset($skusConDesvio[$sku])
                        || array_intersect_key($skusConDesvio, array_flip($skusCompra)) !== [],
                ];
            }, array_slice($lineas, 0, 3));

            $informe['recetas'][] = [
                'codigo' => $codigo,
                'nombre' => (string) $receta['nombre'],
                'sku_venta' => (string) ($receta['sku'] ?? ''),
                'costo' => (float) $receta['costo'],
                'precio' => (float) $receta['precio'],
                'margen' => (float) $receta['precio'] - (float) $receta['costo'],
                'insumos' => $principales,
                'con_desvio' => in_array(true, array_column($principales, 'con_desvio'), true),
            ];
        }

        usort($informe['recetas'], fn ($a, $b) => [$b['con_desvio'], $a['margen']] <=> [$a['con_desvio'], $b['margen']]);
    }

    /**
     * Artículos de compra cuyo SKU alternativo apunta al insumo de la receta.
     *
     * @return list<string>
     */
    private function skusCompraDeInsumo(string $skuInsumo): array
    {
        if ($skuInsumo === '') {
            return [];
        }
        $insumo = Articulo::query()->where('sku', $skuInsumo)->first();
        if ($insumo === null) {
            return [];
        }

        return RecepcionProveedorDepositoSupport::resolverArticulosCompraDesdeInsumo($insumo)
            ->pluck('sku')
            ->map(fn ($s) => (string) $s)
            ->values()
            ->all();
    }

    private function excluirTipos(\Illuminate\Database\Query\Builder $query, string $columna): \Illuminate\Database\Query\Builder
    {
        if ($this->tiposExcluidos === []) {
            return $query;
        }

        return $query->where(fn ($q) => $q->whereNull($columna)->orWhereNotIn($columna, $this->tiposExcluidos));
    }

    private function lineasRecepcion(): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('recepcion_proveedor_articulo as rpa')
            ->join('recepcion_proveedor as rp', 'rp.id', '=', 'rpa.recepcion_proveedor_id')
            ->join('articulo as a', 'a.id', '=', 'rpa.articulo_id')
            ->leftJoin('proveedor as p', 'p.id', '=', 'rp.proveedor_id')
            ->where('rp.estado', RecepcionProveedorEstados::CONFIRMADA)
            ->where('rp.tipo', Recepcion_Proveedor::TIPO_RECEPCION);

        return $this->excluirTipos($query, 'a.tipoarticulo_id')
            ->select([
                'rpa.id as rpa_id', 'rp.id as recepcion_id', 'rp.fecha', 'rp.numerorecepcion', 'rp.numerofactura',
                'p.nombre as proveedor', 'rp.proveedor_id', 'rpa.articulo_id', 'a.sku', 'a.descripcion',
                'a.unidadmedida_id as um_articulo', 'a.unidadesxenvase', 'a.coeficienteconversion as coef_articulo',
                'rpa.unidadmedida_id as um_linea', 'rpa.cantidad', 'rpa.cantidad_rechazada', 'rpa.coeficienteconversion',
                'rpa.precio', 'rpa.precio_stock', 'rpa.precio_ordencompra', 'rpa.moneda_id', 'rpa.cotizacion',
            ]);
    }

    /**
     * @param  list<int>  $articuloIds
     * @return array<int, Collection<int, object>>
     */
    private function articulosProveedor(array $articuloIds): array
    {
        return DB::table('articulo_proveedor as ap')
            ->leftJoin('proveedor as p', 'p.id', '=', 'ap.proveedor_id')
            ->whereIn('ap.articulo_id', $articuloIds)
            ->where('ap.activo', true)
            ->get(['ap.id', 'ap.articulo_id', 'ap.proveedor_id', 'p.nombre as proveedor', 'ap.nombre_articulo_proveedor',
                'ap.unidadmedida_compra_id', 'ap.coeficiente_conversion'])
            ->groupBy('articulo_id')
            ->all();
    }

    /**
     * @param  Collection<int, object>  $proveedores
     */
    private function unidadesEnvase(object $linea, Collection $proveedores): ?float
    {
        $candidatos = [(float) ($linea->unidadesxenvase ?? 0), (float) ($linea->coef_articulo ?? 0)];
        foreach ($proveedores as $ap) {
            $candidatos[] = (float) ($ap->coeficiente_conversion ?? 0);
            $candidatos[] = (float) (Reglas::unidadesDesdeDescripcion((string) ($ap->nombre_articulo_proveedor ?? '')) ?? 0);
        }
        $max = max($candidatos);

        return $max > 1 ? $max : null;
    }

    private function unitarioLocal(object $linea): float
    {
        $unitario = Reglas::precioUnitarioStock(
            (float) $linea->precio,
            $linea->precio_stock !== null ? (float) $linea->precio_stock : null,
            (float) ($linea->coeficienteconversion ?? 1),
        );

        return ArticuloPrecioUltimaCompraSupport::precioUnitarioMonedaLocal(
            $unitario,
            $linea->moneda_id !== null ? (int) $linea->moneda_id : null,
            $linea->cotizacion !== null ? (float) $linea->cotizacion : null,
        );
    }

    /**
     * @param  array<string, mixed>  $informe
     * @param  array<string, true>  $skusConDesvio
     * @param  array<string, mixed>  $fila
     */
    private function agregar(array &$informe, array &$skusConDesvio, string $tipo, string $gravedad, array $fila): void
    {
        $informe['hallazgos'][] = ['tipo' => $tipo, 'gravedad' => $gravedad] + $fila;
        if (($fila['sku'] ?? '') !== '') {
            $skusConDesvio[(string) $fila['sku']] = true;
        }
    }

    private function documentoRecepcion(object $l): string
    {
        $factura = trim((string) ($l->numerofactura ?? ''));

        return 'COM '.(int) $l->numerorecepcion.($factura !== '' ? ' · '.$factura : '');
    }

    private function um(int $id): string
    {
        return $id > 0 ? ($this->unidades[$id] ?? '#'.$id) : 's/UM';
    }

    private function valorCampo(string $campo, string $valor): string
    {
        if ($campo === 'unidadmedida_id' && $valor !== '' && ctype_digit($valor)) {
            return $this->um((int) $valor);
        }

        return $valor === '' ? '∅' : $valor;
    }

    private function numero(float $n, int $decimales = 2): string
    {
        return number_format($n, $decimales, ',', '.');
    }

    /**
     * @param  array<string, mixed>  $informe
     * @param  array<string, mixed>  $config
     */
    private function enviarMail(array &$informe, array $config): void
    {
        $destino = trim((string) ($config['email'] ?? ''));
        if ($destino === '') {
            return;
        }
        if (! filter_var($config['mail_siempre'] ?? false, FILTER_VALIDATE_BOOLEAN) && ! $informe['requiere_alerta']) {
            return;
        }

        $maxFilas = max(10, (int) ($config['max_filas_mail'] ?? 80));
        $informeMail = $informe;
        $informeMail['hallazgos'] = array_slice($informe['hallazgos'], 0, $maxFilas);
        $informeMail['hallazgos_truncados'] = count($informe['hallazgos']) > $maxFilas;

        try {
            Mail::to(array_map('trim', explode(',', $destino)))->send(new RecepcionProveedorCostoAuditorMail($informeMail));
            $informe['mail_enviado'] = true;
            $informe['mail_destino'] = $destino;
        } catch (Throwable $e) {
            $informe['mail_error'] = $e->getMessage();
            Log::error('auditor_costos_compra.mail', ['error' => $e->getMessage()]);
        }
    }
}
