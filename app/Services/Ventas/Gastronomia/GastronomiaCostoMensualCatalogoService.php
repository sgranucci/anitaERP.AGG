<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Seguridad\Usuario;
use App\Models\Stock\Articulo;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Precio;
use App\Services\Stock\FormulaArticuloCostoTotalService;
use App\Services\Stock\FormulaArticuloService;
use App\Support\Stock\PrecioSoloFacturableSupport;
use App\Support\Ventas\GastronomiaSkuCatalogoSupport;
use App\Support\Ventas\Gastronomia\GastronomiaInformeGerenteCostoListaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaStkpreEscrituraSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Calcula el costo mensual de artículos de catálogo (SKU V…) según fórmula + última compra Anita
 * y lo graba en la lista de precios 5000 + mes (ej. junio → 5006) en ERP y Anita (stkpre).
 */
class GastronomiaCostoMensualCatalogoService
{
    public function __construct(
        private readonly FormulaArticuloService $formulaArticuloService,
        private readonly FormulaArticuloCostoTotalService $formulaArticuloCostoTotalService,
        private readonly GastronomiaStkpreEscrituraSupport $stkpreEscritura,
    ) {}

    /**
     * @return array{
     *     fecha_vigencia: string,
     *     listaprecio_id: int,
     *     listaprecio_codigo: string,
     *     mes_label: string,
     *     candidatos: int,
     *     grabados: int,
     *     actualizados: int,
     *     omitidos_duplicado: int,
     *     omitidos_sin_formula: int,
     *     omitidos_costo_incompleto: int,
     *     omitidos_costo_cero: int,
     *     errores: list<string>
     * }
     */
    public function procesar(
        ?Carbon $fechaReferencia = null,
        ?string $skuFiltro = null,
        bool $dryRun = false,
        ?int $usuarioId = null,
        bool $sincronizarAnita = true,
    ): array {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $fecha = ($fechaReferencia ?? Carbon::today())->copy()->startOfDay();
        $listas = GastronomiaInformeGerenteCostoListaSupport::listasDesdeFechaJornada($fecha->toDateString());
        $codigoLista = $listas['lista_actual'];

        $lista = Listaprecio::query()->where('codigo', $codigoLista)->first(['id', 'codigo']);
        if ($lista === null) {
            throw new \RuntimeException(
                "No existe listaprecio con código {$codigoLista} (5000 + mes {$listas['mes_actual']}). "
                .'Sincronice premae desde Anita o cree la lista en el ERP.'
            );
        }

        $usuarioId = max(1, $usuarioId ?? (int) config('gastronomia.costo_mensual_catalogo.usuario_id', 1));
        $usuarioNombre = (string) (Usuario::query()->whereKey($usuarioId)->value('nombre') ?? 'sistema');

        $ret = [
            'fecha_vigencia' => $fecha->toDateString(),
            'listaprecio_id' => (int) $lista->id,
            'listaprecio_codigo' => (string) $lista->codigo,
            'mes_label' => $listas['mes_actual_label'],
            'candidatos' => 0,
            'grabados' => 0,
            'actualizados' => 0,
            'omitidos_duplicado' => 0,
            'omitidos_sin_formula' => 0,
            'omitidos_costo_incompleto' => 0,
            'omitidos_costo_cero' => 0,
            'errores' => [],
        ];

        $articulos = $this->articulosCatalogo($skuFiltro);
        $ret['candidatos'] = count($articulos);

        if ($articulos === [] || $dryRun) {
            return $ret;
        }

        $monedaId = max(1, (int) config('gastronomia.costo_mensual_catalogo.moneda_id', 1));
        $sincronizarAnita = $sincronizarAnita && (bool) config('gastronomia.costo_mensual_catalogo.sincronizar_anita', true);

        foreach ($articulos as $articulo) {
            try {
                $estado = $this->procesarArticulo(
                    $articulo,
                    (int) $lista->id,
                    (string) $lista->codigo,
                    $fecha,
                    $usuarioId,
                    $usuarioNombre,
                    $monedaId,
                    $sincronizarAnita,
                );

                match ($estado) {
                    'grabado' => $ret['grabados']++,
                    'actualizado' => $ret['actualizados']++,
                    'duplicado' => $ret['omitidos_duplicado']++,
                    'sin_formula' => $ret['omitidos_sin_formula']++,
                    'costo_incompleto' => $ret['omitidos_costo_incompleto']++,
                    'costo_cero' => $ret['omitidos_costo_cero']++,
                    default => null,
                };
            } catch (\Throwable $e) {
                $msg = 'SKU '.$articulo['sku'].': '.$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('GastronomiaCostoMensualCatalogo: '.$msg, ['exception' => $e]);
            }
        }

        return $ret;
    }

