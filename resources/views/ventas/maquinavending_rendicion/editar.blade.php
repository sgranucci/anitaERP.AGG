@extends("theme.$theme.layout")
@section('titulo')
    Editar rendici&oacute;n vending
@endsection

@section('styles')
@include('ventas.maquinavending_rendicion.partials.estilos_pantalla')
@endsection

@section('scripts')
@php
    $empresaUnica = ($empresa_query ?? collect())->count() === 1;
    $empresaInicial = (int) old('empresa_id', $rendicion->empresa_id ?? 0);
    $fechaRendicionOld = old('fecha_rendicion', $rendicion->fecha_rendicion?->format('Y-m-d\\TH:i') ?? now()->format('Y-m-d\\TH:i'));
    $fechaJornadaOld = old('fecha_jornada', $rendicion->fecha_jornada?->format('Y-m-d') ?? now()->format('Y-m-d'));
    $datosIniciales = [
        'articulos' => $rendicion->articulos->map(static fn ($a) => [
            'numero_rulo' => (int) $a->numero_rulo,
            'articulo_id' => (int) $a->articulo_id,
            'sku' => (string) ($a->articulo->sku ?? ''),
            'descripcion' => (string) ($a->articulo->descripcion ?? ''),
            'cantidad' => (float) $a->cantidad,
            'precio_lista' => round((float) $a->precio_lista, 2),
        ])->values()->all(),
        'medios_pago' => $rendicion->mediosPago->map(static fn ($m) => [
            'cuentacaja_id' => (int) $m->cuentacaja_id,
            'codigo' => (string) ($m->cuentacaja->codigo ?? ''),
            'nombre' => (string) ($m->cuentacaja->nombre ?? ''),
            'monto' => round((float) $m->monto, 2),
            'cotizacion' => round((float) $m->cotizacion, 4),
        ])->values()->all(),
    ];
@endphp
<script>
    window.MV_RENDICION = {
        csrf: @json(csrf_token()),
        modo: 'edit',
        empresaUnica: @json($empresaUnica),
        empresaId: {{ $empresaInicial }},
        maquinaId: {{ (int) old('maquinavending_id', $rendicion->maquinavending_id ?? 0) }},
        urlMaquinas: @json(route('maquinavending_rendicion_api_maquinas', ['empresaId' => '__EMP__'])),
        urlArticulosBase: @json(url('ventas/gastronomia/maquinas-vending/rendiciones/api/maquina')),
        urlCuentasCaja: @json(route('maquinavending_rendicion_api_cuentas_caja')),
        monedaFacturaId: {{ (int) config('gastronomia.moneda_factura_id', 1) }},
        usocuentacajaGastronomiaId: {{ (int) ($usocuentacaja_gastronomia_id ?? 0) }},
        datosIniciales: @json($datosIniciales),
    };
</script>
<script src="{{ asset('assets/pages/scripts/caja/cuentacaja/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/maquinavending_rendicion/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/maquinavending_rendicion/form.js')) }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')

        <div class="card card-warning">
            <div class="card-header d-flex align-items-center flex-wrap">
                <h3 class="card-title mb-0">Editar rendici&oacute;n vending</h3>
                <span class="badge badge-light border ml-2 mb-0 py-1 px-2" style="font-size:0.85rem;font-weight:600;">
                    N&ordm; cierre #{{ (int) $rendicion->numero_cierre }}
                </span>
                <div class="card-tools ml-auto">
                    @if (can('ver-comprobante-maquinavending-rendicion-gastronomia', false))
                    <a href="{{ route('maquinavending_rendicion_comprobante', ['id' => $rendicion->id, 'inline' => 1]) }}"
                       class="btn btn-primary btn-sm" target="_blank" rel="noopener" title="Imprimir comprobante">
                        <i class="fa fa-print"></i> Imprimir
                    </a>
                    @endif
                    <a href="{{ route('consultar_maquinavending_rendicion_gastronomia') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_maquinavending_rendicion_gastronomia', ['id' => $rendicion->id]) }}" id="form-rendicion-vending" method="POST" autocomplete="off">
                @csrf
                @method('PUT')
                <input type="hidden" name="maquinavending_id" value="{{ (int) $rendicion->maquinavending_id }}">
                <div class="card-body pb-2">
                    @if ($errors->has('general'))
                    <div class="alert alert-danger">{{ $errors->first('general') }}</div>
                    @endif

                    <div class="alert alert-info py-2 small mb-3">
                        Solo puede editar mientras la rendici&oacute;n <strong>no haya sido presentada en caja</strong>.
                        La empresa, la m&aacute;quina y el N&ordm; cierre no se modifican.
                    </div>

                    <div class="card card-outline card-secondary mb-3">
                        <div class="card-header py-2"><strong>Datos de la rendici&oacute;n</strong></div>
                        <div class="card-body py-2">
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label for="empresa_id">Empresa</label>
                                    @include('includes.form-empresa-asignada-control', [
                                        'empresa_query' => $empresa_query,
                                        'empresa_id' => $empresaInicial,
                                        'required' => true,
                                        'opcion_vacia' => '— Seleccionar —',
                                        'solo_lectura' => true,
                                    ])
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="maquinavending_id">M&aacute;quina</label>
                                    <select name="maquinavending_id_display" id="maquinavending_id" class="form-control" disabled>
                                        <option value="">— Seleccionar —</option>
                                        @foreach ($maquinas_query ?? [] as $maq)
                                            <option value="{{ $maq->id }}"
                                                @selected((int) $rendicion->maquinavending_id === (int) $maq->id)>
                                                {{ trim(($maq->puntoventa->codigo ?? '').' — '.$maq->nombre, ' —') }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="fecha_rendicion" class="requerido">Fecha/hora</label>
                                    <input type="datetime-local" name="fecha_rendicion" id="fecha_rendicion" class="form-control" required
                                           value="{{ $fechaRendicionOld }}">
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="fecha_jornada">Fecha jornada</label>
                                    <input type="date" name="fecha_jornada" id="fecha_jornada" class="form-control"
                                           value="{{ $fechaJornadaOld }}">
                                </div>
                            </div>
                            <div class="form-group mb-0">
                                <label for="observacion">Observaciones</label>
                                <textarea name="observacion" id="observacion" class="form-control" rows="2" maxlength="65535">{{ old('observacion', $rendicion->observacion) }}</textarea>
                            </div>
                        </div>
                    </div>

                    @include('ventas.maquinavending_rendicion.partials.form_rendicion_cuerpo', [
                        'empresaInicial' => $empresaInicial,
                    ])
                </div>
                <div class="card-footer">
                    @include('includes.boton-form-editar')
                </div>
            </form>
        </div>
    </div>
</div>

@include('includes.caja.modalconsultacuentacaja')

@include('ventas.maquinavending_rendicion.partials.template_renglon_cuenta')
@endsection
