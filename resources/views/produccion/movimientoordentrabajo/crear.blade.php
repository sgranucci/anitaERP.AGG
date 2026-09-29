@extends("theme.$theme.layout")
@section('titulo')
    Movimientos de Ordenes de Trabajo
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>

<script>
    $(function () {
  		$("#ordenestrabajo").focus();

        @php
            $textosExitoOt = \Illuminate\Support\Arr::wrap(session('mensaje'));
            $textoExitoOt = trim(implode(' ', array_map(function ($texto) {
                return is_array($texto) ? implode(' ', array_map('strval', $texto)) : (string) $texto;
            }, $textosExitoOt)));
        @endphp
        @if ($textoExitoOt !== '')
        if (window.toastr) {
            toastr.success(@json($textoExitoOt), '', {
                positionClass: 'toast-bottom-right',
                timeOut: 1600,
                extendedTimeOut: 400,
                closeButton: false,
                progressBar: false,
                preventDuplicates: true
            });
        }
        @endif
    });
</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje', ['ocultarMensajeExito' => true])
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Crear Movimiento de OT</h3>
                <div class="card-tools">
                    <a href="{{route('movimientoordentrabajo')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('guardar_movimientoordentrabajo')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                <div class="card-body">
        			<input type="hidden" id="movimiento_id" name="movimiento_id" value="" >
                    @include('produccion.movimientoordentrabajo.form')
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
