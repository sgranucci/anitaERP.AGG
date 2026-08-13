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

    $listaIdValor = old('listaprecio_id', $precio->listaprecio_id ?? optional($precio->listaprecios)->id ?? '');
    $listaCodigo = trim((string) old('codigolistaprecio', optional($precio->listaprecios)->codigo ?? ''));
    $listaNombre = trim((string) old('nombrelistaprecio', optional($precio->listaprecios)->nombre ?? ''));
    if ((int) $listaIdValor > 0 && ($listaCodigo === '' || $listaNombre === '')) {
        $listaResuelta = \App\Models\Stock\Listaprecio::query()
            ->select('id', 'codigo', 'nombre')
            ->find($listaIdValor);
        if ($listaResuelta) {
            if ($listaCodigo === '') {
                $listaCodigo = trim((string) $listaResuelta->codigo);
            }
            if ($listaNombre === '') {
                $listaNombre = trim((string) $listaResuelta->nombre);
            }
        }
    }

    $fechaVigenciaRaw = old('fechavigencia', !empty($precio?->fechavigencia)
        ? $precio->fechavigencia
        : date('Y-m-d'));
    try {
        $fechaVigenciaValor = \Carbon\Carbon::parse($fechaVigenciaRaw)->format('Y-m-d');
    } catch (\Throwable $e) {
        try {
            $fechaVigenciaValor = \Carbon\Carbon::createFromFormat('d-m-Y', (string) $fechaVigenciaRaw)->format('Y-m-d');
        } catch (\Throwable $e2) {
            $fechaVigenciaValor = date('Y-m-d');
        }
    }

    $puedeAbrirAbmLista = can('editar-listaprecio', false) || can('listar-listaprecio', false);
    $urlConsultaLista = ((int) $listaIdValor > 0 && $puedeAbrirAbmLista)
        ? route('editar_listaprecio', ['id' => (int) $listaIdValor, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp
<div class="form-group row tm-articulo-campo">
    <label for="codigoarticulo" class="col-lg-3 control-label text-right pr-2 requerido">Art&iacute;culo</label>
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
<div class="form-group row tm-listaprecio-campo">
    <label for="codigolistaprecio" class="col-lg-3 control-label text-right pr-2 requerido">Lista de precios</label>
    <div class="col-lg-8">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="listaprecio_id" id="listaprecio_id" class="listaprecio_id"
                value="{{ $listaIdValor }}" required>
            <button type="button" title="Consulta listas de precios (F1)" class="btn-accion-tabla consultalistaprecio tooltipsC flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeAbrirAbmLista)
                <a href="{{ $urlConsultaLista }}" target="_blank" rel="noopener"
                    class="btn-accion-tabla btn-link-editar-listaprecio tooltipsC flex-shrink-0 {{ (int) $listaIdValor > 0 ? '' : 'd-none' }}"
                    title="Consultar lista de precios en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="form-control codigolistaprecio flex-shrink-0" id="codigolistaprecio"
                value="{{ $listaCodigo }}" placeholder="C&oacute;d." autocomplete="off" style="width: 5.5rem;"
                title="C&oacute;digo de lista (Enter / F1)">
            <input type="text" class="form-control nombrelistaprecio" id="nombrelistaprecio"
                value="{{ $listaNombre }}" placeholder="Descripci&oacute;n" readonly
                style="min-width: 0; flex: 1 1 auto;">
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="fechavigencia" class="col-lg-3 control-label text-right pr-2 requerido">Fecha de vigencia</label>
    <div class="col-lg-8">
        <input type="date" name="fechavigencia" id="fechavigencia" class="form-control"
            value="{{ $fechaVigenciaValor }}" required>
        @if ($esEdicion ?? false)
            <small class="form-text text-muted">Si modifica el precio sin cambiar la fecha de vigencia se actualiza este registro. Si cambia la fecha de vigencia se crea un registro nuevo y el anterior permanece en el historial.</small>
        @endif
    </div>
</div>
<div class="form-group row">
    <label for="moneda_id" class="col-lg-3 control-label text-right pr-2 requerido">Moneda</label>
    <div class="col-lg-8">
        <select name="moneda_id" id="moneda_id" class="form-control" required>
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
    <label for="precio" class="col-lg-3 control-label text-right pr-2 requerido">Precio</label>
    <div class="col-lg-8">
        <input type="number" name="precio" id="precio" class="form-control" step="any" min="0"
            value="{{ old('precio', $precio->precio ?? '') }}" required>
        <small class="form-text text-muted">Hasta 6 decimales (p. ej. impuesto interno 0,7234).</small>
    </div>
</div>
