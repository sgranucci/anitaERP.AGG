@php
    $preciosRegimen = [];
    if (old('precios') !== null) {
        foreach (old('precios', []) as $i => $precio) {
            $preciosRegimen[] = (object) [
                'precio' => $precio,
                'vigencia_desde' => old('precio_vigencia_desde.'.$i),
                'vigencia_hasta' => old('precio_vigencia_hasta.'.$i),
                'creousuario_id' => old('creousuario_precio_ids.'.$i, auth()->id()),
            ];
        }
    } elseif (isset($data) && $data->relationLoaded('precios')) {
        foreach ($data->precios as $precio) {
            $preciosRegimen[] = (object) [
                'precio' => $precio->precio,
                'vigencia_desde' => $precio->vigencia_desde?->format('Y-m-d'),
                'vigencia_hasta' => $precio->vigencia_hasta?->format('Y-m-d'),
                'creousuario_id' => $precio->creousuario_id,
            ];
        }
    }
@endphp
<div class="card card-outline card-info mt-3">
    <div class="card-header">
        <h3 class="card-title">Precios con vigencia</h3>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            Opcional. Si no hay precio, el operador lo tipea en la factura (Bierzo hoy).
            Si hay varios, se usa el vigente a la fecha del comprobante.
        </p>
        <table class="table table-sm table-bordered" id="cv-precio-table">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th>Precio</th>
                    <th>Desde</th>
                    <th>Hasta</th>
                    <th class="width80">Acciones</th>
                </tr>
            </thead>
            <tbody id="tbody-cv-precio-table">
                @foreach ($preciosRegimen as $precio)
                    <tr class="item-cv-precio">
                        <td>
                            <input type="number" step="0.0001" min="0" name="precios[]" class="form-control form-control-sm"
                                value="{{ $precio->precio ?? '' }}">
                        </td>
                        <td>
                            <input type="date" name="precio_vigencia_desde[]" class="form-control form-control-sm"
                                value="{{ $precio->vigencia_desde ?? '' }}">
                        </td>
                        <td>
                            <input type="date" name="precio_vigencia_hasta[]" class="form-control form-control-sm"
                                value="{{ $precio->vigencia_hasta ?? '' }}">
                        </td>
                        <td>
                            <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cv_precio tooltipsC">
                                <i class="fa fa-times-circle text-danger"></i>
                            </button>
                            <input type="hidden" name="creousuario_precio_ids[]" value="{{ $precio->creousuario_id ?? auth()->id() }}">
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <template id="cv-template-renglon-precio">
            <tr class="item-cv-precio">
                <td>
                    <input type="number" step="0.0001" min="0" name="precios[]" class="form-control form-control-sm" value="">
                </td>
                <td>
                    <input type="date" name="precio_vigencia_desde[]" class="form-control form-control-sm" value="">
                </td>
                <td>
                    <input type="date" name="precio_vigencia_hasta[]" class="form-control form-control-sm" value="">
                </td>
                <td>
                    <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cv_precio tooltipsC">
                        <i class="fa fa-times-circle text-danger"></i>
                    </button>
                    <input type="hidden" name="creousuario_precio_ids[]" value="{{ auth()->id() }}">
                </td>
            </tr>
        </template>
        <button type="button" id="cv-agrega_renglon_precio" class="btn btn-outline-primary btn-sm">
            + Agrega precio
        </button>
    </div>
</div>
