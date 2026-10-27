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
                @if (! empty($bloqueado_ingreso_egreso))
                    <div class="alert alert-warning">
                        <i class="fa fa-lock"></i>
                        Esta factura se cargó desde Ingresos y egresos
                        @if (! empty($numero_ingreso_egreso))
                            (movimiento {{ $numero_ingreso_egreso }})
                        @endif
                        . El plan de cuotas se cambia en ese movimiento.
                        @if (! empty($url_ingreso_egreso))
                            <a href="{{ $url_ingreso_egreso }}" class="alert-link">Abrir el movimiento</a>
                        @endif
                    </div>
                @elseif (! $impago)
                    <div class="alert alert-danger">Este comprobante tiene pagos aplicados. No se puede rearmar el plan.</div>
                @endif

                <form method="post" action="{{ route('actualizar_plan_cuotas_comprobante_proveedor', $data->id) }}" id="form-plan-cuotas">
                    @csrf
                    <div class="form-row align-items-end mb-3">
                        <div class="col-md-5">
                            <label for="condicionpago_id">Condición de pago</label>
                            <select name="condicionpago_id" id="condicionpago_id" class="form-control" @disabled(! $impago || ! empty($bloqueado_ingreso_egreso))>
                                <option value="">Sin condición (no recalcula vencimientos)</option>
                                @foreach ($condiciones as $condicion)
                                    <option value="{{ $condicion->id }}" @selected((int) old('condicionpago_id', $data->condicionpago_id) === (int) $condicion->id)>
                                        {{ $condicion->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-7 mt-2">
                            <button type="submit" name="accion" value="unificar" class="btn btn-primary" @disabled(! $impago || ! empty($bloqueado_ingreso_egreso))
                                onclick="return confirm('Se unifica en una cuota con el vencimiento de la primera, la moneda y la cotización de la factura. La condición de pago queda vacía para que esa fecha no se recalcule.');">
                                Unificar en 1 cuota
                            </button>
                            <button type="submit" name="accion" value="condicion" class="btn btn-outline-primary" @disabled(! $impago || ! empty($bloqueado_ingreso_egreso))>
                                Armar según condición
                            </button>
                        </div>
                    </div>

                    @if ($impago && empty($bloqueado_ingreso_egreso))
                        <div class="border rounded p-3 mb-3 bg-light">
                            <div class="font-weight-bold mb-2">Cargar varias cuotas</div>
                            <div class="form-row align-items-end">
                                <div class="form-group col-md-2 mb-2 mb-md-0">
                                    <label for="plan_cuotas_cantidad">Cantidad</label>
                                    <input type="number" min="1" max="60" id="plan_cuotas_cantidad" class="form-control form-control-sm" value="1" title="Número de cuotas a generar">
                                </div>
                                <div class="form-group col-md-3 mb-2 mb-md-0">
                                    <label for="plan_cuotas_fecha_primera">Fecha 1ª cuota <small class="text-muted font-weight-normal">(mensual)</small></label>
                                    <input type="date" id="plan_cuotas_fecha_primera" class="form-control form-control-sm"
                                        value="{{ optional(optional($cuotas->first())->fechavencimiento)->format('Y-m-d') }}"
                                        title="Vencimiento de la primera cuota; las siguientes suman 1 mes">
                                </div>
                                <div class="form-group col-md-7 mb-0">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="js-cuotas-misma-fecha">Crear / reemplazar (misma fecha)</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="js-cuotas-mensual">Generar cuotas mensuales</button>
                                </div>
                            </div>
                            <p class="text-muted small mb-0 mt-2">
                                Reparte el total de la factura en partes iguales, igual que en el comprobante a venir de la orden de compra.
                                Misma fecha repite el vencimiento en todas las filas; mensual suma un mes entre cuotas.
                                La última cuota absorbe los centavos. Después se pueden ajustar importes y fechas, y guardar el plan manual.
                            </p>
                        </div>
                    @endif

                    <table class="table table-sm table-bordered" id="tabla-plan-cuotas"
                        data-total="{{ number_format(abs((float) $data->total), 2, '.', '') }}">
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
                                        @if ($impago && empty($bloqueado_ingreso_egreso))
                                            <button type="button" class="btn btn-sm btn-outline-danger js-quitar-cuota">Quitar</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="small mb-2" id="js-resumen-plan-cuotas"></p>
                    @if ($impago && empty($bloqueado_ingreso_egreso))
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
    var totalFactura = Math.round((parseFloat(tabla.getAttribute('data-total')) || 0) * 100) / 100;
    var resumen = document.getElementById('js-resumen-plan-cuotas');

    function renumerar() {
        tabla.querySelectorAll('tbody tr').forEach(function (tr, i) {
            var celda = tr.querySelector('.js-nro');
            if (celda) celda.textContent = String(i + 1);
        });
    }

    function fmt(n) {
        return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function actualizarResumen() {
        if (!resumen) return;
        var suma = 0;
        var sinImporte = 0;
        tabla.querySelectorAll('input[name="cuota_monto[]"]').forEach(function (input) {
            var monto = parseFloat(input.value) || 0;
            suma += monto;
            if (monto <= 0) sinImporte++;
        });
        suma = Math.round(suma * 100) / 100;
        var diferencia = Math.round((totalFactura - suma) * 100) / 100;
        var cuadra = Math.abs(diferencia) <= 0.05 && sinImporte === 0;
        resumen.className = 'small mb-2 ' + (cuadra ? 'text-success' : 'text-danger');
        resumen.textContent = 'Suma de cuotas ' + fmt(suma)
            + ' · Total de la factura ' + fmt(totalFactura)
            + ' · ' + (sinImporte > 0
                ? 'Hay cuotas sin importe'
                : (cuadra ? 'Cuadra' : 'Diferencia ' + fmt(diferencia)));
    }

    function formapagoActual() {
        var hidden = tabla.querySelector('input[name="cuota_formapago_id[]"]');
        var id = hidden ? parseInt(hidden.value, 10) : 1;
        return Number.isFinite(id) && id > 0 ? id : 1;
    }

    function htmlFila(fecha, monto, formapagoId) {
        return '<tr>'
            + '<td class="text-center js-nro"></td>'
            + '<td><input type="date" name="cuota_fechavencimiento[]" class="form-control form-control-sm" value="' + fecha + '"></td>'
            + '<td><input type="number" step="0.01" min="0" name="cuota_monto[]" class="form-control form-control-sm text-right" value="' + monto + '">'
            + '<input type="hidden" name="cuota_formapago_id[]" value="' + formapagoId + '"></td>'
            + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger js-quitar-cuota">Quitar</button></td>'
            + '</tr>';
    }

    function ymdAddMonths(ymd, add) {
        var p = String(ymd || '').substring(0, 10).split('-');
        if (p.length !== 3) return '';
        var y = parseInt(p[0], 10);
        var mo = parseInt(p[1], 10) - 1;
        var da = parseInt(p[2], 10);
        if (!Number.isFinite(y) || !Number.isFinite(mo) || !Number.isFinite(da)) return '';
        var first = new Date(y, mo + add, 1);
        var lastDay = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
        first.setDate(Math.min(da, lastDay));
        var mm = first.getMonth() + 1;
        var dd = first.getDate();
        return first.getFullYear() + '-' + (mm < 10 ? '0' : '') + mm + '-' + (dd < 10 ? '0' : '') + dd;
    }

    function montosIguales(n, total) {
        var cents = Math.round(total * 100);
        var base = Math.floor(cents / n);
        var out = [];
        var used = 0;
        for (var i = 0; i < n - 1; i++) {
            out.push((base / 100).toFixed(2));
            used += base;
        }
        out.push(((cents - used) / 100).toFixed(2));
        return out;
    }

    function generar(mensual) {
        var n = parseInt(document.getElementById('plan_cuotas_cantidad').value, 10) || 0;
        if (n < 1 || n > 60) {
            alert('Indique una cantidad entre 1 y 60.');
            return;
        }
        if (totalFactura <= 0) {
            alert('La factura no tiene un total para repartir.');
            return;
        }
        var fecha0 = (document.getElementById('plan_cuotas_fecha_primera').value || '').substring(0, 10);
        if (!fecha0) {
            alert('Indique la fecha de la primera cuota.');
            return;
        }
        var montos = montosIguales(n, totalFactura);
        var formapagoId = formapagoActual();
        var html = '';
        for (var i = 0; i < n; i++) {
            html += htmlFila(mensual ? ymdAddMonths(fecha0, i) : fecha0, montos[i], formapagoId);
        }
        tabla.querySelector('tbody').innerHTML = html;
        renumerar();
        actualizarResumen();
    }

    document.getElementById('js-agregar-cuota')?.addEventListener('click', function () {
        var body = tabla.querySelector('tbody');
        var base = body.querySelector('tr');
        if (!base) {
            body.insertAdjacentHTML('beforeend', htmlFila('', '0.00', 1));
        } else {
            var tr = base.cloneNode(true);
            tr.querySelectorAll('input').forEach(function (input) {
                if (input.type === 'date') input.value = '';
                if (input.type === 'number') input.value = '0.00';
            });
            body.appendChild(tr);
        }
        renumerar();
        actualizarResumen();
    });
    document.getElementById('js-cuotas-misma-fecha')?.addEventListener('click', function () { generar(false); });
    document.getElementById('js-cuotas-mensual')?.addEventListener('click', function () { generar(true); });
    tabla.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.js-quitar-cuota');
        if (!btn) return;
        var filas = tabla.querySelectorAll('tbody tr');
        if (filas.length <= 1) return;
        btn.closest('tr').remove();
        renumerar();
        actualizarResumen();
    });
    tabla.addEventListener('input', actualizarResumen);
    actualizarResumen();
})();
</script>
@endsection
