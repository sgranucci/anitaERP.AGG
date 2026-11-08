@extends("theme.$theme.layout")

@section('titulo')
    Nuevo movimiento de mesa
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/tesoreria-crown-jornada.css') }}?v={{ filemtime(public_path('assets/css/tesoreria-crown-jornada.css')) }}">
@endsection

@section('contenido')
@include('includes.mensaje')
@include('caja.tesoreria_crown.jornada.partials.desk_nav', [
    'navActivo' => 'mesas',
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
<div class="row">
    <div class="col-lg-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    Nuevo movimiento · {{ $jornada->fecha->format('d/m/Y') }}
                    — {{ $mesa->codigo }} {{ $mesa->nombre }}
                </h3>
                <div class="card-tools">
                    <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel=mesas&amp;mesa={{ urlencode($mesa->codigo) }}#detalle" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al escritorio
                    </a>
                </div>
            </div>
            <form method="POST" action="{{ route('crear_tesoreria_crown_mesa') }}" class="form-horizontal" data-enter-campos="1" autocomplete="off">
                @csrf
                <input type="hidden" name="jornada_id" value="{{ $jornada->id }}">
                <input type="hidden" name="deposito_id" value="{{ $mesa->id }}">
                <div class="card-body">
                    @include('includes.form-error')
                    @if ($banca)
                        <p class="mb-3">Banca {{ $banca->codigo }} {{ $banca->nombre }}, vigente desde {{ $banca->vigencia_desde->format('d/m/Y') }}.</p>
                    @else
                        <p class="text-muted mb-3">Esta mesa no tiene una banca vigente para esta fecha.</p>
                    @endif
                    <p class="text-muted mb-3">Elegí el tipo. El movimiento se abre para cargar las cantidades.</p>
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
                </div>
            </form>
        </div>
    </div>
</div>
@include('caja.tesoreria_crown.partials.modal_consulta_cajero')
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/cajero.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/cajero.js')) }}"></script>
<script>
(function () {
    var campo = document.querySelector('.tc-cajero-codigo');
    if (!campo || campo.readOnly || campo.disabled) {
        return;
    }
    campo.focus();
})();
</script>
@endsection
