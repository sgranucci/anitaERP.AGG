@php
    use App\Models\Ventas\CambioDevolucionMarketplaceComprobante;
    $tiene = isset($data) && $data && ($data->id ?? null);
    $comprobantes = ($tiene && $data->relationLoaded('comprobantes')) ? $data->comprobantes : collect();
    $vigenteReemplazo = (int) ($data->venta_reemplazo_id ?? 0);
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
            @if ($comprobantes->isNotEmpty())
                @php
                    $idsListados = $comprobantes->pluck('venta_id')->map(static fn ($id): int => (int) $id)->all();
                @endphp
                @foreach ($comprobantes as $comp)
                    @php
                        $ventaComp = $comp->venta;
                        $codigoComp = $ventaComp->codigo ?? ('#'.$comp->venta_id);
                        $vigente = (string) $comp->tipo === CambioDevolucionMarketplaceComprobante::TIPO_FAC_REEMPLAZO
                            && (int) $comp->venta_id === $vigenteReemplazo;
                    @endphp
                    <tr>
                        <td>
                            {{ $comp->etiqueta() }}
                            @if ($vigente)
                                <span class="badge badge-success">vigente</span>
                            @endif
                        </td>
                        <td>{{ $codigoComp }}</td>
                        <td>
                            <a class="text-primary" target="_blank" rel="noopener"
                               href="{{ url('ventas/listaunafactura/'.$comp->venta_id) }}">Ver PDF</a>
                        </td>
                    </tr>
                @endforeach
                @if ($tiene && $data->venta_reemplazo_id && ! in_array((int) $data->venta_reemplazo_id, $idsListados, true))
                    <tr>
                        <td>Factura reemplazo <span class="badge badge-success">vigente</span></td>
                        <td>{{ $data->ventaReemplazo->codigo ?? ('#'.$data->venta_reemplazo_id) }}</td>
                        <td>
                            <a class="text-primary" target="_blank" rel="noopener"
                               href="{{ url('ventas/listaunafactura/'.$data->venta_reemplazo_id) }}">Ver PDF</a>
                        </td>
                    </tr>
                @endif
                @if ($tiene && $data->venta_nc_id && ! in_array((int) $data->venta_nc_id, $idsListados, true))
                    <tr>
                        <td>Nota de crédito de lo devuelto</td>
                        <td>{{ $data->ventaNc->codigo ?? ('#'.$data->venta_nc_id) }}</td>
                        <td>
                            <a class="text-primary" target="_blank" rel="noopener"
                               href="{{ url('ventas/listaunafactura/'.$data->venta_nc_id) }}">Ver PDF</a>
                        </td>
                    </tr>
                @endif
            @else
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
            @endif
            <tr>
                <td>OPP reembolso</td>
                <td>
                    @if ($tiene && $data->caja_movimiento_id)
                        @php
                            $opp = $data->relationLoaded('cajaMovimiento') ? $data->cajaMovimiento : null;
                            $oppNro = $opp->numerotransaccion ?? ('#'.$data->caja_movimiento_id);
                            $oppTipo = $opp->tipotransaccioncajas->abreviatura ?? 'OPP';
                        @endphp
                        {{ $oppTipo }} {{ $oppNro }}
                    @else
                        —
                    @endif
                </td>
                <td>
                    @if ($tiene && $data->caja_movimiento_id)
                        <a class="text-primary" target="_blank" rel="noopener"
                           href="{{ route('imprimir_opp_cambio_devolucion_marketplace', $data->id) }}">Ver PDF</a>
                    @endif
                </td>
            </tr>
        </tbody>
    </table>
</div>
