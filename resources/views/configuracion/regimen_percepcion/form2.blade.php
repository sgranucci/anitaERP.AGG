@php
    $cuentasRegimen = [];
    if (old('cuentacontable_ids') !== null) {
        foreach (old('cuentacontable_ids', []) as $i => $cuentaId) {
            $cuentasRegimen[] = (object) [
                'empresa_id' => old('empresa_ids.'.$i),
                'cuentacontable_id' => $cuentaId,
                'codigo' => old('codigos.'.$i, ''),
                'nombre' => old('nombres.'.$i, ''),
                'creousuario_id' => old('creousuario_cuentacontable_ids.'.$i, auth()->id()),
            ];
        }
    } elseif (isset($data) && $data->relationLoaded('cuentas')) {
        foreach ($data->cuentas as $cuenta) {
            $cuentasRegimen[] = (object) [
                'empresa_id' => $cuenta->empresa_id,
                'cuentacontable_id' => $cuenta->cuentacontable_id,
                'codigo' => $cuenta->cuentacontables->codigo ?? '',
                'nombre' => $cuenta->cuentacontables->nombre ?? '',
                'creousuario_id' => $cuenta->creousuario_id,
            ];
        }
    }
@endphp
<div class="card card-outline card-info mt-3">
    <div class="card-header">
        <h3 class="card-title">Cuentas contables por empresa</h3>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            Cuenta del asiento de facturación de administración para este régimen.
            Una cuenta por empresa. No se usa el ABM de Impuestos nacionales.
        </p>
        <table class="table table-sm table-bordered" id="rp-cuentacontable-table">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th>Empresa</th>
                    <th>Cuenta contable</th>
                    <th class="width80">Acciones</th>
                </tr>
            </thead>
            <tbody id="tbody-rp-cuentacontable-table">
                @foreach ($cuentasRegimen as $cuenta)
                    <tr class="item-rp-cuentacontable">
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
                            <div class="form-group row mb-0">
                                <input type="hidden" name="cuenta[]" class="form-control rp-iicuenta" readonly value="{{ $loop->iteration }}" />
                                <input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="{{ $cuenta->cuentacontable_id ?? '' }}">
                                <input type="hidden" class="cuentacontable_id_previa" name="cuentacontable_id_previa[]" value="{{ $cuenta->cuentacontable_id ?? '' }}">
                                <button type="button" title="Consulta cuentas" class="btn-accion-tabla consultacuentacontable tooltipsC">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" style="WIDTH: 160px;HEIGHT: 38px" class="codigocuentacontable form-control" name="codigos[]" value="{{ $cuenta->codigo ?? '' }}">
                                <input type="text" style="WIDTH: 320px;HEIGHT: 38px" class="nombrecuentacontable form-control" name="nombres[]" value="{{ $cuenta->nombre ?? '' }}" readonly>
                                <input type="hidden" class="codigo_previo" name="codigo_previos[]" value="{{ $cuenta->codigo ?? '' }}">
                            </div>
                        </td>
                        <td>
                            <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_rp_cuentacontable tooltipsC">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                            <input type="hidden" name="creousuario_cuentacontable_ids[]" class="form-control creousuario_cuentacontable_id" value="{{ $cuenta->creousuario_id ?? auth()->id() }}"/>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @include('configuracion.regimen_percepcion.template_cuentas')
        <div class="row">
            <div class="col-md-12">
                <button type="button" id="rp-agrega_renglon_cuentacontable" class="btn btn-outline-primary btn-sm">
                    + Agrega rengl&oacute;n
                </button>
            </div>
        </div>
    </div>
</div>
@include('includes.contable.modalconsultacuentacontable')
