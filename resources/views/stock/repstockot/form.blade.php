@php
    $colLabel = 'col-lg-3 control-label text-right pr-2';
    $colInput = 'col-lg-6';
    $colLabelDep = 'col-lg-3';
    $f = $filtros ?? [];
    $et = $etiquetas ?? [];
    $marcaId = (int) ($f['mventa_id'] ?? 0);
    $ambitoActivo = $f['ambito_deposito'] ?? 'TODOS';
@endphp

<div class="form-group row">
    <label class="{{ $colLabel }}" id="repstockot-marcas-label">Marca</label>
    <div class="col-lg-8">
        <input type="hidden" name="mventa_id" id="mventa_id" value="{{ $marcaId > 0 ? $marcaId : '' }}">
        <div class="repstockot-fichas" id="repstockot-marcas" role="radiogroup" aria-labelledby="repstockot-marcas-label">
            <button type="button" class="repstockot-ficha repstockot-marca {{ $marcaId <= 0 ? 'is-activa' : '' }}" data-id="" aria-pressed="{{ $marcaId <= 0 ? 'true' : 'false' }}" @if($marcaId > 0) tabindex="-1" @endif>Todas</button>
            @foreach ($marcas as $marca)
                <button type="button" class="repstockot-ficha repstockot-marca {{ (int) $marca->id === $marcaId ? 'is-activa' : '' }}" data-id="{{ $marca->id }}" aria-pressed="{{ (int) $marca->id === $marcaId ? 'true' : 'false' }}" @if((int) $marca->id !== $marcaId) tabindex="-1" @endif>
                    {{ $marca->nombre }}
                </button>
            @endforeach
        </div>
        <small class="form-text text-muted">Clic elige la marca. Flechas cambian la ficha. Enter pasa al grupo de depósitos.</small>
    </div>
</div>

<div class="form-group row">
    <label class="{{ $colLabel }}" id="repstockot-ambitos-label">Depósitos</label>
    <div class="col-lg-8">
        <input type="hidden" name="ambito_deposito" id="ambito_deposito" value="{{ $ambitoActivo }}">
        <div class="repstockot-fichas" id="repstockot-ambitos" role="radiogroup" aria-labelledby="repstockot-ambitos-label">
            @foreach ($ambito_deposito_enum as $key => $value)
                <button type="button" class="repstockot-ficha repstockot-ambito {{ $key === $ambitoActivo ? 'is-activa' : '' }}"
                    data-id="{{ $key }}" aria-pressed="{{ $key === $ambitoActivo ? 'true' : 'false' }}"
                    @if ($key !== $ambitoActivo) tabindex="-1" @endif>{{ $value }}</button>
            @endforeach
        </div>
        <small class="form-text text-muted">
            @if ($depositos_locales_etiqueta !== '')
                Locales: {{ $depositos_locales_etiqueta }}.
            @else
                Locales: depósitos con canal Local.
            @endif
            Fábrica: canal Fábrica (planta, Junín, Tal, Suburbano). Las copias Anita (610, 620…) quedan afuera de las dos.
            Enter pasa al depósito puntual.
        </small>
    </div>
</div>

@include('stock.partials.campo_consulta_deposito', [
    'prefix' => 'repstockot',
    'layout' => 'form_row',
    'label' => 'Depósito',
    'inputName' => 'deposito_id',
    'inputId' => 'deposito_repstockot_id',
    'depositoId' => ($f['deposito_id'] ?? 0) > 0 ? $f['deposito_id'] : '',
    'codigo' => $et['deposito']['codigo'] ?? '',
    'descripcion' => $et['deposito']['descripcion'] ?? '',
    'required' => false,
    'col_label' => $colLabelDep,
    'col_input' => $colInput,
    'siguiente' => '#articulo_desde_id_codigo',
    'ayuda' => 'Vacío = todo el grupo de arriba. F1 y Enter muestran solo los depósitos de ese grupo.',
])

