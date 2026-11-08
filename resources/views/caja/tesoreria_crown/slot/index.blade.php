@extends("theme.$theme.layout")

@section('titulo')
    Slots
@endsection

@section('contenido')
@include('includes.mensaje')
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    Slots
                    @if ($jornada)
                        · {{ $jornada->fecha->format('d/m/Y') }}
                    @endif
                </h3>
                <div class="card-tools">
                    @if ($jornada)
                        <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-desktop"></i> Escritorio
                        </a>
                    @elseif ($sala && can('listar-tesoreria-crown-jornada', false))
                        <a href="{{ route('tesoreria_crown_jornada', ['sala_id' => $sala->id]) }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-calendar"></i> Jornadas
                        </a>
                    @endif
                </div>
            </div>
            @include('caja.tesoreria_crown.partials.sala_chips', ['ruta' => 'tesoreria_crown_slot'])
            @include('caja.tesoreria_crown.partials.jornada_selector', ['ruta' => 'tesoreria_crown_slot'])
            <div class="card-body">
                @if (! $sala)
                    <p class="mb-0">Elegí una sala.</p>
                @elseif (! $jornada)
                    <p class="mb-2">
                        @if (trim((string) ($fechaPedida ?? '')) !== '')
                            No hay jornada el {{ \Illuminate\Support\Carbon::parse($fechaPedida)->format('d/m/Y') }} en esta sala.
                        @else
                            Esta sala no tiene jornada. Abrila para ver el drop y el recuento.
                        @endif
                    </p>
                    @if (can('crear-tesoreria-crown-jornada', false))
                        <a href="{{ route('tesoreria_crown_jornada', ['sala_id' => $sala->id]) }}" class="btn btn-primary btn-sm">
                            <i class="fa fa-plus-circle"></i> Ir a abrir jornada
                        </a>
                    @endif
                @else
                    @include('caja.tesoreria_crown.jornada.partials.slots', [
                        'slotsIndexUrl' => route('tesoreria_crown_slot'),
                        'slotsIndexQuery' => array_filter([
                            'sala_id' => $sala->id,
                            'jornada_id' => $jornada->id,
                        ]),
                    ])
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
