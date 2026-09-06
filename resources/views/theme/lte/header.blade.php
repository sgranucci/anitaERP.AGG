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
        <form class="form-inline ml-3 d-none d-md-flex">
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
                <li class="nav-item dropdown anita-notif-dropdown">
                    <a class="nav-link" data-toggle="dropdown" href="#" id="anita-nav-notif"
                       data-feed-url="{{ urlAppCarpeta('notificaciones/feed') }}"
                       data-contador-url="{{ urlAppCarpeta('notificaciones/contador') }}"
                       data-leer-todas-url="{{ urlAppCarpeta('notificaciones/leer-todas') }}"
                       data-leer-url-base="{{ urlAppCarpeta('notificaciones') }}"
                       title="Avisos del sistema">
                        <i class="far fa-bell"></i>
                        <span id="anita-notif-badge"
                              class="badge badge-warning navbar-badge{{ ($anitaNotifUnread ?? 0) > 0 ? '' : ' d-none' }}">{{ ($anitaNotifUnread ?? 0) > 99 ? '99+' : (int) ($anitaNotifUnread ?? 0) }}</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right anita-notif-panel" id="anita-notif-panel">
                        <span class="dropdown-item dropdown-header anita-notif-header">
                            Avisos
                            <button type="button" class="btn btn-link btn-sm p-0 float-right js-anita-notif-all" id="anita-notif-mark-all">Marcar leídos</button>
                        </span>
                        <div class="dropdown-divider"></div>
                        <div id="anita-notif-list" class="anita-notif-list">
                            <span class="dropdown-item text-muted text-sm">Cargando…</span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="{{ $urlMisAprobaciones ?? url('mis-aprobaciones') }}" class="dropdown-item dropdown-footer">Ir a Mis aprobaciones</a>
                    </div>
                </li>
                @if (!empty($puedeVerMisAprobaciones))
                <li class="nav-item">
                    <a href="{{ $urlMisAprobaciones ?? url('mis-aprobaciones') }}"
                       id="anita-nav-aprobaciones"
                       class="nav-link font-weight-bold"
                       data-contador-url="{{ urlAppCarpeta('mis-aprobaciones/contador') }}"
                       data-count="{{ (int) ($misAprobacionesCount ?? 0) }}"
                       title="Mis aprobaciones pendientes">
                        <i class="fas fa-inbox"></i>
                        <span class="d-none d-md-inline anita-aprob-label">Aprobaciones</span>
                        <span id="anita-aprob-badge"
                              class="badge badge-danger anita-aprob-count{{ ($misAprobacionesCount ?? 0) > 0 ? '' : ' d-none' }}">{{ ($misAprobacionesCount ?? 0) > 99 ? '99+' : (int) ($misAprobacionesCount ?? 0) }}</span>
                    </a>
                </li>
                @endif
                <li class="nav-item">
                    <a href="{{ $urlCentroAyuda }}" class="nav-link font-weight-bold" title="Manual de usuario del sistema" target="_blank" rel="noopener">
                        <i class="fas fa-book-open"></i> <span class="d-none d-md-inline">Centro de ayuda</span>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a href="{{route('cambia_password')}}" class="nav-link" title="Cambia password">
                        <i class="fa fa-lock"></i> <span class="d-none d-md-inline">Cambia password</span>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link" data-toggle="dropdown" href="#" title="{{session()->get('nombre_usuario', 'Invitado')}} - {{session()->get('rol_nombre', 'Guest')}}">
                        <i class="far fa-user"></i> <span class="d-none d-md-inline">{{session()->get('nombre_usuario', 'Invitado')}} - {{session()->get('rol_nombre', 'Guest')}}</span>
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
