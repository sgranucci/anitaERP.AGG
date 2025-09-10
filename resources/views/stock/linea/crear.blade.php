@extends("theme.$theme.layout")
@section('titulo')
    L&iacute;neas
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>

@if (config('app.empresa') == 'Calzados Ferli')
    <script>

    $(document).ready(function(){
        let row_number = 1;

        $("#add_row").click(function(e){
            e.preventDefault();
            let new_row_number = row_number - 1;
            $('#modulo' + row_number).html($('#modulo' + new_row_number).html()).find('td:first-child');
            $('#modulos_table').append('<tr id="modulo' + (row_number + 1) + '"></tr>');
            row_number++;
        });

        $("#delete_row").click(function(e){
            e.preventDefault();
            if(row_number > 1){
                $("#modulo" + (row_number - 1)).html('');
                row_number--;
            }
        });
    });

    </script>
@endif

@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Crear L&iacute;nea</h3>
                <div class="card-tools">
                    <a href="{{route('linea')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('guardar_linea')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                <div class="card-body">
                    @include('stock.linea.form')
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
