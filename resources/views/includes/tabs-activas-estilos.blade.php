{{--
    Estilo de solapas (nav-tabs) para formularios crear/editar, con la solapa
    ACTIVA bien diferenciada (barra superior azul + fondo celeste).

    Uso: envolver el <ul class="nav nav-tabs"> en un contenedor con la clase
    "tabs-activas" e incluir este partial una vez en la vista.

        @include('includes.tabs-activas-estilos')
        <div class="tabs-activas">
            <ul class="nav nav-tabs" ...> ... </ul>
        </div>

    Paleta unificada con el resto del sistema (thead #85C1E9).
--}}
<style>
    .tabs-activas .nav-tabs {
        margin-bottom: 0;
    }
    .tabs-activas .nav-tabs .nav-link {
        color: #17202A;
        border-top: 3px solid transparent;
    }
    .tabs-activas .nav-tabs .nav-link:hover {
        background-color: #EBF5FB;
        border-color: #EBF5FB #EBF5FB #dee2e6;
    }
    .tabs-activas .nav-tabs .nav-link.active {
        color: #1B4F72;
        font-weight: 600;
        background-color: #D6EAF8;
        border-color: #85C1E9 #85C1E9 #fff;
        border-top: 3px solid #2471A3;
    }
    .tabs-activas .nav-tabs .nav-link.active i {
        color: #2471A3;
    }
    .tabs-activas .nav-tabs .nav-link .badge {
        margin-left: 4px;
    }
</style>
