@php
    $tabsCobActiva = $tabsCobActiva ?? 'datos';
@endphp
@include('includes.tabs-activas-estilos')
<div class="tabs-activas px-3 pt-2">
    <ul class="nav nav-tabs" id="tabs-cobranza" role="tablist">
        <li class="nav-item">
            <a class="nav-link {{ $tabsCobActiva === 'datos' ? 'active' : '' }}" href="#" id="botonform1" role="tab">
                <i class="fa fa-user"></i> Datos / Deuda
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tabsCobActiva === 'cuentas' ? 'active' : '' }}" href="#" id="botonform2" role="tab">
                <i class="fa fa-cash-register"></i> Cuentas
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tabsCobActiva === 'cheques' ? 'active' : '' }}" href="#" id="botonform3" role="tab">
                <i class="fa fa-money-check"></i> Cheques
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tabsCobActiva === 'retenciones' ? 'active' : '' }}" href="#" id="botonform4" role="tab">
                <i class="fa fa-percent"></i> Retenciones
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tabsCobActiva === 'historia' ? 'active' : '' }}" href="#" id="botonform5" role="tab">
                <i class="fa fa-history"></i> Historia
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tabsCobActiva === 'asiento' ? 'active' : '' }}" href="#" id="botonform6" role="tab">
                <i class="fa fa-book"></i> Asiento Contable
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tabsCobActiva === 'archivos' ? 'active' : '' }}" href="#" id="botonform7" role="tab">
                <i class="fa fa-paperclip"></i> Archivos
            </a>
        </li>
    </ul>
</div>