    /**
     * @return list<array{id: int, sku: string}>
     */
    private function articulosCatalogo(?string $skuFiltro): array
    {
        $query = Articulo::query()
            ->select(['id', 'sku'])
            ->where('nofactura', PrecioSoloFacturableSupport::NOFACTURA_FACTURABLE);

        GastronomiaSkuCatalogoSupport::aplicarScopeFormatoCatalogo($query);

        $skuFiltro = trim((string) $skuFiltro);
        if ($skuFiltro !== '') {
            $query->whereRaw('UPPER(sku) = ?', [mb_strtoupper($skuFiltro, 'UTF-8')]);
        }

        $out = [];
        foreach ($query->orderBy('sku')->cursor() as $articulo) {
            $sku = trim((string) $articulo->sku);
            if ($sku === '') {
                continue;
            }
            $out[] = ['id' => (int) $articulo->id, 'sku' => $sku];
        }

        return $out;
    }

    /**
     * @param  array{id: int, sku: string}  $articulo
     * @return 'grabado'|'actualizado'|'duplicado'|'sin_formula'|'costo_incompleto'|'costo_cero'
     */
    private function procesarArticulo(
        array $articulo,
        int $listaprecioId,
        string $codigoLista,
        Carbon $fechaVigencia,
        int $usuarioId,
        string $usuarioNombre,
        int $monedaId,
        bool $sincronizarAnita,
    ): string {
        $resolucion = $this->formulaArticuloService->resolverIdParaArticulo($articulo['id']);
        $formulaId = (int) ($resolucion['formula_id'] ?? 0);
        if ($formulaId <= 0) {
            return 'sin_formula';
        }

        $costo = $this->formulaArticuloCostoTotalService->calcular($formulaId);
        if (! $costo->completo) {
            return 'costo_incompleto';
        }

        $precioCosto = round($costo->total, 4);
        if ($precioCosto <= 0) {
            return 'costo_cero';
        }

        $fechaStr = $fechaVigencia->toDateString();
        $existente = Precio::query()
            ->where('articulo_id', $articulo['id'])
            ->where('listaprecio_id', $listaprecioId)
            ->whereDate('fechavigencia', $fechaStr)
            ->orderByDesc('id')
            ->first();

        if ($existente !== null && abs((float) $existente->precio - $precioCosto) < 0.0001) {
            return 'duplicado';
        }

        $precioAnterior = $existente !== null
            ? (float) $existente->precio
            : $this->precioAnteriorDesdeLista($articulo['id'], $listaprecioId, $fechaStr);

        if ($existente !== null) {
            $existente->update([
                'precio' => $precioCosto,
                'precioanterior' => $precioAnterior,
                'moneda_id' => $monedaId,
                'usuarioultcambio_id' => $usuarioId,
            ]);
            $estadoErp = 'actualizado';
        } else {
            Precio::create([
                'articulo_id' => $articulo['id'],
                'listaprecio_id' => $listaprecioId,
                'fechavigencia' => $fechaStr,
                'moneda_id' => $monedaId,
                'precio' => $precioCosto,
                'precioanterior' => $precioAnterior,
                'usuarioultcambio_id' => $usuarioId,
            ]);
            $estadoErp = 'grabado';
        }

        if ($sincronizarAnita) {
            $this->stkpreEscritura->upsertPrecio(
                $articulo['sku'],
                $codigoLista,
                $precioCosto,
                $precioAnterior > 0 ? $precioAnterior : null,
                $monedaId,
                $fechaStr,
                $usuarioNombre,
            );
        }

        return $estadoErp;
    }

    private function precioAnteriorDesdeLista(int $articuloId, int $listaprecioId, string $fechaVigencia): float
    {
        $anterior = Precio::query()
            ->where('articulo_id', $articuloId)
            ->where('listaprecio_id', $listaprecioId)
            ->whereDate('fechavigencia', '<', $fechaVigencia)
            ->orderByDesc('fechavigencia')
            ->orderByDesc('id')
            ->value('precio');

        return $anterior !== null ? (float) $anterior : 0.0;
    }
}
