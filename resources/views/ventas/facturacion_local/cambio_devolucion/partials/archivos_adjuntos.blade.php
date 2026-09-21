@php
    $archivos = $data->archivos ?? collect();
    $ocultarInputsConservar = $ocultarInputsConservar ?? false;
@endphp
@if ($archivos->isEmpty())
    <div class="text-center text-muted py-3 bg-light rounded mb-0">No hay archivos adjuntos.</div>
@else
    <div class="row">
        @foreach ($archivos as $archivo)
            @php
                $url = route('descargar_archivo_cambio_devolucion_marketplace', [
                    'id' => $data->id,
                    'archivoId' => $archivo->id,
                ]);
                $ext = strtolower(pathinfo($archivo->nombrearchivo, PATHINFO_EXTENSION));
                $esImg = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
            @endphp
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card card-outline card-secondary h-100">
                    <div class="card-body p-2">
                        <div class="text-truncate font-weight-bold mb-2" title="{{ $archivo->nombrearchivo }}">
                            {{ $archivo->nombrearchivo }}
                        </div>
                        <div class="mb-2 text-center" style="min-height:80px">
                            @if ($esImg)
                                <img src="{{ $url }}" alt="" style="max-height:100px;max-width:100%">
                            @elseif ($ext === 'pdf')
                                <i class="fa fa-file-pdf-o fa-3x text-danger"></i>
                            @else
                                <i class="fa fa-file-o fa-3x text-muted"></i>
                            @endif
                        </div>
                        <div class="d-flex flex-wrap">
                            <a href="{{ $url }}" class="btn btn-sm btn-outline-primary mr-1 mb-1" download>Descargar</a>
                            <a href="{{ $url }}" class="btn btn-sm btn-outline-secondary mr-1 mb-1" target="_blank" rel="noopener noreferrer">Abrir</a>
                            @if (! $ocultarInputsConservar)
                                <input type="hidden" name="nombresanteriores[]" value="{{ $archivo->nombrearchivo }}" class="cdm-conservar-archivo">
                                <button type="button" class="btn btn-sm btn-outline-danger mb-1 cdm-quitar-archivo-existente">Quitar</button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
