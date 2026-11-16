<p class="text-muted small">
    Código de operación IB (<code>operation_code_ib</code>) → código conciliación (capa de respaldo).
</p>
<div class="table-responsive">
    <table class="table table-sm table-bordered mb-2" style="max-width:640px;">
        <thead style="background:#85C1E9;color:#17202A;">
            <tr>
                <th style="width:35%">Cód. operación</th>
                <th style="width:50%">Código</th>
                <th style="width:15%"></th>
            </tr>
        </thead>
        <tbody id="tbody-cod-op" data-prefix="cod_op">
            @forelse ($codOpFilas as $i => $fila)
                <tr>
                    <td>
                        <input type="hidden" name="cod_op[{{ $i }}][id]" value="{{ $fila['id'] ?? '' }}">
                        <input type="text" class="form-control form-control-sm" name="cod_op[{{ $i }}][cod_op]"
                            value="{{ $fila['cod_op'] }}" placeholder="917" autocomplete="off">
                    </td>
                    <td>
                        <select class="form-control form-control-sm" name="cod_op[{{ $i }}][codigo]">
                            @foreach ($codigosGasto as $op)
                                <option value="{{ $op['valor'] }}" @selected((string) $fila['codigo'] === (string) $op['valor'])>
                                    {{ $op['etiqueta'] }}
                                </option>
                            @endforeach
                        </select>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn-accion-tabla js-quitar-fila" title="Quitar">
                            <i class="fa fa-times-circle text-danger"></i>
                        </button>
                    </td>
                </tr>
            @empty
                @include('contable.configuracion_conciliacion_bancaria.partials.fila_cod_op_vacia', ['i' => 0])
            @endforelse
        </tbody>
    </table>
</div>
<button type="button" class="btn btn-outline-primary btn-sm js-agregar-fila" data-tbody="tbody-cod-op" data-template="template-renglon-cod-op">
    <i class="fa fa-plus"></i> Agrega renglón
</button>
