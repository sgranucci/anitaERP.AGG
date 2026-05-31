{{--
    Barra del card-header (mismo patrón que stock/precio/index).

    Variables: filtroValor, tieneCriterios, limpiarUrl, placeholder,
    toggleTarget, toggleId, inputId, nuevoRegistroUrl, nuevoRegistroCan, nuevoRegistroLabel
--}}
@php
    $inputName = $inputName ?? 'filtro_valor';
    $toggleId = $toggleId ?? 'btn-toggle-filtros-listado';
    $placeholder = $placeholder ?? 'Búsqueda…';
    $formId = $formId ?? 'form-filtros-cliente-uif';
@endphp
@include('includes.listado.filtros_estilos_activos')
<button type="button"
        class="btn btn-outline-secondary btn-sm mr-1{{ !empty($tieneCriterios) ? ' listado-filtros-toggle-activo' : '' }}"
        id="{{ $toggleId }}"
        data-toggle="collapse"
        data-target="{{ $toggleTarget }}"
        data-listado-filtros-toggle
        data-listado-filtros-label-show="Filtros"
        data-listado-filtros-label-hide="Ocultar filtros"
        aria-expanded="false"
        style="color: #fff;">
    <i class="fa fa-filter"></i>
    <span class="js-listado-filtros-toggle-text">Filtros</span>
</button>
<input type="text"
       name="{{ $inputName }}"
       id="{{ $inputId ?? 'filtro_valor' }}"
       form="{{ $formId }}"
       class="form-control form-control-sm d-inline-block mr-1{{ !empty($tieneCriterios) ? ' listado-filtros-input-activo' : '' }}"
       style="width: 220px; vertical-align: middle;"
       value="{{ $filtroValor }}"
       placeholder="{{ $placeholder }}"
       autocomplete="off"
       title="Enter: búsqueda en todos los campos">
<button type="submit"
        form="{{ $formId }}"
        class="btn btn-light btn-sm mr-1"
        data-busqueda-rapida="1"
        title="Búsqueda rápida en todos los campos">
    <i class="fa fa-search"></i>
</button>
@include('includes.listado.filtros_aviso_activos', [
    'tieneCriterios' => $tieneCriterios ?? false,
    'limpiarUrl' => $limpiarUrl ?? null,
    'showLimpiar' => true,
])
@if(!empty($nuevoRegistroUrl) && can($nuevoRegistroCan ?? '', false))
    <a href="{{ $nuevoRegistroUrl }}" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-fw fa-plus-circle"></i> {{ $nuevoRegistroLabel ?? 'Nuevo registro' }}
    </a>
@endif
