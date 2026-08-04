@php
    use App\Support\Ventas\GastronomiaAnaliticoReporteFiltros;
    $f = $filtros ?? [];
    $modo = $f['modo'] ?? GastronomiaAnaliticoReporteFiltros::MODO_TODOS;
    $campoActivo = $f['campo'] ?? 'descripcion_articulo';
    $operadorActivo = $f['operador'] ?? 'contiene';
    $modoPeriodo = $f['modo_periodo'] ?? GastronomiaAnaliticoReporteFiltros::PERIODO_RANGO;
    $operadoresJson = [];
    foreach (GastronomiaAnaliticoReporteFiltros::CAMPOS as $key => $meta) {
        $operadoresJson[$key] = GastronomiaAnaliticoReporteFiltros::operadoresParaCampo($key);
    }
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];
    $anioActual = (int) date('Y');
@endphp
<input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
<input type="hidden" name="consultar" value="1">

<div class="card-body bg-light border-bottom py-2">
    @include('includes.reportes.asignacion_empresas_checkboxes', [
        'empresa_query' => $empresa_query ?? collect(),
        'empresa_ids_seleccionados' => $f['empresa_ids'] ?? [],
        'consolidar_empresas' => $f['consolidar_empresas'] ?? true,
        'reporte_clave' => 'gastronomia_analitico_reporte',
        'id_prefix' => 'gar',
        'col_label' => 'col-lg-2',
        'col_body' => 'col-lg-10',
    ])

    <div class="form-row align-items-end">
        <div class="form-group col-md-2 col-sm-6 mb-2">
            <label class="small mb-1" for="modo_periodo">Per&iacute;odo</label>
            <select name="modo_periodo" id="modo_periodo" class="form-control form-control-sm">
                <option value="{{ GastronomiaAnaliticoReporteFiltros::PERIODO_RANGO }}" @selected($modoPeriodo === GastronomiaAnaliticoReporteFiltros::PERIODO_RANGO)>
                    Rango de fechas
                </option>
                <option value="{{ GastronomiaAnaliticoReporteFiltros::PERIODO_MES }}" @selected($modoPeriodo === GastronomiaAnaliticoReporteFiltros::PERIODO_MES)>
                    Mes entero
                </option>
            </select>
        </div>

        <div class="form-group col-md-2 col-sm-6 mb-2 js-periodo-rango" style="{{ $modoPeriodo === GastronomiaAnaliticoReporteFiltros::PERIODO_MES ? 'display:none' : '' }}">
            <label class="small mb-1" for="fecha_desde">Desde jornada</label>
            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control form-control-sm"
                   value="{{ $f['fecha_desde'] ?? '' }}">
        </div>
        <div class="form-group col-md-2 col-sm-6 mb-2 js-periodo-rango" style="{{ $modoPeriodo === GastronomiaAnaliticoReporteFiltros::PERIODO_MES ? 'display:none' : '' }}">
            <label class="small mb-1" for="fecha_hasta">Hasta jornada</label>
            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control form-control-sm"
                   value="{{ $f['fecha_hasta'] ?? '' }}">
        </div>

        <div class="form-group col-md-2 col-sm-6 mb-2 js-periodo-mes" style="{{ $modoPeriodo !== GastronomiaAnaliticoReporteFiltros::PERIODO_MES ? 'display:none' : '' }}">
            <label class="small mb-1" for="mes">Mes</label>
            <select name="mes" id="mes" class="form-control form-control-sm">
                @foreach ($meses as $num => $nombre)
                    <option value="{{ $num }}" @selected((int) ($f['mes'] ?? 0) === $num)>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-2 col-sm-6 mb-2 js-periodo-mes" style="{{ $modoPeriodo !== GastronomiaAnaliticoReporteFiltros::PERIODO_MES ? 'display:none' : '' }}">
            <label class="small mb-1" for="anio">A&ntilde;o</label>
            <select name="anio" id="anio" class="form-control form-control-sm">
                @for ($y = $anioActual; $y >= $anioActual - 5; $y--)
                    <option value="{{ $y }}" @selected((int) ($f['anio'] ?? 0) === $y)>{{ $y }}</option>
                @endfor
            </select>
        </div>

        <div class="form-group col-md-2 col-sm-6 mb-2">
            <label class="small mb-1" for="tipo_venta">Tipo venta</label>
            <select name="tipo_venta" id="tipo_venta" class="form-control form-control-sm">
                <option value="">Todos</option>
                <option value="venta" @selected(($f['tipo_venta'] ?? '') === 'venta')>Venta</option>
                <option value="invitacion" @selected(($f['tipo_venta'] ?? '') === 'invitacion')>Invitaci&oacute;n</option>
            </select>
        </div>

        <div class="form-group col-md-auto mb-2">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fa fa-search"></i> Consultar
            </button>
        </div>
    </div>
</div>

<div class="collapse border-bottom{{ ! empty($tiene_filtros_texto) ? ' show' : '' }}" id="panel-filtros-analitico-gastro" data-listado-filtros-panel>
    <div class="card-body bg-white py-2 text-body">
        <p class="small text-muted mb-2">Filtros inteligentes sobre el detalle (opcional).</p>
        <div class="form-row align-items-end">
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_modo">Buscar en</label>
                <select name="filtro_modo" id="filtro_modo" class="form-control form-control-sm">
                    <option value="{{ GastronomiaAnaliticoReporteFiltros::MODO_TODOS }}" {{ $modo === GastronomiaAnaliticoReporteFiltros::MODO_TODOS ? 'selected' : '' }}>Cualquier campo</option>
                    <option value="{{ GastronomiaAnaliticoReporteFiltros::MODO_CAMPO }}" {{ $modo === GastronomiaAnaliticoReporteFiltros::MODO_CAMPO ? 'selected' : '' }}>Campo determinado</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-campo-wrap" style="{{ $modo !== GastronomiaAnaliticoReporteFiltros::MODO_CAMPO ? 'display:none' : '' }}">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach ($camposFiltro ?? GastronomiaAnaliticoReporteFiltros::CAMPOS as $key => $meta)
                        <option value="{{ $key }}" data-type="{{ $meta['type'] }}" {{ $campoActivo === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condici&oacute;n</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                        data-operadores='@json($operadoresJson)'>
                    @foreach (GastronomiaAnaliticoReporteFiltros::operadoresParaCampo($campoActivo) as $opKey => $opLabel)
                        <option value="{{ $opKey }}" {{ $operadorActivo === $opKey ? 'selected' : '' }}>{{ $opLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-3 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_valor_panel">Valor</label>
                <input type="text"
                       id="filtro_valor_panel"
                       class="form-control form-control-sm"
                       value="{{ $f['valor'] ?? '' }}"
                       placeholder="Art&iacute;culo, mozo, cliente…"
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
