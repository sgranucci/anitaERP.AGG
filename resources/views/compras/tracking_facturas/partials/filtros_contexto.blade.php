@php
    use App\Support\Compras\Tracking\TrackingFacturasListadoFiltros;

    /**
     * Filtros de contexto: eje y rango de fechas, familia.
     *
     * Van fuera del panel plegable porque son los que se usan en cada consulta
     * y no deberían requerir abrir nada. El eje es explícito a propósito:
     * «fecha del comprobante» y «fecha de carga» dan resultados muy distintos
     * en el histórico importado y conviene que el usuario vea cuál está usando.
     *
     * La empresa va en la barra de botones (filtros_externos); acá sólo se
     * persiste como hidden al buscar / aplicar fechas.
     */
    $f = $filtros ?? [];
    $ejeActivo = $f['eje_fecha'] ?? TrackingFacturasListadoFiltros::EJE_FECHA_COMPROBANTE;
    $fScope = $f['empresa_scope'] ?? 'una';
    $fEmp = (int) ($f['empresa_id'] ?? 0);
@endphp
<div class="card-body bg-light border-top border-bottom py-2">
    {{-- Persistencia del filtro externo de empresa al buscar fechas / tipo --}}
    @if ($fScope === 'todas')
        <input type="hidden" name="empresa_todas" value="1">
    @elseif ($fEmp > 0)
        <input type="hidden" name="empresa_id" value="{{ $fEmp }}">
    @endif
    <div class="form-row align-items-end">
        <div class="form-group col-md-2 col-sm-6 mb-2">
            <label class="small mb-1" for="eje_fecha">Fechas por</label>
            <select name="eje_fecha" id="eje_fecha" class="form-control form-control-sm">
                @foreach ($ejesFecha as $clave => $etiqueta)
                    <option value="{{ $clave }}" {{ $ejeActivo === $clave ? 'selected' : '' }}>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-2 col-sm-6 mb-2">
            <label class="small mb-1" for="fecha_desde">Desde</label>
            <input type="date" name="fecha_desde" id="fecha_desde"
                   class="form-control form-control-sm" value="{{ $f['fecha_desde'] ?? '' }}">
        </div>
        <div class="form-group col-md-2 col-sm-6 mb-2">
            <label class="small mb-1" for="fecha_hasta">Hasta</label>
            <input type="date" name="fecha_hasta" id="fecha_hasta"
                   class="form-control form-control-sm" value="{{ $f['fecha_hasta'] ?? '' }}">
        </div>
        <div class="form-group col-md-2 col-sm-6 mb-2">
            <label class="small mb-1" for="familia">Tipo</label>
            <select name="familia" id="familia" class="form-control form-control-sm">
                <option value="">Todos</option>
                @foreach ($familias as $clave => $etiqueta)
                    <option value="{{ $clave }}" {{ ($f['familia'] ?? '') === $clave ? 'selected' : '' }}>
                        {{ $etiqueta }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-auto mb-2">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fa fa-search"></i> Buscar
            </button>
            <a href="{{ route('tracking_facturas', TrackingFacturasListadoFiltros::paraQueryStringEmpresa($f)) }}"
               class="btn btn-outline-secondary btn-sm">
                <i class="fa fa-eraser"></i> Limpiar
            </a>
        </div>
    </div>
    {{-- El segmento viaja en la URL: se conserva al aplicar estos filtros. --}}
    <input type="hidden" name="segmento" value="{{ $f['segmento'] ?? TrackingFacturasListadoFiltros::SEGMENTO_TODOS }}">
    <input type="hidden" name="proveedor_id" value="{{ (int) ($f['proveedor_id'] ?? 0) ?: '' }}">
</div>
