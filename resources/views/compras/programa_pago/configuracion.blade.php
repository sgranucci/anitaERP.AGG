@extends("theme.$theme.layout")
@section('titulo')
    Configuración — Programa de pagos
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Asignación automática de cheques</h3>
                <div class="card-tools">
                    <a href="{{ route('programa_pago') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver a programa de pagos
                    </a>
                </div>
            </div>
            <form method="POST" action="{{ route('actualizar_configuracion_programa_pago') }}" class="form-horizontal" data-enter-campos="1">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Al asignar CHT en cartera, el sistema toma primero los cheques de mayor importe
                        y prioriza usar la menor cantidad posible. El sobrante (cheques por encima del
                        programado neto) queda como anticipo a proveedores, con el tope de abajo.
                    </p>
                    <div class="form-group row">
                        <label for="cheque_anticipo_maximo" class="col-lg-4 control-label text-right pr-2">
                            Tope de anticipo (sobrante)
                        </label>
                        <div class="col-lg-3">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">$</span>
                                </div>
                                <input type="number"
                                       step="0.01"
                                       min="0"
                                       class="form-control"
                                       id="cheque_anticipo_maximo"
                                       name="cheque_anticipo_maximo"
                                       value="{{ old('cheque_anticipo_maximo', number_format((float) $anticipoMaximo, 2, '.', '')) }}"
                                       required>
                            </div>
                            <small class="form-text text-muted">
                                Default {{ number_format(\App\Support\Caja\ProgramaPagoAsignarChequesConfigSupport::ANTICIPO_MAXIMO_DEFAULT, 0, ',', '.') }}.
                                Si la suma de cheques supera el neto en más que este importe, no se acepta ese paquete.
                            </small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="cheque_faltante_tolerancia_pct" class="col-lg-4 control-label text-right pr-2">
                            Tolerancia de faltante
                        </label>
                        <div class="col-lg-3">
                            <div class="input-group">
                                <input type="number"
                                       step="0.01"
                                       min="0"
                                       max="100"
                                       class="form-control"
                                       id="cheque_faltante_tolerancia_pct"
                                       name="cheque_faltante_tolerancia_pct"
                                       value="{{ old('cheque_faltante_tolerancia_pct', number_format((float) $faltanteToleranciaPct, 2, '.', '')) }}"
                                       required>
                                <div class="input-group-append">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <small class="form-text text-muted">
                                Default {{ (int) \App\Support\Caja\ProgramaPagoAsignarChequesConfigSupport::FALTANTE_TOLERANCIA_PCT_DEFAULT }}%.
                                Máximo que la suma de cheques puede quedar por debajo del programado neto.
                            </small>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-save"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
