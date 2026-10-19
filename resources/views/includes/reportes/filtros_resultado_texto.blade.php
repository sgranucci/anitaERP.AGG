{{--
  Barra "Filtrar resultado" debajo de parámetros / encima del listado.

  Variables:
    $filtros, $camposTexto (array key => [label, type?]),
    $tiene_filtros_texto, $limpiar_filtros_texto_url,
    $formId, $panelId (default panel-filtros-resultado),
    $campoDefault, $placeholder
--}}
@php
    use App\Support\Listado\ReporteResultadoTextoSupport;
    $f = $filtros ?? [];
    $camposTexto = $camposTexto ?? [];
    $formId = $formId ?? 'form-reporte';
    $panelId = $panelId ?? 'panel-filtros-resultado';
    $campoDefault = $campoDefault ?? (array_key_first($camposTexto) ?: '');
    $filtroModo = $f['filtro_modo'] ?? ReporteResultadoTextoSupport::MODO_TODOS;
    $campoActivo = $f['filtro_campo'] ?? $campoDefault;
    $operadorActivo = $f['filtro_operador'] ?? 'contiene';
    $tieneTexto = ! empty($tiene_filtros_texto);
    $placeholder = $placeholder ?? 'Buscar en el resultado…';
    $operadoresJson = [];
    foreach ($camposTexto as $key => $_meta) {
        $operadoresJson[$key] = ReporteResultadoTextoSupport::OPERADORES;
    }
@endphp
<input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">

<div class="border-top bg-light px-3 py-3" id="bloque-filtros-resultado">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2" style="gap: 8px;">
        <div>
            <strong class="text-info">
                <i class="fa fa-filter"></i> Filtrar resultado
            </strong>
            <span class="small text-muted d-block d-md-inline ml-md-1">
                Acota el listado de abajo sin cambiar los parámetros de la consulta.
            </span>
        </div>
        <div class="d-flex flex-wrap align-items-center justify-content-end">
            @include('includes.listado.filtros_toolbar', [
                'filtroValor' => $f['filtro_valor'] ?? ($f['filtro_texto'] ?? ''),
                'tieneCriterios' => $tieneTexto,
                'limpiarUrl' => $limpiar_filtros_texto_url ?? '#',
                'placeholder' => $placeholder,
                'toggleTarget' => '#'.$panelId,
                'formId' => $formId,
                'inputId' => 'filtro_valor',
                'toggleLabel' => 'Más filtros',
                'toggleLabelOculto' => 'Ocultar filtros',
            ])
        </div>
    </div>

    <div class="collapse{{ $tieneTexto && ($filtroModo === ReporteResultadoTextoSupport::MODO_CAMPO || ($f['filtro_operador'] ?? '') === 'vacio') ? ' show' : '' }}"
         id="{{ $panelId }}" data-listado-filtros-panel>
        <div class="border rounded bg-white px-3 py-2 text-body">
            <div class="form-row align-items-end">
                <div class="form-group col-md-2 col-sm-6 mb-2">
                    <label class="small mb-1" for="filtro_modo">Buscar en</label>
                    <select name="filtro_modo" id="filtro_modo" class="form-control form-control-sm">
                        <option value="{{ ReporteResultadoTextoSupport::MODO_TODOS }}"
                            @selected($filtroModo === ReporteResultadoTextoSupport::MODO_TODOS)>
                            Cualquier campo
                        </option>
                        <option value="{{ ReporteResultadoTextoSupport::MODO_CAMPO }}"
                            @selected($filtroModo === ReporteResultadoTextoSupport::MODO_CAMPO)>
                            Campo determinado
                        </option>
                    </select>
                </div>
                <div class="form-group col-md-2 col-sm-6 mb-2 filtro-campo-wrap"
                    style="{{ $filtroModo !== ReporteResultadoTextoSupport::MODO_CAMPO ? 'display:none' : '' }}">
                    <label class="small mb-1" for="filtro_campo">Campo</label>
                    <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                        @foreach ($camposTexto as $key => $meta)
                            <option value="{{ $key }}" data-type="{{ $meta['type'] ?? 'texto' }}"
                                @selected($campoActivo === $key)>{{ $meta['label'] ?? $key }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2 col-sm-6 mb-2">
                    <label class="small mb-1" for="filtro_operador">Condición</label>
                    <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm"
                            data-operadores='@json($operadoresJson)'>
                        @foreach (ReporteResultadoTextoSupport::OPERADORES as $opKey => $opLabel)
                            <option value="{{ $opKey }}" @selected($operadorActivo === $opKey)>{{ $opLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-3 col-sm-6 mb-2">
                    <label class="small mb-1" for="filtro_valor_panel">Valor</label>
                    <input type="text"
                           id="filtro_valor_panel"
                           class="form-control form-control-sm"
                           value="{{ $f['filtro_valor'] ?? ($f['filtro_texto'] ?? '') }}"
                           placeholder="{{ $placeholder }}"
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
</div>
