@php
    $porFormulario = [];
    $cantidadPapel = 0;
    foreach (($sesion['pack'] ?? []) as $i => $linea) {
        $clave = $linea['formulario'] ?? 'OTRO';
        if (! isset($porFormulario[$clave])) {
            $porFormulario[$clave] = [
                'etiqueta' => $linea['formulario_etiqueta'] ?? $clave,
                'lineas' => [],
            ];
        }
        $porFormulario[$clave]['lineas'][] = ['i' => $i, 'linea' => $linea];
        $esNasLinea = ! empty($linea['es_nas']) || ($linea['medio'] ?? '') === 'ARCHIVO';
        if (! $esNasLinea) {
            $cantidadPapel++;
        }
    }
    $esLoteReparto = ($sesion['origen_tipo'] ?? '') === 'REPARTO' || ! empty($sesion['lote_venta_ids']);
    $mostrarChecksPapel = $cantidadPapel > 1 || $esLoteReparto;
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
                $esNas = ! empty($linea['es_nas']) || ($linea['medio'] ?? '') === 'ARCHIVO';
                $texto = $linea['leyenda'] ?? $linea['copia_codigo'];
                if (! empty($linea['destinatario'])) {
                    $texto .= ' · '.$linea['destinatario'];
                }
                if ($esNas) {
                    $texto = 'NAS · '.$texto;
                }
            @endphp
            <span class="programa-ruta-flecha">&rarr;</span>
            <span class="programa-ruta-nodo {{ $esNas ? 'es-nas' : '' }}">{{ $texto }}</span>
        @endforeach
    @endforeach
</div>
<p class="small text-muted mb-2">
    Las ventanitas amarillas son copia al NAS: se archivan en segundo plano y
    <strong>no entran al PDF</strong> si lo abrís en Acrobat.
</p>
@if ($mostrarChecksPapel)
    <p class="small text-muted mb-2">
        @if ($esLoteReparto)
            Marcá la copia a enviar a todas las facturas del reparto.
        @else
            Marcá las copias de papel a incluir (impresora o PDF).
        @endif
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
                    $esNas = ! empty($linea['es_nas']) || ($linea['medio'] ?? '') === 'ARCHIVO';
                    $res = $resultado['resultados'][$item['i']] ?? null;
                    $estado = 'pendiente';
                    if ($res) {
                        if (($res['estado'] ?? '') === 'pendiente') {
                            $estado = 'pendiente';
                        } elseif (! empty($res['ok'])) {
                            $estado = 'ok';
                        } else {
                            $estado = 'error';
                        }
                    }
                @endphp
                <div class="sesion-ruta-hoja {{ $estado }}{{ $esNas ? ' es-nas' : '' }}">
                    @if ($esNas)
                        <div class="sesion-nas-badge mb-1">NAS · no va al PDF</div>
                    @elseif ($mostrarChecksPapel)
                        <label class="sesion-copia-check mb-1">
                            <input type="checkbox" name="pack_idx[]" value="{{ $item['i'] }}" form="form-ejecutar-sesion" class="sesion-copia-idx" checked>
                            Incluir
                        </label>
                    @endif
                    <div>
                        <strong>{{ $linea['leyenda'] }}</strong>
                        <span class="text-muted">({{ $linea['copia_codigo'] }})</span>
                    </div>
                    @if (! empty($linea['destinatario']))
                        <div>{{ $linea['destinatario'] }}</div>
                    @endif
                    <div class="{{ ! empty($linea['hereda_usuario']) && empty($linea['salida_usuario_ok']) ? 'text-danger' : 'text-muted' }}">
                        @if ($esNas)
                            Archivo NAS · segundo plano
                        @elseif (! empty($linea['hereda_usuario']))
                            Impresora del usuario:
                            {{ $linea['salida_nombre'] }} · {{ $linea['medio'] }}
                        @else
                            {{ $linea['salida_nombre'] }} · {{ $linea['medio'] }}
                        @endif
                    </div>
                    <div>
                        @if ($res)
                            @if (($res['estado'] ?? '') === 'pendiente')
                                En segundo plano — {{ $res['mensaje'] }}
                            @else
                                {{ ! empty($res['ok']) ? 'OK' : 'Error' }} — {{ $res['mensaje'] }}
                            @endif
                        @elseif ($esNas)
                            Se archiva al ejecutar (no sale en Acrobat)
                        @else
                            Se imprime al ejecutar
                        @endif
                    </div>
                    @if ($mostrarChecksPapel || $esNas)
                        <div class="mt-2">
                            <button type="button" class="btn {{ $esNas ? 'btn-outline-warning' : 'btn-outline-primary' }} btn-sm btn-solo-copia" data-pack-idx="{{ $item['i'] }}">
                                @if ($esNas)
                                    <i class="fa fa-archive"></i> Archivar ahora
                                @else
                                    <i class="fa fa-print"></i>
                                    @if ($esLoteReparto)
                                        Solo esta copia
                                    @else
                                        Solo esta
                                    @endif
                                @endif
                            </button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach
</div>
@endif
