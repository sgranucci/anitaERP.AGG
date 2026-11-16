<p class="text-muted small">
    Concepto IB (<code>code_description_ib</code>) → código cuando el P.C.C. no alcanza.
    Marcar <strong>Acumulado</strong> para conceptos que generan AC (sellos, IVA 10,5 %, etc.).
</p>
<div class="table-responsive">
    <table class="table table-sm table-bordered mb-2">
        <thead style="background:#85C1E9;color:#17202A;">
            <tr>
                <th style="width:22%">Patrón concepto</th>
                <th style="width:22%">Código</th>
                <th style="width:36%">Descripción</th>
                <th style="width:12%">Acumulado</th>
                <th style="width:8%"></th>
            </tr>
        </thead>
        <tbody id="tbody-concepto" data-prefix="concepto">
            @forelse ($conceptoFilas as $i => $fila)
                <tr>
                    <td>
                        <input type="hidden" name="concepto[{{ $i }}][id]" value="{{ $fila['id'] ?? '' }}">
                        <input type="text" class="form-control form-control-sm" name="concepto[{{ $i }}][patron]"
                            value="{{ $fila['patron'] }}" placeholder="TRF.DATA" autocomplete="off">
                    </td>
                    <td>
                        <select class="form-control form-control-sm" name="concepto[{{ $i }}][codigo]">
                            @foreach ($codigosGasto as $op)
                                <option value="{{ $op['valor'] }}" @selected((string) $fila['codigo'] === (string) $op['valor'])>
                                    {{ $op['etiqueta'] }}
                                </option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm" name="concepto[{{ $i }}][descripcion]"
                            value="{{ $fila['descripcion'] }}" maxlength="120" autocomplete="off">
                    </td>
                    <td class="text-center align-middle">
                        <input type="hidden" name="concepto[{{ $i }}][es_acumulado]" value="0">
                        <input type="checkbox" name="concepto[{{ $i }}][es_acumulado]" value="1" @checked(!empty($fila['es_acumulado']))>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn-accion-tabla js-quitar-fila" title="Quitar">
                            <i class="fa fa-times-circle text-danger"></i>
                        </button>
                    </td>
                </tr>
            @empty
                @include('contable.configuracion_conciliacion_bancaria.partials.fila_concepto_vacia', ['i' => 0])
            @endforelse
        </tbody>
    </table>
</div>
<button type="button" class="btn btn-outline-primary btn-sm js-agregar-fila" data-tbody="tbody-concepto" data-template="template-renglon-concepto">
    <i class="fa fa-plus"></i> Agrega renglón
</button>
