@if (!empty($ordenes_compra_vinculadas))
<p class="text-muted small mb-2" id="requisicion-ordenes-compra-texto">
    <i class="fa fa-shopping-cart mr-1" aria-hidden="true"></i>
    {{ count($ordenes_compra_vinculadas) === 1 ? 'Orden de compra generada' : 'Órdenes de compra generadas' }}:
    @foreach ($ordenes_compra_vinculadas as $oc)
        @if (!empty($oc['url_ver']))
        <a href="{{ $oc['url_ver'] }}" class="text-primary" target="_blank" rel="noopener noreferrer" title="Consultar OC {{ $oc['numero'] ?? $oc['id'] }}">{{ $oc['numero'] ?? $oc['id'] }}</a>
        @elseif (!empty($oc['url_editar']))
        <a href="{{ $oc['url_editar'] }}" class="text-primary" target="_blank" rel="noopener noreferrer" title="Editar OC {{ $oc['numero'] ?? $oc['id'] }}">{{ $oc['numero'] ?? $oc['id'] }}</a>
        @else
        <span>{{ $oc['numero'] ?? $oc['id'] }}</span>
        @endif
        @if (!$loop->last)<span class="text-muted">,</span> @endif
    @endforeach
    @if (!empty($requisicion_lineas_pendientes_oc))
    <span class="text-muted"> — {{ $requisicion_lineas_pendientes_oc }} {{ $requisicion_lineas_pendientes_oc === 1 ? 'ítem pendiente' : 'ítems pendientes' }} de OC.</span>
    @endif
</p>
@endif
