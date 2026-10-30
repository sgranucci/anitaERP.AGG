@php
    $filasPiso = $tableroMesas['mesas'] ?? [];
    $enJuego = collect($filasPiso)->where('estado', 'juego')->count();
    $conFinal = collect($filasPiso)->where('estado', 'final')->count();
    $conBorrador = collect($filasPiso)->where('estado', 'borrador')->count();
    $mesaActiva = $mesaFila['mesa'] ?? null;
    $bancaActiva = $mesaFila['banca'] ?? null;
    $movimientosActivos = $mesaFila['movimientos'] ?? collect();
    $sumaMesa = function (array $filas): float {
        return (float) array_sum(array_column($filas, 'monto'));
    };
    $textoMesa = function (float $monto, array $filas): string {
        return $filas === [] ? '—' : number_format($monto, 2, ',', '.');
    };
    $urlMesa = function (?string $codigo = null) use ($jornada): string {
        $query = ['panel' => 'mesas'];
        if ($codigo !== null && $codigo !== '') {
            $query['mesa'] = $codigo;
        }

        return route('ver_tesoreria_crown_jornada', $jornada->id).'?'.http_build_query($query);
    };
@endphp

@if ($filasPiso === [])
    <p class="mb-0">Esta sala no tiene mesas. Se cargan como depósitos de tipo Mesa.</p>
