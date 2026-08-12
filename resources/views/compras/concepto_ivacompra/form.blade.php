@php
    $puedeAbrirAbmCuenta = can('editar-cuentas-contables', false) || can('listar-cuentas-contables', false);

    $lineasOld = old('empresa_ids');
    if (is_array($lineasOld)) {
        $lineasForm = [];
        $n = count($lineasOld);
        for ($i = 0; $i < $n; $i++) {
            $lineasForm[] = (object) [
                'empresa_id' => old('empresa_ids.'.$i),
                'cuentacontabledebe_id' => old('cuentacontabledebe_ids.'.$i),
                'cuentacontablehaber_id' => old('cuentacontablehaber_ids.'.$i),
            ];
        }
        if ($lineasForm === []) {
            $lineasForm = [null];
        }
    } else {
        $lineasEmp = ($data->concepto_ivacompra_empresas ?? collect());
        if ($lineasEmp->count()) {
            $lineasForm = $lineasEmp;
        } elseif (! empty($data->cuentacontabledebe_id) || ! empty($data->cuentacontablehaber_id)) {
            $lineasForm = [(object) [
                'empresa_id' => $data->empresa_id,
                'cuentacontabledebe_id' => $data->cuentacontabledebe_id,
                'cuentacontablehaber_id' => $data->cuentacontablehaber_id,
                'cuentacontabledebe' => $data->cuentacontablesdebe ?? null,
                'cuentacontablehaber' => $data->cuentacontableshaber ?? null,
            ]];
        } else {
            $lineasForm = [null];
        }
    }
@endphp

<style>
    #tabla-concepto-ivacompra-empresas thead th {
        background: #85C1E9;
        color: #17202A;
        font-size: 0.85rem;
        white-space: nowrap;
    }
    #tabla-concepto-ivacompra-empresas .tm-cuentacontable-campo {
        margin-bottom: 0;
    }
    #tabla-concepto-ivacompra-empresas td {
        vertical-align: middle;
    }
    #condicioniva-table thead th {
        background: #85C1E9;
        color: #17202A;
    }
    .cic-cuenta-compact .nombrecuentacontable {
        min-width: 8rem;
    }
    .cic-cabecera .form-group {
        margin-bottom: 0.55rem;
    }
    .cic-cabecera .control-label {
        font-size: 0.9rem;
        padding-top: 0.35rem;
    }
    .cic-cabecera .form-control {
        height: calc(1.5em + 0.5rem + 2px);
        padding: 0.25rem 0.5rem;
        font-size: 0.9rem;
    }
</style>

