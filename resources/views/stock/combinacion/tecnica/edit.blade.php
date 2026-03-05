@extends("theme.$theme.layout")
@section('titulo')
    Editar combinac&oacute;n
@endsection

@section("styles")
<link href="{{asset("assets/js/bootstrap-fileinput/css/fileinput.min.css")}}" rel="stylesheet" type="text/css"/>
@endsection

@section("scriptsPlugins")
<script src="{{asset("assets/js/bootstrap-fileinput/js/fileinput.min.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/js/bootstrap-fileinput/js/locales/es.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/js/bootstrap-fileinput/themes/fas/theme.min.js")}}" type="text/javascript"></script>
@endsection

@section("scripts")

<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/imprimirHtml.js")}}" type="text/javascript"></script>

    <script>
      var totControlConsumo;

      $(function(){
        let row_number = {{ count(old('capelladas', $combinacion->capearts->count() ? $combinacion->capearts : [''])) }};
        let row_number_avio = {{ count(old('avios', $combinacion->avioarts->count() ? $combinacion->avioarts : [''])) }};
        let totConsumo = $("#totControlConsumo").val();
		totControlConsumo = totConsumo.split(',');

		$('#agrega_renglon_capellada').on('click', agregaRenglonCapellada);
		$('.eliminarCapeart').on('click', borraRenglonCapeart);
		$('#marcarTodos').on('click', activaTipoCalculo);

        // Si no tiene items agrega el primero
        if(!$('.item-capellada').length)
            agregaRenglonCapellada();

        $("#add_row_avio").click(function(e){
          e.preventDefault();
          let new_row_number = row_number_avio - 1;
          $('#avio' + row_number_avio).html($('#avio' + new_row_number).html()).find('td:first-child');
          $('#avios_table').append('<tr id="avio' + (row_number_avio + 1) + '"></tr>');
          row_number_avio++;
        });
        $("#delete_row_avio").click(function(e){
          e.preventDefault();
          if(row_number_avio > 1){
            $("#avio" + (row_number_avio - 1)).html('');
            row_number_avio--;
          }
        });
		$( "#form-general" ).submit(function( event ) {
			var allElements = document.querySelectorAll('.tipoCalculo');

			// Solo controla para combinacion distinta a 1 
			if ($("#codigo").val() != '1')
			{
				if (!controlaConsumos())
					return false;
			}

			var mResult = [];
			allElements.forEach((v) => {
  				mResult.push(v.checked);
			});

			$('input[name=capeartTipoCalculo]').val(mResult);
		});
      });

      function controlaConsumos()
      {
        	var totConsumo1 = 0;
        	var totConsumo2 = 0;
        	var totConsumo3 = 0;
        	var totConsumo4 = 0;

        	$(".consumo1").each(function() {
				var tipoMaterial = $(this).parents("tr").find('.tipomaterial').val();

            	if (parseFloat($(this).val()) >= 0 && parseFloat($(this).val()) <= 999999 && tipoMaterial == 'C')
                	totConsumo1 += parseFloat($(this).val());
        	});
        	$(".consumo2").each(function() {
				var tipoMaterial = $(this).parents("tr").find('.tipomaterial').val();

            	if (parseFloat($(this).val()) >= 0 && parseFloat($(this).val()) <= 999999 && tipoMaterial == 'C')
                	totConsumo2 += parseFloat($(this).val());
        	});
        	$(".consumo3").each(function() {
				var tipoMaterial = $(this).parents("tr").find('.tipomaterial').val();

            	if (parseFloat($(this).val()) >= 0 && parseFloat($(this).val()) <= 999999 && tipoMaterial == 'C')
                	totConsumo3 += parseFloat($(this).val());
        	});
        	$(".consumo4").each(function() {
				var tipoMaterial = $(this).parents("tr").find('.tipomaterial').val();

            	if (parseFloat($(this).val()) >= 0 && parseFloat($(this).val()) <= 999999 && tipoMaterial == 'C')
                	totConsumo4 += parseFloat($(this).val());
        	});
			var flError = false;
			if (Math.abs(totConsumo1-totControlConsumo[0]) >= 0.001 && totControlConsumo[0] != 0)
			{
				alert('No coincide total columna 1 '+totConsumo1+' con la columna de la combinacion principal. Debe ser '+totControlConsumo[0]);
				flError = true;
			}
			if (Math.abs(totConsumo2-totControlConsumo[1]) >= 0.001 && totControlConsumo[1] != 0)
			{
				alert('No coincide total columna 2 '+totConsumo2+' con la columna de la combinacion principal. Debe ser '+totControlConsumo[1]);
				flError = true;
			}
			if (Math.abs(totConsumo3-totControlConsumo[2]) >= 0.001 && totControlConsumo[2] != 0)
			{
				alert('No coincide total columna 3 '+totConsumo3+' con la columna de la combinacion principal. Debe ser '+totControlConsumo[2]);
				flError = true;
			}
			if (Math.abs(totConsumo4-totControlConsumo[3]) >= 0.001 && totControlConsumo[3] != 0)
			{
				alert('No coincide total columna 4 '+totConsumo4+' con la columna de la combinacion principal. Debe ser '+totControlConsumo[3]);
				flError = true;
			}
			if (flError)
				return(false);

			return(true);
      }

      $('#foto').fileinput({
        language: 'es',
        allowedFileExtensions: ['jpg', 'jpeg', 'png'],
        maxFileSize: 1500,
        showUpload: false,
        showClose: false,
        initialPreviewAsData: true,
        dropZoneEnabled: false,
        theme: "fa",
      });

    function activa_eventos(flInicio)
    {
        // Si esta agregando items desactiva los eventos
        if (!flInicio)
        {
			$('.eliminarCapeart').off('click');
        }
		$('.eliminarCapeart').on('click', borraRenglonCapeart);
	  }

      function agregaRenglonCapellada(){
        	if (event != undefined)
            	event.preventDefault();
        	var renglon = $('#template-renglon-capellada').html();

		  	$("#capelladas_table").append(renglon);
	
        	activa_eventos(false);
      }

      function borraRenglonCapeart() {
        	event.preventDefault();
        	if (confirm("Desea borrar renglon?"))
        	{
            	$(this).parents('tr').remove();
        	}
    	}

      function activaTipoCalculo() {
	  		$("input:checkbox").prop('checked',$(this).prop("checked"));
	  	}

    </script>

