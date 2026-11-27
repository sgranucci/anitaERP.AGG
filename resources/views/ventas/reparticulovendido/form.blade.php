<div class="form-group row">
    <label for="tipoOrigen" class="col-lg-3 col-form-label requerido">Origen del artículo</label>
    <select name="tipoOrigen" id="tipoOrigen" data-placeholder="Origen" class="col-lg-5 form-control required" data-fouc required>
        <option value="">-- Seleccionar origen --</option>
        @foreach($tipoOrigen_enum as $key => $value)
            @if($key == 'IMPORTADO')
                <option value="{{ $key }}" selected="select">{{ $value }}</option>
            @else
                <option value="{{ $key }}">{{ $value }}</option>
            @endif
        @endforeach
    </select>
</div>
<div class="form-group row">
    <label for="desdefecha" class="col-lg-3 col-form-label requerido">Desde fecha</label>
    <div class="col-lg-4">
        <input type="date" name="desdefecha" id="desdefecha" class="form-control" value="{{substr(old('desdefecha', date('Y-m-d')),0,10)}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="hastafecha" class="col-lg-3 col-form-label requerido">Hasta fecha</label>
    <div class="col-lg-4">
        <input type="date" name="hastafecha" id="hastafecha" class="form-control" value="{{substr(old('hastafecha', date('Y-m-d')),0,10)}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="mventa_id" class="col-lg-3 col-form-label requerido">Marca</label>
    <select name="mventa_id" id="mventa_id" data-placeholder="Marca de Venta" class="col-lg-3 form-control required" data-fouc required>
        <option value="">-- Seleccionar marca --</option>
        @foreach($mventa_query as $key => $value)
            @if((int) $value->id == 0)
                <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>
            @else
                <option value="{{ $value->id }}">{{ $value->nombre }}</option>
            @endif
        @endforeach
    </select>
</div>
@include('ventas.partials.campo_consulta_puntoventa', [
    'prefix' => 'rep_articulovendido',
    'layout' => 'form_row',
    'label' => 'Sucursal',
    'inputName' => 'puntoventa_id',
    'inputId' => 'puntoventa_id',
    'puntoventaId' => old('puntoventa_id', ''),
    'codigo' => old('puntoventa_codigo', ''),
    'nombre' => old('puntoventa_nombre', ''),
    'required' => false,
    'col_label' => 'col-lg-3 col-form-label',
    'col_input' => 'col-lg-5',
])
@php
    $puntosventaExtraOld = \App\Models\Ventas\Puntoventa::query()
        ->whereIn('id', \App\Support\Ventas\IvaVentasListadoFiltros::normalizarPuntoventaIds(old('puntoventa_ids', [])))
        ->orderBy('codigo')
        ->get(['id', 'codigo', 'nombre']);
@endphp
<div class="form-group row mb-1">
    <div class="col-lg-3 d-none d-lg-block"></div>
    <div class="col-lg-5">
        <div id="rep-articulovendido-sucursales-extra">
            @foreach ($puntosventaExtraOld as $pvExtra)
                <div class="d-flex align-items-center mb-1 rep-articulovendido-sucursal-extra">
                    @include('ventas.partials.campo_consulta_puntoventa', [
                        'prefix' => 'rep_articulovendido_extra_'.$pvExtra->id,
                        'layout' => 'inline',
                        'label' => '',
                        'inputName' => 'puntoventa_ids[]',
                        'inputId' => 'puntoventa_extra_'.$pvExtra->id,
                        'puntoventaId' => (int) $pvExtra->id,
                        'codigo' => $pvExtra->codigo ?? '',
                        'nombre' => $pvExtra->nombre ?? '',
                        'required' => false,
                        'mostrar_editar' => false,
                    ])
                    <button type="button" class="btn btn-sm btn-link text-danger quitar-sucursal-articulovendido" title="Quitar sucursal">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            @endforeach
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm mt-1" id="agregar-sucursal-articulovendido">
            <i class="fa fa-plus"></i> Agregar sucursal
        </button>
        <small class="form-text text-muted mt-1">Vac&iacute;o = todas las sucursales. C&oacute;digo + Enter o lupa (F1). Pod&eacute;s sumar las que quieras con Agregar sucursal.</small>
    </div>
