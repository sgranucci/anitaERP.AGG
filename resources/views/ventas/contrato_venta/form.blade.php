@php
    use App\Support\Ventas\ConceptoVentaPlantillaMotor;
    use App\Support\Ventas\ContratoVentaSupport;

    $clienteModel = $data->cliente ?? null;
    $conceptoModel = $data->conceptoVenta ?? null;
    $datosMap = [];
    if (old('dato_claves') !== null) {
        foreach (old('dato_claves', []) as $i => $clave) {
            $claveN = ConceptoVentaPlantillaMotor::normalizarClave((string) $clave);
            if ($claveN !== '') {
                $datosMap[$claveN] = old('dato_valores.'.$i, '');
            }
        }
    } elseif (isset($data) && $data->relationLoaded('datos')) {
        foreach ($data->datos as $dato) {
            $claveN = ConceptoVentaPlantillaMotor::normalizarClave((string) $dato->clave);
            if ($claveN !== '') {
                $datosMap[$claveN] = (string) ($dato->valor ?? '');
            }
        }
    }

    $tagsPedibles = [];
    if ($conceptoModel) {
        $tagsPedibles = \App\Support\Ventas\ConceptoVentaTagSupport::tagsPediblesDesdeConcepto($conceptoModel);
    }
@endphp

<div class="form-group row">
    <label for="codigo" class="col-lg-4 control-label text-right pr-2 requerido">Código</label>
    <div class="col-lg-3">
        <input type="text" name="codigo" id="codigo" class="form-control" maxlength="20"
            value="{{ old('codigo', $data->codigo ?? '') }}" required>
    </div>
</div>

@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => old('empresa_id', $data->empresa_id ?? null),
    'solo_lectura' => isset($data) && $data->exists,
    'col_label' => 'col-lg-4 text-right pr-2',
    'col_input' => 'col-lg-5',
])

<div class="form-group row tm-cliente-campo">
    <label for="codigocliente" class="col-lg-4 control-label text-right pr-2 requerido">Cliente</label>
    <div class="col-lg-8">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" class="cliente_id" name="cliente_id" id="cliente_id"
                value="{{ old('cliente_id', $data->cliente_id ?? '') }}" required>
            <button type="button" title="Consulta clientes (F1)" class="btn-accion-tabla consultacliente flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" class="form-control codigocliente" id="codigocliente" name="codigocliente"
                value="{{ old('codigocliente', $clienteModel->codigo ?? '') }}"
                placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off"
                style="width: 5.5rem; flex-shrink: 0;">
            <input type="text" class="form-control nombrecliente text-truncate" id="nombrecliente"
                value="{{ old('nombrecliente', $clienteModel->nombre ?? '') }}"
                placeholder="Descripci&oacute;n" readonly style="min-width: 0; flex: 1 1 auto;">
        </div>
    </div>
</div>

@include('ventas.partials.campo_consulta_concepto_venta', [
    'conceptoId' => old('concepto_venta_id', $data->concepto_venta_id ?? ''),
    'codigo' => old('concepto_codigo', $conceptoModel->codigo ?? ''),
    'descripcion' => old('concepto_descripcion', $conceptoModel->nombre ?? $conceptoModel->descripcion ?? ''),
    'required' => true,
    'col_label' => 'col-lg-4 control-label text-right pr-2',
    'col_input' => 'col-lg-8',
])

