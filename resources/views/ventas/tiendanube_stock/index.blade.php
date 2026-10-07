@extends("theme.$theme.layout")

@section('titulo', 'Stock y precios Tiendanube')

@section('contenido')
<div class="row">
    <div class="col-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-cloud-upload"></i> Stock y precios Tiendanube</h3>
                <div class="card-tools">
                    <a href="{{ route('editar_configuracion_tiendanube') }}" class="btn btn-sm btn-light">
                        <i class="fa fa-cog"></i> Configuración
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="border rounded p-3 mb-4 bg-light">
                    <h5 class="mb-2">Armar o enviar</h5>
                    <p class="mb-3">
                        El stock sale de los depósitos configurados y los precios de las dos listas.
                        A la hora configurada se envía sola, de lunes a viernes. Los sábados, domingos y feriados no se envía sola.
                        Estos botones son para hacerlo a mano.
                    </p>
                    <div class="d-flex flex-wrap align-items-center" style="gap:.75rem 1rem;">
                        @if ($enCurso)
                            <span class="btn btn-warning disabled"><i class="fa fa-spinner"></i> Hay una corrida en curso</span>
                        @else
                            <form action="{{ route('previsualizar_tiendanube_stock') }}" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="btn btn-info">
                                    <i class="fa fa-eye"></i> Previsualizar
                                </button>
                            </form>
                            <form action="{{ route('subir_tiendanube_stock') }}" method="POST" class="m-0"
                                onsubmit="return confirm('Se van a subir stock y precios a las tiendas que tienen la subida activa. ¿Continuar?');">
                                @csrf
                                <button type="submit" class="btn btn-success">
                                    <i class="fa fa-upload"></i> Subir ahora
                                </button>
                            </form>
                        @endif
                    </div>
                    <p class="text-muted mb-0 mt-2">
                        <strong>Previsualizar</strong> genera una fila nueva en el historial para revisar stock y precios. No toca Tiendanube.
                        <strong>Subir ahora</strong> envía esos datos a la tienda.
                    </p>
                </div>

                <h5 class="mb-2">Historial</h5>
                <p class="text-muted">Cada fila es una previsualización o un envío. <strong>Ver</strong> abre el detalle. <strong>Borrar</strong> la saca de esta lista.</p>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Inicio</th>
                                <th>Fin</th>
                                <th>Tienda</th>
                                <th>Origen</th>
                                <th>Hora</th>
                                <th>Marketplace</th>
                                <th>Resultado</th>
                                <th class="text-right">OK</th>
                                <th class="text-right">Error</th>
                                <th class="text-right">Omitidas</th>
                                <th>Usuario</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($subidas as $subida)
                                <tr>
                                    <td>{{ optional($subida->inicio_at)->format('d/m/Y H:i') }}</td>
                                    <td>{{ optional($subida->fin_at)->format('d/m/Y H:i') }}</td>
                                    <td>{{ $nombresTienda[$subida->store_id] ?? $subida->store_id }}</td>
                                    <td>{{ $subida->origen === 'manual' ? 'Manual' : ($subida->origen === 'simulacion' ? 'Simulación' : 'Diaria') }}</td>
                                    <td>{{ $subida->hora_programada }}</td>
                                    <td>{{ $subida->marketplace_codigo }}</td>
                                    <td>
                                        @if ($subida->estado === 'ok')
                                            <span class="badge badge-success">OK</span>
                                        @elseif ($subida->estado === 'parcial')
                                            <span class="badge badge-warning">Parcial</span>
                                        @elseif ($subida->estado === 'en_proceso')
                                            <span class="badge badge-info">En proceso</span>
                                        @else
                                            <span class="badge badge-danger">Error</span>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ $subida->variantes_ok }}</td>
                                    <td class="text-right">{{ $subida->variantes_error }}</td>
                                    <td class="text-right">{{ $subida->variantes_omitidas }}</td>
                                    <td>{{ $subida->usuario->nombre ?? '' }}</td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('ver_tiendanube_stock_subida', $subida->id) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                                        @if ($subida->estado !== 'en_proceso')
                                            <form action="{{ route('eliminar_tiendanube_stock_subida', $subida->id) }}" method="POST" class="d-inline"
                                                onsubmit="return confirm('Se borra esta corrida del historial. No cambia la tienda.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Borrar</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                                @if ($subida->mensaje)
                                    <tr>
                                        <td colspan="12" class="text-muted small">{{ $subida->mensaje }}</td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center text-muted">Todavía no hay subidas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">
                        @if ($subidas->total() > 0)
                            {{ $subidas->firstItem() }}–{{ $subidas->lastItem() }} de {{ $subidas->total() }}
                        @endif
                    </span>
                    {{ $subidas->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
