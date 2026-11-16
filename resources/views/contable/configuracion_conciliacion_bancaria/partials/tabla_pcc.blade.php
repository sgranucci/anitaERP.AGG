<p class="text-muted small">
    Sin patrones = clasificación directa. Con patrones (separados por <code>|</code>) = regla ambigua.
    Código <strong>AC</strong> = acumulado diario.
</p>
<div class="table-responsive">
    <table class="table table-sm table-bordered mb-2">
        <thead style="background:#85C1E9;color:#17202A;">
            <tr>
                <th style="width:8%">P.C.C.</th>
                <th style="width:18%">Código</th>
                <th style="width:20%">Descripción</th>
                <th style="width:22%">Patrones concepto</th>
                <th style="width:22%">Patrones descripción</th>
                <th style="width:6%"></th>
            </tr>
        </thead>
        <tbody id="tbody-pcc-mapa" data-prefix="pcc">
            @forelse ($pccFilas as $i => $fila)
                <tr>
                    <td>
                        <input type="hidden" name="pcc[{{ $i }}][id]" value="{{ $fila['id'] ?? '' }}">
                        <input type="text" class="form-control form-control-sm" name="pcc[{{ $i }}][pcc]"
                            value="{{ $fila['pcc'] }}" placeholder="6 6 5" autocomplete="off">
                    </td>
                    <td>
                        <select class="form-control form-control-sm" name="pcc[{{ $i }}][codigo]">
                            @foreach ($codigosGasto as $op)
                                <option value="{{ $op['valor'] }}" @selected((string) $fila['codigo'] === (string) $op['valor'])>
                                    {{ $op['etiqueta'] }}
                                </option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm" name="pcc[{{ $i }}][descripcion]"
                            value="{{ $fila['descripcion'] }}" maxlength="120" autocomplete="off">
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm" name="pcc[{{ $i }}][patrones_concepto]"
                            value="{{ $fila['patrones_concepto'] }}" placeholder="RET.IN.BRU|…" autocomplete="off">
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm" name="pcc[{{ $i }}][patrones_descripcion]"
                            value="{{ $fila['patrones_descripcion'] }}" placeholder="AGIP|…" autocomplete="off">
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn-accion-tabla js-quitar-fila" title="Quitar">
                            <i class="fa fa-times-circle text-danger"></i>
                        </button>
                    </td>
                </tr>
            @empty
                @include('contable.configuracion_conciliacion_bancaria.partials.fila_pcc_vacia', ['i' => 0])
            @endforelse
        </tbody>
    </table>
</div>
<button type="button" class="btn btn-outline-primary btn-sm js-agregar-fila" data-tbody="tbody-pcc-mapa" data-template="template-renglon-pcc">
    <i class="fa fa-plus"></i> Agrega renglón
</button>
