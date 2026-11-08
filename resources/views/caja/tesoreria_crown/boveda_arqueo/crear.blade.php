@extends("theme.$theme.layout")

@section('titulo')
    Nuevo arqueo de turno
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/tesoreria-crown-jornada.css') }}?v={{ filemtime(public_path('assets/css/tesoreria-crown-jornada.css')) }}">
@endsection

@section('contenido')
@include('includes.mensaje')
@if ($escritorio)
    @include('caja.tesoreria_crown.jornada.partials.desk_nav', [
        'navActivo' => 'boveda',
        'puedeMesas' => $escritorio['puedeMesas'],
        'puedeBoveda' => $escritorio['puedeBoveda'],
        'navAsignaciones' => $escritorio['asignaciones'],
        'navMesas' => $escritorio['mesas'],
        'navBorradoresMesa' => $escritorio['borradoresMesa'],
        'navBoveda' => $escritorio['movimientosBoveda'],
        'puedeSlots' => $escritorio['puedeSlots'],
        'puedePoder' => $escritorio['puedePoder'],
        'puedeAnexo' => $escritorio['puedeAnexo'],
        'puedeKardex' => $escritorio['puedeKardex'] ?? false,
        'navSlots' => $escritorio['slots'],
    ])
@endif
@php
    $gruposPreview = [];
    foreach ($calculo['lineas'] as $linea) {
        $titulo = (string) ($linea['valor']->tipoValor->nombre ?? 'Valor');
        $gruposPreview[$titulo]['titulo'] = $titulo;
        $gruposPreview[$titulo]['filas'][] = $linea;
        $gruposPreview[$titulo]['totales']['inicial'] = ($gruposPreview[$titulo]['totales']['inicial'] ?? 0) + $linea['monto_inicial'];
        $gruposPreview[$titulo]['totales']['ingresos'] = ($gruposPreview[$titulo]['totales']['ingresos'] ?? 0) + $linea['monto_ingresos'];
        $gruposPreview[$titulo]['totales']['egresos'] = ($gruposPreview[$titulo]['totales']['egresos'] ?? 0) + $linea['monto_egresos'];
        $gruposPreview[$titulo]['totales']['actual'] = ($gruposPreview[$titulo]['totales']['actual'] ?? 0) + $linea['monto_actual'];
    }
@endphp
<div class="row">
    <div class="col-lg-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    Nuevo arqueo · {{ $jornada->fecha->format('d/m/Y') }}
                    — {{ $lugar->codigo }} {{ $lugar->nombre }}
                </h3>
                <div class="card-tools">
                    <a href="{{ route('tesoreria_crown_boveda_arqueo', [
                        'sala_id' => $sala->id ?? $jornada->sala_id,
                        'jornada_id' => $jornada->id,
                        'deposito_id' => $lugar->id,
                    ]) }}" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    Corte al <strong>{{ $calculo['corte_at']->format('d/m/Y H:i') }}</strong>
                    · Desde
                    @if ($calculo['anterior'])
                        arqueo {{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible((int) $calculo['anterior']->numero) }}
                        ({{ $calculo['desde_at']->format('d/m/Y H:i') }})
                    @else
                        apertura / inicio de jornada ({{ $calculo['desde_at']->format('d/m/Y H:i') }})
                    @endif
                </p>
                @if ($calculo['lineas'] === [])
                    <div class="alert alert-warning">No hay valores para armar el arqueo.</div>
                @else
                    @include('caja.tesoreria_crown.boveda_arqueo.partials.tabla_lineas', [
                        'grupos' => array_values($gruposPreview),
                    ])
                    <form method="POST" action="{{ route('crear_tesoreria_crown_boveda_arqueo') }}" class="form-horizontal mt-3" data-enter-campos="1">
                        @csrf
                        <input type="hidden" name="jornada_id" value="{{ $jornada->id }}">
                        <input type="hidden" name="deposito_id" value="{{ $lugar->id }}">
                        <div class="form-group row">
                            <label for="observacion" class="col-lg-2 control-label text-right pr-2">Observación</label>
                            <div class="col-lg-8">
                                <input type="text" name="observacion" id="observacion" class="form-control" maxlength="120" value="{{ old('observacion') }}">
                            </div>
                        </div>
                        <div class="form-group row mb-0">
                            <div class="col-lg-10 offset-lg-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fa fa-save"></i> Guardar borrador
                                </button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
