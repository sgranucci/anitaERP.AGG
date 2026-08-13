@php
    $filasServicio = collect();
    if (old('servicios_clientes') !== null) {
        foreach ((array) old('servicios_clientes', []) as $i => $cliente) {
            $filasServicio->push((object) [
                'cliente' => $cliente,
                'detalle' => old('servicios_detalles.'.$i),
                'empresa_id' => old('servicios_empresa_ids.'.$i),
            ]);
        }
    } elseif (isset($data) && $data->proveedor_servicios && $data->proveedor_servicios->count()) {
        $filasServicio = $data->proveedor_servicios;
    }
@endphp
<div id="tab7" class="card form7 tab-content" style="display: none">
    <div class="card-body">
        <h3>Servicios / medidores</h3>
        <p class="text-muted small mb-2">
            N&uacute;mero de cliente / medidor y detalle (luz, gas, agua, etc.). Se sincroniza con Anita (`servicios`).
            Si hay renglones, la precarga de facturas asigna comprobantes de servicio (FIS / FNS / &hellip;).
        </p>
        <table class="table table-sm table-bordered" id="servicio-table">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 35%;">Cliente / medidor</th>
                    <th style="width: 50%;">Detalle</th>
                    <th style="width: 10%;"></th>
                </tr>
            </thead>
            <tbody id="tbody-servicio-table">
                @foreach ($filasServicio as $servicio)
                    <tr class="item-servicio">
                        <td>
                            <input type="text" name="servicios_nros[]" class="form-control iiservicio" readonly value="{{ $loop->index + 1 }}" />
                            <input type="hidden" name="servicios_empresa_ids[]" class="servicio-empresa-id" value="{{ $servicio->empresa_id ?? ($data->empresa_id ?? '') }}" />
                        </td>
                        <td>
                            <input type="text" name="servicios_clientes[]" class="form-control servicio-cliente"
                                value="{{ $servicio->cliente ?? '' }}" maxlength="255" placeholder="Nro. cliente / medidor" />
                        </td>
                        <td>
                            <input type="text" name="servicios_detalles[]" class="form-control servicio-detalle"
                                value="{{ $servicio->detalle ?? '' }}" maxlength="255" placeholder="Detalle" />
                        </td>
                        <td>
                            <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_servicio tooltipsC">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @include('compras.proveedor.template7')
        <div class="row">
            <div class="col-md-12">
                <button type="button" id="agrega_renglon_servicio" class="pull-right btn btn-outline-primary btn-sm">+ Agrega rengl&oacute;n</button>
            </div>
        </div>
    </div>
</div>
