<div class="card form2" style="display: none">
    <h3>Cuotas</h3>
    <div class="card-body">
        <table class="table" id="ordenventa-cuota-table">
            <thead>
                <tr>
                    <th style="width: 7%;">Nro.</th>
                    <th style="width: 10%;">Fecha Factura</th>
                    <th style="width: 20%;">Monto sin IVA</th=>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-ordenventa-cuota-table" class="container-cuota">
            @if ($data->ordenventa_cuotas ?? '') 
                @foreach (old('cuota', $data->ordenventa_cuotas->count() ? $data->ordenventa_cuotas : ['']) as $cuota)
                        @if (isset($cuota->fechafactura))
                        <tr class="item-ordenventa-cuota">
                            <td>
                                <input type="number" name="cuotas[]" class="form-control iicuota" readonly value="{{ $loop->index+1 }}" />
                            </td>							
                            <td>
                                <input type="date" name="fechafacturas[]" class="form-control fechafactura" value="{{old('fechafacturas[]', $cuota->fechafactura ?? '')}}">
                            </td>
                            <td>
                                <input type="number" name="montofacturas[]" class="form-control montofactura" value="{{old('montofacturas[]', $cuota->montofactura ?? '')}}">
                            </td>
                            <td>
                                <button type="button" class="btn-accion-tabla eliminar_ordenventa_cuota tooltipsC">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </td>
                        </tr>
                    @endif
                @endforeach
            @endif
            </tbody>
        </table>
        <div class="form-group row totales-por-cuota">
        </div>
        <div class="col-sm-6">
            <div class="form-group row">
                <label for="tratamiento" class="col-lg-3 col-form-label">Total Orden de Venta</label>
                <input type="text" name="montoordenventa" id="montoordenventa" class="col-lg-4 form-control" placeholder="Monto sin iva" aria-label="Monto sin iva" value="{{$data->monto??''}}" readonly>
            </div>
        </div>
        @include('ordenventa.ordenventa.template2')
        <div class="row">
            <div class="col-sm-6">
                <div class="form-group row">
                    <button id="agrega_renglon_ordenventa_cuota" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
                </div>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="csrf_token" class="form-control" value="{{csrf_token()}}" />

