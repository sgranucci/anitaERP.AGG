@php
    use App\Support\Stock\ArticuloMarketplaceGrillaSupport;
    $uiMarketplace = ArticuloMarketplaceGrillaSupport::uiActiva();
    $lineasMarketplace = $articuloMarketplaceLineas ?? collect();
    $tieneCanalLocal = ArticuloMarketplaceGrillaSupport::articuloTieneCanalLocal($producto ?? null);
    $oldIds = old('am_marketplace_id');
    $filasMarketplace = [];
    if (is_array($oldIds)) {
        $n = count($oldIds);
        for ($i = 0; $i < $n; $i++) {
            $filasMarketplace[] = [
                'id' => old('am_id.'.$i, ''),
                'marketplace_id' => old('am_marketplace_id.'.$i, ''),
                'codigo' => old('am_marketplace_codigo.'.$i, ''),
                'nombre' => old('am_marketplace_nombre.'.$i, ''),
                'orden' => old('am_orden.'.$i, 0),
                'combinacion_id' => old('am_combinacion_id.'.$i, ''),
                'combinacion_codigo' => old('am_combinacion_codigo.'.$i, ''),
                'combinacion_nombre' => old('am_combinacion_nombre.'.$i, ''),
            ];
        }
    } else {
        foreach ($lineasMarketplace as $linea) {
            $filasMarketplace[] = [
                'id' => $linea->id,
                'marketplace_id' => $linea->marketplace_id,
                'codigo' => $linea->marketplace->codigo ?? '',
                'nombre' => $linea->marketplace->nombre ?? '',
                'orden' => $linea->orden,
                'combinacion_id' => $linea->combinacion_id,
                'combinacion_codigo' => $linea->combinacion->codigo ?? $linea->codigo_combinacion,
                'combinacion_nombre' => $linea->combinacion->nombre ?? '',
            ];
        }
    }
@endphp
@if ($uiMarketplace)
<div id="tab10" class="card form10 tab-content" style="display: none">
    <div class="card-body">
        <p class="text-muted small mb-2">
            Marketplaces en los que se publica este artículo del canal Local.
            Cada fila es un marketplace y, si corresponde, una combinación.
            Estos datos quedan en anitaERP.
        </p>
        @php
            $fieldsetMarketplaceActivo = $tieneCanalLocal || is_array($oldIds);
        @endphp
        <fieldset id="fieldset-articulo-marketplace" @disabled(! $fieldsetMarketplaceActivo)>
            <input type="hidden" name="articulo_marketplace_sync" value="1">
            <div class="table-responsive">
                <table class="table table-sm table-bordered" id="tabla-articulo-marketplace">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th style="min-width: 22rem;">Marketplace</th>
                            <th style="width: 7rem;">Orden</th>
                            <th style="min-width: 22rem;">Combinación</th>
                            <th style="width: 3rem;"></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-articulo-marketplace">
                        @foreach ($filasMarketplace as $fila)
                            @include('stock.articulo.partials.fila_marketplace', ['fila' => $fila])
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-agregar-marketplace-articulo">
                <i class="fa fa-plus"></i> Agregar marketplace
            </button>
        </fieldset>
    </div>
</div>
<template id="template-articulo-marketplace">
    @include('stock.articulo.partials.fila_marketplace', ['fila' => []])
</template>
@endif
