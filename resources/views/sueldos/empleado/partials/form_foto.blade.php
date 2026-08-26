@php
    $fotoUrl = null;
    if (isset($data) && $data->foto) {
        $fotoUrl = \App\Support\Archivos\ArchivoAdjuntoCacheSupport::urlStoragePublico('archivos/empleados/'.$data->id.'/'.$data->foto);
    }
@endphp
<div class="form-group row">
    <label class="col-lg-3 control-label">Foto</label>
    <div class="col-lg-5">
        @if ($fotoUrl)
            <div class="mb-3">
                <img src="{{ $fotoUrl }}" alt="Foto" class="img-thumbnail" style="max-height: 220px;">
            </div>
        @endif
        <input type="file" name="foto_archivo" id="foto_archivo" class="form-control-file" accept="image/*">
        <small class="text-muted">JPG/PNG. Se reemplaza al guardar.</small>
    </div>
</div>
