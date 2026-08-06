<div id="panel-filtros-cert-senasa-surmar" class="collapse {{ !empty($tieneCriterios) ? 'show' : '' }} border-bottom">
    <div class="card-body py-2">
        <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="0">
        <div class="form-row align-items-end">
            <div class="form-group col-md-3 mb-2">
                <label class="mb-0 small" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm" form="form-filtros-cert-senasa-surmar">
                    <option value="">Todos (búsqueda rápida)</option>
                    @foreach ($camposFiltro as $c)
                        <option value="{{ $c['campo'] }}" @if (($filtros['filtro_campo'] ?? '') === $c['campo']) selected @endif>
                            {{ $c['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 mb-2">
                <label class="mb-0 small" for="filtro_operador">Operador</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm" form="form-filtros-cert-senasa-surmar">
                    <option value="contiene" @if (($filtros['filtro_operador'] ?? '') === 'contiene') selected @endif>Contiene</option>
                    <option value="igual" @if (($filtros['filtro_operador'] ?? '') === 'igual') selected @endif>Igual</option>
                    <option value="empieza" @if (($filtros['filtro_operador'] ?? '') === 'empieza') selected @endif>Empieza</option>
                    <option value="mayor" @if (($filtros['filtro_operador'] ?? '') === 'mayor') selected @endif>Mayor</option>
                    <option value="menor" @if (($filtros['filtro_operador'] ?? '') === 'menor') selected @endif>Menor</option>
                </select>
            </div>
            <div class="form-group col-md-3 mb-2">
                <label class="mb-0 small" for="filtro_valor_panel">Valor</label>
                <input type="text" name="filtro_valor" id="filtro_valor_panel" class="form-control form-control-sm"
                       value="{{ $filtros['filtro_valor'] ?? '' }}" form="form-filtros-cert-senasa-surmar">
            </div>
            <div class="form-group col-md-2 mb-2">
                <label class="mb-0 small" for="estado">Estado</label>
                <select name="estado" id="estado" class="form-control form-control-sm" form="form-filtros-cert-senasa-surmar">
                    <option value="">Todos</option>
                    <option value="BORRADOR" @if (($filtros['estado'] ?? '') === 'BORRADOR') selected @endif>Provisorio</option>
                    <option value="CONFIRMADO" @if (($filtros['estado'] ?? '') === 'CONFIRMADO') selected @endif>Confirmado</option>
                    <option value="ANULADO" @if (($filtros['estado'] ?? '') === 'ANULADO') selected @endif>Anulado</option>
                </select>
            </div>
            <div class="form-group col-md-2 mb-2">
                <button type="submit" class="btn btn-sm btn-primary">Aplicar filtros</button>
            </div>
        </div>
        @include('includes.listado.filtros_aviso_activos', [
            'tieneCriterios' => $tieneCriterios ?? false,
            'limpiarUrl' => route('certificado_senasa_surmar'),
        ])
    </div>
</div>
