@php
    use App\Models\Stock\Prestamo;
    $modoVer = ! ($modoEdicion ?? true);
    $tipo = old('tipo', isset($prestamo) ? ($prestamo->tipo ?? Prestamo::TIPO_PRESTAMO) : Prestamo::TIPO_PRESTAMO);
    $destTipo = old('destinatario_tipo', isset($prestamo) ? ($prestamo->destinatario_tipo ?? Prestamo::DEST_DEPOSITO) : Prestamo::DEST_DEPOSITO);
    $espera = old('espera_devolucion', isset($prestamo) ? (bool) ($prestamo->espera_devolucion ?? true) : true);
    $prioridad = old('prioridad', isset($prestamo) ? ($prestamo->prioridad ?? Prestamo::PRIORIDAD_NORMAL) : Prestamo::PRIORIDAD_NORMAL);

    $depOrigenId = old('deposito_origen_id', isset($prestamo) ? $prestamo->deposito_origen_id : '');
    $depDestinoId = old('deposito_destino_id', isset($prestamo) ? $prestamo->deposito_destino_id : '');
    $depOrigenModel = (int) $depOrigenId > 0
        ? (isset($prestamo) && (int) ($prestamo->deposito_origen_id ?? 0) === (int) $depOrigenId
            ? $prestamo->depositoOrigen
            : \App\Models\Stock\Depmae::find((int) $depOrigenId))
        : null;
    $depDestinoModel = (int) $depDestinoId > 0
        ? (isset($prestamo) && (int) ($prestamo->deposito_destino_id ?? 0) === (int) $depDestinoId
            ? $prestamo->depositoDestino
            : \App\Models\Stock\Depmae::find((int) $depDestinoId))
        : null;

    $destUsuarioId = old('destinatario_usuario_id', isset($prestamo) ? $prestamo->destinatario_usuario_id : '');
    $destUsuarioNombre = old('destinatario_usuario_nombre', isset($prestamo) ? optional($prestamo->destinatarioUsuario)->nombre : '');

    $items = old('items');
    if ($items === null && isset($prestamo)) {
        $items = $prestamo->items->map(fn ($i) => [
            'articulo_id' => $i->articulo_id,
            'sku' => optional($i->articulos)->sku,
            'descripcion' => $i->descripcion ?: optional($i->articulos)->descripcion,
            'nro_serie' => $i->nro_serie,
            'condicion_salida' => $i->condicion_salida,
            'cantidad' => $i->cantidad,
            'observaciones' => $i->observaciones,
        ])->all();
    }
    if (empty($items)) {
        $items = [['articulo_id' => '', 'sku' => '', 'descripcion' => '', 'nro_serie' => '', 'condicion_salida' => '', 'cantidad' => '', 'observaciones' => '']];
    }

    $saldosOrigenJson = json_encode($saldosOrigen ?? [], JSON_UNESCAPED_UNICODE);
    $saldosDestinoJson = json_encode($saldosDestino ?? [], JSON_UNESCAPED_UNICODE);
    $condiciones = [
        Prestamo::CONDICION_BUENO => 'Bueno',
        Prestamo::CONDICION_REGULAR => 'Regular',
        Prestamo::CONDICION_DANADO => 'Dañado',
    ];
