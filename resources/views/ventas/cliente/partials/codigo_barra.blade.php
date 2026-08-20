@php
    $codigoCliente = old('codigo', $data->codigo ?? '');
    $codigoEditable = config('app.empresa') === 'INTERFORMING';
@endphp
<div class="d-flex align-items-center ml-2 mr-2">
    <label for="codigo" class="mb-0 mr-2 small font-weight-bold text-white">C&oacute;digo</label>
    <input type="text"
           name="codigo"
           id="codigo"
           form="form-general"
           class="form-control form-control-sm"
           style="width: 7.5rem;"
           value="{{ $codigoCliente }}"
           placeholder="{{ $codigoEditable ? '' : 'Automático' }}"
           autocomplete="off"
           title="C&oacute;digo Anita del cliente"
           @if (! $codigoEditable)
               readonly
           @endif
           @if ($codigoEditable)
               required
           @endif
    >
</div>
