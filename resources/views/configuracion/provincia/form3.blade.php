@php
    $cuentasForm = [];
    $oldEmpresas = old('empresa_ids');
    if (is_array($oldEmpresas)) {
        $n = count($oldEmpresas);
        for ($i = 0; $i < $n; $i++) {
            $cuentasForm[] = (object) [
                'empresa_id' => old('empresa_ids.'.$i),
                'cuentacontable_id' => old('cuentacontable_ids.'.$i),
                'codigo' => old('codigos.'.$i),
                'nombre' => old('nombres.'.$i),
                'creousuario_id' => old('creousuario_cuentacontable_ids.'.$i),
            ];
        }
    } else {
        foreach (($data->provincia_cuentacontableiibbs ?? collect()) as $fila) {
            $cuentasForm[] = (object) [
                'empresa_id' => $fila->empresa_id,
                'cuentacontable_id' => $fila->cuentacontable_id,
                'codigo' => $fila->cuentacontables->codigo ?? '',
                'nombre' => $fila->cuentacontables->nombre ?? '',
                'creousuario_id' => $fila->creousuario_id,
            ];
        }
    }
    $puedeAbrirAbmCuenta = can('editar-cuentas-contables', false) || can('listar-cuentas-contables', false);
@endphp
<style>
    #cuentacontableiibb-table thead th {
        background: #85C1E9;
        color: #17202A;
        font-size: 0.85rem;
        white-space: nowrap;
    }
    #cuentacontableiibb-table .tm-cuentacontable-campo {
        margin-bottom: 0;
    }
    #cuentacontableiibb-table td {
        vertical-align: middle;
    }
</style>
<div class="card card-outline card-info mb-0">
    <div class="card-header py-2">
        <strong>Cuentas contables por empresa</strong>
    </div>
    <div class="card-body p-2">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-2" id="cuentacontableiibb-table">
                <thead>
                    <tr>
                        <th style="min-width: 10rem;">Empresa</th>
                        <th style="min-width: 16rem;">Cuenta contable</th>
                        <th class="text-center" style="width: 3rem;"></th>
                    </tr>
                </thead>
                <tbody id="tbody-cuentacontableiibb-table">
                    @foreach ($cuentasForm as $cuentacontable)
                        @php
                            $cuentaId = (int) ($cuentacontable->cuentacontable_id ?? 0);
                            $editUrlCuenta = $cuentaId > 0
                                ? route('editar_cuentacontable', [
                                    'id' => $cuentaId,
                                    'origen' => 'modal_consulta',
                                    'vista' => 'consulta',
                                ])
                                : '#';
                        @endphp
                        <tr class="item-cuentacontableiibb">
                            <td>
                                @include('includes.form-empresa-asignada-control', [
                                    'empresa_query' => $empresa_query,
                                    'empresa_id' => $cuentacontable->empresa_id ?? null,
                                    'name' => 'empresa_ids[]',
                                    'select_class' => 'empresa',
                                    'permite_vacio' => true,
                                    'opcion_vacia' => '-- Seleccionar --',
                                    'required' => false,
                                ])
                            </td>
                            <td>
                                <div class="tm-cuentacontable-campo d-flex flex-nowrap align-items-center" style="gap:4px;" id="cuenta">
                                    <input type="hidden" name="cuenta[]" class="form-control iicuenta" readonly value="{{ $loop->index + 1 }}" />
                                    <input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="{{ $cuentaId > 0 ? $cuentaId : '' }}">
                                    <input type="hidden" class="cuentacontable_id_previa" name="cuentacontable_id_previa[]" value="{{ $cuentaId > 0 ? $cuentaId : '' }}">
                                    <button type="button" title="Consulta cuentas" class="btn-accion-tabla consultacuentacontable tooltipsC flex-shrink-0">
                                        <i class="fa fa-search text-primary"></i>
                                    </button>
                                    @if ($puedeAbrirAbmCuenta)
                                        <a href="{{ $editUrlCuenta }}" target="_blank" rel="noopener"
                                           class="btn-accion-tabla btn-link-editar-cuentacontable tooltipsC flex-shrink-0 {{ $cuentaId > 0 ? '' : 'd-none' }}"
                                           title="Abrir cuenta en ABM">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    <input type="text" class="codigocuentacontable form-control form-control-sm" style="width:5.5rem;flex-shrink:0;"
                                           name="codigos[]" value="{{ $cuentacontable->codigo ?? '' }}" placeholder="Cód." autocomplete="off">
                                    <input type="text" class="nombrecuentacontable form-control form-control-sm text-truncate" name="nombres[]"
                                           value="{{ $cuentacontable->nombre ?? '' }}" placeholder="Descripción" readonly style="min-width:0;flex:1 1 auto;">
                                    <input type="hidden" class="codigo_previo" name="codigo_previos[]" value="{{ $cuentacontable->codigo ?? '' }}">
                                </div>
                            </td>
                            <td class="text-center">
                                <button type="button" title="Elimina esta línea" class="btn-accion-tabla eliminar_cuentacontableiibb tooltipsC">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                                <input type="hidden" name="creousuario_cuentacontable_ids[]" class="form-control creousuario_cuentacontable_id" value="{{ $cuentacontable->creousuario_id ?? '' }}"/>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('configuracion.provincia.template3')
        <div class="d-flex justify-content-end">
            <button type="button" id="agrega_renglon_cuentacontableiibb" class="btn btn-sm btn-outline-primary">
                <i class="fa fa-plus"></i> Agrega renglón
            </button>
        </div>
    </div>
</div>
