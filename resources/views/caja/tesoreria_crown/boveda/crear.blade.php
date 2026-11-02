@extends("theme.$theme.layout")

@section('titulo')
    Nuevo movimiento de bóveda
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/tesoreria-crown-jornada.css') }}?v={{ filemtime(public_path('assets/css/tesoreria-crown-jornada.css')) }}">
@endsection

@section('contenido')
@include('includes.mensaje')
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
    'navSlots' => $escritorio['slots'],
])
<div class="row">
    <div class="col-lg-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Nuevo movimiento · {{ $fecha }} — {{ $lugar->codigo }} {{ $lugar->nombre }}</h3>
                <div class="card-tools d-flex align-items-center" style="gap:.35rem;">
                    @include('caja.tesoreria_crown.boveda.partials.cambios_dia')
                    <a href="{{ route('tesoreria_crown_boveda', ['sala_id' => $jornada->sala_id, 'jornada_id' => $jornada->id, 'deposito_id' => $lugar->id]) }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form method="POST" action="{{ route('crear_tesoreria_crown_boveda') }}" class="form-horizontal form--label-right" data-enter-campos="1" data-enter-boton="1" autocomplete="off">
                @csrf
                <input type="hidden" name="sala_id" value="{{ $jornada->sala_id }}">
                <input type="hidden" name="jornada_id" value="{{ $jornada->id }}">
                <input type="hidden" name="deposito_id" value="{{ $lugar->id }}">
                <div class="card-body">
                    @include('includes.form-error')
                    @include('caja.tesoreria_crown.boveda.partials.form_carga')
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.sueldos.modalconsultaempleado_sueldos')
@include('includes.ventas.modalconsultacliente')
@include('includes.caja.modalconsultabovedatipo')
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/sueldos/empleado/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/cliente/consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/ventas/cliente/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/efeboveda.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/efeboveda.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/boveda_tipo_consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/boveda_tipo_consulta.js')) }}"></script>
<script>
(function () {
    var campo = document.getElementById('boveda_tipo_codigo');
    if (!campo || campo.readOnly || campo.disabled) {
        return;
    }
    campo.focus();
})();
</script>
@endsection
