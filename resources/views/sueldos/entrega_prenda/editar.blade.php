@extends("theme.$theme.layout")
@section('titulo')
    Editar entrega de indumentaria
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/sueldos/prenda/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/prenda/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/prenda/consulta_variante.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/prenda/consulta_variante.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/entrega_prenda/editar.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/sueldos/entrega_prenda/editar.js')) ?: time() }}"></script>
@endsection

@php
    $fmtCant = static fn ($v): string => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
    $fechaValor = old('fecha', optional($entrega->fecha)->format('Y-m-d'));
    $observacionValor = old('observacion', $entrega->observacion);
    $urlEmpleado = route('editar_empleado_sueldos', ['id' => $empleado->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']);
    $modoGuardado = $puedeLineas ? 'lineas' : 'cabecera';
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')

        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-tshirt"></i> Entrega de indumentaria #{{ $entrega->id }}
                </h3>
                <div class="card-tools">
                    <a href="{{ $urlRetorno }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>

            <form id="form-editar-entrega-prenda" class="form-horizontal" method="POST"
                  action="{{ route('actualizar_entrega_prenda', ['entrega' => $entrega->id]) }}"
                  data-enter-campos="1">
                @csrf
                @method('PUT')
                <input type="hidden" name="modo" value="{{ $modoGuardado }}">
                @if (! empty($retorno))
                    <input type="hidden" name="retorno" value="{{ $retorno }}">
                @endif

                <div class="card-body">
                    @if ($puedeLineasSinConfig)
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle"></i>
                            Falta configurar el depósito de origen y el tipo de transacción de indumentaria: las líneas se muestran solo en lectura.
                        </div>
                    @endif

                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right pr-2">Legajo</label>
                        <div class="col-lg-2">
                            <input type="text" class="form-control form-control-sm" value="{{ $empleado->legajo }}" readonly tabindex="-1">
                        </div>
                        <label class="col-lg-2 control-label text-right pr-2">Empleado</label>
                        <div class="col-lg-5">
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control form-control-sm" value="{{ $empleado->nombre }}" readonly tabindex="-1">
                                @if ($puedeVerEmpleado)
                                    <div class="input-group-append">
                                        <a href="{{ $urlEmpleado }}" class="btn btn-outline-secondary" target="_blank" rel="noopener" title="Consultar empleado">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right pr-2">Empresa</label>
                        <div class="col-lg-4">
                            <input type="text" class="form-control form-control-sm" value="{{ optional($empleado->empresa)->nombre }}" readonly tabindex="-1">
                        </div>
                        <label class="col-lg-2 control-label text-right pr-2">Depósito</label>
                        <div class="col-lg-3">
                            <input type="text" class="form-control form-control-sm" value="{{ $nombreDeposito ?? '' }}" readonly tabindex="-1">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="entrega_editar_fecha" class="col-lg-2 control-label text-right pr-2">Fecha</label>
                        <div class="col-lg-2">
                            <input type="date" id="entrega_editar_fecha" name="fecha" class="form-control form-control-sm" value="{{ $fechaValor }}" required>
                        </div>
                        <label for="entrega_editar_obs" class="col-lg-2 control-label text-right pr-2">Observación</label>
                        <div class="col-lg-5">
                            <input type="text" id="entrega_editar_obs" name="observacion" class="form-control form-control-sm" maxlength="255" value="{{ $observacionValor }}">
                        </div>
                    </div>

                    @if ($puedeCabecera)
                        <div class="form-group row">
                            <div class="col-lg-2"></div>
                            <div class="col-lg-9">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="entrega_editar_reset_tulegajo" name="reset_tulegajo" value="1" {{ old('reset_tulegajo') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="entrega_editar_reset_tulegajo">
                                        Reiniciar el estado de TuLegajo (vuelve a Pendiente para reenviar el comprobante)
                                    </label>
                                </div>
                                <small class="text-muted">
                                    Estado actual: {{ $entrega->tulegajo_estado ?: 'Pendiente' }}
                                    @if ($entrega->tulegajo_enviado_at)
                                        — enviado {{ $entrega->tulegajo_enviado_at->format('d/m/Y H:i') }}
                                    @endif
                                </small>
                            </div>
                        </div>
                    @endif

                    <div class="card card-outline card-info mb-2">
                        <div class="card-header py-2">
                            <h3 class="card-title"><i class="fa fa-list"></i> Líneas de la entrega</h3>
                        </div>
                        <div class="card-body p-2">
                            @if ($puedeLineas)
                                <p class="small text-muted mb-2">
                                    Al guardar se revierte el movimiento de stock y el asiento de esta entrega y se regraban con las líneas de abajo.
                                    Código de prenda y SKU: F1 o lupa abren la consulta; Enter avanza al campo siguiente.
                                </p>
                                <table class="table table-sm table-bordered" id="editar-entrega-tabla-lineas">
                                    <thead style="background:#85C1E9;color:#17202A">
                                        <tr>
                                            <th style="width:4%" class="text-left">Acciones</th>
                                            <th style="width:36%">Prenda</th>
                                            <th style="width:44%">Color / Talle (SKU)</th>
                                            <th style="width:16%">Cant.</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="editar-entrega-agregar-linea">
                                    <i class="fa fa-plus"></i> Agregar prenda
                                </button>
                                @if ($puedeOmitirCupo)
                                    <div class="custom-control custom-checkbox mt-2">
                                        <input type="checkbox" class="custom-control-input" id="entrega_editar_omitir_cupo" name="omitir_cupo" value="1" {{ old('omitir_cupo') ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="entrega_editar_omitir_cupo">Omitir control de cupo</label>
                                    </div>
                                @endif

                                <template id="editar-entrega-template-renglon">
                                    <tr>
                                        <td class="text-left align-middle">
                                            <button type="button" class="btn-accion-tabla btn-quitar-linea tooltipsC" title="Quitar">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </td>
                                        <td>
                                            <div class="tm-prenda-campo d-flex flex-nowrap align-items-center w-100" style="gap:4px;">
                                                <input type="hidden" class="prenda_id" value="">
                                                <button type="button" title="Consulta prendas (F1)" class="btn-accion-tabla consultaprenda flex-shrink-0">
                                                    <i class="fa fa-search text-primary"></i>
                                                </button>
                                                <input type="text" class="form-control form-control-sm codigoprenda" placeholder="Cód." title="Código; Enter valida; F1 consulta" autocomplete="off" style="width:4.5rem;flex-shrink:0;">
                                                <input type="text" class="form-control form-control-sm descripcionprenda text-truncate" placeholder="Descripción" readonly tabindex="-1" style="min-width:0;flex:1 1 auto;">
                                            </div>
                                        </td>
                                        <td>
                                            <div class="tm-variante-prenda-campo d-flex flex-nowrap align-items-center w-100" style="gap:4px;">
                                                <input type="hidden" class="prenda_articulo_id" value="">
                                                <button type="button" title="Consulta color/talle (F1)" class="btn-accion-tabla consultavarianteprenda flex-shrink-0">
                                                    <i class="fa fa-search text-primary"></i>
                                                </button>
                                                <input type="text" class="form-control form-control-sm codigovarianteprenda" placeholder="SKU" title="SKU; Enter valida; F1 consulta" autocomplete="off" style="width:7rem;flex-shrink:0;">
                                                <input type="text" class="form-control form-control-sm descripcionvarianteprenda text-truncate" placeholder="Color / talle" readonly tabindex="-1" style="min-width:0;flex:1 1 auto;">
                                            </div>
                                            <div class="linea-saldo small text-muted mt-1"></div>
                                        </td>
                                        <td>
                                            <input type="number" min="0.01" step="0.01" class="form-control form-control-sm linea-cantidad" value="1">
                                        </td>
                                    </tr>
                                </template>
                            @else
                                <table class="table table-sm table-bordered mb-0">
                                    <thead style="background:#85C1E9;color:#17202A">
                                        <tr>
                                            <th>Prenda</th>
                                            <th>Color</th>
                                            <th>Talle</th>
                                            <th>SKU</th>
                                            <th class="text-right">Cant.</th>
                                            <th>Vence</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($entrega->articulos as $linea)
                                            <tr>
                                                <td>{{ optional($linea->prenda)->codigo }} - {{ optional($linea->prenda)->descripcion }}</td>
                                                <td>{{ optional($linea->color)->nombre }}</td>
                                                <td>{{ optional($linea->talle)->nombre }}</td>
                                                <td>{{ $linea->sku }}</td>
                                                <td class="text-right">{{ $fmtCant($linea->cantidad) }}</td>
                                                <td>{{ optional($linea->vence_el)->format('d/m/Y') }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="text-center text-muted">Sin líneas.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary btn-sm" id="editar-entrega-guardar">
                        <i class="fa fa-save"></i> Guardar
                    </button>
                    <a href="{{ route('comprobante_entrega_prenda', ['entrega' => $entrega->id]) }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                        <i class="fa fa-file-pdf"></i> Comprobante
                    </a>
                    <a href="{{ $urlRetorno }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </form>
        </div>

        @if ($puedeLineas && ! empty($resumen['prendas']))
            <div class="card card-outline card-info">
                <div class="card-header py-2">
                    <h3 class="card-title"><i class="fa fa-list-check"></i> Dotación {{ $resumen['anio'] }} (referencia)</h3>
                </div>
                <div class="card-body p-2">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A">
                            <tr><th>Prenda</th><th class="text-right">Cupo</th><th class="text-right">Entregado</th><th class="text-right">Saldo</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($resumen['prendas'] as $p)
                                <tr>
                                    <td>{{ $p['codigo'] }} - {{ $p['descripcion'] }}</td>
                                    <td class="text-right">{{ $fmtCant($p['limite']) }}</td>
                                    <td class="text-right">{{ $fmtCant($p['entregado']) }}</td>
                                    <td class="text-right">{{ $fmtCant($p['saldo']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <small class="text-muted">El entregado incluye las líneas actuales de esta entrega (se descuentan al validar el cupo).</small>
                </div>
            </div>
        @endif
    </div>
</div>

@if ($puedeLineas)
    <script type="application/json" id="editar-entrega-filas-json">@json($filasLineas)</script>
    @include('includes.sueldos.modalconsultaprenda')
    @include('includes.sueldos.modalconsultavarianteprenda')
@endif
@endsection
