@php
    $archivosList = isset($data) && $data && $data->requisicion_sala_archivos
        ? $data->requisicion_sala_archivos
        : collect();
    $ocultarInputsConservar = $ocultarInputsConservar ?? false;
@endphp

@if ($archivosList->count())
    <div class="row requisicion-sala-archivos-preview">
        @foreach ($archivosList as $arch)
            @php
                $safeName = $arch->nombrearchivo;
                $urlDescarga = route('requisicion_sala_archivo', ['id' => $data->id, 'archivo' => $arch->id]);
                $urlInline = $urlDescarga.'?inline=1';
            @endphp
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card card-outline card-secondary h-100 mb-0">
                    <div class="card-body p-2">
                        <div class="small text-truncate mb-2" title="{{ $safeName }}">{{ $safeName }}</div>
                        <a href="{{ $urlDescarga }}" class="btn btn-sm btn-outline-primary"><i class="fa fa-download"></i> Descargar</a>
                        @if (! $ocultarInputsConservar)
                            <input type="hidden" name="nombresanteriores[]" value="{{ $arch->nombrearchivo }}">
                            <button type="button" class="btn btn-sm btn-outline-danger mt-2 eliminar-archivo-requisicion-sala">Quitar</button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <p class="text-muted mb-0">No hay archivos adjuntos.</p>
@endif
