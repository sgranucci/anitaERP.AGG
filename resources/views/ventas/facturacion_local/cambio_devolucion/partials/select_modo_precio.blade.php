@php
    use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplacePrecioSupport;
    $etiquetaPrecio = $etiquetaPrecio ?? 'Precio';
    $selectName = array_key_exists('selectName', get_defined_vars()) ? $selectName : 'modo_precio';
    $selectClass = $selectClass ?? '';
    $modoSeleccionado = $modoSeleccionado ?? CambioDevolucionMarketplacePrecioSupport::MODO_NETO;
@endphp
<label class="mb-0 mr-1 small text-nowrap">{{ $etiquetaPrecio }}</label>
<select @if ($selectName !== null && $selectName !== '') name="{{ $selectName }}" @endif
        class="form-control form-control-sm mr-1 {{ $selectClass }}"
        style="min-width:22rem;max-width:36rem" required
        title="{{ $etiquetaPrecio }}">
    @foreach (CambioDevolucionMarketplacePrecioSupport::MODOS as $modoKey => $modoLabel)
        <option value="{{ $modoKey }}" {{ $modoKey === $modoSeleccionado ? 'selected' : '' }}>
            {{ $modoLabel }}
        </option>
    @endforeach
</select>