@include('produccion.partials.campo_consulta_articulo', [
    'prefix' => 'desde',
    'label' => 'Desde artículo',
    'inputName' => 'desdearticulo_id',
    'inputId' => 'articulo_desde_id',
    'articuloId' => ($f['desdearticulo_id'] ?? 0) > 0 ? $f['desdearticulo_id'] : '',
    'codigo' => $et['desde_articulo']['codigo'] ?? '',
    'descripcion' => $et['desde_articulo']['descripcion'] ?? '',
    'col_label' => $colLabel,
    'col_input' => $colInput,
    'next_focus' => '#articulo_hasta_id_codigo',
    'help' => 'Vacío = desde el primero. F1 o lupa consulta; Enter resuelve el SKU.',
])
@include('produccion.partials.campo_consulta_articulo', [
    'prefix' => 'hasta',
    'label' => 'Hasta artículo',
    'inputName' => 'hastaarticulo_id',
    'inputId' => 'articulo_hasta_id',
    'articuloId' => ($f['hastaarticulo_id'] ?? 0) > 0 ? $f['hastaarticulo_id'] : '',
    'codigo' => $et['hasta_articulo']['codigo'] ?? '',
    'descripcion' => $et['hasta_articulo']['descripcion'] ?? '',
    'col_label' => $colLabel,
    'col_input' => $colInput,
    'next_focus' => '#linea_desde_id_codigo',
    'help' => 'Vacío = hasta el último. F1 o lupa consulta; Enter resuelve el SKU.',
])

@include('stock.partials.campo_consulta_linea', [
    'prefix' => 'desde',
    'label' => 'Desde línea',
    'inputName' => 'desdelinea_id',
    'inputId' => 'linea_desde_id',
    'lineaId' => ($f['desdelinea_id'] ?? 0) > 0 ? $f['desdelinea_id'] : '',
    'codigo' => $et['desde_linea']['codigo'] ?? '',
    'nombre' => $et['desde_linea']['descripcion'] ?? '',
    'col_label' => $colLabel,
    'col_input' => $colInput,
    'siguiente' => '#linea_hasta_id_codigo',
    'ayuda' => 'Vacío = desde la primera. F1 o lupa consulta; Enter resuelve el código.',
])
@include('stock.partials.campo_consulta_linea', [
    'prefix' => 'hasta',
    'label' => 'Hasta línea',
    'inputName' => 'hastalinea_id',
    'inputId' => 'linea_hasta_id',
    'lineaId' => ($f['hastalinea_id'] ?? 0) > 0 ? $f['hastalinea_id'] : '',
    'codigo' => $et['hasta_linea']['codigo'] ?? '',
    'nombre' => $et['hasta_linea']['descripcion'] ?? '',
    'col_label' => $colLabel,
    'col_input' => $colInput,
    'siguiente' => '#categoria_desde_id_codigo',
    'ayuda' => 'Vacío = hasta la última. F1 o lupa consulta; Enter resuelve el código.',
])

@include('stock.partials.campo_consulta_categoria', [
    'prefix' => 'desde',
    'label' => 'Desde categoría',
    'inputName' => 'desdecategoria_id',
    'inputId' => 'categoria_desde_id',
    'categoriaId' => ($f['desdecategoria_id'] ?? 0) > 0 ? $f['desdecategoria_id'] : '',
    'codigo' => $et['desde_categoria']['codigo'] ?? '',
    'nombre' => $et['desde_categoria']['descripcion'] ?? '',
    'col_label' => $colLabel,
    'col_input' => $colInput,
    'siguiente' => '#categoria_hasta_id_codigo',
    'ayuda' => 'Vacío = desde la primera. F1 o lupa consulta; Enter resuelve el código.',
])
@include('stock.partials.campo_consulta_categoria', [
    'prefix' => 'hasta',
    'label' => 'Hasta categoría',
    'inputName' => 'hastacategoria_id',
    'inputId' => 'categoria_hasta_id',
    'categoriaId' => ($f['hastacategoria_id'] ?? 0) > 0 ? $f['hastacategoria_id'] : '',
    'codigo' => $et['hasta_categoria']['codigo'] ?? '',
    'nombre' => $et['hasta_categoria']['descripcion'] ?? '',
    'col_label' => $colLabel,
    'col_input' => $colInput,
    'siguiente' => '#desdelote',
    'ayuda' => 'Vacío = hasta la última. F1 o lupa consulta; Enter resuelve el código.',
])

