@extends("theme.$theme.layout")
@section('titulo')
    Nuevo reporte definible
@endsection

@section('scripts')
<script src="{{asset('assets/pages/scripts/admin/crear.js')}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Nuevo informe contable definible</h3>
                <div class="card-tools">
                    <a href="{{ route('reporte_definible') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <form method="post" action="{{ route('guardar_reporte_definible') }}" class="form-horizontal" autocomplete="off">
                @csrf
                <div class="card-body">
                    @include('includes.form-error')
                    @include('includes.mensaje')
                    <p class="text-muted">
                        Primero la cabecera (como en Anita <code>infomae</code>).
                        Después se abre el diseñador de estructura: rubros y cuentas.
                    </p>
                    @include('contable.reporte_definible.partials.form_cabecera', [
                        'data' => null,
                        'tiposReporte' => $tiposReporte,
                    ])
                </div>
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Crear y diseñar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
