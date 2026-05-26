@php
use Carbon\Carbon;
$modoConsulta = request()->input('vista') === 'consulta';
@endphp

<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    @if (!$modoConsulta)
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a>
            </li>
        </ul>
        <!-- SEARCH FORM -->
        <form class="form-inline ml-3">
            <div class="input-group input-group-sm">
                <input class="form-control form-control-navbar" type="search" placeholder="Buscar" aria-label="Buscar">
                <div class="input-group-append">
                    <button class="btn btn-navbar" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </form>
    @else
        <span class="navbar-text font-weight-bold text-warning mr-3" title="Esta solapa fue abierta como consulta desde otra pantalla. Pod&eacute;s navegar consultas relacionadas y, si tu rol lo permite, editar. Al guardar te ofrecer&aacute; cerrar la solapa para volver a tu pantalla principal.">
            <i class="fas fa-eye"></i> Modo consulta
        </span>
    @endif
    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
        <!-- Messages Dropdown Menu -->
        <!-- Notifications Dropdown Menu -->
        @guest
            <li class="nav-item">
                <a href="{{ route('login') }}" class="nav-link font-weight-bold">
                    <i class="fas fa-sign-in-alt"></i> Ingresar
                </a>
            </li>
        @endguest
        @auth
            @if (!$modoConsulta)
                <li class="nav-item">
                    <a href="{{ $urlCentroAyuda }}" class="nav-link font-weight-bold" title="Manual de usuario del sistema" target="_blank" rel="noopener">
                        <i class="fas fa-book-open"></i> Centro de ayuda
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a href="{{route('cambia_password')}}" class="nav-link">
                        <i class="fa fa-lock"></i> Cambia password
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link" data-toggle="dropdown" href="#">
                        <i class="far fa-user"></i> {{session()->get('nombre_usuario', 'Invitado')}} - {{session()->get('rol_nombre', 'Guest')}}
                    </a>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                        <a href="{{route('logout')}}" class="nav-link">
                            <i class="fas fa-users mr-2"></i> Salir
                        </a>
                        <div class="dropdown-divider"></div>
                        @if(session()->get("roles") && count(session()->get("roles")) > 1)
                            <a href="#" class="cambiar-rol dropdown-item dropdown-footer">Cambiar Rol</a>
                        @endif
                    </div>
                </li>
            @else
                <li class="nav-item">
                    <span class="nav-link text-muted">
                        <i class="far fa-user"></i> {{session()->get('nombre_usuario', 'Invitado')}}
                    </span>
                </li>
                <li class="nav-item">
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="window.close();" title="Cerrar esta solapa de consulta">
                        <i class="fa fa-times"></i> Cerrar solapa
                    </button>
                </li>
            @endif
        @endauth
    </ul>
</nav>
