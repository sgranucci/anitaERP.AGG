@php
    $oc = $data->ordencompras ?? null;
    $puedeVerOc = can('editar-ordencompra', false) || can('listar-ordencompra', false);
    $ccDestId = \App\Support\Compras\ComprobanteProveedorCentrocostoSupport::resolverDesdeOc($oc);
    $ccDest = null;
    if ($oc) {
        foreach ($oc->ordencompra_articulos ?? [] as $lineaOcCc) {
            if ((int) ($lineaOcCc->centrocostodestino_id ?? 0) === $ccDestId && $lineaOcCc->centrocostos_destino) {
                $ccDest = $lineaOcCc->centrocostos_destino;
                break;
            }
        }
        if (! $ccDest && $oc->centrocostos && (int) ($oc->centrocosto_id ?? 0) === $ccDestId) {
            $ccDest = $oc->centrocostos;
        }
    }
    $lineasOc = $oc->ordencompra_articulos ?? collect();
@endphp
<div id="cp-bloque-ordencompra" class="mt-2">
    <div class="alert alert-light border mb-3">
        <div class="d-flex flex-wrap align-items-start justify-content-between" style="gap:10px;">
            <div>
                <h5 class="mb-1">
                    <i class="fa fa-file-text-o"></i>
                    Orden de compra
                    @if ($oc)
                        <strong>#{{ $oc->numeroordencompra }}</strong>
                    @endif
                </h5>
                @if ($oc)
                <div class="small text-muted">
                    Fecha: {{ $oc->fecha ? \Illuminate\Support\Carbon::parse($oc->fecha)->format('d/m/Y') : '—' }}
                    · CC destino:
                    <strong>
                        @if ($ccDest)
                            {{ $ccDest->codigo }} {{ $ccDest->nombre }}
                        @elseif ($ccDestId > 0)
                            #{{ $ccDestId }}
                        @else
                            —
                        @endif
                    </strong>
                </div>
                @endif
            </div>
            @if ($oc && $puedeVerOc)
            <a href="{{ route('editar_ordencompra', ['id' => $oc->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
               class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener"
               title="Consultar la orden de compra">
                <i class="fa fa-external-link"></i> Abrir OC
            </a>
            @endif
        </div>
    </div>

    @if (! $oc)
        <p class="text-muted mb-0">Este comprobante no tiene orden de compra asociada.</p>
    @elseif ($lineasOc->isEmpty())
        <p class="text-muted mb-0">La OC no tiene renglones de artículos.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" style="table-layout:fixed;width:100%;">
                <colgroup>
                    <col style="width:34%;">
                    <col style="width:10%;">
                    <col style="width:14%;">
                    <col style="width:14%;">
                    <col style="width:28%;">
                </colgroup>
                <thead style="background-color:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Artículo</th>
                        <th class="text-right">Cantidad</th>
                        <th class="text-right">Precio</th>
                        <th class="text-right">Importe</th>
                        <th>CC destino</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lineasOc as $linea)
                        @php
                            $cant = (float) ($linea->cantidad ?? 0);
                            $precio = (float) ($linea->precio ?? 0);
                            $sku = $linea->articulos->sku ?? '';
                            $nombreArt = $linea->articulos->descripcion ?? ($linea->detalle ?? '—');
                            $ccLin = $linea->centrocostos_destino;
                        @endphp
                        <tr>
                            <td class="align-middle"><small>{{ trim($sku.' '.$nombreArt) }}</small></td>
                            <td class="text-right align-middle"><small>{{ number_format($cant, 3, ',', '.') }}</small></td>
                            <td class="text-right align-middle"><small>{{ number_format($precio, 4, ',', '.') }}</small></td>
                            <td class="text-right align-middle"><small>{{ number_format($cant * $precio, 2, ',', '.') }}</small></td>
                            <td class="align-middle">
                                <small>
                                    @if ($ccLin)
                                        {{ $ccLin->codigo }} {{ $ccLin->nombre }}
                                    @else
                                        —
                                    @endif
                                </small>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
