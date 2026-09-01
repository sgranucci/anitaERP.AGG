@extends("theme.$theme.layout")
@section('titulo')
    Facturas Tienda Nube
@endsection

@section("scripts")
<script src="{{asset('assets/pages/scripts/admin/index.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/pages/scripts/ventas/facturante/index.js')}}" type="text/javascript"></script>
@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<meta name="csrf-token" content="{{ csrf_token() }}" />
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <form action="{{route('generar_facturas_tiendanube')}}" id="form-importar-facturas" data-action="{{route('generar_facturas_tiendanube')}}" class="form-horizontal form-label-right" method="POST" autocomplete="off">
            @csrf
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title">Facturas Tienda Nube</h3>
                    <div class="card-tools">
                        <button type="submit" class="btn btn-success" id="btn-importar-facturas">Importar a Anita</button>
                    </div>
                </div>
                <input name="desdefecha" id="desdefecha" type="hidden" value="{{$desdefecha}}">
                <input name="hastafecha" id="hastafecha" type="hidden" value="{{$hastafecha}}">
                <h2 id="loading" style="display:none">Importando facturas ...</h2>
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
                        <tbody id="tabla-factura">
                            @foreach ($datas as $data)
                            <tr class="filafactura">
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
                                    <input name="items[]" class="factura-items" type="hidden" value='@json($data->Items)'>
                                    <input name="caes[]" class="factura-cae" type="hidden" value="{{$data->CAE}}">
                                    <input name="fechaVencimientoCaes[]" class="fechavencimientocae" type="hidden" value="{{$data->FechaVencimientoCae}}">
                                    <input name="clientes[]" class="factura-cliente" type="hidden" value='@json($data->Cliente)'>
                                    <input type="text" name="Comprobantes[]" class="form-control" value="{{$data->TipoComprobante}}&nbsp;{{$data->Prefijo}}-{{$data->Numero}}" readonly>
                                </td>
                                <td>
                                    <input type="text" name="Numeros[]" class="form-control" value="{{$data->Numero}}" readonly>
                                </td>
                                <td>
                                    <input type="text" name="fechas[]" class="form-control" value="{{date('d/m/Y', strtotime($data->FechaHora ?? ''))}}" readonly>
                                </td>
                                <td>
                                    <input type="text" name="clientes_display[]" class="form-control" value="{{$data->Cliente->RazonSocial}}" readonly>
                                </td>
                                <td>
                                    <input type="text" name="documentos[]" class="form-control documento" value="{{$data->Cliente->NroDocumento}}" readonly>
                                </td>
                                <td>
                                    <input type="text" name="caes_display[]" class="form-control" value="{{$data->CAE}}" readonly>
                                </td>
                                <td>
                                    <input type="text" name="totalExentos[]" class="form-control totalexento" value="{{$data->SubTotalExcento}}" readonly>
                                </td>
                                <td>
                                    <input type="text" name="totalNetos[]" class="form-control totalneto" value="{{$data->TotalNeto}}" readonly>
                                </td>
                                <td>
                                    <input type="text" name="totalPercepcionesIIBB[]" class="form-control totalprecepcioniibb" value="{{$data->PercepcionIIBB}}" readonly>
                                </td>
                                <td>
                                    <input type="text" name="totalIvas[]" class="form-control totaliva" value="{{$data->IVA1+$data->IVA2}}" readonly>
                                </td>
                                <td>
                                    <input type="text" name="totales[]" class="form-control total" value="{{$data->Total}}" readonly>
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
