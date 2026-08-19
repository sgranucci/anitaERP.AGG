@extends("theme.$theme.layout")
@section('titulo')
    Flash diario
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script>
window.flashCalcularUrl = @json(route('flash_caja_api_calcular'));
window.flashDesgloseExcelUrl = @json(route('flash_caja_desglose_wigos_excel'));
window.flashOrigenTotalUrl = @json(route('flash_caja_api_origen_total'));
</script>
<script src="{{ asset('assets/pages/scripts/caja/flash/form.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('flash_caja', $filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Editar flash diario #{{ $data->id }}</h3>
                <div class="card-tools">
                    @if (! empty($puedeValidarFlash))
                        @include('caja.flash.partials.boton_validar', [
                            'flash' => $data,
                            'puedeValidarFlash' => true,
                            'mostrarEtiqueta' => true,
                            'retornoListadoQuery' => $filtrosQuery ?? [],
                        ])
                    @endif
                    @if (can('exportar-reporte-flash-caja', false))
                        <a href="{{ route('flash_caja_reporte', ['id' => $data->id, 'formato' => 'PDF']) }}" class="btn btn-outline-danger btn-sm" target="_blank" rel="noopener">
                            <i class="fa fa-file-pdf-o"></i> PDF
                        </a>
                        <a href="{{ route('flash_caja_reporte', ['id' => $data->id, 'formato' => 'EXCEL']) }}" class="btn btn-outline-success btn-sm ml-1">
                            <i class="fa fa-file-excel-o"></i> Excel
                        </a>
                    @endif
                    <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_flash_caja', ['id' => $data->id] + ($filtrosQuery ?? [])) }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                @method('PUT')
                <div class="card-body">
                    @if ($data->estaValidado())
                        <div class="alert alert-success py-2">
                            <i class="fa fa-check"></i>
                            Flash validado
                            @if ($data->validado_en)
                                el {{ $data->validado_en->format('d/m/Y H:i') }}
                            @endif
                            @if ($data->validadoUsuario)
                                por {{ $data->validadoUsuario->usuario ?? $data->validadoUsuario->nombre }}
                            @endif.
                            Si guarda cambios se quitará la validación.
                        </div>
                    @elseif (! empty($puedeValidarFlash))
                        <div class="alert alert-light border py-2 mb-3">
                            Este flash todavía no está validado. Use <strong>Validar</strong> para que el tilde verde
                            aparezca junto a los montos en Contable.
                        </div>
                    @endif
                    @include('caja.flash.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-editar')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