@endphp
<div class="row">
    <div class="col-md-12">
        @include('includes.form-empresa-asignada', [
            'empresa_query' => $empresa_query,
            'empresa_id' => $empresa_id ?? null,
            'col_label' => 'col-lg-2',
            'col_input' => 'col-lg-4',
        ])
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label class="requerido">Tipo</label>
            <select name="tipo" id="sb_tipo" class="form-control" required>
                <option value="PRESTAMO" @if ($tipo === 'PRESTAMO') selected @endif>Préstamo</option>
                <option value="REPARACION" @if ($tipo === 'REPARACION') selected @endif>Reparación</option>
                <option value="ENTREGA" @if ($tipo === 'ENTREGA') selected @endif>Entrega / cesión</option>
            </select>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label class="requerido">Fecha salida</label>
            <input type="date" name="fecha_prestamo" class="form-control"
                value="{{ old('fecha_prestamo', isset($prestamo) ? optional($prestamo->fecha_prestamo)->format('Y-m-d') : date('Y-m-d')) }}" required>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label id="sb_label_fecha_dev">Fecha prometida devolución</label>
            <input type="date" name="fecha_devolucion_prometida" id="sb_fecha_devolucion" class="form-control"
                value="{{ old('fecha_devolucion_prometida', isset($prestamo) ? optional($prestamo->fecha_devolucion_prometida)->format('Y-m-d') : '') }}">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Prioridad</label>
            <select name="prioridad" class="form-control">
                <option value="BAJA" @if ($prioridad === 'BAJA') selected @endif>Baja</option>
                <option value="NORMAL" @if ($prioridad === 'NORMAL') selected @endif>Normal</option>
                <option value="ALTA" @if ($prioridad === 'ALTA') selected @endif>Alta</option>
            </select>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label class="requerido">Destinatario</label>
            <select name="destinatario_tipo" id="sb_destinatario_tipo" class="form-control" required>
                <option value="DEPOSITO" @if ($destTipo === 'DEPOSITO') selected @endif>Depósito</option>
                <option value="USUARIO" @if ($destTipo === 'USUARIO') selected @endif>Usuario interno</option>
                <option value="EXTERNO" @if ($destTipo === 'EXTERNO') selected @endif>Externo (taller / persona)</option>
            </select>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group form-check mt-4">
            <input type="hidden" name="espera_devolucion" value="0">
            <input type="checkbox" class="form-check-input" name="espera_devolucion" id="sb_espera_devolucion" value="1"
                @if ($espera) checked @endif>
            <label class="form-check-label" for="sb_espera_devolucion">Espera devolución</label>
        </div>
    </div>
    <div class="col-md-6">
        @include('stock.partials.campo_consulta_deposito', [
            'prefix' => 'prestamo_origen',
            'layout' => 'inline',
            'label' => 'Depósito origen',
            'inputName' => 'deposito_origen_id',
            'inputId' => 'prestamo_deposito_origen_id',
            'depositoId' => $depOrigenId,
            'codigo' => old('deposito_origen_codigo', optional($depOrigenModel)->codigo ?? ''),
            'descripcion' => old('deposito_origen_descripcion', optional($depOrigenModel)->nombre ?? ''),
            'required' => true,
        ])
    </div>
</div>

<div class="row sb-panel-destino" id="sb_panel_deposito" @if ($destTipo !== 'DEPOSITO') style="display:none;" @endif>
    <div class="col-md-6">
        @include('stock.partials.campo_consulta_deposito', [
            'prefix' => 'prestamo_destino',
            'layout' => 'inline',
            'label' => 'Depósito destino',
            'inputName' => 'deposito_destino_id',
            'inputId' => 'prestamo_deposito_destino_id',
            'depositoId' => $depDestinoId,
            'codigo' => old('deposito_destino_codigo', optional($depDestinoModel)->codigo ?? ''),
            'descripcion' => old('deposito_destino_descripcion', optional($depDestinoModel)->nombre ?? ''),
            'required' => false,
        ])
    </div>
</div>

<div class="row sb-panel-destino" id="sb_panel_usuario" @if ($destTipo !== 'USUARIO') style="display:none;" @endif>
    <div class="col-md-8">
        <div class="form-group">
            <label class="requerido">Usuario destinatario</label>
            <div class="d-flex flex-nowrap align-items-center tm-usuario-campo" style="gap: 4px;">
                <input type="text" name="destinatario_usuario_id" id="destinatario_usuario_id"
                    class="usuario_id form-control flex-shrink-0" style="width: 4.5rem;"
                    value="{{ $destUsuarioId }}" placeholder="ID" autocomplete="off" inputmode="numeric">
                <button type="button" title="Consulta usuarios (F1)"
                    class="btn-accion-tabla consultausuario tooltipsC flex-shrink-0"
                    data-ptrusuario_id="#destinatario_usuario_id"
                    data-ptrnombre="#sb_destinatario_usuario_nombre">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="nombreusuario form-control" id="sb_destinatario_usuario_nombre"
                    value="{{ $destUsuarioNombre }}" placeholder="Nombre" readonly>
            </div>
        </div>
    </div>
