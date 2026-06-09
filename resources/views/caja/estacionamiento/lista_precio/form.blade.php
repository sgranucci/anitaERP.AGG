@php
    use App\Support\Caja\Estacionamiento\ListaPrecioEstacionamientoItemVigenteSupport;

    $esEdicion = isset($data) && $data->id;
    $empresaFormId = (int) old('empresa_id', $empresa_form_id ?? ($data->empresa_id ?? 0));
    $empresaGuardadaId = (int) ($empresa_guardada_id ?? 0);
    $listaIdForm = (int) ($lista_id ?? ($esEdicion ? (int) $data->id : 0));
    $categoriaFormId = (int) old('categoria_automovil_id', $esEdicion ? ($data->categoria_automovil_id ?? 0) : 0);
    $cambioEmpresaPreview = $esEdicion && $empresaGuardadaId > 0 && $empresaFormId !== $empresaGuardadaId;
    $monedaSeleccionada = (int) old('moneda_id', $esEdicion ? ($data->moneda_id ?? 0) : ($moneda_peso_id ?? 0));
    $listaId = $listaIdForm;

    $itemIdsEmpresa = collect($items_empresa)->pluck('id')->map(fn ($id) => (int) $id)->all();

    $lineasPorItem = collect();
    if ($esEdicion && $data->items && $data->items->count()) {
        $lineasPorItem = $data->items
            ->filter(function ($linea) use ($empresaFormId) {
                if ($empresaFormId <= 0) {
                    return true;
                }

                return (int) ($linea->itemEstacionamiento->empresa_id ?? 0) === $empresaFormId;
            })
            ->groupBy('item_estacionamiento_id');
    }

    $renglonesOld = old('precio_renglones');
    if (is_array($renglonesOld) && $renglonesOld !== []) {
        $lineasPorItem = collect($renglonesOld)
            ->filter(fn ($r) => is_array($r) && in_array((int) ($r['item_id'] ?? 0), $itemIdsEmpresa, true))
            ->groupBy(fn ($r) => (int) $r['item_id'])
            ->map(function ($grupo) {
                return $grupo->map(function ($r) {
                    $obj = new \stdClass;
                    $obj->id = $r['linea_id'] ?? '';
                    $obj->item_estacionamiento_id = (int) ($r['item_id'] ?? 0);
                    $obj->precio = $r['precio'] ?? '';
                    $obj->fecha_vigencia = $r['fecha_vigencia'] ?? date('Y-m-d');

                    return $obj;
                });
            });
    }

    $filas = collect();
    foreach ($items_empresa as $item) {
        $itemId = (int) $item->id;
        $lineasItem = $lineasPorItem->get($itemId, collect());
        $vigente = ListaPrecioEstacionamientoItemVigenteSupport::resolverVigente($lineasItem);

        $lineaId = $vigente->id ?? '';
        $precioVal = $vigente->precio ?? '';
        $fechaVal = date('Y-m-d');
        if ($vigente && $vigente->fecha_vigencia) {
            $fv = $vigente->fecha_vigencia;
            $fechaVal = $fv instanceof \DateTimeInterface ? $fv->format('Y-m-d') : substr((string) $fv, 0, 10);
        }

        $historialOculto = $lineasItem->filter(fn ($l) => (int) ($l->id ?? 0) !== (int) $lineaId);

        $filas->push([
            'item' => $item,
            'item_id' => $itemId,
            'linea_id' => $lineaId,
            'precio' => $precioVal,
            'fecha' => $fechaVal,
            'historial_oculto' => $historialOculto,
            'tiene_historial' => $lineasItem->count() > 0,
        ]);
    }
@endphp

<input type="hidden"
       id="lista-precio-meta"
       data-lista-id="{{ $listaIdForm }}"
       data-empresa-guardada-id="{{ $empresaGuardadaId }}"
       data-url-validar-cabecera="{{ route('estacionamiento_lista_precio_validar_cabecera') }}">

@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => $empresaFormId ?: null,
    'solo_lectura' => false,
])

@if ($cambioEmpresaPreview)
    <div class="alert alert-warning">
        Est&aacute; previsualizando otra empresa. La grilla muestra los &iacute;tems de esa empresa; al guardar se actualizar&aacute; la cabecera
        y se eliminar&aacute;n precios de &iacute;tems que no pertenezcan a la empresa elegida.
    </div>
@endif

<div id="cabecera-duplicada-aviso" class="alert alert-danger d-none" role="alert"></div>

<div class="form-group row">
    <label for="categoria_automovil_id" class="col-lg-3 col-form-label requerido">Categor&iacute;a</label>
    <div class="col-lg-6">
        <select name="categoria_automovil_id" id="categoria_automovil_id" class="form-control" required>
            <option value="">-- Elija categor&iacute;a --</option>
            @foreach ($categoria_query as $categoria)
                <option value="{{ $categoria->id }}" {{ $categoriaFormId === (int) $categoria->id ? 'selected' : '' }}>
                    {{ $categoria->nombre }}
                </option>
            @endforeach
        </select>
        <div class="invalid-feedback d-block invalid-feedback-cabecera"></div>
    </div>
