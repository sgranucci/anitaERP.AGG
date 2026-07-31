@php
    use App\Support\Solicitudpago\SolicitudpagoListadoFiltros;
    $f = $filtros ?? [];
    $campoActivo = $f['campo'] ?? '';
    $operadorActivo = $f['operador'] ?? 'contiene';
    $operadoresJson = [];
    foreach (SolicitudpagoListadoFiltros::CAMPOS as $key => $meta) {
        $operadoresJson[$key] = SolicitudpagoListadoFiltros::operadoresParaCampo($key);
    }
    $tieneCriteriosPanel = SolicitudpagoListadoFiltros::tieneCriteriosAplicados($f);
    $limpiarUrlPanel = $limpiarUrl ?? route(
        'consultar_solicitudpago',
        array_merge(SolicitudpagoListadoFiltros::paraQueryStringEmpresa($f), ['limpiar_filtros' => 1])
    );
    $fEmp = (int) ($f['empresa_id'] ?? 0);
    $fScope = $f['empresa_scope'] ?? 'una';
@endphp
<div class="collapse border-bottom" id="panel-filtros-solicitudpago" data-listado-filtros-panel>
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
    @if ($fScope === 'todas')
        <input type="hidden" name="empresa_todas" value="1">
    @elseif ($fEmp > 0)
        <input type="hidden" name="empresa_id" value="{{ $fEmp }}">
    @endif
    <div class="card-body bg-light py-2 text-body">
        @if($tieneCriteriosPanel)
            <div class="mb-2">
                @include('includes.listado.filtros_aviso_activos', [
                    'tieneCriterios' => true,
                    'limpiarUrl' => $limpiarUrlPanel,
                    'compact' => true,
                ])
            </div>
        @endif
        <div class="form-row align-items-end">
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    <option value="">— (búsqueda rápida) —</option>
                    @foreach($camposFiltro ?? SolicitudpagoListadoFiltros::CAMPOS as $clave => $meta)
                        <option value="{{ $clave }}" data-type="{{ $meta['tipo'] }}" @selected($campoActivo === $clave)>{{ $meta['etiqueta'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condici&oacute;n</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                        data-operadores='@json($operadoresJson)'>
                    @foreach(SolicitudpagoListadoFiltros::operadoresParaCampo($campoActivo !== '' ? $campoActivo : 'detalle') as $opKey => $opLabel)
                        <option value="{{ $opKey }}" @selected($operadorActivo === $opKey)>{{ $opLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_valor_panel">Valor</label>
                <input type="text"
                       id="filtro_valor_panel"
                       class="form-control form-control-sm"
                       value="{{ $f['valor'] ?? '' }}"
                       placeholder="Texto o n&uacute;mero"
                       autocomplete="off">
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="estado">Estado</label>
                <select name="estado" id="estado" class="form-control form-control-sm">
                    <option value="">— Todos —</option>
                    @foreach ($estado_enum as $opt)
                        <option value="{{ $opt['valor'] }}" @selected(($f['estado'] ?? '') === $opt['valor'])>{{ $opt['nombre'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="tratamiento">Tratamiento</label>
                <select name="tratamiento" id="tratamiento" class="form-control form-control-sm">
                    <option value="">— Todos —</option>
                    @foreach ($tratamiento_enum as $opt)
                        <option value="{{ $opt['valor'] }}" @selected(($f['tratamiento'] ?? '') === $opt['valor'])>{{ $opt['nombre'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="madre_hija">Madre / Hija</label>
                <select name="madre_hija" id="madre_hija" class="form-control form-control-sm">
                    <option value="">— Todas —</option>
                    <option value="madres" @selected(($f['madre_hija'] ?? '') === 'madres')>Solo madres (sin vínculo)</option>
                    <option value="hijas" @selected(($f['madre_hija'] ?? '') === 'hijas')>Solo hijas</option>
                    <option value="madres_con_plan" @selected(($f['madre_hija'] ?? '') === 'madres_con_plan')>Madres con plan / cuotas</option>
                    <option value="familia" @selected(($f['madre_hija'] ?? '') === 'familia')>Familia (madres e hijas)</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="fecha_desde">Fecha desde</label>
                <input type="date" name="fecha_desde" id="fecha_desde" class="form-control form-control-sm"
                       value="{{ $f['fecha_desde'] ?? '' }}">
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="fecha_hasta">Fecha hasta</label>
                <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control form-control-sm"
                       value="{{ $f['fecha_hasta'] ?? '' }}">
            </div>
            <div class="form-group col-md-auto mb-2">
                <button type="submit" class="btn btn-primary btn-sm" data-aplicar-filtros-panel="1">
                    <i class="fa fa-search"></i> Aplicar filtros
                </button>
            </div>
        </div>
    </div>
</div>
