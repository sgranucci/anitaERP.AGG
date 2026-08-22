@php
    use App\Support\Seguridad\IngresoProveedorEstados;
    $data = $data ?? null;
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
    $prefill = $prefill ?? [];
    $proveedor = $data->proveedores ?? ($prefill['proveedor'] ?? null);
@endphp

<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-toggle="tab" href="#tab-datos-principales" role="tab">Datos principales</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-toggle="tab" href="#tab-archivos" role="tab">Archivos asociados</a>
    </li>
</ul>

<div class="tab-content pt-3">
    <div class="tab-pane fade show active" id="tab-datos-principales" role="tabpanel">
        @include('includes.form-empresa-asignada', [
            'empresa_query' => $empresa_query,
            'empresa_id' => old('empresa_id', $data->empresa_id ?? $prefill['empresa_id'] ?? ''),
            'col_label' => 'col-lg-2',
            'col_input' => 'col-lg-4',
        ])

        <div class="row">
            <div class="col-md-6">
                <div class="form-group row">
                    <label for="fecha" class="col-lg-4 control-label text-right pr-2 requerido">Fecha</label>
                    <div class="col-lg-8">
                        <input type="date" name="fecha" id="fecha" class="form-control" required
                               value="{{ old('fecha', optional($data->fecha ?? null)->format('Y-m-d') ?: date('Y-m-d')) }}">
                    </div>
                </div>

                <div class="form-group row">
                    <label class="col-lg-4 control-label text-right pr-2 requerido">Persona(s) que visita(n)</label>
                    <div class="col-lg-8" id="ingreso-personas">
                        @foreach ($personas as $persona)
                            <div class="ingreso-persona-item mb-2">
                                <input type="text" name="persona_nombres[]" class="form-control mb-1" placeholder="Nombre y apellido" value="{{ $persona['nombre'] }}">
                                <input type="text" name="persona_documentos[]" class="form-control" placeholder="Documento (DNI/CUIL)" value="{{ $persona['documento'] }}">
                            </div>
                        @endforeach
                        <button type="button" class="btn btn-outline-primary btn-sm btn-block" id="ingreso-agregar-persona" style="border-style: dashed;">
                            + agregar persona
                        </button>
                    </div>
                </div>

                <div class="form-group row">
                    <label for="punto_id" class="col-lg-4 control-label text-right pr-2 requerido">Sala / Punto de ingreso</label>
                    <div class="col-lg-8">
                        <select name="punto_id" id="punto_id" class="form-control" required>
                            <option value="">-- Seleccionar --</option>
                            @foreach ($puntos as $item)
                                <option value="{{ $item->id }}" {{ (string) old('punto_id', $data->punto_id ?? '') === (string) $item->id ? 'selected' : '' }}>{{ $item->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group row">
                    <label for="area_id" class="col-lg-4 control-label text-right pr-2 requerido">&Aacute;rea de destino</label>
                    <div class="col-lg-8">
                        <select name="area_id" id="area_id" class="form-control" required>
                            <option value="">-- Seleccionar --</option>
                            @foreach ($areas as $item)
                                <option value="{{ $item->id }}" {{ (string) old('area_id', $data->area_id ?? '') === (string) $item->id ? 'selected' : '' }}>{{ $item->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                @php
                    $esVisitanteOld = old('es_visitante');
                    $esVisitante = $esVisitanteOld !== null
                        ? (string) $esVisitanteOld === '1'
                        : \App\Support\Seguridad\IngresoProveedorVisitanteSupport::esVisitante($data);
                @endphp
                <div class="form-group row">
                    <label class="col-lg-4 control-label text-right pr-2">Tipo de visita</label>
                    <div class="col-lg-8">
                        <div class="form-check mt-1">
                            <input type="hidden" name="es_visitante" value="0">
                            <input type="checkbox" name="es_visitante" id="es_visitante" value="1" class="form-check-input"
                                   {{ $esVisitante ? 'checked' : '' }}>
                            <label class="form-check-label" for="es_visitante">
                                No es proveedor (viene a pasar un presupuesto u otra visita puntual)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group row ingreso-campo-proveedor" @if($esVisitante) style="display:none" @endif>
                    <label for="codigoproveedor" class="col-lg-4 control-label text-right pr-2 requerido">Proveedor / Empresa</label>
                    <div class="col-lg-8">
                        <input type="hidden" name="ordencompra_id" id="ordencompra_id" value="{{ old('ordencompra_id', $data->ordencompra_id ?? $prefill['ordencompra_id'] ?? '') }}">
                        <input type="hidden" id="proveedor_id" name="proveedor_id" value="{{ old('proveedor_id', $data->proveedor_id ?? $prefill['proveedor_id'] ?? '') }}">
                        <div class="d-flex flex-wrap align-items-center">
                            <input type="text" class="form-control codigoproveedor mr-2" id="codigoproveedor" name="codigoproveedor"
                                   value="{{ old('codigoproveedor', $proveedor->codigo ?? '') }}" style="width: 6rem;" placeholder="C&oacute;d.">
                            <input type="text" class="form-control mr-2" id="nombreproveedor" name="nombreproveedor"
                                   value="{{ old('nombreproveedor', $proveedor->nombre ?? '') }}" readonly placeholder="Buscar proveedor..." style="min-width: 8rem; flex: 1;">
                            <button type="button" title="Consulta proveedores (F1)" class="btn-accion-tabla consultaproveedor tooltipsC flex-shrink-0">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-group row ingreso-campo-visitante" @unless($esVisitante) style="display:none" @endunless>
                    <label for="visitante_nombre" class="col-lg-4 control-label text-right pr-2 requerido">Qui&eacute;n visita</label>
                    <div class="col-lg-8">
                        <input type="text" name="visitante_nombre" id="visitante_nombre" class="form-control" maxlength="180"
                               value="{{ old('visitante_nombre', $data->visitante_nombre ?? '') }}"
                               placeholder="Empresa, estudio o nombre de quien viene">
                    </div>
                </div>

                <div class="form-group row">
                    <label for="motivo_id" class="col-lg-4 control-label text-right pr-2 requerido">Motivo de visita</label>
                    <div class="col-lg-8">
                        <select name="motivo_id" id="motivo_id" class="form-control" required>
                            <option value="">-- Seleccionar motivo --</option>
                            @foreach ($motivos as $item)
                                <option value="{{ $item->id }}" {{ (string) old('motivo_id', $data->motivo_id ?? '') === (string) $item->id ? 'selected' : '' }}>{{ $item->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group row">
                    <label for="patente" class="col-lg-4 control-label text-right pr-2">Patente de veh&iacute;culo</label>
                    <div class="col-lg-8">
                        <input type="text" name="patente" id="patente" class="form-control" maxlength="20"
                               value="{{ old('patente', $data->patente ?? '') }}" placeholder="Opcional">
                    </div>
                </div>

                <div class="form-group row">
                    <label for="sector_id" class="col-lg-4 control-label text-right pr-2 requerido">Sector</label>
                    <div class="col-lg-8">
                        <select name="sector_id" id="sector_id" class="form-control" required>
                            <option value="">-- Seleccionar sector --</option>
                            @foreach ($sectores as $item)
                                <option value="{{ $item->id }}" {{ (string) old('sector_id', $data->sector_id ?? '') === (string) $item->id ? 'selected' : '' }}>{{ $item->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group row">
                    <label class="col-lg-4 control-label text-right pr-2">Estado del ticket</label>
                    <div class="col-lg-8">
                        <input type="text" class="form-control" readonly value="{{ IngresoProveedorEstados::etiqueta((string) $estado) }}">
                    </div>
                </div>
            </div>
        </div>

        <hr>
        <h5 class="mb-2">Estad&iacute;sticas de ingreso</h5>
        <p class="text-muted small">
            Fecha y hora se completan cuando Seguridad marca el ticket como Ingresado o Finalizado.
            El tiempo en planta es egreso menos ingreso.
        </p>
        <div class="row">
            <div class="col-md-2">
                <label class="small">Fecha ingreso</label>
                <input type="text" class="form-control" readonly value="{{ optional($data->fecha_ingreso ?? null)->format('d/m/Y') }}">
            </div>
            <div class="col-md-2">
                <label class="small">Hora ingreso</label>
                <input type="text" class="form-control" readonly value="{{ $data->hora_ingreso ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="small">Fecha egreso</label>
                <input type="text" class="form-control" readonly value="{{ optional($data->fecha_egreso ?? null)->format('d/m/Y') }}">
            </div>
            <div class="col-md-2">
                <label class="small">Hora egreso</label>
                <input type="text" class="form-control" readonly value="{{ $data->hora_egreso ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="small">Tiempo en planta</label>
                <div class="input-group">
                    <input type="text" class="form-control" readonly value="{{ $data->minutos_en_planta ?? '' }}">
                    <div class="input-group-append"><span class="input-group-text">min</span></div>
                </div>
            </div>
        </div>

        <div class="form-group row mt-3">
            <label for="titulo" class="col-lg-2 control-label text-right pr-2">T&iacute;tulo</label>
            <div class="col-lg-10">
                <input type="text" name="titulo" id="titulo" class="form-control" maxlength="180"
                       value="{{ old('titulo', $data->titulo ?? '') }}" placeholder="Resumen breve del motivo de la visita">
            </div>
        </div>
        <div class="form-group row">
            <label for="comentario" class="col-lg-2 control-label text-right pr-2">Comentario</label>
            <div class="col-lg-10">
                <textarea name="comentario" id="comentario" class="form-control" rows="3">{{ old('comentario', $data->comentario ?? '') }}</textarea>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-archivos" role="tabpanel">
        @include('seguridad.ingreso_proveedor.partials.solapa_archivos')
    </div>
</div>

<template id="ingreso-template-persona">
    <div class="ingreso-persona-item mb-2">
        <input type="text" name="persona_nombres[]" class="form-control mb-1" placeholder="Nombre y apellido" value="">
        <input type="text" name="persona_documentos[]" class="form-control" placeholder="Documento (DNI/CUIL)" value="">
    </div>
</template>
