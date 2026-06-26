<div class="card card-outline card-secondary form3 mb-0" style="display: none">
    <div class="card-header py-2">
        <strong class="text-dark"><i class="fas fa-exchange-alt mr-1"></i> Tipos de transacci&oacute;n de stock autorizados</strong>
        <small class="text-muted d-block d-md-inline d-md-ml-2">Opcional &mdash; sin filas = todas las transacciones activas</small>
    </div>
    <div class="card-body">
        <table class="table table-sm" id="usuario-tipotransaccion-stock-table">
            <thead>
                <tr>
                    <th style="width: 18%;">Abreviatura</th>
                    <th style="width: 52%;">Tipo de transacci&oacute;n</th>
                    <th style="width: 20%;">Operaci&oacute;n</th>
                    <th style="width: 10%;"></th>
                </tr>
            </thead>
            <tbody id="tbody-usuario-tipotransaccion-stock-table">
                @php
                    $filasTipo = old('tipotransaccion_stock_ids');
                    if ($filasTipo === null && isset($data)) {
                        $filasTipo = $data->tipotransaccionesStockAutorizadas;
                    }
                    if ($filasTipo === null) {
                        $filasTipo = collect();
                    }
                    if (is_array($filasTipo)) {
                        $filasTipo = collect($filasTipo);
                    }
                    $operacionEnum = \App\Models\Stock\Tipotransaccion_Stock::$enumOperacion;
                @endphp
                @foreach ($filasTipo as $tipo)
                    @php
                        if (is_object($tipo)) {
                            $tipoModel = $tipo;
                            $tipoId = (int) ($tipoModel->id ?? 0);
                        } else {
                            $tipoId = (int) $tipo;
                            $tipoModel = isset($data)
                                ? $data->tipotransaccionesStockAutorizadas->firstWhere('id', $tipoId)
                                : null;
                            if (! $tipoModel && $tipoId > 0) {
                                $tipoModel = \App\Models\Stock\Tipotransaccion_Stock::query()->find($tipoId);
                            }
                        }
                    @endphp
                    @if ($tipoId > 0)
                        <tr class="item-usuario-tipotransaccion-stock tm-tipotransaccion-stock-campo">
                            <td>
                                <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;">
                                    <input type="hidden" name="tipotransaccion_stock_ids[]" class="tipotransaccion_stock_id" value="{{ $tipoId }}">
                                    <button type="button" title="Consulta tipos de transacci&oacute;n" class="btn-accion-tabla consultatipotransaccionstock tooltipsC">
                                        <i class="fa fa-search text-primary"></i>
                                    </button>
                                    <input type="text" class="form-control form-control-sm abreviaturatipotransaccionstock" value="{{ $tipoModel->abreviatura ?? '' }}" autocomplete="off" style="max-width: 6rem;">
                                </div>
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm nombretipotransaccionstock" value="{{ $tipoModel->nombre ?? '' }}" readonly>
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm operacion-tipotransaccion-stock" value="{{ $operacionEnum[$tipoModel->operacion ?? ''] ?? ($tipoModel->operacion ?? '') }}" readonly>
                            </td>
                            <td>
                                <button type="button" title="Elimina esta l&iacute;nea" class="btn-accion-tabla eliminar_usuario_tipotransaccion_stock tooltipsC">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
        @include('admin.usuario.template_tipotransaccion_stock')
        <div class="row">
            <div class="col-md-12">
                <button type="button" id="agrega_renglon_usuario_tipotransaccion_stock" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
            </div>
        </div>
    </div>
</div>
