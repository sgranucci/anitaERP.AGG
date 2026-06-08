<div class="card card-outline card-secondary form2 mb-0" style="display: none">
    <div class="card-header py-2">
        <strong class="text-dark"><i class="fas fa-warehouse mr-1"></i> Depósitos autorizados</strong>
        <small class="text-muted d-block d-md-inline d-md-ml-2">Opcional — sin filas = todos los depósitos de sus empresas</small>
    </div>
    <div class="card-body">
        <table class="table table-sm" id="usuario-deposito-table">
            <thead>
                <tr>
                    <th style="width: 18%;">Código</th>
                    <th style="width: 42%;">Depósito</th>
                    <th style="width: 30%;">Empresa</th>
                    <th style="width: 10%;"></th>
                </tr>
            </thead>
            <tbody id="tbody-usuario-deposito-table">
                @php
                    $filasDeposito = old('deposito_ids');
                    if ($filasDeposito === null && isset($data)) {
                        $filasDeposito = $data->depositosAutorizados;
                    }
                    if ($filasDeposito === null) {
                        $filasDeposito = collect();
                    }
                    if (is_array($filasDeposito)) {
                        $filasDeposito = collect($filasDeposito);
                    }
                @endphp
                @foreach ($filasDeposito as $deposito)
                    @php
                        if (is_object($deposito)) {
                            $depModel = $deposito;
                            $depId = (int) ($depModel->id ?? 0);
                        } else {
                            $depId = (int) $deposito;
                            $depModel = isset($data)
                                ? $data->depositosAutorizados->firstWhere('id', $depId)
                                : null;
                            if (! $depModel && $depId > 0) {
                                $depModel = \App\Models\Stock\Depmae::with('empresas')->find($depId);
                            }
                        }
                    @endphp
                    @if ($depId > 0)
                        <tr class="item-usuario-deposito">
                            <td>
                                <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;">
                                    <input type="hidden" name="deposito_ids[]" class="deposito_id" value="{{ $depId }}">
                                    <button type="button" title="Consulta depósitos" class="btn-accion-tabla consultadeposito tooltipsC">
                                        <i class="fa fa-search text-primary"></i>
                                    </button>
                                    <input type="text" class="form-control form-control-sm codigodeposito" value="{{ $depModel->codigo ?? '' }}" autocomplete="off" style="max-width: 6rem;">
                                </div>
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm descripciondeposito" value="{{ $depModel->nombre ?? '' }}" readonly>
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm empresa-deposito-nombre" value="{{ optional($depModel?->empresas)->nombre ?? '' }}" readonly>
                            </td>
                            <td>
                                <button type="button" title="Elimina esta línea" class="btn-accion-tabla eliminar_usuario_deposito tooltipsC">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
        @include('admin.usuario.template_deposito')
        <div class="row">
            <div class="col-md-12">
                <button type="button" id="agrega_renglon_usuario_deposito" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
            </div>
        </div>
    </div>
</div>
