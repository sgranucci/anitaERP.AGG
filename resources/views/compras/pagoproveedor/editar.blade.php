@extends("theme.$theme.layout")
@section('titulo')
    Editar orden de pago
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/cuentacaja/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/asiento/asiento_externo.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/banco/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/ingresoegreso/cheques.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/pagoproveedor/form.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/pagoproveedor/crear.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row" id="editar">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Editar orden de pago</h3>
                <div class="card-tools">
                    <a href="{{ route('pagoproveedor') }}" class="btn btn-outline-info btn-sm"><i class="fa fa-reply"></i> Volver</a>
                </div>
            </div>
            <form action="{{ route('actualizar_pagoproveedor', $data->id) }}" method="POST" id="form-pagoproveedor" class="form-horizontal form--label-right" autocomplete="off">
                @csrf
                @method('PUT')
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm"><i class="fa fa-user"></i> Datos / Deuda</button>
                    <button type="button" id="botonform2" class="btn btn-info btn-sm"><span class="fa fa-copy"></span> Cuentas</button>
                    <button type="button" id="botonform3" class="btn btn-info btn-sm"><span class="fa fa-copy"></span> Cheques</button>
                    <button type="button" id="botonform4" class="btn btn-info btn-sm"><span class="fa fa-copy"></span> Retenciones</button>
                    <button type="button" id="botonform5" class="btn btn-info btn-sm"><span class="fa fa-copy"></span> Historia</button>
                    <button type="button" id="botonform6" class="btn btn-info btn-sm"><span class="fa fa-copy"></span> Asiento Contable</button>
                </div>
                <div class="card-body">
                    @include('compras.pagoproveedor.form')
                    @include('compras.pagoproveedor.form2')
                    @include('compras.pagoproveedor.form3')
                    @include('compras.pagoproveedor.form4')
                    @include('compras.pagoproveedor.form5')
                    @include('includes.contable.formasientoexterno')
                </div>
                <div class="card-footer">
                    <button type="button" id="botonform0" class="btn btn-success"><i class="fa fa-save"></i> Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.compras.modalconsultaproveedor')
@include('includes.caja.modalconsultacuentacaja')
@include('includes.contable.modalconsultacuentacontable')
@endsection
