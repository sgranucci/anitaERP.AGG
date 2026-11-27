@extends("theme.$theme.layout")
@section('titulo')
    Reporte de Artículos Vendidos
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/ventas/puntoventa/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/puntoventa/consulta.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Datos Reporte de Artículos Vendidos</h3>
            </div>
            <form action="{{route('crear_reparticulovendido')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("post")
                <div class="card-body">
                    @include('ventas.reparticulovendido.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-genera-excel', array('ruta' => 'crear_reparticulovendido'))
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.ventas.modalconsultapuntoventa')
<script>
    (function () {
        var lista = document.getElementById('rep-articulovendido-sucursales-extra');
        var tpl = document.getElementById('tpl-sucursal-articulovendido');
        var btn = document.getElementById('agregar-sucursal-articulovendido');
        if (!lista || !tpl || !btn) {
            return;
        }
        btn.addEventListener('click', function () {
            lista.insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/NUEVA/g, String(Date.now())));
            var codigos = lista.querySelectorAll('.codigopuntoventa');
            if (codigos.length) {
                codigos[codigos.length - 1].focus();
            }
        });
        lista.addEventListener('click', function (ev) {
            var quitar = ev.target.closest('.quitar-sucursal-articulovendido');
            var fila = quitar ? quitar.closest('.rep-articulovendido-sucursal-extra') : null;
            if (fila) {
                fila.remove();
            }
        });
    })();
</script>
@endsection
