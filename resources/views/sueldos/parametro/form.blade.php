@php
    use App\Models\Sueldos\Parametro_Sueldos;
    $valoresOld = old('valores');
    if ($valoresOld === null && isset($data)) {
        $valoresOld = $data->valores->map(function ($v) {
            return [
                'fecha_vigencia' => $v->fecha_vigencia?->format('Y-m-d'),
                'valor' => $v->valor,
                'valor_texto' => $v->valor_texto,
            ];
        })->values()->all();
    }
    if (! is_array($valoresOld)) {
        $valoresOld = [];
    }
    if (count($valoresOld) === 0) {
        $valoresOld = [['fecha_vigencia' => '', 'valor' => '', 'valor_texto' => '']];
    }
@endphp
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">C&oacute;digo</label>
    <div class="col-lg-4">
        @if (isset($data))
            <input type="text" id="codigo" class="form-control" value="{{ $data->codigo }}" readonly/>
            <input type="hidden" name="codigo" value="{{ $data->codigo }}"/>
        @else
            <input type="text" name="codigo" id="codigo" class="form-control text-uppercase" maxlength="40" required
                   value="{{ old('codigo') }}"
                   placeholder="Ej. TOPE_SIPA"/>
        @endif
    </div>
</div>
<div class="form-group row">
    <label for="descripcion" class="col-lg-3 col-form-label requerido">Descripci&oacute;n</label>
    <div class="col-lg-6">
        <input type="text" name="descripcion" id="descripcion" class="form-control" maxlength="120" required
               value="{{ old('descripcion', $data->descripcion ?? '') }}"/>
    </div>
