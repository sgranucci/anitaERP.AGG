@php
    $skuTxt = (string) ($sku ?? '');
    $articuloId = (int) ($articuloId ?? 0);
@endphp
@if ($skuTxt !== '' && $skuTxt !== '—' && $articuloId > 0 && can('editar-articulos', false))
    <a href="{{ route('editar_articulo', ['id' => $articuloId, 'origen' => 'modal_consulta']) }}"
       target="_blank"
       rel="noopener"
       class="text-primary"
       title="Consultar artículo">{{ $skuTxt }}</a>
@else
    {{ $skuTxt !== '' ? $skuTxt : '—' }}
@endif
