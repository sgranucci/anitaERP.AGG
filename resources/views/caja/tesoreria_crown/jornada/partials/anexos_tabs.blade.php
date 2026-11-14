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
    $anexosBaseUrl = $anexosBaseUrl ?? route('ver_tesoreria_crown_jornada', $jornada->id);
    $anexosBaseQuery = $anexosBaseQuery ?? [];
    $urlPanelAnexo = function (string $clave) use ($anexosBaseUrl, $anexosBaseQuery): string {
        $query = array_merge($anexosBaseQuery, ['panel' => $clave]);
        // I y VIII en menú llevan rango; el resto de anexos del menú usa la fecha hasta.
        if (in_array($clave, ['anexo', 'anexo8'], true)) {
            if (! empty($anexosBaseQuery['fecha_desde']) || ! empty($anexosBaseQuery['fecha_hasta'])) {
                $query['consultar'] = 1;
            }
        } else {
            unset($query['fecha_desde'], $query['fecha_hasta'], $query['consultar']);
            if (! empty($anexosBaseQuery['fecha_hasta'])) {
                $query['fecha'] = $anexosBaseQuery['fecha_hasta'];
            } elseif (! empty($anexosBaseQuery['fecha_desde'])) {
                $query['fecha'] = $anexosBaseQuery['fecha_desde'];
            }
        }

        return $anexosBaseUrl.'?'.http_build_query($query);
    };
@endphp
<div class="btn-group btn-group-sm mb-2 flex-wrap tc-anexos-tabs" role="group" aria-label="Anexos del día">
    @foreach ($tabs as $clave => $etiqueta)
        <a href="{{ $urlPanelAnexo($clave) }}"
           class="btn {{ $activo === $clave ? 'btn-info' : 'btn-outline-info' }}">
            @if ($clave === 'anexo')
                <i class="fa fa-list-alt" aria-hidden="true"></i>
            @elseif ($clave === 'anexo2')
                <i class="fa fa-th" aria-hidden="true"></i>
            @elseif ($clave === 'rescajero')
                <i class="fa fa-user" aria-hidden="true"></i>
            @elseif ($clave === 'anexo3')
                <i class="fa fa-users" aria-hidden="true"></i>
            @elseif ($clave === 'anexo7')
                <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
            @elseif ($clave === 'anexo8')
                <i class="fa fa-university" aria-hidden="true"></i>
            @endif
            {{ $etiqueta }}
        </a>
    @endforeach
</div>
