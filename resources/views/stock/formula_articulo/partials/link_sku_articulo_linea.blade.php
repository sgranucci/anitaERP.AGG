@php
    $articuloIdLinea = (int) ($articuloId ?? 0);
    $skuLinea = (string) ($sku ?? '');
    $tieneArticuloLinea = $articuloIdLinea > 0 && $skuLinea !== '';
@endphp
<input type="hidden" class="codigoarticulo" name="articulo_skus[]" value="{{ $skuLinea }}" />
<a href="{{ $tieneArticuloLinea ? route('editar_articulo', ['id' => $articuloIdLinea, 'origen' => 'modal_consulta']) : '#' }}"
   class="js-sku-link-articulo-linea text-monospace flex-shrink-0{{ $tieneArticuloLinea ? ' text-primary' : ' text-muted sin-articulo' }}"
   style="width: 18ch; min-width: 18ch; flex: 0 0 18ch; line-height: 2.25rem; box-sizing: border-box;"
   @if($tieneArticuloLinea) target="_blank" rel="noopener" @else tabindex="-1" aria-disabled="true" @endif>{{ $tieneArticuloLinea ? $skuLinea : '—' }}</a>
