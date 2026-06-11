@php
    use App\Support\Ventas\GastronomiaCierresTurnoListadoFiltros;
    $f = $filtros ?? [];
    $modo = $f['modo'] ?? GastronomiaCierresTurnoListadoFiltros::MODO_TODOS;
    $campoActivo = $f['campo'] ?? 'referencia';
    $operadorActivo = $f['operador'] ?? 'contiene';
    $operadoresJson = [];
    foreach (GastronomiaCierresTurnoListadoFiltros::CAMPOS as $key => $meta) {
        $operadoresJson[$key] = GastronomiaCierresTurnoListadoFiltros::operadoresParaCampo($key);
    }
@endphp
<div class="collapse border-bottom" id="panel-filtros-cierres-turno" data-listado-filtros-panel>
    <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
    <div class="card-body bg-light py-2 text-body">
        <div class="form-row align-items-end">
            @include('includes.listado.filtro_empresa_asignada', ['f' => $f])
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="identificador_pc">PC</label>
                @php
                    $pcSeleccionada = (string) ($f['identificador_pc'] ?? '');
                    $todasTerminalesActivo = ! empty($f['todas_terminales']);
                    $pcsConfiguradas = collect($pc_query ?? [])->pluck('identificador_pc')->map(fn ($v) => (string) $v);
                    $pcExtra = $pcSeleccionada !== '' && ! $todasTerminalesActivo && ! $pcsConfiguradas->contains($pcSeleccionada)
                        ? $pcSeleccionada
                        : null;
                @endphp
                <select name="identificador_pc" id="identificador_pc" class="form-control form-control-sm"
                        @disabled($todasTerminalesActivo)>
                    <option value="" @selected($pcSeleccionada === '' || $todasTerminalesActivo)>Todas</option>
                    @foreach ($pc_query ?? [] as $pc)
                        @php
                            $etiquetaPc = (string) $pc->identificador_pc
                                .(! empty($pc->descripcion) ? ' — '.$pc->descripcion : '');
                        @endphp
                        <option value="{{ $pc->identificador_pc }}" @selected($pcSeleccionada === (string) $pc->identificador_pc)>
                            {{ $etiquetaPc }}
                        </option>
                    @endforeach
                    @if ($pcExtra !== null)
                        <option value="{{ $pcExtra }}" selected>{{ $pcExtra }} (sin configurar)</option>
                    @endif
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="fecha_desde">Desde</label>
                <input type="date" name="fecha_desde" id="fecha_desde" class="form-control form-control-sm"
                       value="{{ $f['fecha_desde'] ?? '' }}"/>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="fecha_hasta">Hasta</label>
                <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control form-control-sm"
                       value="{{ $f['fecha_hasta'] ?? '' }}" data-fecha-jornada-hasta/>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="tipo">Tipo cierre</label>
                <select name="tipo" id="tipo" class="form-control form-control-sm">
                    <option value="" @selected(($f['tipo'] ?? '') === '')>Todos</option>
                    <option value="parcial" @selected(($f['tipo'] ?? '') === 'parcial')>Cierre parcial</option>
                    <option value="cierre" @selected(($f['tipo'] ?? '') === 'cierre')>Cierre definitivo</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_modo">Buscar en</label>
                <select name="filtro_modo" id="filtro_modo" class="form-control form-control-sm">
                    <option value="{{ GastronomiaCierresTurnoListadoFiltros::MODO_TODOS }}" {{ $modo === GastronomiaCierresTurnoListadoFiltros::MODO_TODOS ? 'selected' : '' }}>Cualquier campo</option>
                    <option value="{{ GastronomiaCierresTurnoListadoFiltros::MODO_CAMPO }}" {{ $modo === GastronomiaCierresTurnoListadoFiltros::MODO_CAMPO ? 'selected' : '' }}>Campo determinado</option>
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2 filtro-campo-wrap" style="{{ $modo !== GastronomiaCierresTurnoListadoFiltros::MODO_CAMPO ? 'display:none' : '' }}">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach($camposFiltro ?? GastronomiaCierresTurnoListadoFiltros::CAMPOS as $key => $meta)
                        <option value="{{ $key }}" data-type="{{ $meta['type'] }}" {{ $campoActivo === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-1" for="filtro_operador">Condición</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                        data-operadores='@json($operadoresJson)'>
                    @foreach(GastronomiaCierresTurnoListadoFiltros::operadoresParaCampo($campoActivo) as $opKey => $opLabel)
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
                       placeholder="Referencia, PV, turno…"
                       autocomplete="off">
            </div>
            <div class="form-group col-md-auto mb-2">
                <button type="submit" class="btn btn-primary btn-sm" data-aplicar-filtros-panel="1">
                    <i class="fa fa-search"></i> Aplicar filtros
                </button>
            </div>
        </div>
    </div>
</div>
