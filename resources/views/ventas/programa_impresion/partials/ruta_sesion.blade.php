@php
    $porFormulario = [];
    foreach (($sesion['pack'] ?? []) as $i => $linea) {
        $clave = $linea['formulario'] ?? 'OTRO';
        if (! isset($porFormulario[$clave])) {
            $porFormulario[$clave] = [
                'etiqueta' => $linea['formulario_etiqueta'] ?? $clave,
                'lineas' => [],
            ];
        }
        $porFormulario[$clave]['lineas'][] = ['i' => $i, 'linea' => $linea];
    }
@endphp
@if ($porFormulario !== [])
<div class="programa-ruta-preview mb-3" aria-label="Ruta de copias de esta sesión">
    @foreach ($porFormulario as $grupo)
        @if (! $loop->first)
            <span class="programa-ruta-flecha">&rarr;</span>
        @endif
        <span class="programa-ruta-nodo es-doc">{{ $grupo['etiqueta'] }}</span>
        @foreach ($grupo['lineas'] as $item)
            @php
                $linea = $item['linea'];
                $esNas = ($linea['medio'] ?? '') === 'ARCHIVO';
                $texto = $linea['leyenda'] ?? $linea['copia_codigo'];
                if (! empty($linea['destinatario'])) {
                    $texto .= ' · '.$linea['destinatario'];
                }
            @endphp
            <span class="programa-ruta-flecha">&rarr;</span>
            <span class="programa-ruta-nodo {{ $esNas ? 'es-nas' : '' }}">{{ $texto }}</span>
        @endforeach
    @endforeach
</div>
<div class="d-flex flex-wrap" style="gap: 16px;">
    @foreach ($porFormulario as $grupo)
        <div class="sesion-ruta-columna">
            <div class="sesion-ruta-doc">{{ $grupo['etiqueta'] }}</div>
            @foreach ($grupo['lineas'] as $item)
                @php
                    $linea = $item['linea'];
                    $res = $resultado['resultados'][$item['i']] ?? null;
                    $estado = 'pendiente';
                    if ($res) {
                        $estado = ! empty($res['ok']) ? 'ok' : 'error';
                    }
                @endphp
                <div class="sesion-ruta-hoja {{ $estado }}">
                    <div><strong>{{ $linea['leyenda'] }}</strong> <span class="text-muted">({{ $linea['copia_codigo'] }})</span></div>
                    @if (! empty($linea['destinatario']))
                        <div>{{ $linea['destinatario'] }}</div>
                    @endif
                    <div class="text-muted">{{ $linea['salida_nombre'] }} · {{ $linea['medio'] }}</div>
                    <div>
                        @if ($res)
                            {{ ! empty($res['ok']) ? 'OK' : 'Error' }} — {{ $res['mensaje'] }}
                        @else
                            Pendiente
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
</div>
@endif
