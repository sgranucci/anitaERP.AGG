@php
    use App\Support\Ventas\GastronomiaCrown\GastroPlanoMesaListadoFiltros;
    $f = $filtros ?? [];
@endphp
<div id="panel-filtros-plano-mesa" class="collapse border-bottom bg-light">
    <div class="card-body py-2">
        <input type="hidden" name="filtro_modo" id="filtro_modo" value="{{ $f['modo'] ?? 'todos' }}">
        @if (($f['sala_scope'] ?? 'todas') === 'todas')
            <input type="hidden" name="sala_todas" value="1">
        @elseif (!empty($f['sala_id']))
            <input type="hidden" name="sala_id" value="{{ $f['sala_id'] }}">
        @endif
        @if (($f['empresa_scope'] ?? 'una') === 'todas')
            <input type="hidden" name="empresa_todas" value="1">
        @elseif (!empty($f['empresa_id']))
            <input type="hidden" name="empresa_id" value="{{ $f['empresa_id'] }}">
        @endif
        <div class="form-row align-items-end">
            <div class="form-group col-md-3 mb-2">
                <label class="small mb-1" for="filtro_campo">Campo</label>
                <select name="filtro_campo" id="filtro_campo" class="form-control form-control-sm">
                    @foreach (GastroPlanoMesaListadoFiltros::CAMPOS as $key => $meta)
                        <option value="{{ $key }}" {{ ($f['campo'] ?? 'nombre') === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-3 mb-2">
                <label class="small mb-1" for="filtro_operador">Condición</label>
                <select name="filtro_operador" id="filtro_operador" class="form-control form-control-sm">
                    @foreach (GastroPlanoMesaListadoFiltros::OPERADORES_TEXTO as $op => $label)
                        <option value="{{ $op }}" {{ ($f['operador'] ?? 'contiene') === $op ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-4 mb-2">
                <label class="small mb-1" for="filtro_valor_panel">Valor</label>
                <input type="text" name="filtro_valor" id="filtro_valor_panel" class="form-control form-control-sm"
                       value="{{ $f['valor'] ?? '' }}" autocomplete="off">
            </div>
            <div class="form-group col-md-2 mb-2">
                <button type="submit" class="btn btn-info btn-sm btn-block" id="btn-aplicar-filtros-plano">Aplicar filtros</button>
            </div>
        </div>
        @include('includes.listado.filtros_aviso_activos', [
            'tieneCriterios' => GastroPlanoMesaListadoFiltros::tieneCriteriosAplicados($f),
            'limpiarUrl' => $limpiarUrl ?? route('consultar_plano_mesa_gastronomia_crown'),
        ])
    </div>
</div>
