@extends("theme.$theme.layout")
@section('titulo')
    Editar art&iacute;culo
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script>

	$(function()
	{
		$('#agrega_renglon_caja').on('click', agregaRenglonCaja);
		$('.eliminarCaja').on('click', borraRenglonCaja);
	});

    function agregaRenglonCaja() 
	{
    	if (event != undefined)
    		event.preventDefault();

    	var renglon = $('#template-renglon-caja').html();

    	$("#cajas_table").append(renglon);

		// Reactiva eventos
		$('.eliminarCaja').off('click');
		$('.eliminarCaja').on('click', borraRenglonCaja);
    }

    function borraRenglonCaja() 
	{
    	event.preventDefault();
    	if (confirm("Desea borrar renglon?"))
    	{
    		$(this).parents('tr').remove();
    	}
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
                <h3 class="card-title">Editar Art&iacute;culo - Datos T&eacute;cnica</h3>
                <div class="card-tools">
                    <a href="{{ URL::previous() }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('product.tecnica.update', ['id' => $producto->id])}}" id="form-general" enctype="multipart/form-data" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("put")
                <input type="hidden" name="id" class="form-control" value="{{ $producto->id }}" />
                @include('stock.product.tecnica.partials.form', ['edit' => true])
            </form>
        </div>
    </div>
</div>
@endsection