@else
    @if ($mesaActiva)
        <div class="card tc-mesa-detalle" id="detalle">
            <div class="card-header d-flex flex-wrap align-items-center">
                <h3 class="card-title mb-0 mr-auto">
                    {{ $mesaActiva->codigo }} — {{ $mesaActiva->nombre }}
                </h3>
                <a href="{{ $urlMesa() }}#piso" class="small">Ver el piso</a>
            </div>
            <div class="card-body">
                @if ($bancaActiva)
                    <p class="mb-2">Banca {{ $bancaActiva->codigo }} {{ $bancaActiva->nombre }}, vigente desde {{ $bancaActiva->vigencia_desde->format('d/m/Y') }}.</p>
                @else
                    <p class="text-muted mb-2">Esta mesa no tiene una banca vigente para esta fecha.</p>
                @endif

                <div class="tc-mesa-metricas mb-2">
                    <div class="tc-mesa-metrica">
                        <span>Apertura</span>
                        <strong>{{ $textoMesa($sumaMesa($aperturaMesa), $aperturaMesa) }}</strong>
                    </div>
                    <div class="tc-mesa-metrica">
                        <span>Stock</span>
                        <strong>{{ $textoMesa($sumaMesa($saldoMesa), $saldoMesa) }}</strong>
                    </div>
                    <div class="tc-mesa-metrica">
                        <span>Estado final</span>
                        <strong>{{ $textoMesa($sumaMesa($estadoFinalMesa), $estadoFinalMesa) }}</strong>
                    </div>
                    <div class="tc-mesa-metrica">
                        <span>Drop</span>
                        <strong>{{ $textoMesa($sumaMesa($dropMesa), $dropMesa) }}</strong>
                    </div>
                </div>

                @if ($jornada->estaAbierta() && can('crear-tesoreria-crown-mesa', false))
                    <form method="POST" action="{{ route('crear_tesoreria_crown_mesa') }}" class="mb-3" data-enter-campos="1">
                        @csrf
                        <input type="hidden" name="jornada_id" value="{{ $jornada->id }}">
                        <input type="hidden" name="deposito_id" value="{{ $mesaActiva->id }}">
                        <div class="form-row align-items-end">
                            @include('caja.tesoreria_crown.partials.campo_cajero', [
                                'salaId' => $jornada->sala_id,
                                'codigo' => old('cajero_numero'),
                                'nombre' => '',
                                'inputId' => 'cajero_numero_mesa',
                            ])
                            <div class="form-group col-md-5 mb-2">
                                <label for="comentario_mesa">Comentario</label>
                                <input type="text" name="comentario" id="comentario_mesa" class="form-control" maxlength="80" value="{{ old('comentario') }}">
                            </div>
                        </div>
                        @include('caja.tesoreria_crown.partials.operaciones_mesa')
                    </form>
                @endif

                @if ($movimientosActivos->isNotEmpty())
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered mb-0">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Número</th>
                                    <th>Tipo</th>
                                    <th>Cajero</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($movimientosActivos as $movMesa)
                                    <tr>
                                        <td><a class="text-primary" href="{{ route('editar_tesoreria_crown_mesa', $movMesa->id) }}">{{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($movMesa->numero) }}</a></td>
                                        <td>{{ \App\Support\Caja\TesoreriaCrownMesaSupport::tipo($movMesa->tipo) }}</td>
                                        <td>{{ $movMesa->cajero->nombre ?? '' }}</td>
                                        <td>{{ \App\Support\Caja\TesoreriaCrownMesaSupport::estado($movMesa->estado) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($aperturaMesa !== [] || $saldoMesa !== [] || $estadoFinalMesa !== [] || $dropMesa !== [])
                    <a class="text-primary small" data-toggle="collapse" href="#fichas-mesa-activa" role="button" aria-expanded="false">Ver fichas</a>
                    <div class="collapse" id="fichas-mesa-activa">
                        @include('caja.tesoreria_crown.mesa.partials.cantidades', ['titulo' => 'Apertura', 'filas' => $aperturaMesa])
                        @include('caja.tesoreria_crown.mesa.partials.cantidades', ['titulo' => 'Stock según movimientos', 'filas' => $saldoMesa])
                        @include('caja.tesoreria_crown.mesa.partials.cantidades', ['titulo' => 'Estado final contado', 'filas' => $estadoFinalMesa])
                        @include('caja.tesoreria_crown.mesa.partials.cantidades', ['titulo' => 'Drop', 'filas' => $dropMesa])
                    </div>
                @endif

                <p class="mb-0 mt-3">
                    <a class="text-primary" href="{{ route('tesoreria_crown_mesa', ['sala_id' => $sala->id, 'jornada_id' => $jornada->id, 'mesa_codigo' => $mesaActiva->codigo]) }}">Cambiar la vigencia de la banca</a>
                </p>
            </div>
        </div>
    @endif

    <div id="piso">
        <div class="d-flex flex-wrap align-items-center mb-2">
            <div class="tc-mesa-leyenda mb-0 mr-auto">
                <span>{{ count($filasPiso) }} mesas</span>
                <span><i class="tc-dot-juego"></i>{{ $enJuego }} en juego</span>
                <span><i class="tc-dot-final"></i>{{ $conFinal }} con estado final</span>
                <span><i class="tc-dot-borrador"></i>{{ $conBorrador }} borrador</span>
                <span><i class="tc-dot-vacia"></i>sin movimiento</span>
            </div>
            <input type="search" class="form-control form-control-sm tc-mesa-buscar" id="tc-mesa-buscar" placeholder="Buscar mesa" autocomplete="off">
        </div>
        <div class="tc-mesa-piso">
            @foreach ($filasPiso as $filaPiso)
                @php
                    $mesaPiso = $filaPiso['mesa'];
                    $numeroPiso = ltrim(preg_replace('/\D/', '', (string) $mesaPiso->codigo) ?: (string) $mesaPiso->codigo, '0');
                    $nombrePiso = trim((string) preg_replace('/^\d+\s*/', '', (string) $mesaPiso->nombre));
                    $activa = $mesaActiva && (int) $mesaActiva->id === (int) $mesaPiso->id;
                @endphp
                <a class="tc-mesa-ficha estado-{{ $filaPiso['estado'] }} {{ $activa ? 'is-activa' : '' }}"
                   href="{{ $urlMesa($mesaPiso->codigo) }}#detalle"
                   data-busca="{{ strtolower($mesaPiso->codigo.' '.$mesaPiso->nombre) }}">
                    @if ($filaPiso['drop'])
                        <span class="tc-mesa-drop">DROP</span>
                    @endif
                    <span class="tc-mesa-num">{{ $numeroPiso !== '' ? $numeroPiso : $mesaPiso->codigo }}</span>
                    <span class="tc-mesa-nombre">{{ $nombrePiso !== '' ? $nombrePiso : $mesaPiso->nombre }}</span>
                    <span class="tc-mesa-meta">
                        @if ($filaPiso['banca'])
                            {{ $filaPiso['banca']->nombre }}
                        @else
                            Sin banca
                        @endif
                        @if ($filaPiso['borradores'] > 0)
                            · {{ $filaPiso['borradores'] }} sin confirmar
                        @endif
                    </span>
                </a>
            @endforeach
        </div>
    </div>
    <script>
        (function () {
            var buscador = document.getElementById('tc-mesa-buscar');
            if (!buscador) {
                return;
            }
            buscador.addEventListener('input', function () {
                var texto = buscador.value.toLowerCase();
                document.querySelectorAll('.tc-mesa-ficha').forEach(function (ficha) {
                    var coincide = ficha.getAttribute('data-busca').indexOf(texto) !== -1;
                    ficha.style.display = coincide ? '' : 'none';
                });
            });
        })();
    </script>
@endif
