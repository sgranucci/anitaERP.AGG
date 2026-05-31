@php
    $fotoUrl = ! empty($foto ?? null)
        ? asset('storage/imagenes/fotos_uif/'.$foto)
        : null;
    $premioId = $premioId ?? null;
    $mostrarEnlace = ($mostrarEnlace ?? true)
        && $fotoUrl
        && $premioId
        && can('editar-cliente-premio-uif', false);
@endphp
@if ($fotoUrl)
    @if ($mostrarEnlace)
        <a href="{{ route('muestra_foto_cliente_premio_uif', ['id' => $premioId]) }}" class="premio-foto-thumb-link tooltipsC" title="Ver foto del jugador">
            <img src="{{ $fotoUrl }}" alt="Foto del jugador" class="premio-foto-thumb" loading="lazy">
        </a>
    @else
        <img src="{{ $fotoUrl }}" alt="Foto del jugador" class="premio-foto-thumb" loading="lazy">
    @endif
@else
    <span class="premio-foto-sin-imagen tooltipsC" title="Sin foto">—</span>
@endif
