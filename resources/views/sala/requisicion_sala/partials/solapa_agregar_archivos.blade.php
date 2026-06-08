@php
    $archivosList = isset($data) && $data && $data->requisicion_sala_archivos
        ? $data->requisicion_sala_archivos
        : collect();
@endphp
<div class="form-group">
    <label>Archivos adjuntos</label>
    @if(!isset($visualizar) || !$visualizar)
    <input type="file" name="nombrearchivos[]" class="form-control-file" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx">
    @endif
    @include('sala.requisicion_sala.partials.archivos_adjuntos')
</div>
