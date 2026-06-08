@php
    $soloLectura = $soloLectura ?? false;
    $empresa_id = $empresa_id ?? (isset($recuento) ? $recuento->empresa_id : ($empresa_query->first()->id ?? null));
    $depositoId = old('deposito_id', $recuento->deposito_id ?? '');
    $depositoModel = null;
    if ((int) $depositoId > 0) {
        $depositoModel = isset($recuento) && (int) ($recuento->deposito_id ?? 0) === (int) $depositoId
            ? $recuento->deposito
            : \App\Models\Stock\Depmae::find((int) $depositoId);
    }
    $colLabel = 'col-lg-4 control-label text-right pr-2';
    $colInput = 'col-lg-7';
@endphp

<div class="row">
    <div class="col-sm-6">
        @if (isset($recuento))
            <input type="hidden" id="empresa_id" value="{{ (int) $recuento->empresa_id }}">
            <div class="form-group row">
                <label class="{{ $colLabel }}">Empresa</label>
                <div class="{{ $colInput }}">
                    <input type="text" class="form-control" readonly value="{{ optional($recuento->empresa)->nombre }}">
                </div>
            </div>
        @else
            @include('includes.form-empresa-asignada', [
                'empresa_query' => $empresa_query,
                'empresa_id' => $empresa_id,
                'col_label' => $colLabel,
                'col_input' => $colInput,
            ])
        @endif

        @include('stock.partials.campo_consulta_deposito', [
            'prefix' => 'recuento',
            'layout' => 'form_row',
            'label' => 'Depósito',
            'inputName' => 'deposito_id',
            'inputId' => 'recuento_deposito_id',
            'depositoId' => $depositoId,
            'codigo' => old('deposito_codigo', optional($depositoModel)->codigo ?? ''),
            'descripcion' => old('deposito_descripcion', optional($depositoModel)->nombre ?? ''),
            'solo_lectura' => $soloLectura,
            'col_label' => $colLabel,
            'col_input' => $colInput,
        ])

        @if (isset($recuento))
            <div class="form-group row mb-0">
                <label class="{{ $colLabel }}">Estado actual</label>
                <div class="{{ $colInput }} pt-1">
                    @include('stock.recuento.partials.estado_badge', ['estado' => $recuento->estado])
                </div>
            </div>
        @endif
    </div>

    <div class="col-sm-6">
        <div class="form-group row">
            <label for="recuento_fecha" class="{{ $colLabel }} requerido">Fecha del recuento</label>
            <div class="{{ $colInput }}">
                <input type="date" name="fecha" id="recuento_fecha" class="form-control"
                    style="max-width: 11rem;"
                    value="{{ old('fecha', isset($recuento) ? optional($recuento->fecha)->format('Y-m-d') : date('Y-m-d')) }}"
                    @if ($soloLectura) readonly @endif required>
            </div>
        </div>

        <div class="form-group row mb-0">
            <label for="recuento_comentario" class="{{ $colLabel }}">Comentario</label>
            <div class="{{ $colInput }}">
                <textarea name="comentario" id="recuento_comentario" class="form-control" rows="3" @if ($soloLectura) readonly @endif>{{ old('comentario', $recuento->comentario ?? '') }}</textarea>
            </div>
        </div>
    </div>
</div>