</div>

<div class="form-group row">
    <label for="moneda_id" class="col-lg-3 col-form-label requerido">Moneda</label>
    <div class="col-lg-4">
        <select name="moneda_id" id="moneda_id" class="form-control" required>
            @foreach ($moneda_query as $moneda)
                <option value="{{ $moneda->id }}" {{ $monedaSeleccionada === (int) $moneda->id ? 'selected' : '' }}>
                    {{ $moneda->nombre }} ({{ $moneda->abreviatura ?? '' }})
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">Por defecto pesos argentinos (ARS).</small>
    </div>
</div>

<hr>
<h5>Precios por &iacute;tem</h5>
<p class="text-muted small">
    Una fila por &iacute;tem con el precio vigente. Use
    <i class="fa fa-plus text-success"></i> para cargar una nueva vigencia sin duplicar filas, o
    <i class="fa fa-history text-info"></i> para ver el historial.
</p>

<table class="table table-bordered" id="tabla-items-lista-precio">
    <thead>
        <tr>
            <th style="width: 35%;">&Iacute;tem</th>
            <th style="width: 20%;">Precio vigente</th>
            <th style="width: 20%;">Vigente desde</th>
            <th style="width: 15%;">Pendientes</th>
            <th style="width: 10%;">Acciones</th>
        </tr>
    </thead>
    <tbody id="tbody-items-lista-precio">
        @forelse ($filas as $fila)
            <tr class="fila-item-precio"
                data-item-id="{{ $fila['item_id'] }}"
                data-linea-id="{{ $fila['linea_id'] }}"
                data-item-nombre="{{ $fila['item']->nombre }}">
                <td>
                    <strong>{{ $fila['item']->nombre }}</strong>
                </td>
                <td>
                    <input type="number"
                           class="form-control precio-vigente"
                           min="0"
                           step="0.01"
                           value="{{ $fila['precio'] }}"
                           placeholder="Sin precio"
                           data-item-id="{{ $fila['item_id'] }}">
                </td>
                <td>
                    <input type="date"
                           class="form-control fecha-vigente"
                           value="{{ $fila['fecha'] }}"
                           data-item-id="{{ $fila['item_id'] }}">
                </td>
                <td class="celda-pendientes-vigencia small text-muted align-middle">—</td>
                <td class="text-nowrap text-center align-middle">
                    <button type="button"
                            class="btn-accion-tabla btn-nueva-vigencia-item tooltipsC"
                            title="Nueva vigencia">
                        <i class="fa fa-plus text-success"></i>
                    </button>
                    @if ($esEdicion && $fila['tiene_historial'])
                        <button type="button"
                                class="btn-accion-tabla btn-historia-item tooltipsC"
                                title="Ver historial de precios"
                                data-lista-id="{{ $listaId }}"
                                data-item-id="{{ $fila['item_id'] }}">
                            <i class="fa fa-history text-info"></i>
                        </button>
                    @endif
                </td>
            </tr>
            <tr class="fila-nueva-vigencia-wrap d-none" data-item-id="{{ $fila['item_id'] }}">
                <td colspan="5" class="bg-light py-2">
                    <div class="form-row align-items-end">
                        <div class="col-md-3">
                            <label class="small mb-1">Nuevo precio</label>
                            <input type="number" class="form-control form-control-sm nueva-vigencia-precio" min="0" step="0.01" placeholder="0,00">
                        </div>
                        <div class="col-md-3">
                            <label class="small mb-1">Vigente desde</label>
                            <input type="date" class="form-control form-control-sm nueva-vigencia-fecha" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-auto">
                            <button type="button" class="btn btn-success btn-sm btn-confirmar-nueva-vigencia">
                                <i class="fa fa-check"></i> Agregar
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm btn-cancelar-nueva-vigencia">
                                Cancelar
                            </button>
                        </div>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-muted text-center">No hay &iacute;tems activos para la empresa seleccionada.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div id="payload-historial-precios" class="d-none" aria-hidden="true">
    @foreach ($filas as $fila)
        @foreach ($fila['historial_oculto'] as $hist)
            @php
                $hf = $hist->fecha_vigencia;
                $hfStr = $hf instanceof \DateTimeInterface ? $hf->format('Y-m-d') : substr((string) $hf, 0, 10);
            @endphp
            <div class="payload-historial-row"
                 data-linea-id="{{ $hist->id }}"
                 data-item-id="{{ $hist->item_estacionamiento_id }}"
                 data-precio="{{ $hist->precio }}"
                 data-fecha="{{ $hfStr }}">
            </div>
        @endforeach
    @endforeach
</div>
<div id="payload-nuevas-vigencias" class="d-none" aria-hidden="true"></div>
<div id="payload-renglones-envio" aria-hidden="true"></div>

@if ($esEdicion)
    @include('caja.estacionamiento.lista_precio.partials.modal_historia_item')
@endif
