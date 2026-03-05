@extends("theme.$theme.layout")
@section('titulo')
    Cierre de pedidos
@endsection

@section('scripts')

@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Cierre de pedidos</h3>
                <div class="card-tools">
                    <a href="{{route('pedido')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
			    </div>
            </div>
            <form action="{{route('ejecuta_cierre_pedido')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("post")
                <div class="card-body">
                    @include('ventas.pedido.formcierre')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
							<input type="submit" name="extension" value="Procesa cierre de pedidos" class="btn-sm btn-info"></input>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
