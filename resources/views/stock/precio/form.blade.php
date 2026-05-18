@php
    $esEdicion = $esEdicion ?? !empty($precio?->id);
    $articuloSku = trim((string) ($precio?->articulos?->sku ?? ''));
    $articuloDescripcion = trim((string) ($precio?->articulos?->descripcion ?? ''));
    if ($articuloDescripcion === '') {
        $articuloDescripcion = trim((string) ($precio?->articulos?->detalle ?? ''));
    }
    if ($articuloDescripcion === '' && $articuloSku !== '') {
        $articuloDescripcion = $articuloSku;
    }
@endphp
<div class="form-group row">
    <label for="articulo_id" class="col-lg-3 col-form-label requerido">Art&iacute;culo</label>
    <div class="col-lg-8">
        @if ($esEdicion)
            <input type="hidden" name="articulo_id" value="{{ old('articulo_id', $precio->articulo_id) }}">
            <input type="text" id="articulo_sku" class="form-control bg-light" value="{{ $articuloSku }}" readonly tabindex="-1" title="SKU" />
        @else
            <select name="articulo_id" id="articulo_id" class="form-control" required>
                <option value="">Seleccione el art&iacute;culo</option>
                @foreach($articulo_query as $id => $articulo)
                    @if (old('articulo_id', $precio->articulo_id ?? '') == $articulo->id)
                        <option value="{{ $articulo->id }}" selected>{{ $articulo->descripcion }} - {{ $articulo->sku }}</option>
                    @else
                        <option value="{{ $articulo->id }}">{{ $articulo->descripcion }} - {{ $articulo->sku }}</option>
                    @endif
                @endforeach
            </select>
        @endif
    </div>
</div>
@if ($esEdicion)
<div class="form-group row">
    <label for="articulo_descripcion" class="col-lg-3 col-form-label">Descripci&oacute;n</label>
    <div class="col-lg-8">
        <input type="text" id="articulo_descripcion" class="form-control bg-light" value="{{ $articuloDescripcion }}" readonly tabindex="-1" />
    </div>
</div>
@endif
<div class="form-group row">
    <label for="listaprecio_id" class="col-lg-3 col-form-label requerido">Lista de precios</label>
    <div class="col-lg-8">
        <select name="listaprecio_id" id="listaprecio_id" class="form-control">
            <option value="">-- Elija lista de precios --</option>
            @foreach ($listaprecio_query as $listaprecio)
                <option value="{{ $listaprecio->id }}"
                    @if (old('listaprecio_id', $precio->listaprecios->id ?? '') == $listaprecio->id) selected @endif
                    >{{ $listaprecio->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="fechavigencia" class="col-lg-3 col-form-label requerido">Fecha de vigencia</label>
    <div class="col-lg-8">
        <input type="text" name="fechavigencia" id="fechavigencia" class="form-control" value="{{ old('fechavigencia', !empty($precio?->fechavigencia) ? \Carbon\Carbon::parse($precio->fechavigencia)->format('d-m-Y') : date('d-m-Y')) }}" required/>
        @if ($esEdicion ?? false)
            <small class="form-text text-muted">Si modifica el precio sin cambiar la fecha de vigencia se actualiza este registro. Si cambia la fecha de vigencia se crea un registro nuevo y el anterior permanece en el historial.</small>
        @endif
    </div>
</div>
<div class="form-group row">
    <label for="moneda_id" class="col-lg-3 col-form-label requerido">Moneda</label>
    <div class="col-lg-8">
        <select name="moneda_id" id="moneda_id" class="form-control">
            <option value="">-- Elija moneda --</option>
            @foreach ($moneda_query as $moneda)
                <option value="{{ $moneda->id }}"
                    @if (old('moneda_id', $precio->monedas->id ?? '') == $moneda->id) selected @endif
                    >{{ $moneda->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="precio" class="col-lg-3 col-form-label requerido">Precio</label>
    <div class="col-lg-8">
        <input type="number" name="precio" id="precio" class="form-control" value="{{ old('precio', $precio->precio ?? '') }}" required/>
    </div>
</div>
