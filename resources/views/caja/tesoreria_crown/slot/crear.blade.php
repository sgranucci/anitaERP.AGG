@extends("theme.$theme.layout")

@section('titulo')
    Nuevo recuento de slots
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/tesoreria-crown-jornada.css') }}?v={{ filemtime(public_path('assets/css/tesoreria-crown-jornada.css')) }}">
@endsection

@section('contenido')
@include('includes.mensaje')
@php
    $desdeMenu = request()->input('origen') === 'menu';
    $urlVolver = $desdeMenu
        ? route('tesoreria_crown_slot', array_filter(['sala_id' => $jornada->sala_id]))
        : route('ver_tesoreria_crown_jornada', $jornada->id).'?panel=slots';
@endphp
@if (! $desdeMenu)
    @include('caja.tesoreria_crown.jornada.partials.desk_nav', [
        'jornada' => $jornada,
        'navActivo' => 'slots',
        'puedeMesas' => $escritorio['puedeMesas'],
        'puedeBoveda' => $escritorio['puedeBoveda'],
        'puedeSlots' => $escritorio['puedeSlots'],
        'puedePoder' => $escritorio['puedePoder'],
        'puedeAnexo' => $escritorio['puedeAnexo'],
        'puedeKardex' => $escritorio['puedeKardex'] ?? false,
        'navAsignaciones' => $escritorio['asignaciones'],
        'navMesas' => $escritorio['mesas'],
        'navBorradoresMesa' => $escritorio['borradoresMesa'],
        'navBoveda' => $escritorio['movimientosBoveda'],
        'navSlots' => $escritorio['slots'],
    ])
@endif
<div class="row">
    <div class="col-lg-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Nuevo recuento · {{ $jornada->fecha->format('d/m/Y') }}</h3>
                <div class="card-tools">
                    <a href="{{ $urlVolver }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> {{ $desdeMenu ? 'Volver al listado' : 'Volver al escritorio' }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                @include('includes.form-error')
                <p class="text-muted mb-3">Elegí el tipo y la máquina. El drop entra por Anita; acá se carga el recuento del stacker.</p>
                <form method="POST" action="{{ route('crear_tesoreria_crown_slot') }}" data-enter-campos="1">
                    @csrf
                    <input type="hidden" name="jornada_id" value="{{ $jornada->id }}">
                    @if ($desdeMenu)
                        <input type="hidden" name="origen" value="menu">
                    @endif
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="tipo">Tipo</label>
                            <select name="tipo" id="tipo" class="form-control" required>
                                @foreach ($tipos as $codigo => $nombre)
                                    <option value="{{ $codigo }}" @selected(old('tipo') === $codigo)>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="maquina">Máquina</label>
                            <input type="text" name="maquina" id="maquina" class="form-control" maxlength="10" required value="{{ old('maquina') }}" autocomplete="off">
                        </div>
                        <div class="form-group col-md-5">
                            <label for="maquina_nombre">Nombre</label>
                            <input type="text" name="maquina_nombre" id="maquina_nombre" class="form-control" maxlength="30" value="{{ old('maquina_nombre') }}" autocomplete="off">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Abrir recuento</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    var campo = document.getElementById('maquina');
    if (campo) {
        campo.focus();
    }
})();
</script>
@endsection
