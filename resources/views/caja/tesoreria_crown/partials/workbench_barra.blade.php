@php
    $qbeAbierto = (bool) ($qbeAbierto ?? false);
    $limpiarParams = ($sala ?? null)
        ? ['sala_id' => $sala->id, 'vista_estandar' => 1]
        : ['vista_estandar' => 1];
@endphp
@if (! ($workbenchListo ?? false))
    <div class="alert alert-warning lw-aviso-migracion mb-0 rounded-0">
        <strong>Migración pendiente.</strong>
        Para vistas y configuración de grilla hace falta la tabla <code>listado_vista</code>.
    </div>
@endif
<div class="lw-toolbar">
    <div class="lw-toolbar-left">
        <select id="lw-vista-select" class="form-control form-control-sm lw-vista-select"
                data-base-url="{{ route($rutaListado) }}"
                title="Vistas guardadas"
                @if (! ($workbenchListo ?? false)) disabled @endif>
            <option value="">Vista estándar</option>
            @foreach ($vistasListado ?? [] as $vista)
                <option value="{{ $vista->id }}" @selected((int) ($vistaActiva->id ?? 0) === (int) $vista->id)>
                    {{ $vista->nombre }}
                    @if ($vista->es_default) ★ @endif
                    @if ($vista->compartida) (compartida) @endif
                </option>
            @endforeach
        </select>
        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modal-lw-grilla"
                @if (! ($workbenchListo ?? false)) disabled title="Requiere migración" @endif>
            <i class="fa fa-th"></i> Diseñar vista
        </button>
        <button type="button" class="btn btn-sm btn-outline-info {{ $qbeAbierto ? '' : 'collapsed' }}"
                data-toggle="collapse" data-target="#lw-qbe-panel"
                aria-expanded="{{ $qbeAbierto ? 'true' : 'false' }}" aria-controls="lw-qbe-panel">
            <i class="fa fa-filter"></i> QBE
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#modal-lw-etiquetas"
                @if (! ($workbenchListo ?? false)) disabled title="Requiere migración" @endif>
            <i class="fa fa-font"></i> Defaults instalación
        </button>
    </div>
    <div class="lw-toolbar-right">
        <input type="search" id="lw-search-rapida" class="form-control form-control-sm lw-search-rapida"
               value="{{ $filtros['valor'] ?? '' }}"
               placeholder="Texto o número"
               autocomplete="off">
        <button type="button" id="btn-lw-buscar-rapida" class="btn btn-sm btn-primary">
            <i class="fa fa-search"></i>
        </button>
        @if ($tieneCriterios || ($filtros['sort'] ?? []) !== [])
            <a href="{{ route($rutaListado, $limpiarParams) }}" class="btn btn-sm btn-outline-warning">
                <i class="fa fa-eraser"></i> Limpiar
            </a>
        @endif
        @if (($filtros['sort'] ?? []) !== [])
            <a href="{{ route($rutaListado, array_merge($filtrosQuery ?? [], ['quitar_orden' => 1])) }}"
               class="btn btn-sm btn-outline-secondary" title="Vuelve al orden por defecto y lo saca de la vista">
                <i class="fa fa-sort"></i> Quitar orden
            </a>
        @endif
    </div>
</div>
