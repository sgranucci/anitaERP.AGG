@extends("theme.$theme.layout")
@section('titulo')
    Cuentas caja gastro por sala
@endsection

@section('styles')
<style>
    .gc-sala-md-master .list-group-item { cursor: pointer; }
    .gc-sala-md-master .list-group-item.active { background:#2471A3; border-color:#2471A3; }
    .gc-sala-md-tabla thead th { background:#85C1E9; color:#17202A; }
    .gc-sala-md-tabla .tm-cuentacaja-campo { margin-bottom: 0; }
    .gc-sala-panel { display: none; }
    .gc-sala-panel.is-active { display: block; }
</style>
@endsection

@section('scripts')
<script>
    window.GastroCrownSalaCuentacaja = {
        puedeEditar: @json((bool) $puedeEditar),
        salas: @json($salas),
        urls: {
            leerPorCodigo: @json(url('caja/cuentacaja/leercuentacajaporcodigo')),
            consulta: @json(url('caja/cuentacaja/consultacuentacaja')),
            editarBase: @json(url('caja/cuentacaja')),
        }
    };
</script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia_crown/configuracion_cuentacaja_sala.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia_crown/configuracion_cuentacaja_sala.js')) }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cuentas de caja gastronomía por sala (Crown)</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Master-detail: elegí la sala y cargá <strong>una o más</strong> cuentas
                    (efectivo $, u\$s, euro, o una tarjeta especial de esa sala).
                    Marcá <strong>Default F5</strong> en la cuenta de efectivo local que usa el POS al cobrar directo.
                    Los medios comunes (Visa, MP, …) siguen con el uso <strong>Gastronomia</strong> en el ABM de cuentas.
                </p>

                <form method="POST" action="{{ route('actualizar_gastro_crown_sala_cuentacaja') }}" data-enter-campos="1"
                      id="form-gc-sala-cuentacaja">
                    @csrf
                    @method('PUT')

                    <div class="row">
                        <div class="col-lg-3 mb-3">
                            <div class="card card-outline card-info mb-0">
                                <div class="card-header py-2">
                                    <strong>Salas</strong>
                                </div>
                                <div class="list-group list-group-flush gc-sala-md-master" id="gc-sala-master">
                                    @foreach ($salas as $i => $sala)
                                        <button type="button"
                                                class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $i === 0 ? 'active' : '' }}"
                                                data-sala-id="{{ $sala['id'] }}">
                                            <span><strong>{{ $sala['codigo'] }}</strong> — {{ $sala['nombre'] }}</span>
                                            <span class="badge badge-secondary badge-pill gc-sala-count" data-sala-id="{{ $sala['id'] }}">{{ $sala['cantidad'] }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-9 mb-3">
                            @foreach ($salas as $i => $sala)
                                @php $sid = $sala['id']; @endphp
                                <div class="gc-sala-panel {{ $i === 0 ? 'is-active' : '' }}" data-sala-id="{{ $sid }}"
                                     data-empresa-id="{{ $sala['empresa_id'] ?: '' }}">
                                    <input type="hidden" name="salas[{{ $sid }}][sala_id]" value="{{ $sid }}">
                                    <div class="d-flex align-items-center mb-2" style="gap:0.5rem;">
                                        <h5 class="mb-0">{{ $sala['codigo'] }} — {{ $sala['nombre'] }}</h5>
                                        @if ($puedeEditar)
                                            <button type="button" class="btn btn-sm btn-outline-primary ml-auto gc-agregar-linea"
                                                    data-sala-id="{{ $sid }}">
                                                <i class="fa fa-plus"></i> Agregar cuenta
                                            </button>
                                        @endif
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered gc-sala-md-tabla mb-0">
                                            <thead>
                                                <tr>
                                                    <th style="width:52%;">Cuenta de caja</th>
                                                    <th style="width:10%;">Mon.</th>
                                                    <th style="width:14%;" class="text-center">Default F5</th>
                                                    <th style="width:10%;">Orden</th>
                                                    <th style="width:8%;"></th>
                                                </tr>
                                            </thead>
                                            <tbody class="gc-sala-tbody" data-sala-id="{{ $sid }}">
                                                @forelse ($sala['lineas'] as $j => $linea)
                                                    @include('ventas.gastronomia_crown.configuracion_cuentacaja_sala.partials.fila_cuenta', [
                                                        'salaId' => $sid,
                                                        'indice' => $j,
                                                        'linea' => $linea,
                                                        'puedeEditar' => $puedeEditar,
                                                    ])
                                                @empty
                                                    @if ($puedeEditar)
                                                        @include('ventas.gastronomia_crown.configuracion_cuentacaja_sala.partials.fila_cuenta', [
                                                            'salaId' => $sid,
                                                            'indice' => 0,
                                                            'linea' => [
                                                                'cuentacaja_id' => '',
                                                                'codigo' => '',
                                                                'nombre' => '',
                                                                'moneda_abreviatura' => '',
                                                                'es_default_f5' => true,
                                                                'orden' => 1,
                                                            ],
                                                            'puedeEditar' => $puedeEditar,
                                                        ])
                                                    @else
                                                        <tr class="gc-sala-vacia">
                                                            <td colspan="5" class="text-muted text-center py-3">Sin cuentas</td>
                                                        </tr>
                                                    @endif
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if ($puedeEditar)
                        <div class="mt-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-save"></i> Guardar mapa
                            </button>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>

<template id="gc-template-fila-cuenta">
    @include('ventas.gastronomia_crown.configuracion_cuentacaja_sala.partials.fila_cuenta', [
        'salaId' => '__SALA__',
        'indice' => '__IDX__',
        'linea' => [
            'cuentacaja_id' => '',
            'codigo' => '',
            'nombre' => '',
            'moneda_abreviatura' => '',
            'es_default_f5' => false,
            'orden' => 0,
        ],
        'puedeEditar' => true,
    ])
</template>

@include('includes.caja.modalconsultacuentacaja')
@endsection
