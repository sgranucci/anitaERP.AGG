@php
    $archivosList = isset($data) && $data && ($data->asiento_archivos ?? null)
        ? $data->asiento_archivos
        : collect();
    $ocultarInputsConservar = $ocultarInputsConservar ?? false;
    $asientoId = (int) ($data->id ?? 0);
@endphp

@if ($archivosList->count())
    <div class="asiento-archivos-preview row">
        @foreach ($archivosList as $arch)
            @php
                $safeName = $arch->nombrearchivo;
                $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
                $urlInline = asset('storage/archivos/asientos/'.$asientoId.'/'.$safeName);
                $esImagen = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
                $esPdf = $ext === 'pdf';
            @endphp
            <div class="col-md-6 col-lg-4 mb-3 asiento-archivo-item">
                <div class="card card-outline card-secondary h-100 mb-0">
                    <div class="card-body p-2 d-flex flex-column">
                        <div class="small text-truncate mb-2" title="{{ $safeName }}">
                            <i class="fa fa-paperclip text-muted mr-1"></i>{{ $safeName }}
                        </div>
                        @if ($esImagen)
                            <div class="text-center bg-light rounded mb-2" style="min-height: 120px;">
                                <a href="{{ $urlInline }}" target="_blank" rel="noopener noreferrer" title="Abrir imagen">
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
                                <div class="small mt-2">Vista previa no disponible</div>
                            </div>
                        @endif
                        <div class="mt-auto pt-1">
                            <a href="{{ $urlInline }}" class="btn btn-sm btn-outline-primary" download="{{ $safeName }}">
                                <i class="fa fa-download"></i> Descargar
                            </a>
                            <a href="{{ $urlInline }}" class="btn btn-sm btn-outline-secondary ml-1" target="_blank" rel="noopener noreferrer" title="Abrir en nueva pestaña">
                                <i class="fa fa-external-link-alt"></i> Abrir
                            </a>
                        </div>
                        @if (! $ocultarInputsConservar)
                            <input type="hidden" name="nombresanteriores[]" value="{{ $arch->nombrearchivo }}">
                            <button type="button" class="btn btn-sm btn-outline-danger mt-2 eliminar-archivo-asiento" title="Quitar de la lista; se elimina al guardar">
                                <i class="fa fa-times"></i> Quitar
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="text-center text-muted py-4 bg-light rounded mb-0">
        <i class="fa fa-folder-open fa-2x mb-2 d-block"></i>
        No hay archivos adjuntos.
    </div>
@endif
