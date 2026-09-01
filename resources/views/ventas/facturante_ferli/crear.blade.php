@extends("theme.$theme.layout")
@section('titulo')
    Importación facturas Tienda Nube
@endsection

@section("scripts")
<script src="{{asset('assets/pages/scripts/ventas/facturante/crear.js')}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Importación facturas Tienda Nube</h3>
            </div>
            <form action="{{route('listar_facturas_tiendanube')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                <div class="card-body">
                    @include('ventas.facturante.formcrear')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            <button type="submit" class="btn btn-success">Leer comprobantes del periodo</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Verificar importacion del periodo</h3>
            </div>
            <form action="{{route('verificar_importacion_facturante')}}" id="form-verificar-importacion" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                <div class="card-body">
                    <p class="text-muted">Compara Facturante con administracion (venta) y stock local Lugano (stkmov). No modifica datos.</p>
                    <div class="form-group row">
                        <label for="verificar_desdefecha" class="col-lg-3 col-form-label requerido">Desde fecha</label>
                        <div class="col-lg-4">
                            <input type="date" name="desdefecha" id="verificar_desdefecha" class="form-control" value="{{substr(old('desdefecha', date('Y-m-d')),0,10)}}" required/>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="verificar_hastafecha" class="col-lg-3 col-form-label requerido">Hasta fecha</label>
                        <div class="col-lg-4">
                            <input type="date" name="hastafecha" id="verificar_hastafecha" class="form-control" value="{{substr(old('hastafecha', date('Y-m-d')),0,10)}}" required/>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            <button type="submit" class="btn btn-primary">Verificar importacion</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="card card-warning">
            <div class="card-header">
                <h3 class="card-title">Recuperar stock local (Lugano)</h3>
            </div>
            <form action="{{route('recuperar_stock_facturante')}}" id="form-recuperar-stock" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                <div class="card-body">
                    <p class="text-muted">Graba stkmov/stkvmed en el local para facturas Facturante que ya estan en administracion pero no tienen movimiento de stock.</p>
                    <div class="form-group row">
                        <label for="recuperar_desdefecha" class="col-lg-3 col-form-label requerido">Desde fecha</label>
                        <div class="col-lg-4">
                            <input type="date" name="desdefecha" id="recuperar_desdefecha" class="form-control" value="{{substr(old('desdefecha', date('Y-m-d')),0,10)}}" required/>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="recuperar_hastafecha" class="col-lg-3 col-form-label requerido">Hasta fecha</label>
                        <div class="col-lg-4">
                            <input type="date" name="hastafecha" id="recuperar_hastafecha" class="form-control" value="{{substr(old('hastafecha', date('Y-m-d')),0,10)}}" required/>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 col-form-label">Modo</label>
                        <div class="col-lg-4">
                            <div class="form-check">
                                <input type="checkbox" name="dry_run" id="dry_run" class="form-check-input" value="1">
                                <label for="dry_run" class="form-check-label">Solo simular (no grabar)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            <button type="submit" class="btn btn-warning">Recuperar stock local</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
