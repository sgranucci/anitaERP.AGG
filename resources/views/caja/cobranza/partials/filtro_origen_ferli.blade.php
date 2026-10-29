@if (! empty($muestraFiltroOrigen))
    @php
        $origenActual = (string) ($origen ?? '');
        $baseOrigen = $filtrosQuery ?? [];
        unset($baseOrigen['origen']);
        $urlOrigen = function (string $valor) use ($baseOrigen) {
            $q = $baseOrigen;
            // Vacío explicito: «Todas» pisa Locales/Administración guardados en sesión.
            $q['origen'] = $valor;

            return route('cobranza', $q);
        };
    @endphp
    <div class="d-flex flex-wrap align-items-center mb-2">
        <span class="text-muted small mr-2">Origen:</span>
        <div class="btn-group btn-group-sm" role="group" aria-label="Origen de la cobranza">
            <a href="{{ $urlOrigen('') }}"
               class="btn {{ $origenActual === '' ? 'btn-info' : 'btn-outline-info' }}"
               title="Locales y administración">
                Todas
            </a>
            <a href="{{ $urlOrigen('locales') }}"
               class="btn {{ $origenActual === 'locales' ? 'btn-info' : 'btn-outline-info' }}"
               title="Sucursales, tienda online y marketplaces de Facturación Local">
                Locales
            </a>
            <a href="{{ $urlOrigen('administracion') }}"
               class="btn {{ $origenActual === 'administracion' ? 'btn-info' : 'btn-outline-info' }}"
               title="Recibos de cuenta corriente cargados en administración">
                Administración
            </a>
        </div>
    </div>
    @if ($origenActual !== '')
        <input type="hidden" name="origen" value="{{ $origenActual }}">
    @endif
@endif
