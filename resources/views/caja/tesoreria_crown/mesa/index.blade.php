@extends("theme.$theme.layout")

@section('titulo')
    Mesas
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/cajero.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/cajero.js')) }}"></script>
@endsection

@section('contenido')
@include('includes.mensaje')
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    Mesas
                    @if ($jornada)
                        · {{ $jornada->fecha->format('d/m/Y') }}
                    @endif
                </h3>
                <div class="card-tools">
                    @if ($jornada)
                        <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel=mesas{{ $mesa ? '&mesa='.urlencode($mesa->codigo) : '' }}#{{ $mesa ? 'detalle' : 'piso' }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-reply-all"></i> Volver a la jornada
                        </a>
                    @endif
                </div>
            </div>
            @include('caja.tesoreria_crown.partials.sala_chips', ['ruta' => 'tesoreria_crown_mesa'])
            <div class="card-body">
                <p class="text-muted">
                    El stock usa la banca que regía en la fecha de la jornada. Una banca cargada hoy no cambia el drop ni las fichas de un día anterior.
                </p>
                @if (! $jornada)
                    <p>Esta sala no tiene jornada.</p>
                @elseif ($mesas->isEmpty())
                    <p>Esta sala no tiene mesas. Se cargan como depósitos de tipo Mesa.</p>
                @else
                    <form method="GET" action="{{ route('tesoreria_crown_mesa') }}" class="form-inline mb-3">
                        <input type="hidden" name="sala_id" value="{{ $sala->id }}">
                        <input type="hidden" name="jornada_id" value="{{ $jornada->id }}">
                        <label for="mesa_codigo" class="mr-2">Mesa</label>
                        <input type="text" name="mesa_codigo" id="mesa_codigo" class="form-control mr-2" placeholder="Número" value="{{ $mesa->codigo ?? request('mesa_codigo') }}" style="width:8rem;">
                        <button type="submit" class="btn btn-info">Ver</button>
                    </form>
                    @if (! $mesa)
                        <p class="text-muted">No hay una mesa con ese número.</p>
                    @else
                        <h5>{{ $mesa->codigo }} — {{ $mesa->nombre }}</h5>
                        @if ($stock && $stock['banca'])
                            <p>Banca {{ $stock['banca']->codigo }} {{ $stock['banca']->nombre }}, vigente desde {{ $stock['banca']->vigencia_desde->format('d/m/Y') }}.</p>
                        @else
                            <p class="text-muted">Esta mesa no tiene una banca vigente para esta fecha.</p>
                        @endif

                        @include('caja.tesoreria_crown.mesa.partials.cantidades', ['titulo' => 'Apertura', 'filas' => $apertura])
                        @include('caja.tesoreria_crown.mesa.partials.cantidades', ['titulo' => 'Stock según movimientos', 'filas' => $saldo])
                        @include('caja.tesoreria_crown.mesa.partials.cantidades', ['titulo' => 'Estado final contado', 'filas' => $estadoFinal])
                        @include('caja.tesoreria_crown.mesa.partials.cantidades', ['titulo' => 'Drop', 'filas' => $drop])

                        @if ($jornada->estaAbierta() && can('crear-tesoreria-crown-mesa', false))
                            <form method="POST" action="{{ route('crear_tesoreria_crown_mesa') }}" class="border rounded p-3 mt-3" data-enter-campos="1">
                                @csrf
                                <input type="hidden" name="jornada_id" value="{{ $jornada->id }}">
                                <input type="hidden" name="deposito_id" value="{{ $mesa->id }}">
                                <div class="form-row">
                                    @include('caja.tesoreria_crown.partials.campo_cajero', [
                                        'salaId' => $sala->id,
                                        'codigo' => old('cajero_numero'),
                                        'nombre' => '',
                                        'inputId' => 'cajero_numero',
                                    ])
                                    <div class="form-group col-md-5 mb-2">
                                        <label for="comentario">Comentario</label>
                                        <input type="text" name="comentario" id="comentario" class="form-control" maxlength="80" value="{{ old('comentario') }}">
                                    </div>
                                </div>
                                @include('caja.tesoreria_crown.partials.operaciones_mesa')
                            </form>
                        @endif

                        <h5 class="mt-4">Movimientos de la mesa</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Número</th>
                                        <th>Tipo</th>
                                        <th>Cajero</th>
                                        <th>Banca</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($movimientos as $mov)
                                        <tr>
                                            <td><a class="text-primary" href="{{ route('editar_tesoreria_crown_mesa', $mov->id) }}">{{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($mov->numero) }}</a></td>
                                            <td>{{ \App\Support\Caja\TesoreriaCrownMesaSupport::tipo($mov->tipo) }}</td>
                                            <td>{{ $mov->cajero->nombre ?? '' }}</td>
                                            <td>{{ $mov->banca ? $mov->banca->nombre.' desde '.$mov->banca->vigencia_desde->format('d/m/Y') : '' }}</td>
                                            <td>{{ \App\Support\Caja\TesoreriaCrownMesaSupport::estado($mov->estado) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted py-3">No hay movimientos.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if (can('editar-tesoreria-crown-mesa', false) && $juego)
                        <h5 class="mt-4">Nueva vigencia de la banca {{ $juego->banca_codigo }}</h5>
                        <p class="text-muted small">Rige desde la fecha que indiques, de hoy en adelante. No reescribe la banca de los días ya jugados.</p>
                        <form method="POST" action="{{ route('guardar_banca_tesoreria_crown_mesa') }}" data-enter-campos="1">
                            @csrf
                            <input type="hidden" name="sala_id" value="{{ $sala->id }}">
                            <input type="hidden" name="codigo" value="{{ $juego->banca_codigo }}">
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label for="nombre">Nombre</label>
                                    <input type="text" name="nombre" id="nombre" class="form-control" maxlength="40" required value="{{ old('nombre', $stock && $stock['banca'] ? $stock['banca']->nombre : '') }}">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="vigencia_desde">Desde</label>
                                    <input type="date" name="vigencia_desde" id="vigencia_desde" class="form-control" required value="{{ old('vigencia_desde', now()->toDateString()) }}" min="{{ now()->toDateString() }}">
                                </div>
                            </div>
                            @foreach ($gruposFichas as $grupo)
                                <h6>{{ $grupo['titulo'] }}</h6>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm table-bordered">
                                        <thead style="background:#85C1E9;color:#17202A;"><tr><th>Ficha</th><th style="width:120px;">Cantidad</th></tr></thead>
                                        <tbody>
                                            @foreach ($grupo['filas'] as $valor)
                                                <tr>
                                                    <td>{{ $valor->descripcion }}</td>
                                                    <td><input type="number" name="cantidad[{{ $valor->id }}]" class="form-control form-control-sm text-right" min="0" step="1" inputmode="numeric"></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endforeach
                            <button type="submit" class="btn btn-outline-primary">Guardar vigencia</button>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@include('caja.tesoreria_crown.partials.modal_consulta_cajero')
@endsection
