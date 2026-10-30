@extends("theme.$theme.layout")

@section('titulo')
    Parámetros Facturación Local
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0"><i class="fa fa-cogs"></i> Parámetros Facturación Local</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('facturacion_local_shopping_caballito') }}" class="btn btn-outline-light btn-sm mr-1">
                        <i class="fa fa-file-text-o"></i> Shopping Caballito
                    </a>
                    @if (can('reportes-facturacion-local', false))
                        <a href="{{ route('facturacion_local_reporte_costos') }}" class="btn btn-outline-light btn-sm mr-1">
                            <i class="fa fa-calculator"></i> Costos del local
                        </a>
                        <a href="{{ route('facturacion_local_reportes') }}" class="btn btn-outline-light btn-sm">
                            <i class="fa fa-chart-bar"></i> Reportes Local
                        </a>
                    @endif
                </div>
            </div>
            <form method="POST" action="{{ route('actualizar_facturacion_local_parametros') }}" class="form-horizontal" autocomplete="off">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="alert alert-info py-2 mb-3">
                        Valores usados por <strong>Reportes Local</strong>, por el <strong>reporte de costos del local</strong>
                        y por el archivo de ventas del shopping de Caballito.
                        Se guardan en base de datos; el .env solo aporta valores iniciales si la tabla está vacía.
                        Fórmula actual: <code>{{ $formula ?? '' }}</code>
                    </div>

                    @php $tituloShopping = false; @endphp
                    @foreach ($filas as $fila)
                        @if (! $tituloShopping && str_starts_with($fila['clave'], 'shopping_'))
                            @php $tituloShopping = true; @endphp
                            <hr>
                            <h5 class="mb-1">Shopping Caballito</h5>
                            <p class="text-muted small">
                                Un archivo por día, solo de ese local, a la hora indicada.
                                El log de envíos está en
                                <a href="{{ route('facturacion_local_shopping_caballito') }}" class="text-primary">Shopping Caballito</a>.
                            </p>
                        @endif
                        @php $idCampo = 'param_'.$fila['clave']; @endphp
                        <div class="form-group row">
                            <label for="{{ $idCampo }}" class="col-lg-3 control-label text-right pr-2">{{ $fila['etiqueta'] }}</label>
                            <div class="col-lg-6">
                                <input type="text"
                                    class="form-control"
                                    name="valores[{{ $fila['clave'] }}]"
                                    id="{{ $idCampo }}"
                                    value="{{ old('valores.'.$fila['clave'], $fila['valor']) }}"
                                    maxlength="500">
                                <small class="form-text text-muted">{{ $fila['ayuda'] }}</small>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
