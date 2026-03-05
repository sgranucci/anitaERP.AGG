@extends("theme.$theme.layout")
@section('titulo')
    Crear ordenes de trabajo
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script>

    $(function () {
		$('.articulo').on('click', function (event) {
        	var select = $(this);
        	var options = select.children();
        	var articulo_id = $(this).val();
        	var mventa_id = $('#mventa_id').val();
        	var mventa_nombre = $("#mventa_id option:selected").text();
        	let sel_articulos = JSON.parse(document.querySelector('#marca').dataset.articulo);
	
        	select.empty();
	
        	if (mventa_nombre === "-- Seleccionar marca --")
            	select.append('<option value="">-- Articulos sin filrar --</option>');
        	else
            	select.append('<option value="">-- Articulos ' + mventa_nombre + ' --</option>');
	
			// Filtra articulos por marca
        	$.each(sel_articulos, function(obj, item) {
            	if (articulo_id == item.id)
                	op = 'selected="selected"';
            	else
                	op = '';
            	if (mventa_id == undefined || mventa_id == '')
                	select.append('<option value="' + item.id + '"'+op+'>' + item.descripcion + '-' + item.sku + '</option>');
            	else
            	{
                	if (item.mventa_id == mventa_id)
                    	select.append('<option value="' + item.id + '"'+op+'>' + item.descripcion + '-' + item.sku + '</option>');
            	}
        	});
	
        	if (articulo_id > 0)
        	{
            	select.value = articulo_id;
	
            	select.children().filter(function(){
                	return this.text == articulo_id;
            	}).prop('selected', true);
        	}
    	});

		$('.articulo').on('change', function (event) {
			event.preventDefault();
			var articulo = $(this);
	
        	completarCombinaciones(articulo, 0);
    	});
	});

    function completarCombinaciones(articulo, combinacion_id){
        var comb_id;
		var articulo_id = $(articulo).val();
        $.get('/anitaERP/public/stock/leercombinaciones/'+articulo_id, function(data){
            var comb = $.map(data, function(value, index){
                return [value];
            });
            $("#combinacion_id").empty();
            $("#combinacion_id").append('<option value=""></option>');
            $.each(comb, function(index,value){
				if (value.id == combinacion_id)
                	$("#combinacion_id").append('<option value="'+value.id+'" selected>'+value.codigo+'-'+value.nombre+'</option>');
				else
                	$("#combinacion_id").append('<option value="'+value.id+'">'+value.codigo+'-'+value.nombre+'</option>');
            });
        });
        setTimeout(() => {
                var comb_id = $("#combinacion_id").val();
        }, 3000);
    }


</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Crear orden de trabajo</h3>
                <div class="card-tools">
                    <a href="{{ URL::previous() }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('consultar_pendiente_ot')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("post")
                @include('ventas.ordentrabajo.form')
            </form>
        </div>
    </div>
</div>
@endsection
