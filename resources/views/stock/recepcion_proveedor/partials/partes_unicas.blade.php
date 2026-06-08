@if($recepcion->estado !== 'BORRADOR' && $recepcion->recepcion_proveedor_partes_unicas->isNotEmpty())
<div class="card card-outline card-info mt-3">
    <div class="card-header">
        <h3 class="card-title"><i class="fa fa-barcode"></i> Números de parte única (NPU)</h3>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped mb-0">
            <thead>
                <tr>
                    <th>Línea</th>
                    <th>SKU</th>
                    <th>Nº parte</th>
                </tr>
            </thead>
            <tbody>
            @foreach($recepcion->recepcion_proveedor_partes_unicas->sortBy(fn ($p) => [$p->recepcion_proveedor_articulo_id, $p->numeroparte]) as $parte)
                @php $linea = $parte->recepcion_proveedor_articulos; @endphp
                <tr>
                    <td>{{ $linea->orden ?? $linea->penvp_orden ?? '—' }}</td>
                    <td>{{ optional($linea->articulos)->sku ?? '—' }}</td>
                    <td><strong>{{ $parte->numeroparte }}</strong></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
