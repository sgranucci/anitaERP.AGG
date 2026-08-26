@php
    $archivosList = isset($data) && $data && ($data->archivos ?? null)
        ? $data->archivos
        : collect();
    $ocultarInputsConservar = $ocultarInputsConservar ?? false;
@endphp

@if ($archivosList->count())
    <div class="solicitudpago-archivos-preview row">
        @foreach ($archivosList as $arch)
            @php
                $safeName = $arch->nombre_original ?: basename((string) $arch->archivo);
                $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
                $urlInline = route('descargar_archivo_solicitudpago', ['id' => $data->id, 'archivoId' => $arch->id, 'inline' => 1]);
                $urlDescarga = route('descargar_archivo_solicitudpago', [$data->id, $arch->id]);
                $esImagen = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
                $esPdf = $ext === 'pdf';
                $existeArchivo = \App\Support\Solicitudpago\SolicitudpagoArchivoStorageSupport::existe($arch, (int) ($data->codigo ?? 0));
                $urlInline = \App\Support\Archivos\ArchivoAdjuntoCacheSupport::conVersion(
                    $urlInline,
                    \App\Support\Solicitudpago\SolicitudpagoArchivoStorageSupport::rutaAbsoluta($arch, (int) ($data->codigo ?? 0))
                );
            @endphp
            <div class="col-md-6 col-lg-4 mb-3 solicitudpago-archivo-item">
                <div class="card card-outline card-secondary h-100 mb-0">
                    <div class="card-body p-2 d-flex flex-column">
                        <div class="small text-truncate mb-2" title="{{ $safeName }}">{{ $safeName }}</div>
                        @if (! $existeArchivo)
                            <div class="text-center text-warning py-4 mb-2 bg-light rounded">
                                <i class="fa fa-exclamation-triangle fa-2x"></i>
                                <div class="small mt-2">Archivo no encontrado en el repositorio</div>
                            </div>
                        @elseif ($esImagen)
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
                            @if ($existeArchivo)
                                <a href="{{ $urlDescarga }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fa fa-download"></i> Descargar
                                </a>
                                <a href="{{ $urlInline }}" class="btn btn-sm btn-outline-secondary ml-1" target="_blank" rel="noopener noreferrer" title="Abrir en nueva pestaña">
                                    <i class="fa fa-external-link-alt"></i> Abrir
                                </a>
                            @endif
                        </div>
                        @if (! $ocultarInputsConservar)
                            <input type="hidden" name="archivo_ids_existentes[]" value="{{ $arch->id }}" class="archivo-existente-id">
                            <button type="button" class="btn btn-sm btn-outline-danger mt-2 eliminar-archivo-solicitudpago" title="Quitar de la lista; se elimina al guardar">
                                <i class="fa fa-times"></i> Quitar
                            </button>
                            <div class="small text-muted mt-1">
                                {{ optional($arch->usuarios)->nombre ?? '—' }}
                                · {{ optional($arch->fecha)->format('d/m/Y') }} {{ $arch->hora }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <p class="text-muted mb-0">No hay archivos adjuntos.</p>
@endif