</div>

<div class="row sb-panel-destino" id="sb_panel_externo" @if ($destTipo !== 'EXTERNO') style="display:none;" @endif>
    <div class="col-md-4">
        <div class="form-group">
            <label class="requerido">Nombre</label>
            <input type="text" name="externo_nombre" class="form-control" maxlength="180"
                value="{{ old('externo_nombre', $prestamo->externo_nombre ?? '') }}">
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label>Documento</label>
            <input type="text" name="externo_documento" class="form-control" maxlength="40"
                value="{{ old('externo_documento', $prestamo->externo_documento ?? '') }}">
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label>Teléfono</label>
            <input type="text" name="externo_telefono" class="form-control" maxlength="60"
                value="{{ old('externo_telefono', $prestamo->externo_telefono ?? '') }}">
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="externo_email" class="form-control" maxlength="120"
                value="{{ old('externo_email', $prestamo->externo_email ?? '') }}">
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label>Empresa / taller</label>
            <input type="text" name="externo_empresa" class="form-control" maxlength="180"
                value="{{ old('externo_empresa', $prestamo->externo_empresa ?? '') }}">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            <label>Observaciones</label>
            <textarea name="observaciones" class="form-control" rows="2">{{ old('observaciones', $prestamo->observaciones ?? '') }}</textarea>
        </div>
    </div>
</div>

