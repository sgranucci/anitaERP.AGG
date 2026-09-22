@php
    $colLabel = $col_label ?? 'col-lg-2 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-4';
    $empresasDisponibles = collect($empresa_query ?? []);
    $puedeVerVendedor = $puede_ver_vendedor ?? false;
    $vendedorIdFiltro = (int) ($filtros['vendedor_id'] ?? 0);
@endphp

<div class="form-group row">
    <label for="empresa_id" class="{{ $colLabel }} requerido">Empresa</label>
    <div class="{{ $colInput }}">
        @if ($empresasDisponibles->count() > 1)
            <select name="empresa_id" id="empresa_id" class="form-control" required>
                <option value="">Seleccione&hellip;</option>
                @foreach ($empresasDisponibles as $emp)
                    <option value="{{ $emp->id }}" @selected((int) ($filtros['empresa_id'] ?? 0) === (int) $emp->id)>
                        {{ $emp->nombre }}
                    </option>
                @endforeach
            </select>
        @elseif ($empresasDisponibles->count() === 1)
            <input type="hidden" name="empresa_id" id="empresa_id" value="{{ (int) $empresasDisponibles->first()->id }}">
            <span class="form-control-plaintext">{{ $empresasDisponibles->first()->nombre }}</span>
        @else
            <p class="text-danger small mb-0">Sin empresas asignadas.</p>
        @endif
    </div>
</div>

<div class="form-group row">
    <label for="fecha_desde" class="{{ $colLabel }} requerido">Desde</label>
    <div class="{{ $colInput }}">
        <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
            value="{{ $filtros['fecha_desde'] ?? date('Y-m-01') }}" required>
    </div>
    <label for="fecha_hasta" class="{{ $colLabel }} requerido">Hasta</label>
    <div class="{{ $colInput }}">
        <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
            value="{{ $filtros['fecha_hasta'] ?? date('Y-m-d') }}" required>
    </div>
</div>

<div class="form-group row tm-vendedor-campo">
    <label for="codigovendedor" class="{{ $colLabel }}">Vendedor</label>
    <div class="col-lg-8">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" class="vendedor_id" name="vendedor_id" id="vendedor_id" value="{{ $vendedorIdFiltro ?: '' }}">
            <button type="button" title="Consulta vendedores (F1)" class="btn-accion-tabla consultavendedor tooltipsC flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeVerVendedor)
                <a href="{{ $vendedorIdFiltro > 0 ? route('editar_vendedor', ['id' => $vendedorIdFiltro, 'origen' => 'modal_consulta', 'vista' => 'consulta']) : '#' }}"
                    target="_blank" rel="noopener"
                    class="btn-accion-tabla btn-link-editar-vendedor tooltipsC flex-shrink-0 {{ $vendedorIdFiltro > 0 ? '' : 'd-none' }}"
                    title="Consultar vendedor en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="form-control codigovendedor flex-shrink-0" id="codigovendedor" name="vendedor_codigo"
                value="{{ $filtros['vendedor_codigo'] ?? '' }}"
                placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off" style="width: 5.5rem;">
            <input type="text" class="form-control nombrevendedor text-truncate" id="nombrevendedor" name="vendedor_nombre"
                value="{{ $filtros['vendedor_nombre'] ?? '' }}"
                placeholder="Descripci&oacute;n" readonly style="min-width: 0; flex: 1 1 auto;">
        </div>
        <p class="text-muted small mb-0 mt-1">Vac&iacute;o = todos los vendedores con ventas en el per&iacute;odo.</p>
    </div>
</div>

<div class="form-group row">
    <label for="tipotransaccion_id" class="{{ $colLabel }}">Tipo</label>
    <div class="{{ $colInput }}">
        <select name="tipotransaccion_id" id="tipotransaccion_id" class="form-control">
            <option value="">Todos</option>
            @foreach ($tipo_query ?? [] as $tipo)
                <option value="{{ $tipo->id }}" @selected((int) ($filtros['tipotransaccion_id'] ?? 0) === (int) $tipo->id)>
                    {{ trim(($tipo->abreviatura ?? '').' — '.($tipo->nombre ?? '')) }}
                </option>
            @endforeach
        </select>
    </div>
</div>
