@extends("theme.$theme.layout")
@section('titulo')
    Genera Asientos de Partidas de Gastos
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/presupuesto/generaasiento/crear.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Datos Proceso de Generación de Asientos</h3>
            </div>
            <form action="{{route('crear_genera_asiento_partidagasto')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("post")
                <div class="card-body">
                    @include('presupuesto.generaasiento.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-genera-excel', array('ruta' => 'crear_generaasiento'))
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
