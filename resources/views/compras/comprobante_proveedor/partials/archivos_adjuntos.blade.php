@php
    $archivosAdjuntos = collect($data->comprobante_proveedor_archivos ?? [])
        ->filter(fn ($a) => in_array($a->tipo, \App\Support\Compras\ComprobanteProveedorArchivoTipos::subibles(), true));
    $bloqueado = ! empty($bloqueado_edicion);
@endphp

@if ($archivosAdjuntos->isNotEmpty())
    <div class="row cp-archivos-preview">
        @foreach ($archivosAdjuntos as $arch)
            @php
                $safeName = $arch->nombrearchivo;
                $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
                $urlDescarga = route('comprobante_proveedor_archivo', ['id' => $data->id, 'archivo' => $arch->id]);
                $urlInline = $urlDescarga.'?inline=1';
                $esImagen = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
                $esPdf = $ext === 'pdf';
            @endphp
            <div class="col-md-6 col-lg-4 mb-3 cp-archivo-item">
                <div class="card card-outline card-secondary h-100 mb-0">
                    <div class="card-body p-2 d-flex flex-column">
                        <div class="small text-truncate mb-1" title="{{ $safeName }}">
                            <span class="badge badge-light">{{ \App\Support\Compras\ComprobanteProveedorArchivoTipos::etiqueta($arch->tipo) }}</span> {{ $safeName }}
                        </div>
                        @if ($esImagen)
                            <div class="text-center bg-light rounded mb-2" style="min-height: 120px;">
                                <a href="{{ $urlInline }}" target="_blank" rel="noopener noreferrer">
                                    <img src="{{ $urlInline }}" alt="" class="img-fluid rounded" style="max-height: 180px; object-fit: contain;">
                                </a>
                            </div>
                        @elseif ($esPdf)
                            <div class="flex-grow-1 mb-2" style="min-height: 200px;">
                                <iframe src="{{ $urlInline }}" class="w-100 rounded border-0 bg-secondary" style="height: 220px;" title="Vista previa PDF"></iframe>
                            </div>
                        @else
                            <div class="text-center text-muted py-4 mb-2 bg-light rounded">
                                <i class="fa fa-file-o fa-3x"></i>
                            </div>
                        @endif
                        <div class="mt-auto pt-1">
                            <a href="{{ $urlDescarga }}" class="btn btn-sm btn-outline-primary" download="{{ $safeName }}">
                                <i class="fa fa-download"></i> Descargar
                            </a>
                            <a href="{{ $urlInline }}" class="btn btn-sm btn-outline-secondary ml-1" target="_blank" rel="noopener noreferrer">
                                <i class="fa fa-external-link-alt"></i> Abrir
                            </a>
                        </div>
                        @if (! $bloqueado)
                            <input type="hidden" name="nombresanteriores[]" value="{{ $arch->nombrearchivo }}">
                            <input type="hidden" name="nombresanteriores_tipo[]" value="{{ $arch->tipo }}">
                            <button type="button" class="btn btn-sm btn-outline-danger mt-2 cp-eliminar-archivo" title="Quitar de la lista; se elimina al guardar">
                                <i class="fa fa-times"></i> Quitar
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <p class="text-muted mb-3">No hay adjuntos cargados en ERP.</p>
@endif
