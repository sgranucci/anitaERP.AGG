@extends("theme.$theme.layout")
@section('titulo')
    Movimientos de Stock
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/movimientostock/crear.js")}}" type="text/javascript"></script>

<script>
	function sub()
	{
        $('#formgeneral').submit();
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
                <h3 class="card-title">Editar Movimiento de Stock</h3>
				&nbsp;- ID: {{ $movimientostock->id }} - Movimiento: {{$movimientostock->codigo}}
                <div class="card-tools">
                    <a href="{{route('movimientostock')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('actualizar_movimientostock', ['id' => $movimientostock->id])}}" id="formgeneral" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("put")
                <div class="card-body">
        			<input type="hidden" id="codigo" name="codigo" value="{{$movimientostock->codigo}}" >
        			<input type="hidden" id="movimientostockid" name="movimientostockid" value="{{$movimientostock->id}}" >
                    @php $datos = ["funcion" => "editar"]; @endphp
                    @include('stock.movimientostock.form', $datos)
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-6">
							<button type="submit" onclick="sub()" class="btn btn-success">Actualizar</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
