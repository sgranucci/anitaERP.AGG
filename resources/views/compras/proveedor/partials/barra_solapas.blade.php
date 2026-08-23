@php
    $mostrarIngresos = !empty($mostrar_solapa_ingresos);
    $cantTickets = ($tickets_ingreso ?? collect())->count();
@endphp
<div class="text-center py-2 border-bottom rounded-top bg-white">
    <button type="button" id="botonform1" class="btn btn-primary btn-sm mx-1 prov-tab-solapa font-weight-bold">
        <i class="fa fa-user"></i> Datos principales
    </button>
    <button type="button" id="botonform2" class="btn btn-info btn-sm mx-1 prov-tab-solapa">
        <span class="fa fa-cash-register"></span> Datos impuestos
    </button>
    <button type="button" id="botonform3" class="btn btn-info btn-sm mx-1 prov-tab-solapa">
        <span class="fa fa-truck"></span> Formas de pago
    </button>
    <button type="button" id="botonform4" class="btn btn-info btn-sm mx-1 prov-tab-solapa">
        <span class="fa fa-comment"></span> Leyendas
    </button>
    <button type="button" id="botonform5" class="btn btn-info btn-sm mx-1 prov-tab-solapa">
        <span class="fa fa-copy"></span> Archivos asociados
    </button>
    <button type="button" id="botonform6" class="btn btn-info btn-sm mx-1 prov-tab-solapa">
        <span class="fa fa-id-card"></span> CM05 / CUIT
    </button>
    <button type="button" id="botonform7" class="btn btn-info btn-sm mx-1 prov-tab-solapa">
        <span class="fa fa-bolt"></span> Servicios
    </button>
    @if ($mostrarIngresos)
        <button type="button" id="botonform8" class="btn btn-info btn-sm mx-1 prov-tab-solapa">
            <span class="fa fa-id-badge"></span> Ingresos
            <span class="badge badge-light ml-1 ingreso-solapa-badge-count">{{ $cantTickets }}</span>
        </button>
    @endif
    <button type="button" id="btn-consulta-arca-padron-crear" class="btn btn-outline-secondary btn-sm mx-1" title="Ingresá el CUIT y consultá el padrón ARCA">
        <i class="fa fa-search"></i> Consulta padrón ARCA
    </button>
</div>
