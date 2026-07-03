@if (!empty($data->id))
    <input type="hidden" id="depmae_registro_id" value="{{ $data->id }}">
@endif

@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => $data->empresa_id ?? null,
])

<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">Código</label>
    <div class="col-lg-2">
        <input type="text" name="codigo" id="codigo" class="form-control"
            value="{{ old('codigo', $data->codigo ?? '') }}" required maxlength="10" autocomplete="off"
            pattern="[A-Za-z0-9._ -]+" title="Letras, n&uacute;meros, espacios, punto, gui&oacute;n o gui&oacute;n bajo (m&aacute;x. 10)."/>
        <small class="form-text text-muted">C&oacute;digo del dep&oacute;sito en el ERP (alfanum&eacute;rico, m&aacute;x. 10). No se replica a Anita desde este ABM.</small>
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Descripción</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control"
            value="{{ old('nombre', $data->nombre ?? '') }}" required maxlength="50"
            placeholder="Descripción"/>
        <small class="form-text text-muted">Descripción = depm_desc.</small>
    </div>
</div>
<div class="form-group row">
    <label for="tipodeposito" class="col-lg-3 col-form-label requerido">Tipo de depósito</label>
    <div class="col-lg-4">
        <select id="tipodeposito" name="tipodeposito" class="form-control" required>
            <option value="">-- Elija tipo de depósito --</option>
            @foreach($tipodeposito_enum as $tipodeposito)
                @if ($tipodeposito['nombre'] == old('tipodeposito', $data->tipodeposito ?? ''))
                    <option value="{{ $tipodeposito['nombre'] }}" selected>{{ $tipodeposito['nombre'] }}</option>
                @else
                    <option value="{{ $tipodeposito['nombre'] }}">{{ $tipodeposito['nombre'] }}</option>
                @endif
            @endforeach
        </select>
    </div>
</div>
