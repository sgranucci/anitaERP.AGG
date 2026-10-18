@php
    use App\Support\Compras\ProveedorCuentacorrienteReporteFiltros;
    $f = $filtros ?? [];
    $filtroModo = $f['filtro_modo'] ?? ProveedorCuentacorrienteReporteFiltros::FILTRO_MODO_TODOS;
    $campoActivo = $f['filtro_campo'] ?? 'proveedor_nombre';
    $operadorActivo = $f['filtro_operador'] ?? 'contiene';
    $tieneTexto = ! empty($tiene_filtros_texto);
    $operadoresJson = [];
    foreach (ProveedorCuentacorrienteReporteFiltros::CAMPOS_TEXTO as $key => $meta) {
        $operadoresJson[$key] = ProveedorCuentacorrienteReporteFiltros::operadoresParaCampoTexto($key);
    }
@endphp
<input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">

<div class="collapse border-bottom{{ $tieneTexto ? ' show' : '' }}" id="panel-filtros-cc-proveedores" data-listado-filtros-panel>
    <div class="card-body bg-white py-2 text-body">
        <p class="small text-muted mb-2">
            Filtro sobre el resultado (como en los índices): acota proveedores / comprobantes sin cambiar el resto de la consulta.
        </p>
        <div class="form-row align-items-end">
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_modo">Buscar en</label>
                <select name="filtro_modo" id="filtro_modo" class="form-control form-control-sm">
                    <option value="{{ ProveedorCuentacorrienteReporteFiltros::FILTRO_MODO_TODOS }}"
                        @selected($filtroModo === ProveedorCuentacorrienteReporteFiltros::FILTRO_MODO_TODOS)>
                        Cualquier campo
                    </option>
                    <option value="{{ ProveedorCuentacorrienteReporteFiltros::FILTRO_MODO_CAMPO }}"
                        @selected($filtroModo === ProveedorCuentacorrienteReporteFiltros::FILTRO_MODO_CAMPO)>
                        Campo determinado
                    </option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-campo-wrap"
                style="{{ $filtroModo !== ProveedorCuentacorrienteReporteFiltros::FILTRO_MODO_CAMPO ? 'display:none' : '' }}">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach (ProveedorCuentacorrienteReporteFiltros::CAMPOS_TEXTO as $key => $meta)
                        <option value="{{ $key }}" data-type="{{ $meta['type'] }}"
                            @selected($campoActivo === $key)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condición</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                        data-operadores='@json($operadoresJson)'>
                    @foreach (ProveedorCuentacorrienteReporteFiltros::operadoresParaCampoTexto($campoActivo) as $opKey => $opLabel)
                        <option value="{{ $opKey }}" @selected($operadorActivo === $opKey)>{{ $opLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-3 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_valor_panel">Valor</label>
                <input type="text"
                       id="filtro_valor_panel"
                       class="form-control form-control-sm"
                       value="{{ $f['filtro_valor'] ?? '' }}"
                       placeholder="Proveedor, comprobante, empresa…"
                       autocomplete="off">
            </div>
            <div class="form-group col-md-auto mb-2">
                <button type="submit" class="btn btn-outline-primary btn-sm" data-aplicar-filtros-panel="1">
                    <i class="fa fa-filter"></i> Aplicar
                </button>
            </div>
        </div>
    </div>
</div>
