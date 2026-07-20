@php
    $archivosList = isset($data) && $data ? ($data->archivos ?? collect()) : collect();
@endphp

<div class="form-group row">
    <label class="col-lg-3 control-label">Archivos</label>
    <div class="col-lg-7">
        <input type="file" name="nombrearchivos[]" class="form-control-file" multiple>
        <small class="text-muted">PDF, imágenes u otros (máx. 10 MB c/u). Se guardan al actualizar.</small>
    </div>
</div>

@if ($archivosList->count())
    <div class="empleado-archivos-preview row">
        @foreach ($archivosList as $arch)
            @php
                $safeName = $arch->nombrearchivo;
                $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
                $urlInline = asset('storage/archivos/empleados/'.$data->id.'/'.$safeName);
                $esImagen = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
                $esPdf = $ext === 'pdf';
            @endphp
            <div class="col-md-6 col-lg-4 mb-3 empleado-archivo-item">
                <div class="card card-outline card-secondary h-100 mb-0">
                    <div class="card-body p-2 d-flex flex-column">
                        <div class="small text-truncate mb-2" title="{{ $safeName }}">{{ $safeName }}</div>
                        @if ($esImagen)
                            <div class="text-center bg-light rounded mb-2" style="min-height: 120px;">
                                <a href="{{ $urlInline }}" target="_blank" rel="noopener">
                                    <img src="{{ $urlInline }}" alt="" class="img-fluid rounded" style="max-height: 180px; object-fit: contain;">
                                </a>
                            </div>
                        @elseif ($esPdf)
                            <iframe src="{{ $urlInline }}" class="w-100 rounded border-0 mb-2" style="height: 180px;" title="PDF"></iframe>
                        @else
                            <div class="text-center text-muted py-4 mb-2 bg-light rounded">
                                <i class="fa fa-file-o fa-3x"></i>
                            </div>
                        @endif
                        <div class="mt-auto">
                            <a href="{{ $urlInline }}" class="btn btn-sm btn-outline-primary" download="{{ $safeName }}">
                                <i class="fa fa-download"></i> Descargar
                            </a>
                            <input type="hidden" name="nombresanteriores[]" value="{{ $arch->nombrearchivo }}">
                            <button type="button" class="btn btn-sm btn-outline-danger mt-2 eliminar-archivo-empleado">
                                <i class="fa fa-times"></i> Quitar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <p class="text-muted">No hay archivos adjuntos.</p>
@endif
