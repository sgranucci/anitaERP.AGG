@extends("theme.$theme.layout")
@section('titulo')
    Vouchers
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/caja/voucher/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/receptivo/reserva/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/receptivo/servicioterrestre/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/receptivo/guia/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/proveedor/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/caja/cuentacaja/consulta.js")}}" type="text/javascript"></script>
<script>
    var urlConsultaProveedor = "{{ route('editar_proveedor', ':id') }}";
</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Crear Voucher</h3>
                <div class="card-tools">
                    <a href="{{route('voucher')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('guardar_voucher')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                        <i class="fa fa-user"></i> Datos principales
                    </button>
                    <button type="button" id="botonform2" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Reservas
                    </button>
                    <button type="button" id="botonform3" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Formas de pago
                    </button>
                </div>
                <div class="card-body">
                    @include('caja.voucher.form')
                    @include('caja.voucher.form2')
                    @include('caja.voucher.form3')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-crear')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