<div class="row cic-cabecera mb-2">
    <div class="col-md-6">
        <div class="form-group row">
            <label for="nombre" class="col-lg-4 control-label text-right pr-2 requerido">Nombre</label>
            <div class="col-lg-8">
                <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $data->nombre ?? '') }}" required/>
            </div>
        </div>
        <div class="form-group row">
            <label for="nombre_ia" class="col-lg-4 control-label text-right pr-2 requerido">Nombre IA</label>
            <div class="col-lg-8">
                <input type="text" name="nombre_ia" id="nombre_ia" class="form-control" value="{{ old('nombre_ia', $data->nombre_ia ?? '') }}" required/>
            </div>
        </div>
        <div class="form-group row">
            <label for="tipoconcepto" class="col-lg-4 control-label text-right pr-2 requerido">Tipo concepto</label>
            <div class="col-lg-8">
                <select name="tipoconcepto" id="tipoconcepto" class="form-control" required>
                    <option value="">-- Elija tipo --</option>
                    @foreach ($tipoconcepto_enum as $tipoconcepto)
                        <option value="{{ $tipoconcepto['valor'] }}"
                            @if (old('tipoconcepto', $data->tipoconcepto ?? '') == $tipoconcepto['valor']) selected @endif
                            >{{ $tipoconcepto['nombre'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="columna_ivacompra_id" class="col-lg-4 control-label text-right pr-2">Columna IVA</label>
            <div class="col-lg-8">
                <select name="columna_ivacompra_id" id="columna_ivacompra_id" class="form-control">
                    <option value="">-- Seleccionar --</option>
                    @foreach ($columna_ivacompra_query as $value)
                        <option value="{{ $value->id }}"
                            @if ((int) $value->id === (int) old('columna_ivacompra_id', $data->columna_ivacompra_id ?? '')) selected @endif>
                            {{ $value->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="retieneganancia" class="col-lg-4 control-label text-right pr-2 requerido">Retiene Gan.</label>
            <div class="col-lg-8">
                <select name="retieneganancia" id="retieneganancia" class="form-control" required>
                    <option value="">-- Elija --</option>
                    @foreach ($retiene_enum as $retiene)
                        <option value="{{ $retiene['valor'] }}"
                            @if (old('retieneganancia', $data->retieneganancia ?? '') == $retiene['valor']) selected @endif
                            >{{ $retiene['nombre'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group row">
            <label for="retieneIIBB" class="col-lg-4 control-label text-right pr-2 requerido">Retiene IIBB</label>
            <div class="col-lg-8">
                <select name="retieneIIBB" id="retieneIIBB" class="form-control" required>
                    <option value="">-- Elija --</option>
                    @foreach ($retiene_enum as $retiene)
                        <option value="{{ $retiene['valor'] }}"
                            @if (old('retieneIIBB', $data->retieneIIBB ?? '') == $retiene['valor']) selected @endif
                            >{{ $retiene['nombre'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="provincia_id" class="col-lg-4 control-label text-right pr-2">Provincia</label>
            <div class="col-lg-8">
                <select name="provincia_id" id="provincia_id" class="form-control">
                    <option value="">-- Seleccionar --</option>
                    @foreach ($provincia_query as $value)
                        <option value="{{ $value->id }}"
                            @if ((int) $value->id === (int) old('provincia_id', $data->provincia_id ?? '')) selected @endif>
                            {{ $value->nombre }} Jur: {{ $value->jurisdiccion }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="impuesto_id" class="col-lg-4 control-label text-right pr-2">Impuesto</label>
            <div class="col-lg-8">
                <select name="impuesto_id" id="impuesto_id" class="form-control">
                    <option value="">-- Seleccionar --</option>
                    @foreach ($impuesto_query as $value)
                        <option value="{{ $value->id }}"
                            @if ((int) $value->id === (int) old('impuesto_id', $data->impuesto_id ?? '')) selected @endif>
                            {{ $value->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="formula" class="col-lg-4 control-label text-right pr-2">Fórmula</label>
            <div class="col-lg-8">
                <input type="text" name="formula" id="formula" class="form-control" value="{{ old('formula', $data->formula ?? '') }}">
            </div>
        </div>
        <div class="form-group row">
            <label for="codigo" class="col-lg-4 control-label text-right pr-2 requerido">Código Anita</label>
            <div class="col-lg-4">
                <input type="text" name="codigo" id="codigo" class="form-control" value="{{ old('codigo', $data->codigo ?? '') }}" required>
            </div>
        </div>
    </div>
</div>

<div class="card card-outline card-info mb-3">
    <div class="card-header d-flex align-items-center justify-content-between py-2">
        <strong>Cuentas contables por empresa</strong>
        <small class="text-muted mb-0">Una fila por empresa (Debe / Haber). Use el modal de consulta.</small>
    </div>
    <div class="card-body p-2">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-2" id="tabla-concepto-ivacompra-empresas">
                <thead>
                    <tr>
                        <th style="min-width: 10rem;">Empresa</th>
                        <th style="min-width: 16rem;">Cuenta Debe</th>
                        <th style="min-width: 16rem;">Cuenta Haber</th>
                        <th class="text-center" style="width: 3rem;"></th>
                    </tr>
                </thead>
                <tbody id="tbody-concepto-ivacompra-empresas">
                    @foreach ($lineasForm as $linea)
                        @include('compras.concepto_ivacompra.partials.fila_empresa', [
                            'linea' => $linea,
                            'empresa_query' => $empresa_query,
                            'puedeAbrirAbmCuenta' => $puedeAbrirAbmCuenta,
                            'indice' => $loop->index,
                        ])
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('compras.concepto_ivacompra.partials.template_fila_empresa', [
            'empresa_query' => $empresa_query,
            'puedeAbrirAbmCuenta' => $puedeAbrirAbmCuenta,
        ])
        <div class="d-flex justify-content-end flex-wrap" style="gap: 6px;">
            <button type="button" id="replicar_concepto_ivacompra_todas" class="btn btn-sm btn-outline-info"
                    title="Toma la primera fila completa y genera el resto de empresas con la misma cuenta (por código)">
                <i class="fa fa-copy"></i> Enviar a todas las empresas
            </button>
            <button type="button" id="agrega_renglon_concepto_ivacompra" class="btn btn-sm btn-outline-primary">
                <i class="fa fa-plus"></i> Agregar empresa
            </button>
        </div>
    </div>
</div>

<div class="card card-outline card-info mb-0">
    <div class="card-header py-2">
        <strong>Condiciones de IVA en las que se usa el concepto</strong>
    </div>
    <div class="card-body p-2">
        <table class="table table-sm table-bordered mb-2" id="condicioniva-table">
            <thead>
                <tr>
                    <th style="width: 4%;"></th>
                    <th style="width: 40%;">Condición de IVA</th>
                    <th style="width: 4%;"></th>
                </tr>
            </thead>
            <tbody id="tbody-condicioniva-table">
            @php
                $condicionesForm = old('condicioniva_ids');
                if (is_array($condicionesForm)) {
                    $conds = collect($condicionesForm)->map(fn ($id) => (object) ['condicioniva_id' => $id]);
                    if ($conds->isEmpty()) {
                        $conds = collect([(object) ['condicioniva_id' => '']]);
                    }
                } else {
                    $conds = ($data->concepto_ivacompra_condicionivas ?? collect())->count()
                        ? $data->concepto_ivacompra_condicionivas
                        : collect([(object) ['condicioniva_id' => '']]);
                }
            @endphp
            @foreach ($conds as $condicionivas)
                <tr class="item-condicioniva">
                    <td>
                        <input type="text" name="condicioniva[]" class="form-control form-control-sm iicondicioniva" readonly value="{{ $loop->index + 1 }}" />
                    </td>
                    <td>
                        <select name="condicioniva_ids[]" class="form-control form-control-sm condicioniva_id">
                            <option value="">-- Elija condición de IVA --</option>
                            @foreach ($condicioniva_query as $condicioniva)
                                <option value="{{ $condicioniva->id }}"
                                    @if ((int) old('condicioniva_ids.'.$loop->parent->index, $condicionivas->condicioniva_id ?? '') === (int) $condicioniva->id) selected @endif
                                    >{{ $condicioniva->nombre }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td class="text-center">
                        <button type="button" title="Eliminar renglón" class="btn-accion-tabla eliminar_condicioniva tooltipsC">
                            <i class="fa fa-times-circle text-danger"></i>
                        </button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @include('compras.concepto_ivacompra.template')
        <div class="d-flex justify-content-end">
            <button type="button" id="agrega_renglon_condicioniva" class="btn btn-sm btn-outline-primary">
                <i class="fa fa-plus"></i> Agregar renglón
            </button>
        </div>
    </div>
</div>
