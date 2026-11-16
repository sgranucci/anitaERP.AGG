@php
    use App\Support\Compras\ImportacionEstados;
    use App\Support\Compras\ImportacionGastoTratamiento;
    $esEdicion = isset($data) && $data && $data->id;
    $puedeEditar = $esEdicion ? can('actualizar-importacion', false) : can('crear-importacion', false);
@endphp

@include('includes.form-error')
@include('includes.mensaje')
@include('includes.tabs-activas-estilos')

<form method="POST"
      id="form-importacion"
      data-enter-campos="1"
      enctype="multipart/form-data"
      action="{{ $esEdicion ? route('actualizar_importacion', $data->id) : route('guardar_importacion') }}"
      class="form-horizontal">
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <div class="tabs-activas">
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-cabecera" role="tab"><i class="fa fa-info-circle"></i> Cabecera</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-oc" role="tab"><i class="fa fa-shopping-cart"></i> Órdenes de compra</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-embarques" role="tab"><i class="fa fa-ship"></i> Embarques</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-gastos" role="tab"><i class="fa fa-money"></i> Gastos</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-despachos" role="tab"><i class="fa fa-file-text"></i> Despachos</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-docs" role="tab"><i class="fa fa-paperclip"></i> Documentos</a></li>
            @if ($esEdicion)
                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-checklist" role="tab"><i class="fa fa-check-square"></i> Checklist</a></li>
                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-costos" role="tab"><i class="fa fa-calculator"></i> Landed cost</a></li>
            @endif
        </ul>
    </div>

    <div class="tab-content pt-3">
        <div class="tab-pane fade show active" id="tab-cabecera" role="tabpanel">
            @include('includes.form-empresa-asignada', [
                'empresa_query' => $empresa_query,
                'empresa_id' => old('empresa_id', $data->empresa_id ?? null),
                'solo_lectura' => $esEdicion,
                'col_label' => 'col-lg-4 control-label text-right pr-2',
                'col_input' => 'col-lg-7',
            ])

            <div class="form-group row">
                <label class="col-lg-4 control-label text-right pr-2">Código</label>
                <div class="col-lg-7">
                    <input type="text" name="codigo" class="form-control" maxlength="40"
                           value="{{ old('codigo', $data->codigo ?? '') }}"
                           placeholder="Vacío = automático (IMP-AAAA-NNNN)">
                </div>
            </div>

            @include('compras.partials.campo_consulta_proveedor', [
                'prefix' => 'importacion',
                'proveedorId' => old('proveedor_id', $data->proveedor_id ?? ''),
                'codigo' => old('proveedor_codigo', $data->proveedor->codigo ?? ''),
                'nombre' => old('proveedor_nombre', $data->proveedor->nombre ?? ''),
                'inputName' => 'proveedor_id',
                'inputId' => 'proveedor_importacion_id',
                'col_label' => 'col-lg-4 control-label text-right pr-2',
                'col_input' => 'col-lg-7',
                'required' => false,
            ])

            @include('configuracion.partials.campo_consulta_moneda', [
                'prefix' => 'importacion',
                'monedaId' => (int) old('moneda_id', $data->moneda_id ?? 0),
                'codigo' => old('moneda_codigo', $data->moneda->codigo ?? ($data->moneda->abreviatura ?? '')),
                'nombre' => old('moneda_nombre', $data->moneda->nombre ?? ''),
                'inputName' => 'moneda_id',
                'inputId' => 'moneda_id',
                'col_label' => 'col-lg-4 control-label text-right pr-2',
                'col_input' => 'col-lg-4',
                'required' => false,
            ])

            <div class="form-group row">
                <label class="col-lg-4 control-label text-right pr-2">Cotización</label>
                <div class="col-lg-3">
                    <input type="number" step="0.000001" name="cotizacion" class="form-control"
                           value="{{ old('cotizacion', $data->cotizacion ?? '') }}">
                </div>
            </div>

            @include('ventas.partials.campo_consulta_incoterm', [
                'prefix' => 'importacion',
                'incotermId' => old('incoterm_id', $data->incoterm_id ?? ''),
                'codigo' => old('incoterm_codigo', $data->incoterm->abreviatura ?? ''),
                'nombre' => old('incoterm_nombre', $data->incoterm->nombre ?? ''),
                'inputName' => 'incoterm_id',
                'inputId' => 'incoterm_importacion_id',
                'col_label' => 'col-lg-4 control-label text-right pr-2',
                'col_input' => 'col-lg-7',
            ])

            <div class="form-group row">
                <label class="col-lg-4 control-label text-right pr-2">Estado</label>
                <div class="col-lg-7">
                    <select name="estado" class="form-control">
                        @foreach ($estados as $est)
                            <option value="{{ $est['id'] }}" @if (old('estado', $data->estado ?? 'borrador') === $est['id']) selected @endif>
                                {{ $est['nombre'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group row">
                <label class="col-lg-4 control-label text-right pr-2">Fechas</label>
                <div class="col-lg-7">
                    <div class="form-row">
                        <div class="col-md-4">
                            <small>Apertura</small>
                            <input type="date" name="fecha_apertura" class="form-control"
                                   value="{{ old('fecha_apertura', optional($data->fecha_apertura ?? null)->format('Y-m-d') ?? date('Y-m-d')) }}">
                        </div>
                        <div class="col-md-4">
                            <small>ETD</small>
                            <input type="date" name="fecha_etd" class="form-control"
                                   value="{{ old('fecha_etd', optional($data->fecha_etd ?? null)->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-4">
                            <small>ETA</small>
                            <input type="date" name="fecha_eta" class="form-control"
                                   value="{{ old('fecha_eta', optional($data->fecha_eta ?? null)->format('Y-m-d')) }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group row">
                <label class="col-lg-4 control-label text-right pr-2">FOB estimado</label>
                <div class="col-lg-3">
                    <input type="number" step="0.01" name="fob_estimado" class="form-control"
                           value="{{ old('fob_estimado', $data->fob_estimado ?? 0) }}">
                </div>
                <div class="col-lg-4">
                    <div class="form-check mt-2">
                        <input type="checkbox" class="form-check-input" name="exige_stock_para_cerrar" id="exige_stock_para_cerrar" value="1"
                               @if (old('exige_stock_para_cerrar', $data->exige_stock_para_cerrar ?? true)) checked @endif>
                        <label class="form-check-label" for="exige_stock_para_cerrar">Exigir stock/COM para cerrar costos</label>
                    </div>
                </div>
            </div>

            <div class="form-group row">
                <label class="col-lg-4 control-label text-right pr-2">Observación</label>
                <div class="col-lg-7">
                    <textarea name="observacion" class="form-control" rows="3">{{ old('observacion', $data->observacion ?? '') }}</textarea>
                </div>
            </div>

            @if ($esEdicion)
                <div class="form-group row">
                    <label class="col-lg-4 control-label text-right pr-2">Totales</label>
                    <div class="col-lg-7">
                        <span class="badge badge-secondary">Gastos est. {{ number_format((float)$data->gastos_estimados, 2, ',', '.') }}</span>
                        <span class="badge badge-info">Gastos real {{ number_format((float)$data->gastos_reales, 2, ',', '.') }}</span>
                        <span class="badge badge-success">Landed {{ number_format((float)$data->landed_cost, 2, ',', '.') }}</span>
                        @if ($data->coeficiente_embarque)
                            <span class="badge badge-dark">Coef. {{ number_format((float)$data->coeficiente_embarque, 4, ',', '.') }}</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <div class="tab-pane fade" id="tab-oc" role="tabpanel">
            @php
                $ocRows = $esEdicion ? $data->ordenescompra : collect();
                $ocIdsOld = old('ordencompra_ids', $ocRows->pluck('id')->all());
            @endphp
            <div class="mb-2">
                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-consulta-oc-importacion">
                    <i class="fa fa-search"></i> Agregar OC (F1 / lupa)
                </button>
                <small class="text-muted ml-2">También puede tipear el n&uacute;mero de OC y Enter.</small>
            </div>
            <div class="form-group row mb-2">
                <label class="col-lg-2 control-label text-right pr-2">N&ordm; OC</label>
                <div class="col-lg-4">
                    <input type="text" id="importacion_oc_codigo" class="form-control form-control-sm" placeholder="N&uacute;mero OC" autocomplete="off">
                </div>
            </div>
            <table class="table table-sm table-bordered">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr><th>ID</th><th>Número</th><th>Fecha</th><th>Proveedor</th><th>Estado</th><th></th></tr>
                </thead>
                <tbody id="tbody-oc-importacion">
                    @foreach ($ocRows as $oc)
                        <tr data-oc-id="{{ $oc->id }}">
                            <td>
                                <input type="hidden" name="ordencompra_ids[]" value="{{ $oc->id }}">
                                {{ $oc->id }}
                            </td>
                            <td>{{ $oc->numeroordencompra }}</td>
                            <td>{{ $oc->fecha }}</td>
                            <td>{{ $oc->proveedores->nombre ?? '' }}</td>
                            <td>{{ $oc->estadoordencompra }}</td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger js-quitar-oc"><i class="fa fa-times-circle"></i></button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @foreach ((array) $ocIdsOld as $ocIdHidden)
                @if (! $ocRows->contains('id', (int) $ocIdHidden) && (int) $ocIdHidden > 0)
                    <input type="hidden" name="ordencompra_ids[]" value="{{ (int) $ocIdHidden }}" class="js-oc-orphan">
                @endif
            @endforeach
        </div>

        <div class="tab-pane fade" id="tab-embarques" role="tabpanel">
            <div class="mb-2">
                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-agregar-embarque">+ Embarque</button>
            </div>
            <table class="table table-sm table-bordered">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Tipo</th><th>Número</th><th>Contenedor</th><th>Buque/Vuelo</th>
                        <th>Pto origen</th><th>Pto destino</th><th>Embarque</th><th>Arribo</th><th></th>
                    </tr>
                </thead>
                <tbody id="tbody-embarques">
                    @foreach (old('embarques', $esEdicion ? $data->embarques : []) as $i => $emb)
                        @php $emb = is_array($emb) ? (object) $emb : $emb; @endphp
                        <tr>
                            <td>
                                <input type="hidden" name="embarques[{{ $i }}][id]" value="{{ $emb->id ?? '' }}">
                                <select name="embarques[{{ $i }}][tipo_documento]" class="form-control form-control-sm">
                                    @foreach (['BL','AWB','CRT','OTRO'] as $td)
                                        <option value="{{ $td }}" @if (($emb->tipo_documento ?? 'BL') === $td) selected @endif>{{ $td }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input name="embarques[{{ $i }}][numero_documento]" class="form-control form-control-sm" value="{{ $emb->numero_documento ?? '' }}"></td>
                            <td><input name="embarques[{{ $i }}][contenedor]" class="form-control form-control-sm" value="{{ $emb->contenedor ?? '' }}"></td>
                            <td><input name="embarques[{{ $i }}][buque_vuelo]" class="form-control form-control-sm" value="{{ $emb->buque_vuelo ?? '' }}"></td>
                            <td><input name="embarques[{{ $i }}][puerto_origen]" class="form-control form-control-sm" value="{{ $emb->puerto_origen ?? '' }}"></td>
                            <td><input name="embarques[{{ $i }}][puerto_destino]" class="form-control form-control-sm" value="{{ $emb->puerto_destino ?? '' }}"></td>
                            <td><input type="date" name="embarques[{{ $i }}][fecha_embarque]" class="form-control form-control-sm"
                                       value="{{ isset($emb->fecha_embarque) ? (\Illuminate\Support\Carbon::parse($emb->fecha_embarque)->format('Y-m-d')) : '' }}"></td>
                            <td><input type="date" name="embarques[{{ $i }}][fecha_arribo]" class="form-control form-control-sm"
                                       value="{{ isset($emb->fecha_arribo) ? (\Illuminate\Support\Carbon::parse($emb->fecha_arribo)->format('Y-m-d')) : '' }}"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger js-quitar-fila"><i class="fa fa-times-circle"></i></button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="tab-pane fade" id="tab-gastos" role="tabpanel">
            <div class="mb-2">
                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-agregar-gasto">+ Gasto</button>
                <a href="{{ route('importacion_gasto_tipo') }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">Tipos de gasto</a>
            </div>
            <table class="table table-sm table-bordered">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="min-width:11rem;">Tipo</th>
                        <th>Naturaleza</th>
                        <th>Importe</th>
                        <th>Cotiz.</th>
                        <th>Fecha</th>
                        <th style="min-width:9rem;">Factura CP</th>
                        <th>Ref.</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tbody-gastos">
                    @foreach (old('gastos', $esEdicion ? $data->gastos : []) as $i => $gasto)
                        @php
                            $gasto = is_array($gasto) ? (object) $gasto : $gasto;
                            $tipoGasto = $gasto->tipo ?? null;
                            if (! $tipoGasto && ! empty($gasto->importacion_gasto_tipo_id)) {
                                $tipoGasto = collect($gasto_tipos)->firstWhere('id', (int) $gasto->importacion_gasto_tipo_id);
                            }
                            $cp = $gasto->comprobanteProveedor ?? null;
                            $cpCodigo = old('gastos.'.$i.'.comprobante_codigo', $cp
                                ? trim(($cp->tipotransaccion_compras->abreviatura ?? '').' '.($cp->letra ?? '').' '.($cp->sucursal ?? '').'-'.($cp->numerocomprobante ?? ''))
                                : '');
                        @endphp
                        <tr class="js-gasto-fila">
                            <td>
                                <input type="hidden" name="gastos[{{ $i }}][id]" value="{{ $gasto->id ?? '' }}">
                                <input type="hidden" name="gastos[{{ $i }}][importacion_gasto_tipo_id]" class="js-gasto-tipo-id"
                                       value="{{ $gasto->importacion_gasto_tipo_id ?? '' }}">
                                <div class="d-flex align-items-center" style="gap:2px;">
                                    <button type="button" class="btn-accion-tabla js-consulta-gasto-tipo" title="Tipo de gasto (F1)"><i class="fa fa-search text-primary"></i></button>
                                    <input type="text" class="form-control form-control-sm js-gasto-tipo-codigo" style="width:4.5rem;"
                                           value="{{ $tipoGasto->codigo ?? '' }}" placeholder="Cód." autocomplete="off">
                                    <input type="text" class="form-control form-control-sm js-gasto-tipo-nombre" readonly
                                           value="{{ $tipoGasto->nombre ?? '' }}" placeholder="Tipo">
                                </div>
                            </td>
                            <td>
                                <select name="gastos[{{ $i }}][naturaleza]" class="form-control form-control-sm">
                                    <option value="estimado" @if (($gasto->naturaleza ?? '') === 'estimado') selected @endif>Estimado</option>
                                    <option value="real" @if (($gasto->naturaleza ?? '') === 'real') selected @endif>Real</option>
                                </select>
                            </td>
                            <td><input type="number" step="0.01" name="gastos[{{ $i }}][importe]" class="form-control form-control-sm" value="{{ $gasto->importe ?? '' }}"></td>
                            <td><input type="number" step="0.0001" name="gastos[{{ $i }}][cotizacion]" class="form-control form-control-sm" value="{{ $gasto->cotizacion ?? '' }}"></td>
                            <td><input type="date" name="gastos[{{ $i }}][fecha]" class="form-control form-control-sm"
                                       value="{{ isset($gasto->fecha) ? \Illuminate\Support\Carbon::parse($gasto->fecha)->format('Y-m-d') : '' }}"></td>
                            <td>
                                <input type="hidden" name="gastos[{{ $i }}][comprobante_proveedor_id]" class="js-gasto-cp-id"
                                       value="{{ $gasto->comprobante_proveedor_id ?? '' }}">
                                <div class="d-flex align-items-center" style="gap:2px;">
                                    <button type="button" class="btn-accion-tabla js-consulta-gasto-cp" title="Factura proveedor (F1)"><i class="fa fa-search text-primary"></i></button>
                                    <input type="text" class="form-control form-control-sm js-gasto-cp-codigo" value="{{ $cpCodigo }}"
                                           placeholder="Nº / ID" autocomplete="off">
                                </div>
                            </td>
                            <td><input name="gastos[{{ $i }}][referencia]" class="form-control form-control-sm" value="{{ $gasto->referencia ?? '' }}"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger js-quitar-fila"><i class="fa fa-times-circle"></i></button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="small text-muted">Tipo y factura CP por c&oacute;digo/lupa. Al elegir una CP se precargan importe/fecha/cotizaci&oacute;n y la naturaleza pasa a <strong>Real</strong> (pod&eacute;s editarlos). Inventariable vs gasto lo define el tipo.</p>
        </div>

        <div class="tab-pane fade" id="tab-despachos" role="tabpanel">
            @php
                $loteRows = $esEdicion ? $data->lotes : collect();
                $loteIdsOld = old('lote_ids', $loteRows->pluck('id')->all());
            @endphp
            <div class="mb-2">
                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-consulta-lote-importacion">
                    <i class="fa fa-search"></i> Agregar lote / despacho
                </button>
                <small class="text-muted ml-2">Tambi&eacute;n n&uacute;mero de despacho + Enter.</small>
            </div>
            <div class="form-group row mb-2">
                <label class="col-lg-2 control-label text-right pr-2">N&ordm; despacho</label>
                <div class="col-lg-4">
                    <input type="text" id="importacion_lote_codigo" class="form-control form-control-sm" placeholder="N&uacute;mero despacho" autocomplete="off">
                </div>
            </div>
            <table class="table table-sm table-bordered">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr><th>ID</th><th>Despacho</th><th>Ingreso</th><th></th></tr>
                </thead>
                <tbody id="tbody-lote-importacion">
                    @foreach ($loteRows as $lote)
                        <tr data-lote-id="{{ $lote->id }}">
                            <td>
                                <input type="hidden" name="lote_ids[]" value="{{ $lote->id }}">
                                {{ $lote->id }}
                            </td>
                            <td>{{ $lote->numerodespacho }}</td>
                            <td>{{ $lote->fechaingreso }}</td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger js-quitar-lote"><i class="fa fa-times-circle"></i></button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @foreach ((array) $loteIdsOld as $loteIdHidden)
                @if (! $loteRows->contains('id', (int) $loteIdHidden) && (int) $loteIdHidden > 0)
                    <input type="hidden" name="lote_ids[]" value="{{ (int) $loteIdHidden }}" class="js-lote-orphan">
                @endif
            @endforeach
        </div>

        <div class="tab-pane fade" id="tab-docs" role="tabpanel">
            @if ($esEdicion)
                <div class="card card-outline card-info mb-3">
                    <div class="card-header"><h3 class="card-title">Archivos asociados</h3></div>
                    <div class="card-body">
                        <div class="row">
                            @forelse ($data->archivos as $archivo)
                                <div class="col-md-6 col-lg-4 mb-3 js-archivo-card">
                                    <div class="card card-outline card-secondary h-100">
                                        <div class="card-body">
                                            <div class="text-truncate" title="{{ $archivo->nombre_original }}">{{ $archivo->nombre_original }}</div>
                                            <div class="mt-2">
                                                <a class="btn btn-sm btn-outline-primary" href="{{ route('importacion_archivo', [$data->id, $archivo->id]) }}">Descargar</a>
                                                @if ($puedeEditar)
                                                    <button type="button" class="btn btn-sm btn-outline-danger js-quitar-archivo-existente" data-id="{{ $archivo->id }}">Quitar</button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12 text-center text-muted">No hay archivos adjuntos.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif
            <div class="card card-outline card-primary">
                <div class="card-header"><h3 class="card-title">Agregar archivos nuevos</h3></div>
                <div class="card-body">
                    <p class="small text-muted">Un archivo por renglón. Use + Agrega renglón.</p>
                    <table class="table table-sm table-bordered">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr><th>Archivo nuevo</th><th>Acciones</th></tr>
                        </thead>
                        <tbody id="tbody-archivos-nuevos">
                            <tr>
                                <td><input type="file" name="nombrearchivos[]" class="form-control"></td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger js-quitar-archivo-nuevo"><i class="fa fa-times-circle text-danger"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-agregar-archivo">+ Agrega renglón</button>
                    <template id="importacion-template-renglon-archivo">
                        <tr>
                            <td><input type="file" name="nombrearchivos[]" class="form-control"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger js-quitar-archivo-nuevo"><i class="fa fa-times-circle text-danger"></i></button></td>
                        </tr>
                    </template>
                </div>
            </div>
        </div>

        @if ($esEdicion)
            <div class="tab-pane fade" id="tab-checklist" role="tabpanel">
                <table class="table table-sm table-bordered">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr><th>Código</th><th>Documento</th><th>Oblig.</th><th>Cumplido</th><th>Vence</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($data->checklist as $item)
                            <tr>
                                <td>{{ $item->codigo }}</td>
                                <td>{{ $item->nombre }}</td>
                                <td>{{ $item->obligatorio ? 'Sí' : 'No' }}</td>
                                <td>{{ $item->cumplido ? 'Sí' : 'No' }}</td>
                                <td>{{ optional($item->fecha_vencimiento)->format('d/m/Y') }}</td>
                                <td>
                                    <button type="submit" form="form-checklist-{{ $item->id }}" class="btn btn-sm btn-outline-success">
                                        {{ $item->cumplido ? 'Desmarcar' : 'Marcar' }}
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="tab-pane fade" id="tab-costos" role="tabpanel">
                <div class="mb-2">
                    <button type="submit" form="form-calcular-costos" class="btn btn-sm btn-primary">Calcular prorrateo</button>
                    @if (can('cerrar-costos-importacion', false) && $data->estado === ImportacionEstados::NACIONALIZADA)
                        <button type="submit" form="form-cerrar-costos" class="btn btn-sm btn-dark"
                                onclick="return confirm('¿Cerrar costos? No se podrán cargar más gastos.');">Cerrar costos</button>
                    @endif
                    @if (can('nacionalizar-importacion', false) && ImportacionEstados::puedeTransicionar($data->estado, ImportacionEstados::NACIONALIZADA))
                        <span class="text-muted mx-2">|</span>
                        <span class="small">Nacionalizar:</span>
                    @endif
                </div>
                @if ($data->prorrateos->isNotEmpty())
                    @php $ult = $data->prorrateos->sortByDesc('id')->first(); @endphp
                    <p>Último prorrateo #{{ $ult->id }} —
                        {{ $ult->aplicado ? 'aplicado' : 'pendiente' }} —
                        {{ optional($ult->calculado_en)->format('d/m/Y H:i') }}</p>
                    <table class="table table-sm table-bordered">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr><th>SKU</th><th>Base</th><th class="text-right">Importe</th><th>Inv.</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($ult->lineas as $lin)
                                <tr>
                                    <td>{{ $lin->sku }}</td>
                                    <td>{{ number_format((float)$lin->base, 4, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float)$lin->importe_asignado, 2, ',', '.') }}</td>
                                    <td>{{ $lin->inventariable ? 'Sí' : 'No' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <div class="card card-outline card-info mt-3">
                    <div class="card-header"><h3 class="card-title">Libro IVA importaciones (cbte 066)</h3></div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="col-md-3">
                                <input type="date" form="form-sync-libro-iva" name="fecha" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-3">
                                <input type="number" step="0.01" form="form-sync-libro-iva" name="importe" class="form-control form-control-sm" placeholder="Importe" required>
                            </div>
                            <div class="col-md-3">
                                <input type="number" step="0.01" form="form-sync-libro-iva" name="iva" class="form-control form-control-sm" placeholder="IVA">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" form="form-sync-libro-iva" class="btn btn-sm btn-outline-primary">Sincronizar</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</form>

@if ($esEdicion)
    @foreach ($data->checklist as $item)
        <form id="form-checklist-{{ $item->id }}" method="POST" action="{{ route('importacion_checklist_marcar', [$data->id, $item->id]) }}" class="d-none">
            @csrf
            <input type="hidden" name="cumplido" value="{{ $item->cumplido ? 0 : 1 }}">
        </form>
    @endforeach
    <form id="form-calcular-costos" method="POST" action="{{ route('importacion_calcular_costos', $data->id) }}" class="d-none">
        @csrf
        <input type="hidden" name="usar_reales" value="1">
    </form>
    <form id="form-cerrar-costos" method="POST" action="{{ route('importacion_cerrar_costos', $data->id) }}" class="d-none">@csrf</form>
    <form id="form-sync-libro-iva" method="POST" action="{{ route('importacion_sync_libro_iva', $data->id) }}" class="d-none">@csrf</form>

    @if (can('nacionalizar-importacion', false))
        <div class="card card-outline card-success mt-3">
            <div class="card-header"><h3 class="card-title">Nacionalizar</h3></div>
            <div class="card-body">
                <form method="POST" action="{{ route('importacion_nacionalizar', $data->id) }}" data-enter-campos="1" class="form-horizontal">
                    @csrf
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="control-label">Nro. despacho</label>
                            <input type="text" name="numerodespacho" class="form-control" required>
                        </div>
                        <div class="col-md-5">
                            @include('configuracion.partials.campo_consulta_pais', [
                                'prefix' => 'nacionalizar',
                                'paisId' => old('pais_id', ''),
                                'codigo' => old('pais_codigo', ''),
                                'nombre' => old('pais_nombre', ''),
                                'inputName' => 'pais_id',
                                'inputId' => 'pais_nacionalizar_id',
                                'required' => true,
                                'col_label' => 'col-lg-3 control-label text-right pr-2',
                                'col_input' => 'col-lg-9',
                            ])
                        </div>
                        <div class="form-group col-md-2">
                            <label class="control-label">Fecha ingreso</label>
                            <input type="date" name="fechaingreso" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="form-group col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-success btn-sm btn-block">Nacionalizar</button>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right pr-2">COM (opcional)</label>
                        <div class="col-lg-6">
                            <div class="d-flex align-items-center" style="gap:4px;">
                                <input type="hidden" name="recepcion_proveedor_id" id="nacionalizar_recepcion_id" value="{{ old('recepcion_proveedor_id', '') }}">
                                <button type="button" class="btn-accion-tabla" id="btn-consulta-com-nacionalizar" title="Buscar recepción COM">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" id="nacionalizar_recepcion_numero" class="form-control" placeholder="N&ordm; recepción"
                                       value="{{ old('recepcion_numero', '') }}" autocomplete="off" style="max-width:10rem;">
                                <input type="text" id="nacionalizar_recepcion_desc" class="form-control" readonly placeholder="Proveedor / fecha"
                                       value="{{ old('recepcion_desc', '') }}">
                            </div>
                            <small class="text-muted">Enter con el n&uacute;mero o lupa. Vac&iacute;o = sin v&iacute;nculo.</small>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endif
