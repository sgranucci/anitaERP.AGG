<p class="text-muted small">
    Reglas para clasificar movimientos de extracto sin par (intereses, fees, FX, etc.).
    <strong>Patrones</strong> separados por <code>|</code>; se buscan en el texto del banco.
    Completá <strong>concepto IVA</strong> o <strong>cuenta contable</strong> para que el posting residual quede listo.
</p>
<div class="table-responsive">
    <table class="table table-sm table-bordered mb-2">
        <thead style="background:#85C1E9;color:#17202A;">
            <tr>
                <th style="width:9%">Clave</th>
                <th style="width:14%">Descripción</th>
                <th style="width:18%">Patrones</th>
                <th style="width:6%">D/H</th>
                <th style="width:14%">Cuenta</th>
                <th style="width:14%">Concepto IVA</th>
                <th style="width:8%">Tipo doc.</th>
                <th style="width:7%">Activo</th>
                <th style="width:5%"></th>
            </tr>
        </thead>
        <tbody id="tbody-residual" data-prefix="residual">
            @forelse ($residualFilas as $i => $fila)
                <tr>
                    <td>
                        <input type="hidden" name="residual[{{ $i }}][id]" value="{{ $fila['id'] ?? '' }}">
                        <input type="text" class="form-control form-control-sm" name="residual[{{ $i }}][clave]"
                            value="{{ $fila['clave'] }}" placeholder="interes" autocomplete="off" maxlength="40">
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm" name="residual[{{ $i }}][descripcion]"
                            value="{{ $fila['descripcion'] }}" placeholder="Intereses bancarios" autocomplete="off" maxlength="120">
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm" name="residual[{{ $i }}][patrones]"
                            value="{{ $fila['patrones'] }}" placeholder="INTERES|INT." autocomplete="off" maxlength="500"
                            title="Patrones separados por |">
                    </td>
                    <td>
                        <select class="form-control form-control-sm" name="residual[{{ $i }}][dh]">
                            <option value="D" @selected(($fila['dh'] ?? 'D') === 'D')>D</option>
                            <option value="H" @selected(($fila['dh'] ?? '') === 'H')>H</option>
                        </select>
                    </td>
                    <td>
                        <div class="d-flex align-items-center" style="gap:4px;">
                            <input type="text" class="form-control form-control-sm js-doc-cuenta-codigo"
                                name="residual[{{ $i }}][cuenta_codigo]"
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
                                name="residual[{{ $i }}][concepto_ivacompra_codigo]"
                                value="{{ $fila['concepto_ivacompra_codigo'] }}" placeholder="150" autocomplete="off"
                                title="Código concepto IVA. F1 o lupa = catálogo">
                            <button type="button" class="btn-accion-tabla js-consulta-concepto-doc" title="Consultar concepto IVA (F1)">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                        </div>
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm" name="residual[{{ $i }}][tipo_documento]"
                            value="{{ $fila['tipo_documento'] }}" placeholder="EGR" autocomplete="off" maxlength="10">
                    </td>
                    <td class="text-center align-middle">
                        <input type="hidden" name="residual[{{ $i }}][activo]" value="0">
                        <input type="checkbox" name="residual[{{ $i }}][activo]" value="1" @checked(!empty($fila['activo']))>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn-accion-tabla js-quitar-fila" title="Quitar">
                            <i class="fa fa-times-circle text-danger"></i>
                        </button>
                    </td>
                </tr>
            @empty
                @include('contable.configuracion_conciliacion_bancaria.partials.fila_residual_vacia', ['i' => 0])
            @endforelse
        </tbody>
    </table>
</div>
<button type="button" class="btn btn-outline-primary btn-sm js-agregar-fila" data-tbody="tbody-residual" data-template="template-renglon-residual">
    <i class="fa fa-plus"></i> Agrega renglón
</button>
