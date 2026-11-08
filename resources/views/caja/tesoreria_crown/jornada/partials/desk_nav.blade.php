@if (! empty($jornada))
    <nav class="tc-desk-nav" aria-label="Escritorio de la jornada">
        <div class="tc-desk-nav-ops">
            <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel=cajeros"
               class="tc-nav-cajeros {{ ($navActivo ?? '') === 'cajeros' ? 'is-on' : '' }}">
                <i class="fa fa-users" aria-hidden="true"></i>
                Cajeros <span class="tc-count">{{ $navAsignaciones ?? 0 }}</span>
            </a>
            @if ($puedeMesas ?? false)
                <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel=mesas#piso"
                   class="tc-nav-mesas {{ ($navActivo ?? '') === 'mesas' ? 'is-on' : '' }}">
                    <i class="fa fa-th-large" aria-hidden="true"></i>
                    Mesas
                    <span class="tc-count {{ ($navBorradoresMesa ?? 0) > 0 ? 'is-alerta' : '' }}">{{ $navMesas ?? 0 }}</span>
                </a>
            @endif
            @if ($puedeBoveda ?? false)
                <a href="{{ route('tesoreria_crown_boveda', ['sala_id' => $jornada->sala_id, 'jornada_id' => $jornada->id]) }}"
                   class="tc-nav-boveda {{ ($navActivo ?? '') === 'boveda' ? 'is-on' : '' }}">
                    <i class="fa fa-university" aria-hidden="true"></i>
                    Bóveda <span class="tc-count">{{ $navBoveda ?? 0 }}</span>
                </a>
            @endif
            @if ($puedeSlots ?? false)
                <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel=slots"
                   class="tc-nav-slots {{ ($navActivo ?? '') === 'slots' ? 'is-on' : '' }}">
                    <i class="fa fa-gamepad" aria-hidden="true"></i>
                    Slots <span class="tc-count">{{ $navSlots ?? 0 }}</span>
                </a>
            @endif
            @if ($puedeBingo ?? false)
                <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel=bingo"
                   class="tc-nav-bingo {{ ($navActivo ?? '') === 'bingo' ? 'is-on' : '' }}">
                    <i class="fa fa-ticket" aria-hidden="true"></i>
                    Bingo <span class="tc-count">{{ ($navBingo ?? false) ? 1 : 0 }}</span>
                </a>
            @endif
        </div>
        @if (($puedePoder ?? false) || ($puedeAnexo ?? false) || ($puedeKardex ?? false))
            <div class="tc-desk-nav-reportes" aria-label="Informes del día">
                @if ($puedePoder ?? false)
                    <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel=poder"
                       class="tc-desk-nav-informe tc-nav-poder {{ ($navActivo ?? '') === 'poder' ? 'is-on' : '' }}"
                       title="Stock de fichas (poder del público)">
                        <i class="fa fa-power-off" aria-hidden="true"></i>
                        <span>Stock de fichas</span>
                    </a>
                @endif
                @if ($puedeKardex ?? false)
                    <a href="{{ route('tesoreria_crown_kardex_valores', [
                            'sala_id' => $jornada->sala_id,
                            'jornada_id' => $jornada->id,
                            'consultar' => 1,
                        ]) }}"
                       class="tc-desk-nav-informe tc-nav-kardex {{ ($navActivo ?? '') === 'kardex' ? 'is-on' : '' }}"
                       title="Kardex unificado de valores (bóveda / pre-depósito / caja chica)">
                        <i class="fa fa-exchange" aria-hidden="true"></i>
                        <span>Kardex valores</span>
                    </a>
                @endif
                @if ($puedeAnexo ?? false)
                    @php
                        $anexoActivo = in_array($navActivo ?? '', ['anexo', 'anexo2', 'rescajero', 'anexo3', 'anexo7', 'anexo8'], true);
                    @endphp
                    <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel=anexo"
                       class="tc-desk-nav-informe tc-nav-anexo {{ $anexoActivo ? 'is-on' : '' }}"
                       title="Anexos del día (I, II, III, VII, VIII y resumen de cajeros)">
                        <i class="fa fa-file-text-o" aria-hidden="true"></i>
                        <span>Anexos</span>
                    </a>
                @endif
            </div>
        @endif
    </nav>
@endif
