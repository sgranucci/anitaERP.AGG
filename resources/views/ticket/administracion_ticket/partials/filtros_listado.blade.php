@php
    use App\Support\Ticket\AdministracionTicketListadoFiltros;
    $f = $filtros ?? [];
    $modo = $f['modo'] ?? AdministracionTicketListadoFiltros::MODO_TODOS;
    $campoActivo = $f['campo'] ?? 'titulo';
    $operadorActivo = $f['operador'] ?? 'contiene';
    $filtroEstado = AdministracionTicketListadoFiltros::normalizarFiltroEstado(
        (string) ($f['filtro_estado'] ?? AdministracionTicketListadoFiltros::FILTRO_ESTADO_EN_CURSO)
    );
    $operadoresJson = [];
    foreach (AdministracionTicketListadoFiltros::CAMPOS as $key => $meta) {
        $operadoresJson[$key] = AdministracionTicketListadoFiltros::operadoresParaCampo($key);
    }
@endphp
<div class="collapse border-bottom" id="panel-filtros-administracion-ticket" data-listado-filtros-panel>
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
    @if ($filtroEstado === AdministracionTicketListadoFiltros::FILTRO_ESTADO_TODOS)
        <input type="hidden" name="filtro_estado" value="{{ AdministracionTicketListadoFiltros::FILTRO_ESTADO_TODOS }}">
    @elseif ($filtroEstado !== AdministracionTicketListadoFiltros::FILTRO_ESTADO_EN_CURSO)
        <input type="hidden" name="filtro_estado" value="{{ $filtroEstado }}">
    @endif
    <div class="card-body bg-light py-2 text-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_modo">Buscar en</label>
                <select name="filtro_modo" id="filtro_modo" class="form-control form-control-sm">
                    <option value="{{ AdministracionTicketListadoFiltros::MODO_TODOS }}" {{ $modo === AdministracionTicketListadoFiltros::MODO_TODOS ? 'selected' : '' }}>Cualquier campo</option>
                    <option value="{{ AdministracionTicketListadoFiltros::MODO_CAMPO }}" {{ $modo === AdministracionTicketListadoFiltros::MODO_CAMPO ? 'selected' : '' }}>Campo determinado</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-campo-wrap" style="{{ $modo !== AdministracionTicketListadoFiltros::MODO_CAMPO ? 'display:none' : '' }}">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach($camposFiltro ?? AdministracionTicketListadoFiltros::CAMPOS as $key => $meta)
                        <option value="{{ $key }}" data-type="{{ $meta['type'] }}" {{ $campoActivo === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condición</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                        data-operadores='@json($operadoresJson)'>
                    @foreach(AdministracionTicketListadoFiltros::operadoresParaCampo($modo === AdministracionTicketListadoFiltros::MODO_CAMPO ? $campoActivo : 'titulo') as $opKey => $opLabel)
                        <option value="{{ $opKey }}" {{ $operadorActivo === $opKey ? 'selected' : '' }}>{{ $opLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_valor_panel">Valor</label>
                <input type="text"
                       id="filtro_valor_panel"
                       class="form-control form-control-sm"
                       value="{{ $f['valor'] ?? '' }}"
                       placeholder="Texto o número"
                       autocomplete="off">
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="fecha_desde">Fecha desde (alta)</label>
                <input type="date" name="fecha_desde" id="fecha_desde" class="form-control form-control-sm" value="{{ $f['fecha_desde'] ?? '' }}">
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="fecha_hasta">Fecha hasta (alta)</label>
                <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control form-control-sm" value="{{ $f['fecha_hasta'] ?? '' }}">
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="fecha_resolucion_desde">Resoluci&oacute;n desde</label>
                <input type="date" name="fecha_resolucion_desde" id="fecha_resolucion_desde" class="form-control form-control-sm" value="{{ $f['fecha_resolucion_desde'] ?? '' }}">
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="fecha_resolucion_hasta">Resoluci&oacute;n hasta</label>
                <input type="date" name="fecha_resolucion_hasta" id="fecha_resolucion_hasta" class="form-control form-control-sm" value="{{ $f['fecha_resolucion_hasta'] ?? '' }}">
            </div>
            <div class="form-group col-md-auto mb-2">
                <button type="submit" class="btn btn-primary btn-sm" data-aplicar-filtros-panel="1">
                    <i class="fa fa-search"></i> Aplicar filtros
                </button>
            </div>
        </div>
    </div>
</div>
