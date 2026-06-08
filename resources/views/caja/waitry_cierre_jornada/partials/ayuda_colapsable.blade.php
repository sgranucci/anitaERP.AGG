@php
    $collapseId = $id ?? 'waitry-ayuda';
    $label = $label ?? 'Ayuda';
@endphp
@once
<style>
    .waitry-ayuda-colapsable .waitry-ayuda-toggle {
        font-size: 0.8125rem;
        line-height: 1.4;
    }
    .waitry-ayuda-colapsable .waitry-ayuda-cuerpo {
        border-left: 3px solid #dee2e6;
        padding-left: 0.75rem;
        margin-left: 0.15rem;
    }
</style>
@endonce
<div @if (! empty($wrapperId)) id="{{ $wrapperId }}" @endif
     class="waitry-ayuda-colapsable mb-2{{ ! empty($wrapperClass) ? ' '.$wrapperClass : '' }}">
    <button type="button"
            class="btn btn-outline-secondary btn-sm waitry-ayuda-toggle collapsed py-0 px-2"
            data-toggle="collapse"
            data-target="#{{ $collapseId }}"
            aria-expanded="false"
            aria-controls="{{ $collapseId }}">
        <i class="fa fa-question-circle"></i> {{ $label }}
    </button>
    <div class="collapse waitry-ayuda-cuerpo mt-1 text-muted small" id="{{ $collapseId }}">
        @isset($inner)
            @include($inner)
        @elseif (! empty($contenido))
            {!! $contenido !!}
        @endif
    </div>
</div>