@endsection

@section('contenido')
<div class="container-fluid">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div id="enc" class="card-header">
                <h3 class="card-title">Editar Combinaci&oacute;n - Datos T&eacute;cnica - Combinacion: {{ $combinacion->codigo }} - {{ $combinacion->nombre }} - Estado: {{ $combinacion->estado }} </h3>
                <div class="card-tools">
                    <a href="{{  URL::previous() }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    <a href="#" class="btn btn-outline-info btn-sm" onclick="imprimirHtml('printableArea', 'enc')">
                        <i class="fa fa-fw fa-print"></i> Imprimir ficha t&eacute;cnica
                    </a>
                </div>
            </div>
            <br>
            <form action="{{route('combinacion.tecnica.update', ['id' => $combinacion->id])}}" enctype="multipart/form-data" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("put")
                <input type="hidden" name="id" class="form-control" value="{{ $id }}" />
                <input type="hidden" name="combinacion_id" class="form-control" value="{{ $combinacion->id }}" />
                <input type="hidden" id="codigo" name="codigo" class="form-control" value="{{ $combinacion->codigo }}" />
                <input type="hidden" name="sku" class="form-control" value="{{ $combinacion->articulos->sku }}" />
                <input type="hidden" id='xxx' name="capeartTipoCalculo" class="form-control" value="" />
                <input type="hidden" id='totControlConsumo' name="totControlConsumo" class="form-control" value="{{ $totControlConsumo }}" />
                @include('stock.combinacion.tecnica.partials.form', ['edit' => true])
            </form>
        </div>
    </div>
</div>
@endsection
