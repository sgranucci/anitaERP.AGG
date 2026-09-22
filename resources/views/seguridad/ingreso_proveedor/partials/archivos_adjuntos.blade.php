@php
    $archivosList = isset($data) && $data && ($data->archivos ?? null)
        ? $data->archivos
        : collect();
    $ocultarInputsConservar = $ocultarInputsConservar ?? false;
@endphp

@if ($archivosList->count())
    <div class="ingreso-archivos-preview row">
        @foreach ($archivosList as $arch)
            @php
                $safeName = $arch->nombre_original;
                $ext = strtolower(pathinfo($arch->nombre_archivo, PATHINFO_EXTENSION));
                $hashPublico = $hashPublico ?? null;
                if ($hashPublico && isset($data) && $data && $data->id) {
                    $urlDescarga = \App\Support\Seguridad\IngresoProveedorEnlacePublicoSupport::urlArchivo(
                        (int) $data->id,
                        (int) $arch->id,
                        (string) $hashPublico,
                        false
                    );
                    $urlInline = \App\Support\Seguridad\IngresoProveedorEnlacePublicoSupport::urlArchivo(
                        (int) $data->id,
                        (int) $arch->id,
                        (string) $hashPublico,
                        true
                    );
                } else {
                    $urlInline = $arch->urlPublica();
                    $urlDescarga = $urlInline;
                }
                $esImagen = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
                $esPdf = $ext === 'pdf';
                $etiquetaTipo = \App\Support\Seguridad\IngresoProveedorArchivoTipos::etiqueta($arch->tipo ?? null);
                $pideVencimiento = \App\Support\Seguridad\IngresoProveedorArchivoTipos::pideVencimiento($arch->tipo ?? null);
                $fechaVence = $arch->vencimiento ?? null;
                $vencido = $fechaVence && \Illuminate\Support\Carbon::parse($fechaVence)->endOfDay()->isPast();
                $pathAdjunto = \Illuminate\Support\Facades\Storage::disk(\App\Models\Seguridad\IngresoProveedorArchivo::DISCO)
                    ->path($arch->rutaRelativa());
                $urlInline = \App\Support\Archivos\ArchivoAdjuntoCacheSupport::conVersion($urlInline, $pathAdjunto);
            @endphp
            <div class="col-md-6 col-lg-4 mb-3 ingreso-archivo-item">
                <div class="card card-outline card-secondary h-100 mb-0">
                    <div class="card-body p-2 d-flex flex-column">
                        @if ($etiquetaTipo !== '')
                            <div class="font-weight-bold mb-1">{{ $etiquetaTipo }}</div>
                        @endif
                        <div class="small text-truncate mb-2" title="{{ $safeName }}">
                            <i class="fa fa-paperclip text-muted mr-1"></i>{{ $safeName }}
                        </div>
                        @if ($pideVencimiento)
                            @if (! $ocultarInputsConservar)
                                <label class="small mb-0">Vencimiento</label>
                                <input type="date" name="archivo_vencimiento_id[{{ $arch->id }}]"
                                       class="form-control form-control-sm mb-2"
                                       value="{{ old('archivo_vencimiento_id.'.$arch->id, $fechaVence ? \Illuminate\Support\Carbon::parse($fechaVence)->format('Y-m-d') : '') }}">
                            @elseif ($fechaVence)
                                <div class="small mb-2 {{ $vencido ? 'text-danger' : 'text-muted' }}">
                                    Vence {{ \Illuminate\Support\Carbon::parse($fechaVence)->format('d/m/Y') }}
                                </div>
                            @endif
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
                            <a href="{{ $urlInline }}" class="btn btn-sm btn-outline-secondary ml-1" target="_blank" rel="noopener noreferrer">
                                <i class="fa fa-external-link-alt"></i> Abrir
                            </a>
                        </div>
                        @if (! $ocultarInputsConservar)
                            <input type="hidden" name="nombresanteriores[]" value="{{ $arch->id }}">
                            <button type="button" class="btn btn-sm btn-outline-danger mt-2 ingreso-quitar-archivo" title="Quitar de la lista; se elimina al guardar">
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
