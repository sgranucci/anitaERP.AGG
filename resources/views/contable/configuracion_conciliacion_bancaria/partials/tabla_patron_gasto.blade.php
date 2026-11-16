<p class="text-muted small">
    Capa final: patrones de texto (concepto + descripción) → código. Separar patrones con <code>|</code>.
    También define las etiquetas de código usadas en las otras solapas.
</p>
<div class="table-responsive">
    <table class="table table-sm table-bordered mb-2">
        <thead style="background:#85C1E9;color:#17202A;">
            <tr>
                <th style="width:10%">Código</th>
                <th style="width:28%">Descripción</th>
                <th style="width:54%">Patrones</th>
                <th style="width:8%"></th>
            </tr>
        </thead>
        <tbody id="tbody-patron-gasto" data-prefix="patron_gasto">
            @forelse ($patronGastoFilas as $i => $fila)
                <tr>
                    <td>
                        <input type="hidden" name="patron_gasto[{{ $i }}][id]" value="{{ $fila['id'] ?? '' }}">
                        <input type="text" class="form-control form-control-sm" name="patron_gasto[{{ $i }}][codigo]"
                            value="{{ $fila['codigo'] }}" placeholder="7" autocomplete="off">
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm" name="patron_gasto[{{ $i }}][descripcion]"
                            value="{{ $fila['descripcion'] }}" maxlength="120" autocomplete="off">
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm" name="patron_gasto[{{ $i }}][patrones]"
                            value="{{ $fila['patrones'] }}" placeholder="COMISION|GTOS BANC|…" autocomplete="off">
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn-accion-tabla js-quitar-fila" title="Quitar">
                            <i class="fa fa-times-circle text-danger"></i>
                        </button>
                    </td>
                </tr>
            @empty
                @include('contable.configuracion_conciliacion_bancaria.partials.fila_patron_gasto_vacia', ['i' => 0])
            @endforelse
        </tbody>
    </table>
</div>
<button type="button" class="btn btn-outline-primary btn-sm js-agregar-fila" data-tbody="tbody-patron-gasto" data-template="template-renglon-patron-gasto">
    <i class="fa fa-plus"></i> Agrega renglón
</button>
