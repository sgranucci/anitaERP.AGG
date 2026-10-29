@php
    use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplacePrecioSupport;
@endphp
<label class="mb-0 mr-1 small text-nowrap">Precio</label>
<select name="modo_precio" class="form-control form-control-sm mr-1" style="max-width:340px" required
        title="Precio de esta emisión">
    @foreach (CambioDevolucionMarketplacePrecioSupport::MODOS as $modoKey => $modoLabel)
        <option value="{{ $modoKey }}" {{ $modoKey === CambioDevolucionMarketplacePrecioSupport::MODO_NETO ? 'selected' : '' }}>
            {{ $modoLabel }}
        </option>
    @endforeach
</select>
