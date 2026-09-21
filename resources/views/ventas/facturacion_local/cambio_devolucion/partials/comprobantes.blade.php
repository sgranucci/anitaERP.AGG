@php
    $tiene = isset($data) && $data && ($data->id ?? null);
@endphp
<div class="table-responsive">
    <table class="table table-sm table-bordered">
        <thead style="background:#85C1E9;color:#17202A;">
            <tr>
                <th>Tipo</th>
                <th>Comprobante</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Factura original</td>
                <td>
                    @if ($tiene && $data->venta_original_id)
                        {{ $data->ventaOriginal->codigo ?? ('#'.$data->venta_original_id) }}
                    @else
                        —
                    @endif
                </td>
                <td>
                    @if ($tiene && $data->venta_original_id)
                        <a class="text-primary" target="_blank" rel="noopener"
                           href="{{ url('ventas/listaunafactura/'.$data->venta_original_id) }}">Ver PDF</a>
                    @endif
                </td>
            </tr>
            <tr>
                <td>Factura reemplazo</td>
                <td>
                    @if ($tiene && $data->venta_reemplazo_id)
                        {{ $data->ventaReemplazo->codigo ?? ('#'.$data->venta_reemplazo_id) }}
                    @else
                        —
                    @endif
                </td>
                <td>
                    @if ($tiene && $data->venta_reemplazo_id)
                        <a class="text-primary" target="_blank" rel="noopener"
                           href="{{ url('ventas/listaunafactura/'.$data->venta_reemplazo_id) }}">Ver PDF</a>
                    @endif
                </td>
            </tr>
            <tr>
                <td>Nota de crédito</td>
                <td>
                    @if ($tiene && $data->venta_nc_id)
                        {{ $data->ventaNc->codigo ?? ('#'.$data->venta_nc_id) }}
                    @else
                        —
                    @endif
                </td>
                <td>
                    @if ($tiene && $data->venta_nc_id)
                        <a class="text-primary" target="_blank" rel="noopener"
                           href="{{ url('ventas/listaunafactura/'.$data->venta_nc_id) }}">Ver PDF</a>
                    @endif
                </td>
            </tr>
        </tbody>
    </table>
</div>
