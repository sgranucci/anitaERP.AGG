@php
    $visualizar = ! empty($visualizar);
    $lineasOld = old('articulo_ids');
    if (is_array($lineasOld)) {
        $lineas = collect();
        $n = count($lineasOld);
        for ($i = 0; $i < $n; $i++) {
            $lineas->push((object) [
                'id' => old('linea_ids.'.$i),
                'articulo_id' => old('articulo_ids.'.$i),
                'sku' => old('codigoarticulos.'.$i),
                'descripcion' => old('descripcionarticulos.'.$i),
                'precio' => old('precios.'.$i),
                'descuento' => old('descuentos.'.$i),
                'codigo_articulo_proveedor' => old('codigos_articulo_proveedor.'.$i),
                'fechavigencia' => old('fechavigencias.'.$i),
            ]);
        }
        if ($lineas->isEmpty()) {
            $lineas = collect([(object) []]);
        }
    } else {
        $lineas = (isset($data) && $data && $data->listaprecio_proveedor_articulos && $data->listaprecio_proveedor_articulos->count())
            ? $data->listaprecio_proveedor_articulos
            : collect([new \App\Models\Compras\Listaprecio_Proveedor_Articulo()]);
    }
@endphp
@include('compras.listaprecio_proveedor.partials.card_importar_excel')

<div class="card card-outline card-info mb-3">
    <div class="card-header py-2">
        <h3 class="card-title mb-0"><i class="fa fa-list"></i> Precios por art&iacute;culo</h3>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Puede repetir el mismo art&iacute;culo con distinta fecha de vigencia (hist&oacute;rico de precios).
            F1 o la lupa resuelven el SKU. Si importa un Excel, no hace falta cargar renglones a mano.
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered lp-grilla-articulos mb-2" id="tabla-articulos-listaprecio">
                <thead>
                    <tr>
                        <th style="width: 18%;">Art&iacute;culo</th>
                        <th>Descripci&oacute;n</th>
                        <th style="width: 12%;">Precio</th>
                        <th style="width: 8%;">% Desc.</th>
                        <th style="width: 14%;">C&oacute;d. art. proveedor</th>
                        <th style="width: 12%;">Fecha vigencia</th>
                        @if (! $visualizar)
                        <th style="width: 6%;" class="text-center">Acciones</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lineas as $idx => $linea)
                        @include('compras.listaprecio_proveedor.partials.renglon_articulo', [
                            'linea' => $linea,
                            'idx' => $idx,
                            'visualizar' => $visualizar,
                        ])
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (! $visualizar)
            @include('compras.listaprecio_proveedor.partials.template_renglon_articulo')
            <div class="text-right">
                <button type="button" class="btn btn-outline-primary btn-sm" id="agrega_renglon_listaprecio_articulo">
                    <i class="fa fa-plus"></i> Agregar rengl&oacute;n
                </button>
            </div>
        @endif
    </div>
</div>
