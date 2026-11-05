@php
    $activo = (string) ($panel ?? 'anexo');
    $tabs = [
        'anexo' => 'Anexo I',
        'anexo2' => 'Anexo II',
        'rescajero' => 'Res. cajeros',
        'anexo3' => 'Anexo III',
        'anexo7' => 'Anexo VII',
        'anexo8' => 'Anexo VIII',
    ];
@endphp
<div class="btn-group btn-group-sm mb-2 flex-wrap" role="group" aria-label="Anexos del día">
    @foreach ($tabs as $clave => $etiqueta)
        <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel={{ $clave }}"
           class="btn {{ $activo === $clave ? 'btn-info' : 'btn-outline-info' }}">
            {{ $etiqueta }}
        </a>
    @endforeach
</div>
