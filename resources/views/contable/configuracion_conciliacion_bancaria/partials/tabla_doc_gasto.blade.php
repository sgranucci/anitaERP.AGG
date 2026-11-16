<p class="text-muted small">
    Por cada código de gasto: <strong>concepto IVA compras</strong> (manda si está) o <strong>cuenta contable</strong> (código del plan).
    Los tipos EGR/ICO se configuran en la solapa Reglas. Códigos 9 (cheques) y 10 (transferencias) no entran al documento.
</p>
<div class="table-responsive">
    <table class="table table-sm table-bordered mb-2">
        <thead style="background:#85C1E9;color:#17202A;">
            <tr>
                <th style="width:12%">Código gasto</th>
                <th style="width:28%">Cuenta contable (código)</th>
                <th style="width:28%">Concepto IVA compras (código)</th>
                <th style="width:24%">Etiqueta (ref.)</th>
                <th style="width:8%"></th>
            </tr>
        </thead>
        <tbody id="tbody-doc-gasto" data-prefix="doc_gasto">
            @php
                $etiquetas = [];
                foreach ($codigosGasto as $op) {
                    $etiquetas[(string) $op['valor']] = $op['etiqueta'];
                }
            @endphp
            @forelse ($docGastoFilas as $i => $fila)
                <tr>
                    <td>
                        <input type="hidden" name="doc_gasto[{{ $i }}][id]" value="{{ $fila['id'] ?? '' }}">
                        <input type="text" class="form-control form-control-sm" name="doc_gasto[{{ $i }}][codigo]"
                            value="{{ $fila['codigo'] }}" placeholder="2" autocomplete="off">
                    </td>
                    <td>
                        <div class="d-flex align-items-center" style="gap:4px;">
                            <input type="text" class="form-control form-control-sm js-doc-cuenta-codigo"
                                name="doc_gasto[{{ $i }}][cuenta_codigo]"
                                value="{{ $fila['cuenta_codigo'] }}" placeholder="532050000" autocomplete="off"
                                title="Código del plan. F1 o lupa = consulta">
                            <button type="button" class="btn-accion-tabla js-consulta-cuenta-doc" title="Consultar cuenta (F1)">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center" style="gap:4px;">
                            <input type="text" class="form-control form-control-sm js-doc-concepto-codigo"
                                name="doc_gasto[{{ $i }}][concepto_ivacompra_codigo]"
                                value="{{ $fila['concepto_ivacompra_codigo'] }}" placeholder="150" autocomplete="off"
                                title="Código concepto IVA. F1 o lupa = catálogo">
                            <button type="button" class="btn-accion-tabla js-consulta-concepto-doc" title="Consultar concepto IVA (F1)">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                        </div>
                    </td>
                    <td class="small text-muted align-middle">
                        {{ $etiquetas[(string) $fila['codigo']] ?? '' }}
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn-accion-tabla js-quitar-fila" title="Quitar">
                            <i class="fa fa-times-circle text-danger"></i>
                        </button>
                    </td>
                </tr>
            @empty
                @include('contable.configuracion_conciliacion_bancaria.partials.fila_doc_gasto_vacia', ['i' => 0])
            @endforelse
        </tbody>
    </table>
</div>
<button type="button" class="btn btn-outline-primary btn-sm js-agregar-fila" data-tbody="tbody-doc-gasto" data-template="template-renglon-doc-gasto">
    <i class="fa fa-plus"></i> Agrega renglón
</button>