<div class="form-group row">
    <label for="desdelote" class="{{ $colLabel }}">Desde lote</label>
    <div class="col-lg-2">
        <input type="text" name="desdelote" id="desdelote" class="form-control" value="{{ $f['desdelote'] ?? '' }}" autocomplete="off"
            title="Enter pasa al lote hasta">
    </div>
</div>
<div class="form-group row">
    <label for="hastalote" class="{{ $colLabel }}">Hasta lote</label>
    <div class="col-lg-2">
        <input type="text" name="hastalote" id="hastalote" class="form-control" value="{{ $f['hastalote'] ?? '' }}" autocomplete="off"
            title="Enter pasa al estado">
    </div>
</div>
<div class="row">
    <div class="col-sm-6">
        <div class="form-group row">
            <label for="estado" class="col-lg-4 col-form-label text-right pr-2 requerido">Estado de las combinaciones</label>
            <div class="col-lg-8">
                <select name="estado" id="estado" class="form-control required" required>
                    <option value="">-- Seleccionar estado de las combinaciones --</option>
                    @foreach ($estado_enum as $key => $value)
                        <option value="{{ $key }}" @selected(($f['estado'] ?? 'TODAS') === $key)>{{ $value }}</option>
                    @endforeach
                </select>
                <small class="form-text text-muted">
                    Por defecto <strong>Todas</strong>: el stock importado suele tener combinaciones inactivas (Tomahawk, Ferli, etc.). Con solo Activas el Excel puede salir vacío.
                </small>
            </div>
        </div>
        <div class="form-group row">
            <label for="estadoOt" class="col-lg-4 col-form-label text-right pr-2 requerido">Stock a incluir</label>
            <div class="col-lg-8">
                <select name="estadoOt" id="estadoOt" class="form-control required" required
                    title="Enter pasa a imprime fotos">
                    @foreach ($estadoOt_enum as $key => $value)
                        <option value="{{ $key }}" @selected(($f['estadoOt'] ?? 'TODAS') === $key)>{{ $value }}</option>
                    @endforeach
                </select>
                <small class="form-text text-muted">
                    Todo trae el reporte completo. Para entregar deja solo lo que no va en rojo.
                    Solo lo rojo deja en producción y pendiente de fabricación.
                </small>
            </div>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group row">
            <label for="imprimefoto" class="col-lg-4 col-form-label text-right pr-2 requerido">Imprime fotos</label>
            <div class="col-lg-8">
                <select name="imprimefoto" id="imprimefoto" class="form-control required" required>
                    <option value="">-- Seleccionar si imprime las fotos --</option>
                    @foreach ($foto_enum as $key => $value)
                        <option value="{{ $key }}" @selected(($f['imprimefoto'] ?? 'SIN_FOTO') === $key)>{{ $value }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="apertura" class="col-lg-4 col-form-label text-right pr-2 requerido">Apertura</label>
            <div class="col-lg-8">
                <select name="apertura" id="apertura" class="form-control required" required>
                    <option value="">-- Seleccionar si totaliza o abre por movimientos --</option>
                    @foreach ($apertura_enum as $key => $value)
                        <option value="{{ $key }}" @selected(($f['apertura'] ?? 'TOTALIZADO') === $key)>{{ $value }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>
