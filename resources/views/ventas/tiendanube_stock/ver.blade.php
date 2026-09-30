@extends("theme.$theme.layout")

@section('titulo', 'Detalle de subida Tiendanube')

@section('scripts')
<script src="{{ asset('assets/pages/scripts/ventas/tiendanube_stock/export.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/tiendanube_stock/export.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'tn-stock-subida-overlay',
    'tituloId' => 'tn-stock-subida-titulo',
    'subtituloId' => 'tn-stock-subida-subtitulo',
    'titulo' => 'Exportando…',
    'subtitulo' => 'Generando el archivo. Pulse Esc para cerrar este aviso.',
])
<div class="row">
    <div class="col-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-list"></i>
                    {{ $tiendaNombre }}
                    · {{ optional($subida->inicio_at)->format('d/m/Y H:i') }}
                    · {{ $subida->origen === 'manual' ? 'Manual' : ($subida->origen === 'simulacion' ? 'Simulación' : 'Diaria') }}
                </h3>
                <div class="card-tools">
                    @if ($subida->estado !== 'en_proceso')
                        <form action="{{ route('eliminar_tiendanube_stock_subida', $subida->id) }}" method="POST" class="d-inline"
                            onsubmit="return confirm('Se borra esta corrida del historial. No cambia la tienda.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-light text-danger">Borrar</button>
                        </form>
                    @endif
                    <a href="{{ route('tiendanube_stock_subidas') }}" class="btn btn-sm btn-light">
                        <i class="fa fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <div class="card-body">
                @if ($subida->origen === 'simulacion')
                    <div class="alert alert-info">
                        Simulación sobre anitaERP: stock de los depósitos configurados y precios de las dos listas.
                        No consulta Tiendanube y no modifica la tienda.
                    </div>
                @endif
                <div class="mb-3">
                    <span class="mr-3"><strong>Estado:</strong> {{ $subida->estado }}</span>
                    <span class="mr-3"><strong>OK:</strong> {{ $subida->variantes_ok }}</span>
                    <span class="mr-3"><strong>Error:</strong> {{ $subida->variantes_error }}</span>
                    <span class="mr-3"><strong>Omitidas:</strong> {{ $subida->variantes_omitidas }}</span>
                    <span class="mr-3"><strong>Marketplace:</strong> {{ $subida->marketplace_codigo }}</span>
                    <span><strong>Usuario:</strong> {{ $subida->usuario->nombre ?? '—' }}</span>
                    @if ($subida->mensaje)
                        <div class="text-muted mt-1">{{ $subida->mensaje }}</div>
                    @endif
                </div>

                <div id="tn-stock-subida-export" class="mb-3">
                    @include('includes.exportar-tabla-id', [
                        'ruta' => 'listar_tiendanube_stock_subida',
                        'id' => $subida->id,
                    ])
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>SKU</th>
                                <th>Variante</th>
                                <th>Combinación</th>
                                <th>Talle</th>
                                <th class="text-right">Stock</th>
                                <th class="text-right">Precio</th>
                                <th class="text-right">Promocional</th>
                                <th>Estado</th>
                                <th>Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($lineas as $linea)
                                <tr>
                                    <td>{{ $linea->sku }}</td>
                                    <td>{{ $linea->variante_sku }}</td>
                                    <td>{{ $linea->combinacion_codigo }}</td>
                                    <td>{{ $linea->talle }}</td>
                                    <td class="text-right">{{ $linea->stock }}</td>
                                    <td class="text-right">{{ $linea->precio !== null ? number_format((float) $linea->precio, 2, ',', '.') : '' }}</td>
                                    <td class="text-right">{{ $linea->precio_promocional !== null ? number_format((float) $linea->precio_promocional, 2, ',', '.') : '' }}</td>
                                    <td>{{ \App\Models\Ventas\TiendanubeStockSubidaLinea::etiquetaEstado($linea->estado) }}</td>
                                    <td>{{ $linea->mensaje }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted">Sin líneas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">
                        @if ($lineas->total() > 0)
                            {{ $lineas->firstItem() }}–{{ $lineas->lastItem() }} de {{ $lineas->total() }}
                        @endif
                    </span>
                    {{ $lineas->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
