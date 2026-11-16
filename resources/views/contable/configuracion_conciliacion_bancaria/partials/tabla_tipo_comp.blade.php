<p class="text-muted small">
    Compatibilidad tipo de comprobante del mayor (OPP, OPA, TRF…) con conceptos del extracto bancario.
    Patrones separados por <code>|</code>.
</p>
<div class="table-responsive">
    <table class="table table-sm table-bordered mb-2" style="max-width:900px;">
        <thead style="background:#85C1E9;color:#17202A;">
            <tr>
                <th style="width:18%">Tipo comp.</th>
                <th style="width:72%">Patrones concepto banco</th>
                <th style="width:10%"></th>
            </tr>
        </thead>
        <tbody id="tbody-tipo-comp" data-prefix="tipo_comp">
            @forelse ($tipoCompFilas as $i => $fila)
                <tr>
                    <td>
                        <input type="hidden" name="tipo_comp[{{ $i }}][id]" value="{{ $fila['id'] ?? '' }}">
                        <input type="text" class="form-control form-control-sm" name="tipo_comp[{{ $i }}][tipo_comp]"
                            value="{{ $fila['tipo_comp'] }}" placeholder="OPP" autocomplete="off">
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm" name="tipo_comp[{{ $i }}][patrones]"
                            value="{{ $fila['patrones'] }}" placeholder="TRF.DATA|CH DEP|…" autocomplete="off">
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn-accion-tabla js-quitar-fila" title="Quitar">
                            <i class="fa fa-times-circle text-danger"></i>
                        </button>
                    </td>
                </tr>
            @empty
                @include('contable.configuracion_conciliacion_bancaria.partials.fila_tipo_comp_vacia', ['i' => 0])
            @endforelse
        </tbody>
    </table>
</div>
<button type="button" class="btn btn-outline-primary btn-sm js-agregar-fila" data-tbody="tbody-tipo-comp" data-template="template-renglon-tipo-comp">
    <i class="fa fa-plus"></i> Agrega renglón
</button>
