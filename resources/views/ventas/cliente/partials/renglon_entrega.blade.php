@php
    $idxEntrega = (int) ($idx ?? 0);
    $entrega = $entrega ?? null;
    $esTemplate = (bool) ($esTemplate ?? false);

    $nombreEntrega = $esTemplate ? '' : (old('nombres.'.$idxEntrega) ?? optional($entrega)->nombre ?? '');
    $domicilioEntrega = $esTemplate ? '' : (old('domicilios.'.$idxEntrega) ?? optional($entrega)->domicilio ?? '');
    $cpEntrega = $esTemplate ? '' : old('codigospostales.'.$idxEntrega, optional($entrega)->codigopostal ?? '');

    $provEntregaId = $esTemplate ? '' : old('provincias_id.'.$idxEntrega, optional($entrega)->provincia_id ?? '');
    $provEntrega = optional($entrega)->provincias
        ?? collect($provincia_query ?? [])->firstWhere('id', (int) $provEntregaId);

    $iibbEntregaId = $esTemplate ? '' : old('provincias_iibb_id.'.$idxEntrega, optional($entrega)->provincia_iibb_id ?? '');
    $iibbEntrega = optional($entrega)->provinciasIibb
        ?? collect($provincia_query ?? [])->firstWhere('id', (int) $iibbEntregaId);

    $locEntregaId = $esTemplate ? '' : old('localidades_id.'.$idxEntrega, optional($entrega)->localidad_id ?? '');
    $locEntrega = optional($entrega)->localidades;

    $zonavtaId = $esTemplate ? '' : old('zonavtas_id.'.$idxEntrega, optional($entrega)->zonavta_id ?? '');
    $zonavtaCodigo = $esTemplate ? '' : old('codigozonavtas.'.$idxEntrega, optional(optional($entrega)->zonavtas)->codigo ?? '');
    $zonavtaNombre = $esTemplate ? '' : old('nombrezonavtas.'.$idxEntrega, optional(optional($entrega)->zonavtas)->nombre ?? '');

    $transpEntregaId = $esTemplate ? '' : old('transportes_id.'.$idxEntrega, optional($entrega)->transporte_id ?? '');
    $transpEntrega = optional($entrega)->transportes
        ?? collect($transporte_query ?? [])->firstWhere('id', (int) $transpEntregaId);
    $transporteCodigo = $esTemplate ? '' : old('codigotransportes.'.$idxEntrega, optional($transpEntrega)->codigo ?? '');
    $transporteNombre = $esTemplate ? '' : old('nombretransportes.'.$idxEntrega, optional($transpEntrega)->nombre ?? '');

    $nroVisible = $esTemplate ? 1 : ($idxEntrega + 1);
@endphp
<tr class="item-entrega{{ ! empty($activa) ? ' activa' : '' }}" data-entrega-idx="{{ $idxEntrega }}">
    <td class="p-0 border-0">
        <input type="hidden" name="entregas[]" class="iientrega" value="{{ $nroVisible }}">
        <div class="form-group row">
            <label class="col-lg-3 control-label text-right pr-2">Nombre</label>
            <div class="col-lg-8">
                <input type="text" name="nombres[]" class="form-control"
                    value="{{ $nombreEntrega }}" placeholder="Ej. Isidro Casanova, sucursal, depósito" maxlength="255">
            </div>
        </div>
        <div class="form-group row">
            <label class="col-lg-3 control-label text-right pr-2">Domicilio</label>
            <div class="col-lg-8">
                <input type="text" name="domicilios[]" class="form-control"
                    value="{{ $domicilioEntrega }}" placeholder="Calle y número" maxlength="255">
            </div>
        </div>
        <div class="form-group row">
            <label class="col-lg-3 control-label text-right pr-2">Código postal</label>
            <div class="col-lg-4">
                <input type="text" name="codigospostales[]" class="form-control codigospostales"
                    value="{{ $cpEntrega }}" placeholder="CP" maxlength="50">
            </div>
        </div>
        <div class="form-group row">
            <label class="col-lg-3 control-label text-right pr-2">Provincia</label>
            <div class="col-lg-8">
                @include('configuracion.partials.campo_consulta_provincia', [
                    'layout' => 'inline',
                    'inputName' => 'provincias_id[]',
                    'provinciaId' => $provEntregaId,
                    'codigo' => optional($provEntrega)->codigo ?? '',
                    'nombre' => optional($provEntrega)->nombre ?? (optional($entrega)->desc_provincias ?? ''),
                    'descName' => 'desc_provincias[]',
                ])
            </div>
        </div>
        <div class="form-group row">
            <label class="col-lg-3 control-label text-right pr-2">Jurisdicción IIBB</label>
            <div class="col-lg-8">
                @include('configuracion.partials.campo_consulta_provincia', [
                    'layout' => 'inline',
                    'inputName' => 'provincias_iibb_id[]',
                    'provinciaId' => $iibbEntregaId,
                    'codigo' => optional($iibbEntrega)->codigo ?? '',
                    'nombre' => optional($iibbEntrega)->nombre ?? '',
                    'extra_class' => 'tm-provincia-iibb-campo',
                ])
            </div>
        </div>
        <div class="form-group row">
            <label class="col-lg-3 control-label text-right pr-2">Localidad</label>
            <div class="col-lg-8">
                @include('configuracion.partials.campo_consulta_localidad', [
                    'layout' => 'inline',
                    'inputName' => 'localidades_id[]',
                    'localidadId' => $locEntregaId,
                    'codigo' => optional($locEntrega)->codigo ?? '',
                    'nombre' => optional($locEntrega)->nombre ?? (optional($entrega)->desc_localidades ?? ''),
                    'previaName' => 'localidad_id_previas[]',
                    'descName' => 'desc_localidades[]',
                ])
            </div>
        </div>
        <div class="form-group row">
            <label class="col-lg-3 control-label text-right pr-2">Zona de venta</label>
            <div class="col-lg-8">
                @include('ventas.cliente.partials.campo_zonavta_entrega', [
                    'zonavtaId' => $zonavtaId,
                    'zonavtaCodigo' => $zonavtaCodigo,
                    'zonavtaNombre' => $zonavtaNombre,
                ])
            </div>
        </div>
        <div class="form-group row mb-0">
            <label class="col-lg-3 control-label text-right pr-2">Reparto</label>
            <div class="col-lg-8">
                @include('ventas.cliente.partials.campo_transporte_entrega', [
                    'transporteId' => $transpEntregaId,
                    'transporteCodigo' => $transporteCodigo,
                    'transporteNombre' => $transporteNombre,
                ])
            </div>
        </div>
    </td>
</tr>
