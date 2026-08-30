@php
    $cuentasRegimen = [];
    if (old('cuentacontable_ids') !== null) {
        foreach (old('cuentacontable_ids', []) as $i => $cuentaId) {
            $cuentasRegimen[] = (object) [
                'empresa_id' => old('empresa_ids.'.$i),
                'tipotransaccion_id' => old('tipotransaccion_ids.'.$i),
                'cuentacontable_id' => $cuentaId,
                'codigo' => old('codigos.'.$i, ''),
                'nombre' => old('nombres.'.$i, ''),
                'vigencia_desde' => old('vigencia_desde.'.$i),
                'vigencia_hasta' => old('vigencia_hasta.'.$i),
                'centrocosto_id' => old('centrocosto_ids.'.$i),
                'centrocosto_codigo' => old('codigos_centrocosto.'.$i, ''),
                'centrocosto_nombre' => old('nombres_centrocosto.'.$i, ''),
                'creousuario_id' => old('creousuario_cuentacontable_ids.'.$i, auth()->id()),
            ];
        }
    } elseif (isset($data) && $data->relationLoaded('cuentas')) {
        foreach ($data->cuentas as $cuenta) {
            $cuentasRegimen[] = (object) [
                'empresa_id' => $cuenta->empresa_id,
                'tipotransaccion_id' => $cuenta->tipotransaccion_id,
                'cuentacontable_id' => $cuenta->cuentacontable_id,
                'codigo' => $cuenta->cuentacontables->codigo ?? '',
                'nombre' => $cuenta->cuentacontables->nombre ?? '',
                'vigencia_desde' => $cuenta->vigencia_desde?->format('Y-m-d'),
                'vigencia_hasta' => $cuenta->vigencia_hasta?->format('Y-m-d'),
                'centrocosto_id' => $cuenta->centrocosto_id,
                'centrocosto_codigo' => $cuenta->centrocosto->codigo ?? '',
                'centrocosto_nombre' => $cuenta->centrocosto->nombre ?? '',
                'creousuario_id' => $cuenta->creousuario_id,
            ];
        }
    }
@endphp
<div class="card card-outline card-info mt-3">
    <div class="card-header">
        <h3 class="card-title">Cuentas contables (matriz)</h3>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            Default: una cuenta por empresa, tipo vacío y sin fechas (como hoy).
            Tipo y vigencia son opcionales: solo se usan si se cargan. El centro de costo es opcional;
            si queda vacío el asiento sigue con la lógica actual.
        </p>
        <div class="table-responsive">
        <table class="table table-sm table-bordered" id="cv-cuentacontable-table">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th>Empresa</th>
                    <th>Tipo</th>
                    <th>Desde</th>
                    <th>Hasta</th>
                    <th>Cuenta contable</th>
                    <th>Centro de costo</th>
                    <th class="width80">Acciones</th>
                </tr>
            </thead>
            <tbody id="tbody-cv-cuentacontable-table">
                @foreach ($cuentasRegimen as $cuenta)
                    <tr class="item-cv-cuentacontable">
                        <td>
                            @include('includes.form-empresa-asignada-control', [
                                'empresa_query' => $empresa_query ?? [],
                                'empresa_id' => $cuenta->empresa_id ?? null,
                                'name' => 'empresa_ids[]',
                                'select_class' => 'empresa',
                                'permite_vacio' => true,
                                'opcion_vacia' => '-- Seleccionar --',
                                'required' => false,
                            ])
                        </td>
                        <td>
                            <select name="tipotransaccion_ids[]" class="form-control form-control-sm">
                                <option value="">Todos (default)</option>
                                @foreach ($tipo_query ?? [] as $tipo)
                                    <option value="{{ $tipo->id }}" @selected((int) ($cuenta->tipotransaccion_id ?? 0) === (int) $tipo->id)>
                                        {{ trim(($tipo->abreviatura ?? '').' — '.($tipo->nombre ?? '')) }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="date" name="vigencia_desde[]" class="form-control form-control-sm"
                                value="{{ $cuenta->vigencia_desde ?? '' }}">
                        </td>
                        <td>
                            <input type="date" name="vigencia_hasta[]" class="form-control form-control-sm"
                                value="{{ $cuenta->vigencia_hasta ?? '' }}">
                        </td>
                        <td>
                            <div class="form-group row mb-0">
                                <input type="hidden" name="cuenta[]" class="form-control cv-iicuenta" readonly value="{{ $loop->iteration }}" />
                                <input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="{{ $cuenta->cuentacontable_id ?? '' }}">
                                <input type="hidden" class="cuentacontable_id_previa" name="cuentacontable_id_previa[]" value="{{ $cuenta->cuentacontable_id ?? '' }}">
                                <button type="button" title="Consulta cuentas" class="btn-accion-tabla consultacuentacontable tooltipsC">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" style="WIDTH: 110px;HEIGHT: 38px" class="codigocuentacontable form-control" name="codigos[]" value="{{ $cuenta->codigo ?? '' }}">
                                <input type="text" style="WIDTH: 180px;HEIGHT: 38px" class="nombrecuentacontable form-control" name="nombres[]" value="{{ $cuenta->nombre ?? '' }}" readonly>
                                <input type="hidden" class="codigo_previo" name="codigo_previos[]" value="{{ $cuenta->codigo ?? '' }}">
                            </div>
                        </td>
                        <td>
                            <div class="tm-centrocosto-campo d-flex flex-nowrap align-items-center" style="gap: 4px;">
                                <input type="hidden" class="centrocosto_id" name="centrocosto_ids[]" value="{{ $cuenta->centrocosto_id ?? '' }}">
                                <button type="button" title="Consulta centros de costo (F1)" class="btn-accion-tabla consultacentrocosto flex-shrink-0">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" class="form-control form-control-sm codigocentrocosto" name="codigos_centrocosto[]"
                                    value="{{ $cuenta->centrocosto_codigo ?? '' }}" placeholder="Cód." style="width: 4.5rem;">
                                <input type="text" class="form-control form-control-sm descripcioncentrocosto" name="nombres_centrocosto[]"
                                    value="{{ $cuenta->centrocosto_nombre ?? '' }}" placeholder="CC" readonly style="width: 7rem;">
                            </div>
                        </td>
                        <td>
                            <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cv_cuentacontable tooltipsC">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                            <input type="hidden" name="creousuario_cuentacontable_ids[]" class="form-control creousuario_cuentacontable_id" value="{{ $cuenta->creousuario_id ?? auth()->id() }}"/>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        @include('ventas.concepto_venta.template_cuentas')
        <div class="row">
            <div class="col-md-12">
                <button type="button" id="cv-agrega_renglon_cuentacontable" class="btn btn-outline-primary btn-sm">
                    + Agrega rengl&oacute;n
                </button>
            </div>
        </div>
    </div>
</div>
@include('ventas.concepto_venta.form3')
