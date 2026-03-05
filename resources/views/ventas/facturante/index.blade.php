@extends("theme.$theme.layout")
@section('titulo')
    Facturas Tienda Nube
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <form action="{{route('generar_facturas_tiendanube')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
            @csrf
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title">Facturas Tienda Nube</h3>
                    <div class="card-tools">
                        @include('includes.boton-form-crear')
                    </div>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-striped table-bordered table-hover" id="tabla-data-3">
                        <thead>
                            <tr>
                                <th class="width40">ID</th>
                                <th class="width20">Número</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Documento</th>
                                <th>CAE</th>
                                <th>Exento</th>
                                <th>Gravado</th>
                                <th>Percepciones IIBB</th>
                                <th>Iva</th>
                                <th>Total</th>
                                <th>Medio de pago</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($datas as $data)
                            <tr>
                                <td>
                                    <input name="tipoComprobantes[]" class="tipocomprobante" type="hidden" value="{{$data->TipoComprobante}}">    
                                    <input name="prefijos[]" class="prefijo" type="hidden" value="{{$data->Prefijo}}">
                                    <input name="numeros[]" class="numero" type="hidden" value="{{$data->Numero}}">
                                    <input name="condicionVentas[]" class="condicionventa" type="hidden" value="{{$data->CondicionVenta}}">
                                    <input name="fechaHoras[]" class="fechahora" type="hidden" value="{{$data->FechaHora}}">
                                    <input name="ivas1[]" class="iva1" type="hidden" value="{{$data->IVA1}}">
                                    <input name="ivas2[]" class="iva2" type="hidden" value="{{$data->IVA2}}">
                                    <input name="subtotalNoAlcanzados[]" class="subnoalc" type="hidden" value="{{$data->SubtotalNoAlcanzado}}">
                                    <input name="subtotalExcentos[]" class="subexcento" type="hidden" value="{{$data->SubTotalExcento}}">
                                    <input name="items[]" class="item" type="hidden" value="{{json_encode($data->Items,TRUE)}}">
                                    <input name="caes[]" class="cae" type="hidden" value="{{$data->CAE}}">
                                    <input name="fechaVencimientoCaes[]" class="fecha vencimientocae" type="hidden" value="{{$data->FechaVencimientoCae}}">
                                    <input name="clientes[]" class="cliente" type="hidden" value="{{json_encode($data->Cliente)}}">
                                    <input type="text" name="Comprobantes[]" class="form-control item" value="{{$data->TipoComprobante}}&nbsp;{{$data->Prefijo}}-{{$data->Numero}}" readonly>
                                </td>
                                <td>
                                    <input type="text" name="Numeros[]" class="form-control item" value="{{$data->Numero}}" readonly>
                                </td>
                                <td>
                                    <input type="text" name="fechas[]" class="form-control item" value="{{date('d/m/Y', strtotime($data->FechaHora ?? ''))}}" readonly>
                                </td>
                                <td>
                                    <input type="text" name="clientes[]" class="form-control item" value="{{$data->Cliente->RazonSocial}}" readonly>
                                </td>
                                <td>
                                    <input type="text" name="documentos[]" class="form-control item" value="{{$data->Cliente->NroDocumento}}" readonly>                                
                                </td>
                                <td>
                                    <input type="text" name="caes[]" class="form-control item" value="{{$data->CAE}}" readonly>                                
                                </td>
                                <td>
                                    <input type="text" name="totalExentos[]" class="form-control item" value="{{$data->SubTotalExcento}}" readonly>                                
                                </td>
                                <td>
                                    <input type="text" name="totalNetos[]" class="form-control item" value="{{$data->TotalNeto}}" readonly>
                                </td>
                                <td>
                                    <input type="text" name="totalPercepcionesIIBB[]" class="form-control item" value="{{$data->PercepcionIIBB}}" readonly>
                                </td>
                                <td>
                                    <input type="text" name="totalIvas[]" class="form-control item" value="{{$data->IVA1+$data->IVA2}}" readonly>
                                </td>
                                <td>
                                    <input type="text" name="totales[]" class="form-control item" value="{{$data->Total}}" readonly>
                                </td>
                                <td>
                                    <select name="mediospago[]" data-placeholder="Medios de Pago" class="form-control mediopago" data-fouc>
                                        <option value="">-- Seleccionar medio de pago --</option>
                                        @foreach ($medioPago_enum as $value => $mediopago)
        					                <option value="{{ $value }}"
        						                @if ($data->mediopago == $value) selected @endif
        						                >{{ $mediopago }}</option>
        				                @endforeach
                                    </select>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
