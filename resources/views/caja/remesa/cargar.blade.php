@extends("theme.$theme.layout")
@section('titulo')
    {{ ! empty($modo_edicion) ? 'Editar remesa' : 'Nueva remesa' }}
@endsection

@section("scripts")
<style>
    .remesa-workbench { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 991px) { .remesa-workbench { grid-template-columns: 1fr; } }
    .remesa-panel thead th { background: #85C1E9; color: #17202A; font-size: 0.85rem; white-space: nowrap; }
    .remesa-panel tbody td { vertical-align: middle; }
    .remesa-panel .col-codigo { width: 4.5rem; }
    .remesa-panel .col-monto { width: 11rem; min-width: 10rem; }
    .remesa-panel .js-monto-ar { min-width: 9rem; font-weight: 600; }
    .remesa-acciones-fijas {
        position: sticky;
        bottom: 0;
        z-index: 1050;
        background: #fff;
        border-top: 2px solid #85C1E9 !important;
        box-shadow: 0 -4px 14px rgba(23, 32, 42, 0.12);
        padding: 0.75rem 1rem;
    }
    .remesa-acciones-fijas .totales-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 0.45rem 0.65rem;
        flex: 1 1 auto;
        min-width: 0;
    }
    .remesa-acciones-fijas .tot-item {
        background: #f8fbfc;
        border: 1px solid #d6eaf8;
        border-radius: 4px;
        padding: 0.35rem 0.5rem;
        text-align: center;
    }
    .remesa-acciones-fijas .tot-item.is-alerta { border-color: #e74c3c; background: #fdedec; }
    .remesa-acciones-fijas .lbl { font-size: 0.7rem; color: #566573; display: block; }
    .remesa-acciones-fijas .val { font-weight: 700; font-size: 0.98rem; color: #17202A; }
    #remesa-preview-asiento { font-size: 0.85rem; max-height: 180px; overflow-y: auto; }
</style>
<script src="{{ asset('assets/pages/scripts/caja/remesa/cargar.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Caja\Remesa\RemesaSupport;

    $formAction = ! empty($modo_edicion)
        ? route('actualizar_remesa', ['id' => $remesa_id])
        : route('guardar_remesa');
    $formMethod = ! empty($modo_edicion) ? 'PUT' : 'POST';
    $soloLectura = ! empty($remesa) && $remesa->estaInactiva();
    $destinoLineas = $datos['destino'] ?? [];
    $origenLineas = $datos['origen'] ?? [];
    $totales = $datos['totales'] ?? ['destino' => 0, 'origen' => 0];
    $usoOrigenActual = $datos['uso_origen']
        ?? RemesaSupport::usoOrigenParaTipo((string) ($tipo ?? RemesaSupport::TIPO_EXTERNA));
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')

        <div id="remesa-app"
             class="card card-primary"
             data-modo-edicion="{{ ! empty($modo_edicion) ? '1' : '0' }}"
             data-empresa-id="{{ (int) $empresa_id }}"
             data-remesa-id="{{ (int) $remesa_id }}"
             data-tipo-externa="{{ RemesaSupport::TIPO_EXTERNA }}">

            <div class="card-header">
                <h3 class="card-title">{{ ! empty($modo_edicion) ? 'Editar remesa' : 'Nueva remesa' }}</h3>
                <div class="card-tools">
                    @if (! empty($remesa?->asiento_id) && (can('listar-asiento', false) || can('editar-asiento', false)))
                        <a href="{{ route('editar_asiento', ['id' => $remesa->asiento_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                           class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
                            <i class="fa fa-book"></i> Asiento
                        </a>
                    @endif
                    @if (! empty($remesa?->caja_movimiento_id) && (can('listar-ingresos-egresos-caja', false) || can('editar-ingresos-egresos-caja', false)))
                        <a href="{{ route('editar_ingresoegreso', ['id' => $remesa->caja_movimiento_id, 'origen' => 'modal_consulta']) }}"
                           class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                            <i class="fa fa-university"></i> Mov. caja
                        </a>
                    @endif
                    <a href="{{ route('remesa', $filtrosQuery ?? []) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>

            <form id="form-remesa" method="POST" action="{{ $formAction }}" autocomplete="off">
                @csrf
                @if ($formMethod === 'PUT')
                    @method('PUT')
                @endif

                <div class="card-body">
                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $empresa_id,
                        'solo_lectura' => ! empty($modo_edicion),
                        'col_label' => 'col-lg-2 text-right pr-2',
                        'col_input' => 'col-lg-6',
                    ])
                    <div class="form-group row">
                        <label for="fecha_remesa" class="col-lg-2 control-label text-right pr-2 requerido">Fecha</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha" id="fecha_remesa" class="form-control"
                                   value="{{ old('fecha', $fecha ?? date('Y-m-d')) }}"
                                   max="{{ date('Y-m-d') }}" {{ $soloLectura ? 'readonly' : '' }} required>
                        </div>
                        <label for="tipo_remesa" class="col-lg-2 control-label text-right pr-2 requerido">Tipo</label>
                        <div class="col-lg-3">
                            <select name="tipo" id="tipo_remesa" class="form-control" {{ $soloLectura ? 'disabled' : '' }} required>
                                @foreach (RemesaSupport::enumTipo() as $opt)
                                    <option value="{{ $opt['valor'] }}"
                                        {{ old('tipo', $tipo ?? RemesaSupport::TIPO_EXTERNA) === $opt['valor'] ? 'selected' : '' }}>
                                        {{ $opt['nombre'] }}
                                    </option>
                                @endforeach
                            </select>
                            @if ($soloLectura)
                                <input type="hidden" name="tipo" value="{{ $tipo }}">
                            @endif
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="remito" class="col-lg-2 control-label text-right pr-2">Remito</label>
                        <div class="col-lg-3">
                            <input type="text" name="remito" id="remito" class="form-control"
                                   value="{{ old('remito', $remesa->remito ?? '') }}" maxlength="45" {{ $soloLectura ? 'readonly' : '' }}>
                        </div>
                        <label for="bolsa" class="col-lg-2 control-label text-right pr-2">Bolsa</label>
                        <div class="col-lg-3">
                            <input type="text" name="bolsa" id="bolsa" class="form-control"
                                   value="{{ old('bolsa', $remesa->bolsa ?? '') }}" maxlength="45" {{ $soloLectura ? 'readonly' : '' }}>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="precinto" class="col-lg-2 control-label text-right pr-2">Precinto</label>
                        <div class="col-lg-3">
                            <input type="text" name="precinto" id="precinto" class="form-control"
                                   value="{{ old('precinto', $remesa->precinto ?? '') }}" maxlength="45" {{ $soloLectura ? 'readonly' : '' }}>
                        </div>
                        <label for="observacion" class="col-lg-2 control-label text-right pr-2">Observaci&oacute;n</label>
                        <div class="col-lg-3">
                            <textarea name="observacion" id="observacion" class="form-control" rows="2" {{ $soloLectura ? 'readonly' : '' }}>{{ old('observacion', $remesa->observacion ?? '') }}</textarea>
                        </div>
                    </div>

                    <div class="remesa-workbench mt-3">
                        <div class="card card-outline card-info remesa-panel">
                            <div class="card-header py-2">
                                <strong>Destino (Debe)</strong>
                                <span class="text-muted small ml-2">{{ RemesaSupport::USO_DESTINO }}</span>
                            </div>
                            <div class="card-body p-0 table-responsive">
                                <table class="table table-sm table-bordered mb-0" id="tabla-destino">
                                    <thead>
                                        <tr>
                                            <th class="col-codigo">C&oacute;d.</th>
                                            <th>Cuenta</th>
                                            <th class="col-monto text-right">Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($destinoLineas as $idx => $linea)
                                        <tr data-moneda-id="{{ (int) ($linea['moneda_id'] ?? 1) }}" data-moneda-abrev="{{ $linea['moneda_abrev'] ?? '' }}">
                                            <td class="col-codigo">{{ $linea['codigo'] }}</td>
                                            <td title="{{ $linea['descripcion_operaciones'] ?? '' }}">{{ $linea['nombre'] }}</td>
                                            <td class="col-monto">
                                                <input type="hidden" name="destino_cuentacaja_ids[]" value="{{ $linea['cuentacaja_id'] }}">
                                                <input type="text"
                                                       name="destino_montos[]"
                                                       class="form-control form-control-sm text-right js-monto-ar js-linea-destino"
                                                       data-lado="destino"
                                                       data-moneda-id="{{ (int) ($linea['moneda_id'] ?? 1) }}"
                                                       value="{{ number_format((float) ($linea['monto'] ?? 0), 2, ',', '.') }}"
                                                       {{ $soloLectura ? 'readonly tabindex="-1"' : '' }}>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr class="js-sin-lineas-destino">
                                            <td colspan="3" class="text-muted text-center py-3">Seleccione empresa y tipo para cargar cuentas destino.</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card card-outline card-info remesa-panel">
                            <div class="card-header py-2">
                                <strong>Origen / Haber</strong>
                                <span class="text-muted small ml-2" id="label-uso-origen">{{ $usoOrigenActual }}</span>
                            </div>
                            <div class="card-body p-0 table-responsive">
                                <table class="table table-sm table-bordered mb-0" id="tabla-origen">
                                    <thead>
                                        <tr>
                                            <th class="col-codigo">C&oacute;d.</th>
                                            <th>Cuenta</th>
                                            <th class="col-monto text-right">Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($origenLineas as $idx => $linea)
                                        <tr data-moneda-id="{{ (int) ($linea['moneda_id'] ?? 1) }}" data-moneda-abrev="{{ $linea['moneda_abrev'] ?? '' }}">
                                            <td class="col-codigo">{{ $linea['codigo'] }}</td>
                                            <td title="{{ $linea['descripcion_operaciones'] ?? '' }}">{{ $linea['nombre'] }}</td>
                                            <td class="col-monto">
                                                <input type="hidden" name="origen_cuentacaja_ids[]" value="{{ $linea['cuentacaja_id'] }}">
                                                <input type="text"
                                                       name="origen_montos[]"
                                                       class="form-control form-control-sm text-right js-monto-ar js-linea-origen"
                                                       data-lado="origen"
                                                       data-moneda-id="{{ (int) ($linea['moneda_id'] ?? 1) }}"
                                                       value="{{ number_format((float) ($linea['monto'] ?? 0), 2, ',', '.') }}"
                                                       {{ $soloLectura ? 'readonly tabindex="-1"' : '' }}>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr class="js-sin-lineas-origen">
                                            <td colspan="3" class="text-muted text-center py-3">Seleccione empresa y tipo para cargar cuentas origen.</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card card-outline card-secondary mt-3" id="card-preview-asiento">
                        <div class="card-header py-2">
                            <strong>Vista previa asiento</strong>
                            <span class="text-muted small ml-2">(solo remesa externa)</span>
                        </div>
                        <div class="card-body py-2" id="remesa-preview-asiento">
                            <p class="text-muted mb-0">Complete montos para ver el preview.</p>
                        </div>
                    </div>
                </div>

                @if (! $soloLectura)
                <div class="remesa-acciones-fijas d-flex flex-wrap align-items-center">
                    <div class="totales-grid mr-3">
                        <div class="tot-item" id="tot-destino-wrap">
                            <span class="lbl">Total destino</span>
                            <span class="val" id="tot-destino">{{ number_format((float) ($totales['destino'] ?? 0), 2, ',', '.') }}</span>
                        </div>
                        <div class="tot-item" id="tot-origen-wrap">
                            <span class="lbl">Total origen</span>
                            <span class="val" id="tot-origen">{{ number_format((float) ($totales['origen'] ?? 0), 2, ',', '.') }}</span>
                        </div>
                        <div class="tot-item" id="tot-diferencia-wrap">
                            <span class="lbl">Diferencia</span>
                            <span class="val" id="tot-diferencia">0,00</span>
                        </div>
                    </div>
                    <div class="ml-auto">
                        <button type="submit" class="btn btn-success" id="btn-guardar-remesa">
                            <i class="fa fa-save"></i> Guardar
                        </button>
                    </div>
                </div>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection
