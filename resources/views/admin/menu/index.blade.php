@extends("theme.$theme.layout")
@section("titulo")
Menú
@endsection

@section("styles")
<link href="{{asset("assets/js/jquery-nestable/jquery.nestable.css")}}" rel="stylesheet" type="text/css" />
<style>
    .menu-sel-label { cursor: pointer; position: relative; z-index: 5; }
    .dd3-content .menu-sel { vertical-align: middle; }
    .dd3-content { height: auto; min-height: 30px; overflow: visible; }
    #nestable li.menu-filtro-oculto { display: none; }
    #nestable.dd { float: none; width: 100%; max-width: none; }
</style>
@endsection

@section("scriptsPlugins")
<script src="{{asset("assets/js/jquery-nestable/jquery.nestable.js")}}" type="text/javascript"></script>
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/menu/index.js")}}?v={{ @filemtime(public_path('assets/pages/scripts/admin/menu/index.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Menús</h3>
                <div class="card-tools">
                    <a href="{{route('crear_menu')}}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-fw fa-plus-circle"></i> Crear menú
                    </a>
                </div>
            </div>
            <div class="card-body">
                @csrf
                <form id="form-eliminar-varios-menu" method="POST" action="{{ route('eliminar_varios_menu') }}" data-mensaje-grabacion="Eliminando menús…" hidden>
                    @csrf
                </form>
                <div class="form-inline flex-wrap mb-3 menu-toolbar-lote">
                    <label class="mr-2 mb-2" for="menu-filtro">Filtrar</label>
                    <input type="search" id="menu-filtro" class="form-control form-control-sm mr-2 mb-2" placeholder="Nombre o URL (ej. bingo, waitry)" style="min-width:260px;">
                    <button type="button" id="menu-sel-visibles" class="btn btn-outline-info btn-sm mr-2 mb-2">
                        Marcar visibles
                    </button>
                    <button type="button" id="menu-sel-ninguno" class="btn btn-outline-secondary btn-sm mr-2 mb-2">
                        Desmarcar
                    </button>
                    <button type="button" id="menu-eliminar-seleccionados" class="btn btn-outline-danger btn-sm mb-2">
                        <i class="fa fa-trash-o"></i> Eliminar seleccionados
                        <span id="menu-sel-contador" class="badge badge-danger">0</span>
                    </button>
                </div>
                <p class="text-muted small">
                    Marque los ítems de AGG u otros que no correspondan. Al marcar un padre se marcan sus submenús.
                    Eliminar seleccionados borra todo el árbol marcado en un solo paso.
                </p>
                <div class="dd" id="nestable">
                    <ol class="dd-list">
                        @foreach ($menus as $key => $item)
                            @if ($item["menu_id"] != 0)
                                @break
                            @endif
                            @include("admin.menu.menu-item",["item" => $item])
                        @endforeach
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
