@php
    use App\Support\Stock\ArticuloConsultaDesdeModal;

    $esEdicion = $esEdicion ?? !empty($precio?->id);
    $articuloIdValor = old('articulo_id', $precio->articulo_id ?? '');
    $articuloSku = trim((string) ($precio?->articulos?->sku ?? ''));
    $articuloDescripcion = trim((string) ($precio?->articulos?->descripcion ?? ''));
    if ($articuloDescripcion === '') {
        $articuloDescripcion = trim((string) ($precio?->articulos?->detalle ?? ''));
    }
    if ($articuloIdValor && ($articuloSku === '' || $articuloDescripcion === '')) {
        $articuloResuelto = \App\Models\Stock\Articulo::query()
            ->select('id', 'sku', 'descripcion', 'detalle')
            ->find($articuloIdValor);
        if ($articuloResuelto) {
            if ($articuloSku === '') {
                $articuloSku = trim((string) $articuloResuelto->sku);
            }
            if ($articuloDescripcion === '') {
                $articuloDescripcion = trim((string) $articuloResuelto->descripcion);
                if ($articuloDescripcion === '') {
                    $articuloDescripcion = trim((string) $articuloResuelto->detalle);
                }
            }
        }
    }
    if ($articuloDescripcion === '' && $articuloSku !== '') {
        $articuloDescripcion = $articuloSku;
    }
    $puedeConsultarArticulo = ArticuloConsultaDesdeModal::puedeConsultar();
    $urlConsultaArticulo = ((int) $articuloIdValor > 0 && $puedeConsultarArticulo)
        ? ArticuloConsultaDesdeModal::urlEditar((int) $articuloIdValor)
        : '#';
@endphp
<div class="form-group row tm-articulo-campo">
    <label for="codigoarticulo" class="col-lg-3 col-form-label requerido">Art&iacute;culo</label>
    <div class="col-lg-8">
        @if ($esEdicion)
            <input type="hidden" name="articulo_id" id="articulo_id" class="articulo_id" value="{{ $articuloIdValor }}">
            <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
                @if ($puedeConsultarArticulo)
                    <a href="{{ $urlConsultaArticulo }}" target="_blank" rel="noopener"
                        class="btn-accion-tabla btn-link-articulo tooltipsC flex-shrink-0"
                        title="Consultar art&iacute;culo en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" id="codigoarticulo" class="form-control codigoarticulo bg-light flex-shrink-0"
                    value="{{ $articuloSku }}" readonly tabindex="-1" title="SKU" style="width: 5.5rem;">
                <input type="text" id="descripcionarticulo" class="form-control descripcionarticulo bg-light"
                    value="{{ $articuloDescripcion }}" readonly tabindex="-1" style="min-width: 0; flex: 1 1 auto;">
            </div>
        @else
            <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
                <input type="hidden" name="articulo_id" id="articulo_id" class="articulo_id"
                    value="{{ $articuloIdValor }}" required>
                <button type="button" title="Consulta art&iacute;culos" class="btn-accion-tabla consultaarticulo tooltipsC flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if ($puedeConsultarArticulo)
                    <a href="{{ $urlConsultaArticulo }}" target="_blank" rel="noopener"
                        class="btn-accion-tabla btn-link-articulo tooltipsC flex-shrink-0 {{ (int) $articuloIdValor > 0 ? '' : 'd-none' }}"
                        title="Consultar art&iacute;culo en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="form-control codigoarticulo flex-shrink-0" id="codigoarticulo"
                    value="{{ $articuloSku }}" placeholder="SKU" autocomplete="off" style="width: 5.5rem;">
                <input type="text" class="form-control descripcionarticulo" id="descripcionarticulo"
                    value="{{ $articuloDescripcion }}" placeholder="Descripci&oacute;n" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            </div>
        @endif
    </div>
</div>
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
