@extends("theme.$theme.layout")
@section('titulo') Imputación contable de conceptos @endsection
@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/concepto/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/imputacion_concepto/form.js') }}" type="text/javascript"></script>
@endsection
@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Crear imputación contable</h3>
                <div class="card-tools">
                    @include('includes.sueldos.boton-manual')
                    <a href="{{ route('consultar_imputacion_concepto_sueldos') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_imputacion_concepto_sueldos') }}" method="POST" class="form-horizontal form--label-right" id="form-general" autocomplete="off">
                @csrf
                <div class="card-body">
                    @include('sueldos.imputacion_concepto.form')
                </div>
                <div class="card-footer">
                    @include('includes.boton-form-crear')
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.sueldos.modalconsultaconcepto_sueldos')
@include('includes.contable.modalconsultacuentacontable')
@endsection
