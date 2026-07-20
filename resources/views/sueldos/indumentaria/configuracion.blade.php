@extends("theme.$theme.layout")
@section('titulo')
    Configuración de indumentaria
@endsection

@section('contenido')
@php $puedeEditar = can('editar-configuracion-indumentaria', false); @endphp
<div class="row">
    <div class="col-lg-8">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-cogs"></i> Configuración de entrega de indumentaria</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_prenda_sueldos') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-arrow-left"></i> Volver a Prendas
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_config_indumentaria') }}" method="POST" class="form-horizontal">
                @csrf @method('put')
                <div class="card-body">
                    <p class="text-muted">
                        La entrega de prendas descuenta stock y genera el asiento contable reutilizando el circuito de
                        <strong>Movimientos de stock</strong> (la cuenta contable se toma del artículo, igual que en mov. de stock).
                    </p>

                    <div class="form-group row">
                        <label class="col-lg-4 col-form-label text-right">Depósito de origen <span class="text-danger">*</span></label>
                        <div class="col-lg-8">
                            <select name="deposito_id" class="form-control" {{ $puedeEditar ? '' : 'disabled' }} required>
                                <option value="">— Seleccione —</option>
                                @foreach ($depositos as $d)
                                    <option value="{{ $d->id }}" {{ (int) old('deposito_id', $config->deposito_id) === (int) $d->id ? 'selected' : '' }}>
                                        {{ $d->codigo ? $d->codigo.' - ' : '' }}{{ $d->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Depósito del que salen las prendas al entregarlas.</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-4 col-form-label text-right">Tipo de transacción de stock <span class="text-danger">*</span></label>
                        <div class="col-lg-8">
                            <select name="tipotransaccion_stock_id" class="form-control" {{ $puedeEditar ? '' : 'disabled' }} required>
                                <option value="">— Seleccione —</option>
                                @foreach ($tipos as $t)
                                    <option value="{{ $t->id }}" {{ (int) old('tipotransaccion_stock_id', $config->tipotransaccion_stock_id) === (int) $t->id ? 'selected' : '' }}>
                                        {{ $t->nombre }} ({{ $t->abreviatura }}){{ $t->maneja_contabilidad ? ' · con asiento' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Salida de stock que maneja contabilidad. Preconfigurado: ENTREGA DE INDUMENTARIA.</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-4 col-form-label text-right">Centro de costo (opcional)</label>
                        <div class="col-lg-8">
                            <select name="centrocosto_id" class="form-control" {{ $puedeEditar ? '' : 'disabled' }}>
                                <option value="">— Usa el del empleado —</option>
                                @foreach ($centrocostos as $c)
                                    <option value="{{ $c->id }}" {{ (int) old('centrocosto_id', $config->centrocosto_id) === (int) $c->id ? 'selected' : '' }}>
                                        {{ $c->codigo }} - {{ $c->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Si se deja vacío, el asiento usa el centro de costo del empleado.</small>
                        </div>
                    </div>
                </div>
                @if ($puedeEditar)
                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Guardar configuración</button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection
