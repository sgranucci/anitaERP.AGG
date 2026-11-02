@php
    $nuevos = (int) ($preview['resumen']['nuevos'] ?? 0);
    $enganchar = (int) ($preview['resumen']['enganchar'] ?? 0);
    $formId = $formId ?? 'form-confirmar-transferencia';
    $ruta = $ruta ?? '';
    $token = $token ?? '';
@endphp
@if ($nuevos + $enganchar > 0)
    <form method="post" action="{{ $ruta }}" id="{{ $formId }}" class="d-inline js-confirmar-transferencia">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <button type="submit" class="btn btn-success btn-sm">
            <i class="fa fa-check"></i>
            @if ($nuevos > 0 && $enganchar > 0)
                Generar {{ $nuevos }} y enganchar {{ $enganchar }} asientos
            @elseif ($enganchar > 0)
                Enganchar {{ $enganchar }} asientos
            @else
                Generar {{ $nuevos }} facturas
            @endif
        </button>
    </form>
@endif
