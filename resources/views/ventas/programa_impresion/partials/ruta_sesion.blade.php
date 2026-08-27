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
@if (count($sesion['pack'] ?? []) > 1)
    <p class="small text-muted mb-2">
        Marcá las copias a imprimir.
        <a href="#" class="sesion-marcar-copias" data-marcar="1">Todas</a>
        ·
        <a href="#" class="sesion-marcar-copias" data-marcar="0">Ninguna</a>
    </p>
@endif
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
                    @if (count($sesion['pack'] ?? []) > 1)
                        <label class="sesion-copia-check mb-1">
                            <input type="checkbox" name="pack_idx[]" value="{{ $item['i'] }}" form="form-ejecutar-sesion" class="sesion-copia-idx" checked>
                            Incluir
                        </label>
                    @endif
                    <div><strong>{{ $linea['leyenda'] }}</strong> <span class="text-muted">({{ $linea['copia_codigo'] }})</span></div>
                    @if (! empty($linea['destinatario']))
                        <div>{{ $linea['destinatario'] }}</div>
                    @endif
                    <div class="{{ ! empty($linea['hereda_usuario']) && empty($linea['salida_usuario_ok']) ? 'text-danger' : 'text-muted' }}">
                        @if (! empty($linea['hereda_usuario']))
                            Impresora del usuario:
                        @endif
                        {{ $linea['salida_nombre'] }} · {{ $linea['medio'] }}
                    </div>
                    <div>
                        @if ($res)
                            {{ ! empty($res['ok']) ? 'OK' : 'Error' }} — {{ $res['mensaje'] }}
                        @else
                            Pendiente
                        @endif
                    </div>
                    @if (count($sesion['pack'] ?? []) > 1)
                        <div class="mt-2">
                            <button type="button" class="btn btn-outline-primary btn-sm btn-solo-copia" data-pack-idx="{{ $item['i'] }}">
                                <i class="fa fa-print"></i> Solo esta
                            </button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach
</div>
@endif
