@extends("theme.$theme.layout")

@section('titulo')
    Tipos de valor
@endsection

@section('contenido')
@include('includes.mensaje')
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Tipos de valor</h3>
                <div class="card-tools">
                    <a href="{{ route('tesoreria_crown_valor', $sala ? ['sala_id' => $sala->id] : []) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    @if (can('crear-tesoreria-crown-valor', false))
                        <a href="{{ route('crear_tesoreria_crown_tipo_valor', $sala ? ['sala_id' => $sala->id] : []) }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-plus-circle"></i> Nuevo tipo
                        </a>
                    @endif
                </div>
            </div>
            @include('caja.tesoreria_crown.partials.sala_chips', ['ruta' => 'tesoreria_crown_tipo_valor'])
            <div class="card-body border-bottom py-2">
                <p class="text-muted mb-0">El tipo agrupa el valor. La ficha o el billete concreto está en Valores: la serie y la denominación. Fichas de color no llevan stock. Fichas de valor, pesos, dólares y euros sí. Dólares y euros toman la cotización de la jornada; el resto queda en 1.</p>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover table-bordered mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Controla stock</th>
                            <th>Moneda de referencia</th>
                            <th>Cotización fija</th>
                            <th>Activo</th>
                            <th class="text-center" style="width:90px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tipos as $tipo)
                            <tr>
                                <td>{{ $tipo->codigo }}</td>
                                <td>{{ $tipo->nombre }}</td>
                                <td>{{ $tipo->controla_stock ? 'Sí' : 'No' }}</td>
                                <td>{{ $tipo->es_moneda_referencia ? 'Sí' : 'No' }}</td>
                                <td>{{ $tipo->cotizacion_fija ? 'Sí' : 'No' }}</td>
                                <td>{{ $tipo->activo ? 'Sí' : 'No' }}</td>
                                <td class="text-center text-nowrap">
                                    @if (can('editar-tesoreria-crown-valor', false))
                                        <a href="{{ route('editar_tesoreria_crown_tipo_valor', $tipo->id) }}" class="btn-accion-tabla" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('eliminar-tesoreria-crown-valor', false))
                                        <form action="{{ route('eliminar_tesoreria_crown_tipo_valor', $tipo->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este tipo de valor?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-accion-tabla" title="Eliminar">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">No hay tipos en esta sala.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
