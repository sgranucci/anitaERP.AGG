@php
    $tabsIeActiva = $tabsIeActiva ?? 'datos';
@endphp
@include('includes.tabs-activas-estilos')
<div class="tabs-activas px-3 pt-2">
    <ul class="nav nav-tabs" id="tabs-ingresoegreso" role="tablist">
        <li class="nav-item">
            <a class="nav-link {{ $tabsIeActiva === 'datos' ? 'active' : '' }}" href="#" id="botonform1" role="tab">
                <i class="fa fa-info-circle"></i> Datos principales
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tabsIeActiva === 'cheques' ? 'active' : '' }}" href="#" id="botonform2" role="tab">
                <i class="fa fa-money-check"></i> Cheques
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tabsIeActiva === 'comprobantes' ? 'active' : '' }}" href="#" id="botonform3" role="tab">
                <i class="fa fa-file-invoice"></i> Comprobantes
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tabsIeActiva === 'retenciones' ? 'active' : '' }}" href="#" id="botonform4" role="tab">
                <i class="fa fa-percent"></i> Retenciones
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tabsIeActiva === 'asiento' ? 'active' : '' }}" href="#" id="botonform5" role="tab">
                <i class="fa fa-book"></i> Asiento contable
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tabsIeActiva === 'archivos' ? 'active' : '' }}" href="#" id="botonform6" role="tab">
                <i class="fa fa-paperclip"></i> Archivos
            </a>
        </li>
    </ul>
</div>