<div class="card card-outline card-info mt-3">
    <div class="card-header">
        <h4 class="card-title"><i class="fa fa-cubes"></i> Ítems</h4>
        <div class="card-tools">
            <span class="badge badge-info">Origen: <span id="badge-deposito-origen">—</span></span>
            <span class="badge badge-light ml-2">Destino: <span id="badge-deposito-destino">—</span></span>
        </div>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-sm table-bordered table-hover" id="tabla-prestamo-items">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th style="width:10%">Artículo</th>
                    <th style="width:20%">Descripción</th>
                    <th style="width:10%">Nº serie / ID</th>
                    <th style="width:10%">Condición</th>
                    <th style="width:8%">Cantidad</th>
                    <th style="width:8%">Saldo orig.</th>
                    <th style="width:8%">Saldo dest.</th>
                    <th style="width:16%">Observaciones</th>
                    <th style="width:4%"></th>
                </tr>
            </thead>
            <tbody id="tbody-prestamo-items">
                @foreach ($items as $idx => $item)
                    <tr class="prestamo-item-row">
                        <td>
                            <input type="hidden" class="articulo_id" name="items[{{ $idx }}][articulo_id]"
                                value="{{ old('items.'.$idx.'.articulo_id', $item['articulo_id'] ?? '') }}">
                            <div class="d-flex align-items-center flex-nowrap">
                                <button type="button" title="Consulta artículos" class="btn-accion-tabla consultaarticulo tooltipsC flex-shrink-0">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" class="codigoarticulo form-control form-control-sm ml-1"
                                    style="width:100px; min-width:100px;"
                                    value="{{ old('items.'.$idx.'.sku', $item['sku'] ?? '') }}" autocomplete="off"
                                    placeholder="opcional">
                            </div>
                        </td>
                        <td>
                            <input type="text" class="descripcionarticulo form-control form-control-sm"
                                name="items[{{ $idx }}][descripcion]"
                                value="{{ old('items.'.$idx.'.descripcion', $item['descripcion'] ?? '') }}"
                                placeholder="Obligatorio sin artículo" maxlength="255">
                        </td>
                        <td>
                            <input type="text" name="items[{{ $idx }}][nro_serie]" class="form-control form-control-sm"
                                value="{{ old('items.'.$idx.'.nro_serie', $item['nro_serie'] ?? '') }}" maxlength="80">
                        </td>
                        <td>
                            <select name="items[{{ $idx }}][condicion_salida]" class="form-control form-control-sm">
                                <option value="">—</option>
                                @foreach ($condiciones as $cod => $lab)
                                    <option value="{{ $cod }}" @if (old('items.'.$idx.'.condicion_salida', $item['condicion_salida'] ?? '') === $cod) selected @endif>{{ $lab }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="number" step="0.000001" min="0.000001"
                                name="items[{{ $idx }}][cantidad]"
                                class="form-control form-control-sm input-cantidad"
                                value="{{ old('items.'.$idx.'.cantidad', $item['cantidad'] ?? '') }}" required>
                        </td>
                        <td><span class="saldo-origen text-monospace">—</span></td>
                        <td><span class="saldo-destino text-monospace">—</span></td>
                        <td>
                            <input type="text" name="items[{{ $idx }}][observaciones]" class="form-control form-control-sm"
                                value="{{ old('items.'.$idx.'.observaciones', $item['observaciones'] ?? '') }}" maxlength="255">
                        </td>
                        <td>
                            <button type="button" class="btn btn-link text-danger btn-eliminar-item" title="Eliminar">
                                <i class="fa fa-times-circle"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <button type="button" id="btn-agregar-item" class="btn btn-outline-primary btn-sm">
            <i class="fa fa-plus"></i> Agregar ítem
        </button>
        <small class="text-muted ml-2">Sin artículo = solo documental (estadísticas / avisos). Con artículo = mueve stock.</small>
    </div>
</div>

<input type="hidden" id="prestamo-saldos-origen" value='{!! $saldosOrigenJson !!}'>
<input type="hidden" id="prestamo-saldos-destino" value='{!! $saldosDestinoJson !!}'>
<input type="hidden" id="prestamo-saldo-articulo-url" value="{{ route('salida_bienes_saldo_articulo') }}">

<template id="template-prestamo-item-row">
    <tr class="prestamo-item-row">
        <td>
            <input type="hidden" class="articulo_id" name="items[0][articulo_id]" value="">
            <div class="d-flex align-items-center flex-nowrap">
                <button type="button" title="Consulta artículos" class="btn-accion-tabla consultaarticulo tooltipsC flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="codigoarticulo form-control form-control-sm ml-1"
                    style="width:100px; min-width:100px;" autocomplete="off" placeholder="opcional">
            </div>
        </td>
        <td>
            <input type="text" class="descripcionarticulo form-control form-control-sm"
                name="items[0][descripcion]" placeholder="Obligatorio sin artículo" maxlength="255">
        </td>
        <td><input type="text" name="items[0][nro_serie]" class="form-control form-control-sm" maxlength="80"></td>
        <td>
            <select name="items[0][condicion_salida]" class="form-control form-control-sm">
                <option value="">—</option>
                <option value="BUENO">Bueno</option>
                <option value="REGULAR">Regular</option>
                <option value="DANADO">Dañado</option>
            </select>
        </td>
        <td>
            <input type="number" step="0.000001" min="0.000001" name="items[0][cantidad]"
                class="form-control form-control-sm input-cantidad" required>
        </td>
        <td><span class="saldo-origen text-monospace">—</span></td>
        <td><span class="saldo-destino text-monospace">—</span></td>
        <td><input type="text" name="items[0][observaciones]" class="form-control form-control-sm" maxlength="255"></td>
        <td>
            <button type="button" class="btn btn-link text-danger btn-eliminar-item" title="Eliminar">
                <i class="fa fa-times-circle"></i>
            </button>
        </td>
    </tr>
</template>
