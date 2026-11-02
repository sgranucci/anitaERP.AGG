@if (! empty($jornada))
    <nav class="tc-desk-nav" aria-label="Escritorio de la jornada">
        <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel=cajeros" class="{{ ($navActivo ?? '') === 'cajeros' ? 'is-on' : '' }}">
            Cajeros <span class="tc-count">{{ $navAsignaciones ?? 0 }}</span>
        </a>
        @if ($puedeMesas ?? false)
            <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel=mesas#piso" class="{{ ($navActivo ?? '') === 'mesas' ? 'is-on' : '' }}">
                Mesas
                <span class="tc-count {{ ($navBorradoresMesa ?? 0) > 0 ? 'is-alerta' : '' }}">{{ $navMesas ?? 0 }}</span>
            </a>
        @endif
        @if ($puedeBoveda ?? false)
            <a href="{{ route('tesoreria_crown_boveda', ['sala_id' => $jornada->sala_id, 'jornada_id' => $jornada->id]) }}" class="{{ ($navActivo ?? '') === 'boveda' ? 'is-on' : '' }}">
                Bóveda <span class="tc-count">{{ $navBoveda ?? 0 }}</span>
            </a>
        @endif
        @if ($puedeSlots ?? false)
            <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel=slots" class="{{ ($navActivo ?? '') === 'slots' ? 'is-on' : '' }}">
                Slots <span class="tc-count">{{ $navSlots ?? 0 }}</span>
            </a>
        @endif
        @if ($puedePoder ?? false)
            <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel=poder" class="{{ ($navActivo ?? '') === 'poder' ? 'is-on' : '' }}">
                Poder público
            </a>
        @endif
        @if ($puedeAnexo ?? false)
            <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel=anexo" class="{{ ($navActivo ?? '') === 'anexo' ? 'is-on' : '' }}">
                Anexo I
            </a>
        @endif
    </nav>
@endif
