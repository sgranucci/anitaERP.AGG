@php
    use App\Support\Caja\CotizacionTesoreriaMonedasSupport;
    $monedasColumnas = $monedasColumnas ?? CotizacionTesoreriaMonedasSupport::monedasParaColumnas();
    $fechaValor = old('fecha', isset($data) && $data->fecha ? $data->fecha->format('Y-m-d') : date('Y-m-d'));
    $empresaIdValor = old('empresa_id', $data->empresa_id ?? 1);
@endphp
@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query ?? collect(),
    'empresa_id' => $empresaIdValor,
    'col_label' => 'col-lg-4 control-label text-right pr-2',
    'col_input' => 'col-lg-3',
    'required' => true,
])
<div class="form-group row">
    <label for="fecha" class="col-lg-4 control-label text-right pr-2 requerido">Fecha</label>
    <div class="col-lg-3">
        <input type="date" name="fecha" id="fecha" class="form-control" value="{{ $fechaValor }}" required>
    </div>
</div>

<div class="card card-outline card-info">
    <div class="card-header">
        <h3 class="card-title mb-0">Tasas compra / venta (monedas Anita 2–9)</h3>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width: 35%;">Moneda</th>
                        <th style="width: 32.5%;">Cotización compra</th>
                        <th style="width: 32.5%;">Cotización venta</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($monedasColumnas as $moneda)
                        @php
                            $colCompra = CotizacionTesoreriaMonedasSupport::columnaCompra((int) $moneda->codigo);
                            $colVenta = CotizacionTesoreriaMonedasSupport::columnaVenta((int) $moneda->codigo);
                            $valCompra = old($colCompra, isset($data) ? $data->{$colCompra} : null);
                            $valVenta = old($colVenta, isset($data) ? $data->{$colVenta} : null);
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $moneda->nombre }}</strong>
                                <small class="text-muted d-block">Código {{ $moneda->codigo }} · {{ $moneda->abreviatura }}</small>
                            </td>
                            <td>
                                <input type="number"
                                       step="0.000001"
                                       min="0"
                                       name="{{ $colCompra }}"
                                       id="{{ $colCompra }}"
                                       class="form-control form-control-sm text-right"
                                       value="{{ $valCompra }}">
                            </td>
                            <td>
                                <input type="number"
                                       step="0.000001"
                                       min="0"
                                       name="{{ $colVenta }}"
                                       id="{{ $colVenta }}"
                                       class="form-control form-control-sm text-right"
                                       value="{{ $valVenta }}">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