</div>
<template id="tpl-sucursal-articulovendido">
    <div class="d-flex align-items-center mb-1 rep-articulovendido-sucursal-extra">
        @include('ventas.partials.campo_consulta_puntoventa', [
            'prefix' => 'rep_articulovendido_extra_NUEVA',
            'layout' => 'inline',
            'label' => '',
            'inputName' => 'puntoventa_ids[]',
            'inputId' => 'puntoventa_extra_NUEVA',
            'puntoventaId' => '',
            'codigo' => '',
            'nombre' => '',
            'required' => false,
            'mostrar_editar' => false,
        ])
        <button type="button" class="btn btn-sm btn-link text-danger quitar-sucursal-articulovendido" title="Quitar sucursal">
            <i class="fa fa-times"></i>
        </button>
    </div>
</template>
<div class="row">
    <div class="col-sm-6">
        <div class="form-group row">
            <label for="desdecliente_id" class="col-lg-3 col-form-label">Desde cliente</label>
            <select name="desdecliente_id" id="desdecliente_id" data-placeholder="cliente" class="col-lg-4 form-control" data-fouc>
                <option value="">-- Seleccionar cliente --</option>
                @foreach($cliente_query as $key => $value)
                    @if((int) $value->id == 0)
                        <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>
                    @else
                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>
                    @endif
                @endforeach
            </select>
        </div>
        <div class="form-group row">
            <label for="hastacliente_id" class="col-lg-3 col-form-label">Hasta cliente</label>
            <select name="hastacliente_id" id="hastacliente_id" data-placeholder="cliente" class="col-lg-4 form-control" data-fouc>
                <option value="">-- Seleccionar cliente --</option>
                @foreach($cliente_query as $key => $value)
                    @if((int) $value->id == 99999999)
                        <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>
                    @else
                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>
                    @endif
                @endforeach
            </select>
        </div>
        <div class="form-group row">
            <label for="desdearticulo_id" class="col-lg-3 col-form-label">Desde art&iacute;culo</label>
            <select name="desdearticulo_id" id="desdearticulo_id" data-placeholder="articulo" class="col-lg-4 form-control" data-fouc>
                <option value="">-- Seleccionar art&iacute;culo --</option>
                @foreach($articulo_query as $key => $value)
                    @if((int) $value->id == 0)
                        <option value="{{ $value->id }}" selected="select">{{ $value->descripcion }}</option>
                    @else
                        <option value="{{ $value->id }}">{{ $value->descripcion }}</option>
                    @endif
                @endforeach
            </select>
        </div>
        <div class="form-group row">
            <label for="hastaarticulo_id" class="col-lg-3 col-form-label">Hasta art&iacute;culo</label>
            <select name="hastaarticulo_id" id="hastaarticulo_id" data-placeholder="articulo" class="col-lg-4 form-control" data-fouc>
                <option value="">-- Seleccionar art&iacute;culo --</option>
                @foreach($articulo_query as $key => $value)
                    @if((int) $value->id == 99999999)
                        <option value="{{ $value->id }}" selected="select">{{ $value->descripcion }}</option>
                    @else
                        <option value="{{ $value->id }}">{{ $value->descripcion }}</option>
                    @endif
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group row">
            <label for="desdelinea_id" class="col-lg-3 col-form-label">Desde l&iacute;nea</label>
            <select name="desdelinea_id" id="desdelinea_id" data-placeholder="linea" class="col-lg-4 form-control" data-fouc>
                <option value="">-- Seleccionar l&iacute;nea --</option>
                @foreach($linea_query as $key => $value)
                    @if((int) $value->id == 0)
                        <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>
                    @else
                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>
                    @endif
                @endforeach
            </select>
        </div>
        <div class="form-group row">
            <label for="hastalinea_id" class="col-lg-3 col-form-label">Hasta l&iacute;nea</label>
            <select name="hastalinea_id" id="hastalinea_id" data-placeholder="linea" class="col-lg-4 form-control" data-fouc>
                <option value="">-- Seleccionar l&iacute;nea --</option>
                @foreach($linea_query as $key => $value)
                    @if((int) $value->id == 99999999)
                        <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>
                    @else
                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>
                    @endif
                @endforeach
            </select>
        </div>
    </div>
</div>
