@extends("theme.$theme.layout")
@section('titulo')
    M&oacute;dulos
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>

    <script>
      $(document).ready(function(){
        let row_number = {{ count(old('talles', $modulo->talles->count() ? $modulo->talles : [''])) }};
        $("#add_row").click(function(e){
          e.preventDefault();
          let new_row_number = row_number - 1;
          $('#talle' + row_number).html($('#talle' + new_row_number).html()).find('td:first-child');
          $('#talles_table').append('<tr id="talle' + (row_number + 1) + '"></tr>');
          row_number++;
        });
        $("#delete_row").click(function(e){
          e.preventDefault();
          if(row_number > 1){
            $("#talle" + (row_number - 1)).html('');
            row_number--;
          }
        });
      });
    </script>

@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Editar M&oacute;dulo</h3>
                <div class="card-tools">
                    <a href="{{route('modulo')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('actualizar_modulo', ['id' => $modulo->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("put")
                <div class="card-body">
                    @include('stock.modulo.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-editar')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
