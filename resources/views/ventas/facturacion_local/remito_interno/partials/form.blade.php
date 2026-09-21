@php
    use App\Support\Ventas\FacturacionLocal\RemitoInternoEstadosSupport;
    $lineasExistentes = old('lineas');
    if (! is_array($lineasExistentes)) {
        $lineasExistentes = ($data->lineas ?? collect())->map(function ($l) {
            return [
                'id' => $l->id,
                'articulo_id' => $l->articulo_id,
                'articulo_codigo' => $l->articulo->sku ?? '',
                'descripcion' => $l->descripcion ?: ($l->articulo->descripcion ?? ''),
                'cantidad' => $l->cantidad,
                'talle_id' => $l->talle_id,
                'talle_label' => trim(($l->talle->nombre ?? '').' '.($l->talle->codigo ?? '')),
                'color_id' => $l->color_id,
                'combinacion_id' => $l->combinacion_id,
                'combinacion_label' => trim(($l->combinacion->codigo ?? '').' '.($l->combinacion->nombre ?? '')),
                'modulo_id' => $l->modulo_id,
            ];
        })->values()->all();
    }
    if ($lineasExistentes === []) {
        $lineasExistentes = [
            [
                'articulo_id' => '',
                'articulo_codigo' => '',
                'descripcion' => '',
                'cantidad' => 1,
                'talle_id' => '',
                'talle_label' => '',
                'color_id' => '',
                'combinacion_id' => '',
                'combinacion_label' => '',
            ],
        ];
    }
    $ro = ! ($editable ?? true);
@endphp

@include('includes.tabs-activas-estilos')
<div class="tabs-activas">
    <ul class="nav nav-tabs" id="tabs-ri" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-toggle="tab" href="#tab-datos" role="tab">
                <i class="fa fa-info-circle"></i> Datos
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tab-lineas" role="tab">
                <i class="fa fa-list"></i> Artículos
            </a>
        </li>
    </ul>
</div>

<div class="tab-content pt-3">
    <div class="tab-pane fade show active" id="tab-datos" role="tabpanel">
        @if ($data->id ?? false)
            <div class="form-group row">
                <label class="col-lg-4 control-label text-right pr-2">Nº / Estado</label>
                <div class="col-lg-6">
                    <p class="form-control-plaintext mb-0">
                        {{ $data->numero }} —
                        {{ RemitoInternoEstadosSupport::etiqueta($data->estado) }}
                    </p>
                </div>
            </div>
        @endif

        <div class="form-group row">
            <label class="col-lg-4 control-label text-right pr-2 requerido" for="fecha">Fecha</label>
            <div class="col-lg-3">
                <input type="date" name="fecha" id="fecha" class="form-control"
                       value="{{ old('fecha', optional($data->fecha)->format('Y-m-d') ?: now()->format('Y-m-d')) }}"
                       {{ $ro ? 'readonly' : 'required' }}>
            </div>
        </div>

        <div class="form-group row">
            <label class="col-lg-4 control-label text-right pr-2 requerido" for="local_venta_id">Local</label>
            <div class="col-lg-6">
                <select name="local_venta_id" id="local_venta_id" class="form-control" {{ $ro ? 'disabled' : 'required' }}>
                    <option value="">Seleccione…</option>
                    @foreach ($locales as $loc)
                        <option value="{{ $loc->id }}"
                            data-empresa="{{ $loc->empresa_id }}"
                            data-deposito="{{ $loc->deposito_id }}"
                            {{ (int) old('local_venta_id', $data->local_venta_id) === (int) $loc->id ? 'selected' : '' }}>
                            {{ $loc->codigo }} — {{ $loc->nombre }}
                        </option>
                    @endforeach
                </select>
                @if ($ro)
                    <input type="hidden" name="local_venta_id" value="{{ $data->local_venta_id }}">
                @endif
                <small class="form-text text-muted">El depósito de salida es el del local.</small>
            </div>
        </div>

        <div class="form-group row">
            <label class="col-lg-4 control-label text-right pr-2" for="destinatario">Destinatario</label>
            <div class="col-lg-6">
                <input type="text" name="destinatario" id="destinatario" class="form-control"
                       maxlength="160"
                       value="{{ old('destinatario', $data->destinatario) }}"
                       {{ $ro ? 'readonly' : '' }}
                       placeholder="Local / sector / persona destino">
            </div>
        </div>

        <div class="form-group row">
            <label class="col-lg-4 control-label text-right pr-2" for="leyenda">Leyenda</label>
            <div class="col-lg-6">
                <input type="text" name="leyenda" id="leyenda" class="form-control"
                       maxlength="255"
                       value="{{ old('leyenda', $data->leyenda) }}"
                       {{ $ro ? 'readonly' : '' }}>
            </div>
        </div>

        <div class="form-group row">
            <label class="col-lg-4 control-label text-right pr-2" for="observacion">Observación</label>
            <div class="col-lg-6">
                <textarea name="observacion" id="observacion" class="form-control" rows="2"
                          {{ $ro ? 'readonly' : '' }}>{{ old('observacion', $data->observacion) }}</textarea>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-lineas" role="tabpanel">
        <p class="text-muted small mb-2">
            Cargue SKU (Enter), elija combinación/color y talle como en el POS. Cada fila es un artículo + variante + cantidad.
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered" id="ri-lineas-table">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width:14%;">SKU</th>
                        <th style="width:28%;">Descripción</th>
                        <th style="width:22%;">Combinación / Color</th>
                        <th style="width:12%;">Talle</th>
                        <th style="width:10%;">Cantidad</th>
                        <th style="width:8%;"></th>
                    </tr>
                </thead>
                <tbody id="ri-lineas-tbody">
                    @foreach ($lineasExistentes as $idx => $linea)
                        @include('ventas.facturacion_local.remito_interno.partials.fila_linea', [
                            'idx' => $idx,
                            'linea' => $linea,
                            'ro' => $ro,
                        ])
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (! $ro)
            <button type="button" class="btn btn-outline-primary btn-sm" id="ri-agregar-linea">
                <i class="fa fa-plus"></i> Agregar renglón
            </button>
        @endif
    </div>
</div>

@if (! $ro)
<template id="ri-template-linea">
    @include('ventas.facturacion_local.remito_interno.partials.fila_linea', [
        'idx' => '__IDX__',
        'linea' => [
            'articulo_id' => '',
            'articulo_codigo' => '',
            'descripcion' => '',
            'cantidad' => 1,
            'talle_id' => '',
            'talle_label' => '',
            'color_id' => '',
            'combinacion_id' => '',
            'combinacion_label' => '',
        ],
        'ro' => false,
    ])
</template>
@endif
