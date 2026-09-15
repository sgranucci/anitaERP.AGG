@extends("theme.$theme.layout")
@section('titulo')
    Emisión de OT
@endsection

@section("scripts")

<script src="{{asset("assets/pages/scripts/configuracion/salida.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/configurar_salida.js")}}" type="text/javascript"></script>

<script>

window.seteoSalidaPrograma = @json(\App\Support\Configuracion\SeteoSalidaProgramaSupport::VENTAS_REPEMISIONOT);
window.seteoSalidaConfigurarUrl = @json(route('configurar_salida', ['programa' => ':programa']));

    $(function () {
		$("#ordenestrabajo").focus();
    });

</script>

@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Datos Emisión de OT</h3>
				@include('includes.configurar-salida')
            </div>
            <form action="{{route('crearemisionot')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method("post")
                <div class="card-body">
                    @include('ventas.repemisionot.form')
                    <p class="text-muted mb-0" style="font-size: 0.9rem;">
                        Impresora: genera PDF en anitaERP y lo envía por JetDirect (IP:9100)
                        a la impresora configurada en «Configura salida» — no hace falta CUPS en el L12.
                        PDF: descarga el mismo documento sin imprimir.
                    </p>
                </div>
            </form>
            <div class="card-footer">
                <div class="row">
                    <div class="col-lg-3"></div>
                    <div class="col-lg-8">
                        <button type="submit" form="form-general" class="btn btn-sm btn-primary mr-2" name="destino" value="impresora">
                            <i class="fa fa-print"></i> Imprimir OT
                        </button>
                        <button type="submit" form="form-general" class="btn btn-sm btn-outline-danger" formaction="{{ route('crearemisionot_pdf') }}" name="destino" value="pdf">
                            <i class="fas fa-file-pdf"></i> Generar PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
