@if (($grupos ?? []) === [])
    <p class="text-muted mb-0">{{ $vacio }}</p>
@else
    <div class="d-flex flex-wrap">
        @foreach ($grupos as $grupo)
            <article class="tc-cierre-doc mr-2 mb-2" style="min-width:16rem;flex:1;">
                <header>
                    <strong>{{ $grupo['titulo'] }}</strong>
                    <span>{{ number_format($grupo['monto'], 2, ',', '.') }}</span>
                </header>
                <ul class="list-unstyled mb-0 px-2">
                    @foreach ($grupo['filas'] as $fila)
                        @php
                            $denominacionFila = (float) $fila['valor']->denominacion;
                            $denominacionTexto = abs($denominacionFila - round($denominacionFila)) < 0.001
                                ? number_format($denominacionFila, 0, ',', '.')
                                : number_format($denominacionFila, 2, ',', '.');
                        @endphp
                        <li>
                            <span>
                                @if (! empty($detalleBillete))
                                    <button type="button" class="btn-accion-tabla js-arqueo-billete" title="Operaciones de este billete"
                                            data-valor="{{ (int) $fila['valor']->id }}">
                                        <i class="fa fa-list"></i>
                                    </button>
                                @endif
                                {{ $denominacionTexto }} × {{ number_format($fila['cantidad'], 0, ',', '.') }}
                            </span>
                            <span>{{ number_format($fila['monto'], 2, ',', '.') }}</span>
                        </li>
                    @endforeach
                </ul>
            </article>
        @endforeach
    </div>
@endif