<div class="form-group row">
    <label for="estado" class="col-lg-4 control-label text-right pr-2 requerido">Estado</label>
    <div class="col-lg-3">
        <select name="estado" id="estado" class="form-control" required>
            @foreach ($estados ?? ContratoVentaSupport::ESTADOS as $estado)
                <option value="{{ $estado }}" @selected(old('estado', $data->estado ?? ContratoVentaSupport::ESTADO_ACTIVO) === $estado)>
                    {{ ucfirst($estado) }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-group row">
    <label for="vigencia_desde" class="col-lg-4 control-label text-right pr-2 requerido">Vigencia desde</label>
    <div class="col-lg-3">
        <input type="date" name="vigencia_desde" id="vigencia_desde" class="form-control"
            value="{{ old('vigencia_desde', optional($data->vigencia_desde)->format('Y-m-d') ?? date('Y-m-d')) }}" required>
    </div>
    <label for="vigencia_hasta" class="col-lg-2 control-label text-right pr-2">Hasta</label>
    <div class="col-lg-3">
        <input type="date" name="vigencia_hasta" id="vigencia_hasta" class="form-control"
            value="{{ old('vigencia_hasta', optional($data->vigencia_hasta)->format('Y-m-d')) }}">
    </div>
</div>

<div class="form-group row">
    <label for="periodicidad" class="col-lg-4 control-label text-right pr-2 requerido">Periodicidad</label>
    <div class="col-lg-3">
        <select name="periodicidad" id="periodicidad" class="form-control" required>
            @foreach ($periodicidades ?? ContratoVentaSupport::PERIODICIDADES as $per)
                <option value="{{ $per }}" @selected(old('periodicidad', $data->periodicidad ?? ContratoVentaSupport::PERIODICIDAD_MENSUAL) === $per)>
                    {{ ucfirst($per) }}
                </option>
            @endforeach
        </select>
    </div>
    <label for="dia_facturacion" class="col-lg-2 control-label text-right pr-2 requerido">Día facturación</label>
    <div class="col-lg-2">
        <input type="number" name="dia_facturacion" id="dia_facturacion" class="form-control" min="1" max="28"
            value="{{ old('dia_facturacion', $data->dia_facturacion ?? 1) }}" required>
    </div>
</div>

<div class="form-group row">
    <label for="precio" class="col-lg-4 control-label text-right pr-2">Precio</label>
    <div class="col-lg-3">
        <input type="number" name="precio" id="precio" class="form-control" step="0.0001" min="0"
            value="{{ old('precio', $data->precio ?? '') }}">
    </div>
</div>

<div class="form-group row">
    <label for="observacion" class="col-lg-4 control-label text-right pr-2">Observación</label>
    <div class="col-lg-8">
        <input type="text" name="observacion" id="observacion" class="form-control" maxlength="255"
            value="{{ old('observacion', $data->observacion ?? '') }}">
    </div>
</div>

<div class="card card-outline card-info mt-3">
    <div class="card-header">
        <h3 class="card-title">Datos del abono (tags pedibles del concepto)</h3>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-2">
            Al elegir el concepto se cargan las claves pedibles. Los tags de sistema se completan al facturar.
            El tag <code>periodo</code> puede quedar vacío (se calcula en la factura).
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered" id="cv-dato-table">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width:22%;">Clave</th>
                        <th>Valor</th>
                        <th class="width80">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbody-cv-dato-table">
                    @forelse ($tagsPedibles as $tag)
                        @php
                            $claveTag = $tag['clave'] ?? '';
                            $valorTag = $datosMap[$claveTag] ?? '';
                        @endphp
                        <tr class="item-cv-dato">
                            <td>
                                <input type="text" name="dato_claves[]" class="form-control form-control-sm cv-dato-clave"
                                    maxlength="40" value="{{ $claveTag }}" readonly>
                                <small class="text-muted">{{ $tag['etiqueta'] ?? $claveTag }}</small>
                            </td>
                            <td>
                                <input type="text" name="dato_valores[]" class="form-control form-control-sm"
                                    maxlength="255" value="{{ $valorTag }}"
                                    placeholder="{{ ($claveTag === 'periodo') ? 'Opcional (se calcula al facturar)' : '' }}">
                            </td>
                            <td>
                                <button type="button" title="Quitar" class="btn-accion-tabla eliminar_cv_dato tooltipsC">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        @foreach ($datosMap as $claveDato => $valorDato)
                            <tr class="item-cv-dato">
                                <td>
                                    <input type="text" name="dato_claves[]" class="form-control form-control-sm cv-dato-clave"
                                        maxlength="40" value="{{ $claveDato }}">
                                </td>
                                <td>
                                    <input type="text" name="dato_valores[]" class="form-control form-control-sm"
                                        maxlength="255" value="{{ $valorDato }}">
                                </td>
                                <td>
                                    <button type="button" title="Quitar" class="btn-accion-tabla eliminar_cv_dato tooltipsC">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    @endforelse
                </tbody>
            </table>
        </div>
        <template id="cv-template-renglon-dato">
            <tr class="item-cv-dato">
                <td>
                    <input type="text" name="dato_claves[]" class="form-control form-control-sm cv-dato-clave"
                        maxlength="40" value="" readonly>
                    <small class="text-muted cv-dato-etiqueta"></small>
                </td>
                <td>
                    <input type="text" name="dato_valores[]" class="form-control form-control-sm"
                        maxlength="255" value="">
                </td>
                <td>
                    <button type="button" title="Quitar" class="btn-accion-tabla eliminar_cv_dato tooltipsC">
                        <i class="fa fa-times-circle text-danger"></i>
                    </button>
                </td>
            </tr>
        </template>
    </div>
</div>
