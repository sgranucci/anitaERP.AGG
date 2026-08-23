@php
    use App\Support\Seguridad\IngresoProveedorEstados;
    $data = $data ?? new \App\Models\Seguridad\IngresoProveedor();
    $prefill = $prefill ?? [];
    $proveedor = $data?->proveedores ?? ($prefill['proveedor'] ?? null);
    $proveedorBloqueado = $proveedorBloqueado ?? (int) ($prefill['proveedor_id'] ?? 0) > 0;
    $personasOld = old('persona_nombres');
    $personas = [];
    if (is_array($personasOld)) {
        foreach ($personasOld as $i => $nombre) {
            $personas[] = [
                'nombre' => $nombre,
                'documento' => old('persona_documentos.'.$i, ''),
            ];
        }
    } elseif ($data && $data->personas) {
        foreach ($data->personas as $p) {
            $personas[] = ['nombre' => $p->nombre, 'documento' => $p->documento];
        }
    }
    if ($personas === []) {
        $personas[] = ['nombre' => '', 'documento' => ''];
    }
    $estado = old('estado', $data->estado ?? IngresoProveedorEstados::PENDIENTE);
    $empresaId = old('empresa_id', $data->empresa_id ?? $prefill['empresa_id'] ?? '');
    $action = $data && $data->id
        ? route('actualizar_ingreso_proveedor', $data->id)
        : route('guardar_ingreso_proveedor');
