<li class="dd-item dd3-item" data-id="{{$item["id"]}}">
    <div class="dd-handle dd3-handle"></div>
    <div class="dd3-content {{$item["url"] == "javascript:;" ? "font-weight-bold" : ""}}">
        <a href="{{route("editar_menu", ['id' => $item["id"]])}}">{{$item["nombre"] . " | Url -> " . $item["url"]}} Icono -> <i style="font-size:20px;" class="fa fa-fw {{isset($item["icono"]) ? $item["icono"] : ""}}"></i></a>
        <a href="{{route('eliminar_menu', ['id' => $item["id"]])}}" class="eliminar-menu tooltipsC" title="Eliminar este menú"><i class="text-danger fa fa-trash-o"></i></a>
        <label class="menu-sel-label dd-nodrag mb-0 ml-2" title="Marcar para borrar">
            <input type="checkbox" class="menu-sel" value="{{$item["id"]}}" data-nombre="{{ $item["nombre"] }}">
        </label>
    </div>
    @if ($item["submenu"] != [])
    <ol class="dd-list">
        @foreach ($item["submenu"] as $submenu)
        @include("admin.menu.menu-item",[ "item" => $submenu ])
        @endforeach
    </ol>
    @endif
</li>
