@extends("theme.$theme.layout")
@section('titulo')
    POS Crown
@endsection
@section('contenido')
<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card card-outline card-warning">
            <div class="card-header">
                <h3 class="card-title">Sin configuración de terminal</h3>
            </div>
            <div class="card-body">
                <p>Esta PC (<code>{{ $identificador }}</code>) no tiene fila en
                    <strong>Configuración → Configuración por módulo → Ventas → Config. terminales gastronomía</strong>.</p>
                <p class="mb-0">
                    <a href="{{ route('crear_configuracion_puntoventa_gastronomia') }}" class="btn btn-primary btn-sm">
                        Crear configuración
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
