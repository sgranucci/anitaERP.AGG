@extends("theme.$theme.layout")

@section('titulo')
    Configuración de ingresos y egresos
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Configuración de ingresos y egresos</h3>
            </div>
            <form action="{{ route('actualizar_configuracion_ingresoegreso') }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                @method('PUT')
                <div class="card-body">
                    @php
                        $valorCobranza = old('muestra_cobranza_tarjeta', $muestraCobranzaTarjeta ?? '1');
                        $valorCobranza = (string) $valorCobranza === '1' ? '1' : '0';
                    @endphp
                    <div class="form-group row">
                        <label for="muestra_cobranza_tarjeta" class="col-lg-4 control-label text-right pr-2">Cobranza de tarjeta</label>
                        <div class="col-lg-4">
                            <select class="form-control" name="muestra_cobranza_tarjeta" id="muestra_cobranza_tarjeta" required>
                                <option value="1" @selected($valorCobranza === '1')>Visible</option>
                                <option value="0" @selected($valorCobranza === '0')>Oculta</option>
                            </select>
                            <small class="form-text text-muted">
                                Visible: al cargar un ingreso o egreso aparece la opción Cobranza de tarjeta.
                                Oculta: esa opción no se ofrece. Un movimiento que ya la tiene se puede seguir editando.
                            </small>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-4"></div>
                        <div class="col-lg-6">
                            @if (can('actualizar-configuracion-ingresoegreso', false))
                                @include('includes.boton-form-editar')
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
