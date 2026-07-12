@php
    $filtrosDet = $filtros ?? [];
    $queryBase = $filtrosQueryBase ?? ($filtrosQuery ?? []);
@endphp
<div class="px-3 py-2 border-bottom bg-white">
    <form method="get" action="{{ route('mayor_concepto') }}" id="form-filtros-detalle-mayor-concepto" class="mb-0">
        <input type="hidden" name="consultar" value="1">
        @foreach (($filtrosDet['empresa_ids'] ?? []) as $empresaIdFiltro)
            <input type="hidden" name="empresa_ids[]" value="{{ (int) $empresaIdFiltro }}">
        @endforeach
        @if (empty($filtrosDet['consolidar_empresas']))
            <input type="hidden" name="consolidar_empresas" value="0">
        @endif
        <input type="hidden" name="moneda_id" value="{{ (int) ($filtrosDet['moneda_id'] ?? 1) }}">
        <input type="hidden" name="modo_periodo" value="{{ $filtrosDet['modo_periodo'] ?? 'mes' }}">
        <input type="hidden" name="mes" value="{{ (int) ($filtrosDet['mes'] ?? 0) }}">
        <input type="hidden" name="anio" value="{{ (int) ($filtrosDet['anio'] ?? 0) }}">
        <input type="hidden" name="fecha_desde" value="{{ $filtrosDet['fecha_desde'] ?? '' }}">
        <input type="hidden" name="fecha_hasta" value="{{ $filtrosDet['fecha_hasta'] ?? '' }}">
        @if (! empty($filtrosDet['solo_moneda_origen']))
            <input type="hidden" name="solo_moneda_origen" value="1">
        @endif
        <input type="hidden" name="agrupacion_resumen" id="agrupacion_resumen_detalle"
            value="{{ $filtrosDet['agrupacion_resumen'] ?? 'concepto_cuenta' }}">

        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
            <h6 class="mb-0 font-weight-bold">Buscar en el detalle</h6>
            @if (! empty($filtro_detalle_activo))
                <a href="{{ route('mayor_concepto', $queryBase) }}"
                   class="btn btn-outline-secondary btn-xs">
                    <i class="fa fa-times"></i> Quitar filtros de detalle
                </a>
            @endif
        </div>
        <p class="small text-muted mb-2">
            Filtra sobre el resultado ya generado (no vuelve a procesar el período). Los campos se combinan con AND.
        </p>

        <div class="form-row">
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-0" for="filtro_nro_asiento">N. asiento</label>
                <input type="text" name="filtro_nro_asiento" id="filtro_nro_asiento" class="form-control form-control-sm"
                    value="{{ $filtrosDet['filtro_nro_asiento'] ?? '' }}" placeholder="Ej. 5263579">
                <small class="text-muted">Sin prefijo S; evite ceros de más (5263579, no 52603579)</small>
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-0" for="filtro_cuenta">Cuenta</label>
                <input type="text" name="filtro_cuenta" id="filtro_cuenta" class="form-control form-control-sm"
                    value="{{ $filtrosDet['filtro_cuenta'] ?? '' }}" placeholder="Código o nombre">
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-0" for="filtro_concepto">Concepto</label>
                <input type="text" name="filtro_concepto" id="filtro_concepto" class="form-control form-control-sm"
                    value="{{ $filtrosDet['filtro_concepto'] ?? '' }}" placeholder="ID o nombre">
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-0" for="filtro_comprobante">Comprobante</label>
                <input type="text" name="filtro_comprobante" id="filtro_comprobante" class="form-control form-control-sm"
                    value="{{ $filtrosDet['filtro_comprobante'] ?? '' }}" placeholder="Tipo o nro.">
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-0" for="filtro_emisor">Emisor</label>
                <input type="text" name="filtro_emisor" id="filtro_emisor" class="form-control form-control-sm"
                    value="{{ $filtrosDet['filtro_emisor'] ?? '' }}">
            </div>
            <div class="form-group col-md-2 col-sm-6 mb-2">
                <label class="small mb-0" for="filtro_cuit">CUIT</label>
                <input type="text" name="filtro_cuit" id="filtro_cuit" class="form-control form-control-sm"
                    value="{{ $filtrosDet['filtro_cuit'] ?? '' }}">
            </div>
        </div>
        <div class="form-row align-items-end">
            <div class="form-group col-md-6 col-sm-8 mb-2">
                <label class="small mb-0" for="filtro_texto">Texto libre</label>
                <input type="text" name="filtro_texto" id="filtro_texto" class="form-control form-control-sm"
                    value="{{ $filtrosDet['filtro_texto'] ?? '' }}"
                    placeholder="Descripción, cheque, OC, comprobante, emisor…">
            </div>
            <div class="form-group col-md-6 col-sm-4 mb-2">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa fa-filter"></i> Filtrar detalle
                </button>
            </div>
        </div>

        @if (! empty($filtro_detalle_activo) && ! empty($filtros_detalle_texto))
            <p class="small mb-0 text-info">
                <i class="fa fa-info-circle"></i>
                Filtro activo: {{ implode(' · ', $filtros_detalle_texto) }}
            </p>
        @endif
    </form>
</div>