</div>
<div class="form-group row">
    <label for="tipo" class="col-lg-3 col-form-label requerido">Tipo</label>
    <div class="col-lg-4">
        <select name="tipo" id="tipo" class="form-control" required>
            @foreach (Parametro_Sueldos::TIPOS as $val => $label)
                <option value="{{ $val }}" {{ old('tipo', $data->tipo ?? 'numero') === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="unidad" class="col-lg-3 col-form-label">Unidad</label>
    <div class="col-lg-3">
        <input type="text" name="unidad" id="unidad" class="form-control" maxlength="20"
               value="{{ old('unidad', $data->unidad ?? '') }}"
               placeholder="Ej. $, %, d&iacute;as"/>
    </div>
</div>
<div class="form-group row">
    <label for="activo" class="col-lg-3 col-form-label">Activo</label>
    <div class="col-lg-6">
        <div class="custom-control custom-checkbox mt-2">
            <input type="checkbox" class="custom-control-input" name="activo" id="activo" value="1"
                   {{ old('activo', $data->activo ?? true) ? 'checked' : '' }}/>
            <label class="custom-control-label" for="activo">Par&aacute;metro habilitado para liquidaci&oacute;n</label>
        </div>
    </div>
</div>

<hr class="my-3"/>

<div class="form-group row">
    <label class="col-lg-3 col-form-label">Valores con vigencia</label>
    <div class="col-lg-9">
        <p class="text-muted small mb-2">
            Cada fila define el valor que rige desde la fecha indicada. En liquidaci&oacute;n se usa el vigente a la fecha del c&aacute;lculo.
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered" id="tabla-valores-parametro">
                <thead style="background-color:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width: 160px;">Fecha vigencia</th>
                        <th style="width: 160px;">Valor num&eacute;rico</th>
                        <th>Valor texto</th>
                        <th style="width: 60px;"></th>
                    </tr>
                </thead>
                <tbody id="tbody-valores-parametro">
                    @foreach ($valoresOld as $idx => $fila)
                        <tr class="fila-valor-parametro">
                            <td>
                                <input type="date" name="valores[{{ $idx }}][fecha_vigencia]" class="form-control form-control-sm"
                                       value="{{ $fila['fecha_vigencia'] ?? '' }}"/>
                            </td>
                            <td>
                                <input type="number" step="0.000001" name="valores[{{ $idx }}][valor]" class="form-control form-control-sm input-valor-numero"
                                       value="{{ $fila['valor'] ?? '' }}"/>
                            </td>
                            <td>
                                <input type="text" name="valores[{{ $idx }}][valor_texto]" class="form-control form-control-sm input-valor-texto" maxlength="120"
                                       value="{{ $fila['valor_texto'] ?? '' }}"/>
                            </td>
                            <td class="text-center align-middle">
                                <button type="button" class="btn btn-sm btn-outline-danger btn-quitar-valor-parametro" title="Quitar fila">
                                    <i class="fa fa-times"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-agregar-valor-parametro">
            <i class="fa fa-plus"></i> Agregar vigencia
        </button>
    </div>
</div>

<script>
(function () {
    'use strict';

    var tbody = document.getElementById('tbody-valores-parametro');
    var btnAgregar = document.getElementById('btn-agregar-valor-parametro');
    var tipoSelect = document.getElementById('tipo');

    if (!tbody || !btnAgregar) {
        return;
    }

    function nextIndex() {
        return tbody.querySelectorAll('.fila-valor-parametro').length;
    }

    function crearFila(idx) {
        var tr = document.createElement('tr');
        tr.className = 'fila-valor-parametro';
        tr.innerHTML =
            '<td><input type="date" name="valores[' + idx + '][fecha_vigencia]" class="form-control form-control-sm" value=""/></td>' +
            '<td><input type="number" step="0.000001" name="valores[' + idx + '][valor]" class="form-control form-control-sm input-valor-numero" value=""/></td>' +
            '<td><input type="text" name="valores[' + idx + '][valor_texto]" class="form-control form-control-sm input-valor-texto" maxlength="120" value=""/></td>' +
            '<td class="text-center align-middle">' +
            '<button type="button" class="btn btn-sm btn-outline-danger btn-quitar-valor-parametro" title="Quitar fila">' +
            '<i class="fa fa-times"></i></button></td>';
        return tr;
    }

    function reindexFilas() {
        tbody.querySelectorAll('.fila-valor-parametro').forEach(function (tr, idx) {
            tr.querySelectorAll('input[name^="valores["]').forEach(function (input) {
                var field = input.name.replace(/^valores\[\d+\]\[([^\]]+)\]$/, '$1');
                input.name = 'valores[' + idx + '][' + field + ']';
            });
        });
    }

    function actualizarVisibilidadPorTipo() {
        var esTexto = tipoSelect && tipoSelect.value === 'texto';
        tbody.querySelectorAll('.input-valor-numero').forEach(function (el) {
            el.closest('td').style.display = esTexto ? 'none' : '';
        });
        tbody.querySelectorAll('.input-valor-texto').forEach(function (el) {
            el.closest('td').style.display = esTexto ? '' : 'none';
        });
        var thNum = document.querySelector('#tabla-valores-parametro thead th:nth-child(2)');
        var thTxt = document.querySelector('#tabla-valores-parametro thead th:nth-child(3)');
        if (thNum) {
            thNum.style.display = esTexto ? 'none' : '';
        }
        if (thTxt) {
            thTxt.style.display = esTexto ? '' : 'none';
        }
    }

    btnAgregar.addEventListener('click', function () {
        tbody.appendChild(crearFila(nextIndex()));
        reindexFilas();
        actualizarVisibilidadPorTipo();
    });

    tbody.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.btn-quitar-valor-parametro');
        if (!btn) {
            return;
        }
        var filas = tbody.querySelectorAll('.fila-valor-parametro');
        if (filas.length <= 1) {
            filas[0].querySelectorAll('input').forEach(function (input) {
                input.value = '';
            });
            return;
        }
        btn.closest('tr').remove();
        reindexFilas();
    });

    if (tipoSelect) {
        tipoSelect.addEventListener('change', actualizarVisibilidadPorTipo);
        actualizarVisibilidadPorTipo();
    }
})();
</script>
