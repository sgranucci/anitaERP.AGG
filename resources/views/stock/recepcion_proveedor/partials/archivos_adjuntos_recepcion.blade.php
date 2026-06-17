@php
    use App\Models\Stock\Recepcion_Proveedor_Archivo;

    $archivosList = ($recepcion ?? null)
        ? $recepcion->recepcion_proveedor_archivos->sortByDesc('id')
        : collect();
    $ocultarInputsConservar = $ocultarInputsConservar ?? false;
@endphp

@if ($archivosList->isEmpty())
    <p class="text-muted mb-0">No hay archivos adjuntos.</p>
@else
    <div class="recepcion-archivos-preview row">
        @foreach ($archivosList as $arch)
            @php
                $safeName = $arch->nombre;
                $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
                $urlDescarga = route('recepcion_proveedor_archivo', ['id' => $recepcion->id, 'archivo' => $arch->id]);
                $urlInline = $urlDescarga.'?'.http_build_query(['inline' => '1']);
                $esImagen = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
                $esPdf = $ext === 'pdf';
                $esOcr = $arch->tipo_archivo === Recepcion_Proveedor_Archivo::TIPO_OCR;
            @endphp
            <div class="col-md-6 col-lg-4 mb-3 recepcion-archivo-item">
                <div class="card card-outline card-secondary h-100 mb-0">
                    <div class="card-body p-2 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="small text-truncate flex-grow-1 mr-1" title="{{ $safeName }}">{{ $safeName }}</div>
                            @if ($esOcr)
                                <span class="badge badge-info flex-shrink-0">OCR</span>
                            @else
                                <span class="badge badge-secondary flex-shrink-0">Adjunto</span>
                            @endif
                        </div>
                        @if ($esOcr && $arch->ocr_estado)
                            <div class="small text-muted mb-2">Estado OCR: {{ $arch->ocr_estado }}</div>
                        @endif
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
                            <a href="{{ $urlDescarga }}" class="btn btn-sm btn-outline-primary" download="{{ $safeName }}">
                                <i class="fa fa-download"></i> Descargar
                            </a>
                            <a href="{{ $urlInline }}" class="btn btn-sm btn-outline-secondary ml-1" target="_blank" rel="noopener noreferrer" title="Abrir en nueva pestaña">
                                <i class="fa fa-external-link-alt"></i> Abrir
                            </a>
                        </div>
                        @if (! $ocultarInputsConservar && ! $esOcr)
                            <input type="hidden" name="archivos_adjuntos_conservar[]" value="{{ $arch->id }}">
                            <button type="button" class="btn btn-sm btn-outline-danger mt-2 eliminar-archivo-recepcion" title="Quitar de la lista; se elimina al guardar">
                                <i class="fa fa-times"></i> Quitar
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
