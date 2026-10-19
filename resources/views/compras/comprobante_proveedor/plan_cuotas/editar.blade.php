@extends("theme.$theme.layout")
@section('titulo')
    Plan de cuotas {{ $etiqueta }}
@endsection
@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">{{ $etiqueta }}</h3>
                <div class="card-tools">
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('plan_cuotas_comprobante_proveedor') }}">Volver</a>
                    @if (can('editar-comprobante-proveedor', false))
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('editar_comprobante_proveedor', $data->id) }}">Editar factura</a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <p>
                    Proveedor <strong>{{ $data->proveedores->codigo ?? '' }} {{ $data->proveedores->nombre ?? '' }}</strong>.
                    Total <strong>{{ number_format((float) $data->total, 2, ',', '.') }} {{ $data->monedas->abreviatura ?? '' }}</strong>.
                    Cotización de la factura <strong>{{ number_format((float) $data->cotizacion, 2, ',', '.') }}</strong>.
                    Condición actual: <strong>{{ $data->condicionpagos->nombre ?? 'sin condición' }}</strong>.
                    Estado {{ $data->estado }}.
                </p>
                @if ($cotizaciones_distintas->count() > 1)
                    <div class="alert alert-warning">
                        Las cuotas tienen cotizaciones distintas
                        ({{ $cotizaciones_distintas->map(fn ($c) => number_format((float) $c, 2, ',', '.'))->implode(' · ') }}).
                        Al guardar quedan todas con la cotización de la factura. Eso pasa cuando cada vencimiento se importó
                        desde Anita con el tipo de cambio de ese movimiento.
                    </div>
                @else
                    <p class="text-muted">
                        La cotización de liquidación de cada cuota es la de la factura, no la del día de cada vencimiento.
                        Si en la orden de pago ves el mismo número en todas las filas, el plan está alineado.
                    </p>
                @endif
                @if (! $impago)
                    <div class="alert alert-danger">Este comprobante tiene pagos aplicados. No se puede rearmar el plan.</div>
                @endif

                <form method="post" action="{{ route('actualizar_plan_cuotas_comprobante_proveedor', $data->id) }}" id="form-plan-cuotas">
                    @csrf
                    <div class="form-row align-items-end mb-3">
                        <div class="col-md-5">
                            <label for="condicionpago_id">Condición de pago</label>
                            <select name="condicionpago_id" id="condicionpago_id" class="form-control" @disabled(! $impago)>
                                <option value="">Sin condición (no recalcula vencimientos)</option>
                                @foreach ($condiciones as $condicion)
                                    <option value="{{ $condicion->id }}" @selected((int) old('condicionpago_id', $data->condicionpago_id) === (int) $condicion->id)>
                                        {{ $condicion->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-7 mt-2">
                            <button type="submit" name="accion" value="unificar" class="btn btn-primary" @disabled(! $impago)
                                onclick="return confirm('Se unifica en una cuota con el vencimiento de la primera, la moneda y la cotización de la factura. La condición de pago queda vacía para que esa fecha no se recalcule.');">
                                Unificar en 1 cuota
                            </button>
                            <button type="submit" name="accion" value="condicion" class="btn btn-outline-primary" @disabled(! $impago)>
                                Armar según condición
                            </button>
                        </div>
                    </div>

                    <table class="table table-sm table-bordered" id="tabla-plan-cuotas">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th style="width:5rem;">Cuota</th>
                                <th>Vencimiento</th>
                                <th class="text-right">Importe</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cuotas as $cuota)
                                <tr>
                                    <td class="text-center js-nro">{{ $cuota->numero_cuota }}</td>
                                    <td>
                                        <input type="date" name="cuota_fechavencimiento[]" class="form-control form-control-sm"
                                            value="{{ optional($cuota->fechavencimiento)->format('Y-m-d') }}" @disabled(! $impago)>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" name="cuota_monto[]" class="form-control form-control-sm text-right"
                                            value="{{ number_format((float) $cuota->monto, 2, '.', '') }}" @disabled(! $impago)>
                                        <input type="hidden" name="cuota_formapago_id[]" value="{{ (int) ($cuota->formapago_id ?: 1) }}">
                                    </td>
                                    <td class="text-center">
                                        @if ($impago)
                                            <button type="button" class="btn btn-sm btn-outline-danger js-quitar-cuota">Quitar</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($impago)
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="js-agregar-cuota">Agregar cuota</button>
                        <button type="submit" name="accion" value="manual" class="btn btn-success">Guardar plan manual</button>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
@section('scripts')
<script>
(function () {
    var tabla = document.getElementById('tabla-plan-cuotas');
    if (!tabla) return;
    function renumerar() {
        tabla.querySelectorAll('tbody tr').forEach(function (tr, i) {
            var celda = tr.querySelector('.js-nro');
            if (celda) celda.textContent = String(i + 1);
        });
    }
    document.getElementById('js-agregar-cuota')?.addEventListener('click', function () {
        var body = tabla.querySelector('tbody');
        var base = body.querySelector('tr');
        var tr = base ? base.cloneNode(true) : null;
        if (!tr) {
            tr = document.createElement('tr');
            tr.innerHTML = '<td class="text-center js-nro"></td>'
                + '<td><input type="date" name="cuota_fechavencimiento[]" class="form-control form-control-sm"></td>'
                + '<td><input type="number" step="0.01" min="0" name="cuota_monto[]" class="form-control form-control-sm text-right" value="0.00">'
                + '<input type="hidden" name="cuota_formapago_id[]" value="1"></td>'
                + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger js-quitar-cuota">Quitar</button></td>';
        } else {
            tr.querySelectorAll('input').forEach(function (input) {
                if (input.type === 'date') input.value = '';
                if (input.type === 'number') input.value = '0.00';
            });
        }
        body.appendChild(tr);
        renumerar();
    });
    tabla.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.js-quitar-cuota');
        if (!btn) return;
        var filas = tabla.querySelectorAll('tbody tr');
        if (filas.length <= 1) return;
        btn.closest('tr').remove();
        renumerar();
    });
})();
</script>
@endsection
