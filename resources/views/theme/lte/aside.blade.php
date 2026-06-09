<aside class="main-sidebar sidebar-dark-primary elevation-4 anita-sidebar">
    <!-- Brand Logo -->
    <a href="{{ config('app.empresa_link') }}" class="brand-link">
        <img src="{{asset("assets/$theme/dist/img/AdminLTELogo.png")}}" alt="AdminLTE Logo"
            class="brand-image img-circle elevation-3" style="opacity: .8">
        <span class="brand-text font-weight-light">{{ config('app.empresa') }}</span>
    </a>
    <!-- sidebar: style can be found in sidebar.less -->
    <div class="sidebar">
        <!-- Sidebar user panel -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
			@php
				$foto = session()->get('foto_usuario');

				if ($foto)
					$_url = "storage/imagenes/fotos_usuarios/$foto";
				else
					$_url = "assets/$theme/dist/img/user2-160x160.jpg";
			@endphp
                <img src="{{asset($_url)}}" class="img-circle elevation-2"
                    alt="User Image">
            </div>
            <div class="info">
                @guest
                    <a href="{{ route('login') }}" class="d-block">{{ session()->get('nombre_usuario', 'Invitado') }}</a>
                @else
                    <a href="#" class="d-block">{{ session()->get('nombre_usuario', 'Invitado') }}</a>
                @endguest
            </div>
        </div>
        <!-- sidebar menu: : style can be found in sidebar.less -->
        <nav class="mt-1">
        <ul class="nav nav-pills nav-sidebar nav-child-indent flex-column" data-widget="treeview" role="menu" data-accordion="false">
            @foreach ($menusComposer as $key => $item)
                @if ($item["menu_id"] != 0)
                    @break
                @endif
                @include("theme.$theme.menu-item", ["item" => $item])
            @endforeach
        </ul>
        </nav>
    </div>
    <!-- /.sidebar -->
</aside>