@endphp
<form action="{{ $action }}" id="form-ingreso-proveedor-modal" class="form-horizontal" method="POST" autocomplete="off" enctype="multipart/form-data">
    @csrf
    @if ($data && $data->id)
        @method('PUT')
    @endif
    <input type="hidden" name="origen" value="modal_vinculo">
    <input type="hidden" name="ordencompra_id" id="ingreso-modal-ordencompra_id" value="{{ old('ordencompra_id', $data->ordencompra_id ?? $prefill['ordencompra_id'] ?? '') }}">
    <input type="hidden" name="proveedor_id" id="ingreso-modal-proveedor_id" value="{{ old('proveedor_id', $data->proveedor_id ?? $prefill['proveedor_id'] ?? '') }}">
    <input type="hidden" name="es_visitante" value="0">

    <div id="ingreso-modal-errores" class="alert alert-danger d-none"></div>

    @include('includes.form-empresa-asignada', [
        'empresa_query' => $empresa_query,
        'empresa_id' => $empresaId,
        'id' => 'ingreso-modal-empresa_id',
        'name' => 'empresa_id',
        'col_label' => 'col-lg-3',
        'col_input' => 'col-lg-6',
        'solo_lectura' => $proveedorBloqueado && (int) $empresaId > 0,
    ])

    <div class="form-group row">
        <label class="col-lg-3 control-label text-right pr-2">Proveedor</label>
        <div class="col-lg-6">
            <input type="text" class="form-control" readonly
                   value="{{ trim(($proveedor->codigo ?? '').' '.($proveedor->nombre ?? '')) ?: '—' }}">
        </div>
    </div>

    @include('seguridad.ingreso_proveedor.partials.datos_ordencompra', [
        'ordencompra' => $data?->ordencompras ?? ($prefill['ordencompra'] ?? null),
        'col_label' => 'col-lg-3',
        'col_input' => 'col-lg-6',
    ])

    <div class="form-group row">
        <label for="ingreso-modal-fecha" class="col-lg-3 control-label text-right pr-2 requerido">Fecha de carga</label>
        <div class="col-lg-3">
            <input type="date" id="ingreso-modal-fecha" class="form-control" readonly
                   value="{{ optional($data->fecha ?? null)->format('Y-m-d') ?: date('Y-m-d') }}">
        </div>
        <label class="col-lg-2 control-label text-right pr-2">Estado</label>
        <div class="col-lg-2">
            <input type="text" class="form-control" readonly value="{{ IngresoProveedorEstados::etiqueta((string) $estado) }}">
        </div>
    </div>

    <div class="form-group row">
        <label class="col-lg-3 control-label text-right pr-2 requerido">Persona(s) que visita(n)</label>
        <div class="col-lg-6" id="ingreso-modal-personas">
            @foreach ($personas as $persona)
                <div class="ingreso-persona-item mb-2">
                    <input type="text" name="persona_nombres[]" class="form-control mb-1" placeholder="Nombre y apellido" value="{{ $persona['nombre'] }}">
                    <input type="text" name="persona_documentos[]" class="form-control" placeholder="Documento (DNI/CUIL)" value="{{ $persona['documento'] }}">
                </div>
            @endforeach
            <button type="button" class="btn btn-outline-primary btn-sm btn-block js-ingreso-modal-agregar-persona" style="border-style: dashed;">
                + agregar persona
            </button>
        </div>
    </div>

    <div class="form-group row">
        <label for="ingreso-modal-motivo_id" class="col-lg-3 control-label text-right pr-2 requerido">Motivo de visita</label>
        <div class="col-lg-6">
            <select name="motivo_id" id="ingreso-modal-motivo_id" class="form-control" required>
                <option value="">-- Seleccionar motivo --</option>
                @foreach ($motivos as $item)
                    <option value="{{ $item->id }}" data-codigo="{{ $item->codigo }}" @selected((string) old('motivo_id', $data->motivo_id ?? '') === (string) $item->id)>{{ $item->nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-group row">
        <label for="ingreso-modal-punto_id" class="col-lg-3 control-label text-right pr-2 requerido">Sala / Punto de ingreso</label>
        <div class="col-lg-6">
            <select name="punto_id" id="ingreso-modal-punto_id" class="form-control" required>
                <option value="">-- Seleccionar --</option>
                @foreach ($puntos as $item)
                    <option value="{{ $item->id }}" @selected((string) old('punto_id', $data->punto_id ?? '') === (string) $item->id)>{{ $item->nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-group row">
        <label for="ingreso-modal-area_id" class="col-lg-3 control-label text-right pr-2 requerido">&Aacute;rea de destino</label>
        <div class="col-lg-6">
            <select name="area_id" id="ingreso-modal-area_id" class="form-control" required>
                <option value="">-- Seleccionar --</option>
                @foreach ($areas as $item)
                    <option value="{{ $item->id }}" @selected((string) old('area_id', $data->area_id ?? '') === (string) $item->id)>{{ $item->nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-group row">
        <label for="ingreso-modal-sector_id" class="col-lg-3 control-label text-right pr-2 requerido">Sector</label>
        <div class="col-lg-6">
            <select name="sector_id" id="ingreso-modal-sector_id" class="form-control" required>
                <option value="">-- Seleccionar sector --</option>
                @foreach ($sectores as $item)
                    <option value="{{ $item->id }}" @selected((string) old('sector_id', $data->sector_id ?? '') === (string) $item->id)>{{ $item->nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-group row">
        <label for="ingreso-modal-patente" class="col-lg-3 control-label text-right pr-2">Patente de veh&iacute;culo</label>
        <div class="col-lg-3">
            <input type="text" name="patente" id="ingreso-modal-patente" class="form-control" maxlength="20"
                   value="{{ old('patente', $data->patente ?? '') }}" placeholder="Opcional">
        </div>
    </div>

    <div class="form-group row ingreso-campo-motivo-otro" style="display:none">
        <label for="ingreso-modal-motivo_otro" class="col-lg-3 control-label text-right pr-2 requerido">Otro motivo</label>
        <div class="col-lg-6">
            <input type="text" name="motivo_otro" id="ingreso-modal-motivo_otro" class="form-control" maxlength="180"
                   value="{{ old('motivo_otro', $data->motivo_otro ?? '') }}">
        </div>
    </div>
    <div class="form-group row">
        <label for="ingreso-modal-fecha_prevista" class="col-lg-3 control-label text-right pr-2">Fecha prevista</label>
        <div class="col-lg-3">
            <input type="date" name="fecha_prevista" id="ingreso-modal-fecha_prevista" class="form-control"
                   value="{{ old('fecha_prevista', optional($data->fecha_prevista ?? null)->format('Y-m-d')) }}">
        </div>
    </div>
    <div class="form-group row">
        <label for="ingreso-modal-titulo" class="col-lg-3 control-label text-right pr-2 requerido">T&iacute;tulo</label>
        <div class="col-lg-7">
            <input type="text" name="titulo" id="ingreso-modal-titulo" class="form-control" maxlength="180" required
                   value="{{ old('titulo', $data->titulo ?? '') }}" placeholder="Resumen breve del motivo de la visita">
        </div>
    </div>
    <div class="form-group row">
        <label for="ingreso-modal-comentario" class="col-lg-3 control-label text-right pr-2">Comentario</label>
        <div class="col-lg-7">
            <textarea name="comentario" id="ingreso-modal-comentario" class="form-control" rows="2">{{ old('comentario', $data->comentario ?? '') }}</textarea>
        </div>
    </div>

    @include('seguridad.ingreso_proveedor.partials.movimiento_planta', ['data' => $data])

    <div class="card card-outline card-primary mb-3">
        <div class="card-header py-2">
            <h5 class="mb-0"><i class="fa fa-paperclip"></i> Archivos (ART, seguro, etc.)</h5>
        </div>
        <div class="card-body py-3">
            <p class="text-muted small mb-2">
                Un archivo por rengl&oacute;n (ART, seguro de vida, DNI, etc.).
                Use <strong>+ Agrega rengl&oacute;n</strong> para sumar m&aacute;s.
            </p>
            @if ($data && $data->id && ($data->archivos?->count() ?? 0) > 0)
                <div class="mb-3">
                    @include('seguridad.ingreso_proveedor.partials.archivos_adjuntos', [
                        'data' => $data,
                        'ocultarInputsConservar' => false,
                    ])
                </div>
            @endif
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-2" id="ingreso-modal-archivo-table">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Archivo nuevo</th>
                            <th style="width: 90px;" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="ingreso-modal-tbody-archivo">
                        <tr class="item-archivo-ingreso">
                            <td>
                                <input type="file" name="nombrearchivos[]" class="form-control ingreso-nombrearchivos">
                            </td>
                            <td class="text-center align-middle">
                                <button type="button" title="Quitar este rengl&oacute;n" class="btn-accion-tabla js-ingreso-modal-eliminar-archivo tooltipsC">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="text-right">
                <button type="button" class="btn btn-outline-primary btn-sm js-ingreso-modal-agrega-archivo">
                    <i class="fa fa-plus"></i> Agrega rengl&oacute;n
                </button>
            </div>
        </div>
    </div>

    <div class="text-right border-top pt-3">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
        @if ($data && $data->id)
            @if (can('actualizar-ingreso-proveedor', false))
                <button type="submit" class="btn btn-success">Actualizar</button>
            @endif
        @else
            <button type="submit" class="btn btn-primary">Guardar ticket</button>
        @endif
    </div>
</form>
@include('seguridad.ingreso_proveedor.partials.acciones_seguridad', ['ticket' => $data])
<template id="ingreso-modal-template-persona">
    <div class="ingreso-persona-item mb-2">
        <input type="text" name="persona_nombres[]" class="form-control mb-1" placeholder="Nombre y apellido" value="">
        <input type="text" name="persona_documentos[]" class="form-control" placeholder="Documento (DNI/CUIL)" value="">
    </div>
</template>
<template id="ingreso-modal-template-archivo">
    <tr class="item-archivo-ingreso">
        <td>
            <input type="file" name="nombrearchivos[]" class="form-control ingreso-nombrearchivos">
        </td>
        <td class="text-center align-middle">
            <button type="button" title="Quitar este rengl&oacute;n" class="btn-accion-tabla js-ingreso-modal-eliminar-archivo tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
