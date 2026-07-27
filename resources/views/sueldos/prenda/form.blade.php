@php
    use App\Support\Stock\ArticuloConsultaDesdeModal;

    $variantesExistentes = old('variantes');
    if ($variantesExistentes === null && isset($data)) {
        $variantesExistentes = $data->variantes->map(fn ($v) => [
            'color_id' => $v->color_id,
            'talle_id' => $v->talle_id,
            'sku' => $v->sku,
            'articulo_id' => $v->articulo_id,
            'descripcion' => $v->articulo->descripcion ?? '',
        ])->values()->all();
    }
    $variantesExistentes = $variantesExistentes ?: [];
    $puedeConsultarArticulo = ArticuloConsultaDesdeModal::puedeConsultar();
@endphp
<div class="row">
    <div class="col-lg-6">
        <div class="form-group row">
            <label for="codigo" class="col-lg-4 col-form-label">C&oacute;digo</label>
            <div class="col-lg-4">
                @if (isset($data))
                    <input type="text" id="codigo" class="form-control" value="{{ $data->codigo }}" readonly/>
                @else
                    <input type="number" name="codigo" id="codigo" class="form-control" min="1"
                           value="{{ old('codigo') }}" placeholder="Autom&aacute;tico si se deja vac&iacute;o"/>
                @endif
            </div>
        </div>
        <div class="form-group row">
            <label for="descripcion" class="col-lg-4 col-form-label requerido">Descripci&oacute;n</label>
            <div class="col-lg-8">
                <input type="text" name="descripcion" id="descripcion" class="form-control" maxlength="60" required
                       value="{{ old('descripcion', $data->descripcion ?? '') }}"/>
            </div>
        </div>
        <div class="form-group row">
            <label for="marca" class="col-lg-4 col-form-label">Marca</label>
            <div class="col-lg-8">
                <input type="text" name="marca" id="marca" class="form-control" maxlength="30"
                       value="{{ old('marca', $data->marca ?? '') }}"/>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="form-group row">
            <label for="porcentaje_pedido" class="col-lg-4 col-form-label">% Pedido</label>
            <div class="col-lg-4">
                <input type="number" step="0.01" name="porcentaje_pedido" id="porcentaje_pedido" class="form-control" min="0"
                       value="{{ old('porcentaje_pedido', $data->porcentaje_pedido ?? '') }}"/>
                <small class="form-text text-muted">Para curva de pedido/reposici&oacute;n.</small>
            </div>
        </div>
        <div class="form-group row">
            <label for="orden" class="col-lg-4 col-form-label">Orden</label>
            <div class="col-lg-4">
                <input type="number" name="orden" id="orden" class="form-control" min="0"
                       value="{{ old('orden', $data->orden ?? 0) }}"/>
            </div>
        </div>
        <div class="form-group row">
            <label for="vida_util_meses" class="col-lg-4 col-form-label">Vida &uacute;til (meses)</label>
            <div class="col-lg-4">
                <input type="number" name="vida_util_meses" id="vida_util_meses" class="form-control" min="0"
                       value="{{ old('vida_util_meses', $data->vida_util_meses ?? '') }}" placeholder="Ej. 12"/>
                <small class="form-text text-muted">Si se define, el cupo se repone al vencer (no por a&ntilde;o).</small>
            </div>
        </div>
        <div class="form-group row">
            <label for="norma" class="col-lg-4 col-form-label">Norma</label>
            <div class="col-lg-8">
                <input type="text" name="norma" id="norma" class="form-control" maxlength="80"
                       value="{{ old('norma', $data->norma ?? '') }}" placeholder="Ej. IRAM 3610 / IRAM-ISO 20345"/>
            </div>
        </div>
        <div class="form-group row">
            <label class="col-lg-4 col-form-label">Opciones</label>
            <div class="col-lg-8">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" name="es_seguridad" id="es_seguridad" value="1"
                           {{ old('es_seguridad', $data->es_seguridad ?? false) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="es_seguridad">Es elemento de seguridad (EPP)</label>
                </div>
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" name="requiere_certificacion" id="requiere_certificacion" value="1"
                           {{ old('requiere_certificacion', $data->requiere_certificacion ?? false) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="requiere_certificacion">Requiere certificaci&oacute;n</label>
                </div>
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" name="activo" id="activo" value="1"
                           {{ old('activo', $data->activo ?? true) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="activo">Activo</label>
                </div>
            </div>
        </div>
    </div>
</div>

<hr>
<div class="d-flex align-items-center mb-2">
    <h5 class="mb-0">Variantes: color &times; talle &rarr; art&iacute;culo (SKU)</h5>
    <button type="button" class="btn btn-outline-primary btn-sm ml-auto" id="btn-agregar-variante">
        <i class="fa fa-plus"></i> Agregar variante
    </button>
</div>
<p class="text-muted small">Cada combinaci&oacute;n de color y talle se mapea a un SKU del maestro de art&iacute;culos para descontar stock en la entrega. Use la lupa o F1 sobre el SKU para consultar.</p>

<div class="table-responsive">
    <table class="table table-sm table-bordered" id="tabla-variantes">
        <thead>
            <tr>
                <th style="width:22%">Color</th>
                <th style="width:18%">Talle</th>
                <th style="width:50%">Art&iacute;culo (SKU)</th>
                <th style="width:10%" class="text-center">Quitar</th>
            </tr>
        </thead>
        <tbody id="tbody-variantes">
            @foreach ($variantesExistentes as $i => $v)
            @include('sueldos.prenda.partials.fila_variante_articulo', [
                'idx' => $i,
                'colorId' => $v['color_id'] ?? null,
                'talleId' => $v['talle_id'] ?? null,
                'sku' => $v['sku'] ?? '',
                'articuloId' => $v['articulo_id'] ?? null,
                'descripcionArticulo' => $v['descripcion'] ?? '',
                'colores' => $colores,
                'talles' => $talles,
                'puedeConsultarArticulo' => $puedeConsultarArticulo,
            ])
            @endforeach
        </tbody>
    </table>
</div>

<template id="tpl-variante-row">
@include('sueldos.prenda.partials.fila_variante_articulo', [
    'idx' => '__IDX__',
    'colorId' => null,
    'talleId' => null,
    'sku' => '',
    'articuloId' => null,
    'descripcionArticulo' => '',
    'colores' => $colores,
    'talles' => $talles,
    'puedeConsultarArticulo' => $puedeConsultarArticulo,
])
</template>

@include('includes.stock.modalconsultaarticulo')
