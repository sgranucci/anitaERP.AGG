<ul class="nav nav-tabs" id="tabs-cliente" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="tab-datos-principales-link" data-toggle="tab" href="#tab-datos-principales" role="tab">
            <i class="fa fa-user"></i> Datos principales
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-datos-facturacion-link" data-toggle="tab" href="#tab-datos-facturacion" role="tab">
            <i class="fa fa-file-invoice"></i> Datos facturaci&oacute;n
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-lugares-entrega-link" data-toggle="tab" href="#tab-lugares-entrega" role="tab">
            <i class="fa fa-truck"></i> Lugares de entrega
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-leyendas-link" data-toggle="tab" href="#tab-leyendas" role="tab">
            <i class="fa fa-comment"></i> Leyendas
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-archivos-link" data-toggle="tab" href="#tab-archivos" role="tab">
            <i class="fa fa-paperclip"></i> Archivos asociados
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-seguimiento-link" data-toggle="tab" href="#tab-seguimiento" role="tab">
            <i class="fa fa-history"></i> Seguimiento
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-articulos-suspendidos-link" data-toggle="tab" href="#tab-articulos-suspendidos" role="tab">
            <i class="fa fa-ban"></i> Art&iacute;culos suspendidos
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-cm05-link" data-toggle="tab" href="#tab-cm05" role="tab">
            <i class="fa fa-map"></i> CM05
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-exclusion-percepcion-link" data-toggle="tab" href="#tab-exclusion-percepcion" role="tab">
            <i class="fa fa-percent"></i> Exclusiones percepci&oacute;n
        </a>
    </li>
    @if (!empty($mostrarSuitecrm))
    <li class="nav-item">
        <a class="nav-link" id="tab-suitecrm-link" data-toggle="tab" href="#tab-suitecrm" role="tab">
            <i class="fa fa-comments"></i> SuiteCRM
        </a>
    </li>
    @endif
</ul>
