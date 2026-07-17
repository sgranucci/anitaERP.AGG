@if (isset($data))
    <input type="hidden" name="archivos_gestionados" value="1">
@endif
@if (isset($data) && $data->archivos && $data->archivos->count())
    <div class="table-responsive mb-3">
        <table class="table table-sm table-bordered">
            <thead class="thead-light">
                <tr>
                    <th>Nro</th>
                    <th>Archivo</th>
                    <th>Usuario</th>
                    <th>Fecha</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data->archivos as $arch)
                    <tr>
                        <td>{{ $arch->nro_linea }}</td>
                        <td>
                            <a href="{{ route('descargar_archivo_solicitudpago', [$data->id, $arch->id]) }}">
                                {{ $arch->nombre_original ?: basename($arch->archivo) }}
                            </a>
                            <input type="hidden" name="archivo_ids_existentes[]" value="{{ $arch->id }}" class="archivo-existente-id">
                        </td>
                        <td>{{ optional($arch->usuarios)->nombre ?? '—' }}</td>
                        <td>{{ optional($arch->fecha)->format('d/m/Y') }} {{ $arch->hora }}</td>
                        <td class="text-center">
                            <button type="button" class="btn-accion-tabla eliminar_sp_archivo tooltipsC" title="Quitar archivo">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="form-group">
    <label for="archivos_nuevos">Adjuntar archivos</label>
    <input type="file" name="archivos_nuevos[]" id="archivos_nuevos" class="form-control-file" multiple>
    <small class="form-text text-muted">M&aacute;ximo 10 MB por archivo. Al guardar se sincroniza el nombre a Anita si la escritura est&aacute; activa.</small>
</div>
