@extends("theme.$theme.layout")

@section('titulo')
    Configuraci&oacute;n facturaci&oacute;n juego
@endsection

@section('contenido')
<div class="row">
    <div class="col-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Configuraci&oacute;n facturaci&oacute;n juego</h3>
                <div class="card-tools">
                    <a href="{{ route('facturacion_juego') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al proceso
                    </a>
                </div>
            </div>
            <div class="card-body">
                @include('includes.mensaje')
                @include('includes.form-error')

                <form method="post" action="{{ route('guardar_facturacion_juego_configuracion') }}" data-enter-campos="1">
                    @csrf

                    <h5 class="mb-3">Tipos de comprobante (defaults Anita p-resultado.dft + FOL/COL)</h5>
                    <div class="form-row">
                        @foreach ([
                            'tipo_gan_mesa' => 'Ganancia mesas',
                            'tipo_per_mesa' => 'P&eacute;rdida mesas',
                            'tipo_gan_slot' => 'Ganancia slots',
                            'tipo_per_slot' => 'P&eacute;rdida slots',
                            'tipo_gan_ruleta_el' => 'Ganancia ruleta el.',
                            'tipo_per_ruleta_el' => 'P&eacute;rdida ruleta el.',
                            'tipo_gan_online' => 'Ganancia on-line (FOL)',
                            'tipo_per_online' => 'P&eacute;rdida on-line (COL)',
                        ] as $campo => $label)
                            <div class="form-group col-md-2">
                                <label class="control-label">{!! $label !!}</label>
                                <input type="text" name="{{ $campo }}" maxlength="3" class="form-control text-uppercase"
                                    value="{{ old($campo, $parametro->$campo ?? '') }}">
                            </div>
                        @endforeach
                        <div class="form-group col-md-1">
                            <label class="control-label">Letra</label>
                            <input type="text" name="letra" maxlength="1" class="form-control text-uppercase"
                                value="{{ old('letra', $parametro->letra) }}">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="control-label">Tipo asiento</label>
                            <input type="text" name="tipoasiento_abreviatura" maxlength="10" class="form-control text-uppercase"
                                value="{{ old('tipoasiento_abreviatura', $parametro->tipoasiento_abreviatura) }}">
                        </div>
                    </div>

                    <h5 class="mt-3 mb-2">Puntos de venta on-line / Betsur (FOL/COL)</h5>
                    <p class="text-muted small mb-2">
                        Se eligen con el modal est&aacute;ndar (F1 / lupa). Al emitir FOL/COL se usa el c&oacute;digo del PV
                        (67 Del R&iacute;o, 68 Betsur).
                    </p>
                    @include('ventas.partials.campo_consulta_puntoventa', [
                        'prefix' => 'fj_del_rio',
                        'label' => 'PV Del R&iacute;o online',
                        'layout' => 'form_row',
                        'inputName' => 'puntoventa_del_rio_id',
                        'inputId' => 'puntoventa_del_rio_id',
                        'puntoventaId' => $pvDelRio['id'] ?? '',
                        'codigo' => $pvDelRio['codigo'] ?? '',
                        'nombre' => $pvDelRio['nombre'] ?? '',
                        'col_label' => 'col-lg-3 control-label text-right pr-2',
                        'col_input' => 'col-lg-6',
                        'required' => true,
                    ])
                    @include('ventas.partials.campo_consulta_puntoventa', [
                        'prefix' => 'fj_betsur',
                        'label' => 'PV Betsur',
                        'layout' => 'form_row',
                        'inputName' => 'puntoventa_betsur_id',
                        'inputId' => 'puntoventa_betsur_id',
                        'puntoventaId' => $pvBetsur['id'] ?? '',
                        'codigo' => $pvBetsur['codigo'] ?? '',
                        'nombre' => $pvBetsur['nombre'] ?? '',
                        'col_label' => 'col-lg-3 control-label text-right pr-2',
                        'col_input' => 'col-lg-6',
                        'required' => true,
                    ])

                    <h5 class="mt-4 mb-2">Salas — PV Anita + c&oacute;digos de cuenta</h5>
                    <p class="text-muted small">
                        C&oacute;digos de cuenta de caja y contables (como en Anita). Se resuelven al guardar.
                        Impcont: mesas 500+suc, slots 700+suc, ruleta 800+suc.
                        Del R&iacute;o online y Betsur usan FOL/COL con PV 67/68 y las cuentas configuradas aqu&iacute;.
                    </p>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Sala</th>
                                    <th>Activa</th>
                                    <th>PV / suc.</th>
                                    <th>Cta. caja</th>
                                    <th>Cta. mesas</th>
                                    <th>Cta. slots</th>
                                    <th>Cta. ruleta</th>
                                    <th>Cta. Del R&iacute;o</th>
                                    <th>Cta. Betsur</th>
                                    <th>Centro costo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($salasCfg as $cfg)
                                    @php $sid = $cfg->id; @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $cfg->sala->codigo ?? '' }}</strong>
                                            <div class="small text-muted">{{ $cfg->sala->nombre ?? '' }}</div>
                                        </td>
                                        <td class="text-center">
                                            <input type="checkbox" name="salas[{{ $sid }}][activo]" value="1"
                                                @checked(old("salas.$sid.activo", $cfg->activo))>
                                        </td>
                                        <td style="max-width:90px;">
                                            <input type="number" class="form-control form-control-sm"
                                                name="salas[{{ $sid }}][sucursal_anita]"
                                                value="{{ old("salas.$sid.sucursal_anita", $cfg->sucursal_anita) }}">
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm"
                                                name="salas[{{ $sid }}][cuentacaja_codigo]"
                                                value="{{ old("salas.$sid.cuentacaja_codigo", $cfg->cuentacaja->codigo ?? '') }}"
                                                placeholder="C&oacute;d.">
                                            <div class="small text-muted">{{ $cfg->cuentacaja->nombre ?? '' }}</div>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm"
                                                name="salas[{{ $sid }}][cuentacontable_mesa_codigo]"
                                                value="{{ old("salas.$sid.cuentacontable_mesa_codigo", $cfg->cuentacontableMesa->codigo ?? '') }}"
                                                placeholder="C&oacute;d.">
                                            <div class="small text-muted">{{ \Illuminate\Support\Str::limit($cfg->cuentacontableMesa->nombre ?? '', 28) }}</div>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm"
                                                name="salas[{{ $sid }}][cuentacontable_slot_codigo]"
                                                value="{{ old("salas.$sid.cuentacontable_slot_codigo", $cfg->cuentacontableSlot->codigo ?? '') }}"
                                                placeholder="C&oacute;d.">
                                            <div class="small text-muted">{{ \Illuminate\Support\Str::limit($cfg->cuentacontableSlot->nombre ?? '', 28) }}</div>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm"
                                                name="salas[{{ $sid }}][cuentacontable_ruleta_el_codigo]"
                                                value="{{ old("salas.$sid.cuentacontable_ruleta_el_codigo", $cfg->cuentacontableRuletaEl->codigo ?? '') }}"
                                                placeholder="C&oacute;d.">
                                            <div class="small text-muted">{{ \Illuminate\Support\Str::limit($cfg->cuentacontableRuletaEl->nombre ?? '', 28) }}</div>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm"
                                                name="salas[{{ $sid }}][cuentacontable_del_rio_codigo]"
                                                value="{{ old("salas.$sid.cuentacontable_del_rio_codigo", $cfg->cuentacontableDelRio->codigo ?? '') }}"
                                                placeholder="C&oacute;d.">
                                            <div class="small text-muted">{{ \Illuminate\Support\Str::limit($cfg->cuentacontableDelRio->nombre ?? '', 28) }}</div>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm"
                                                name="salas[{{ $sid }}][cuentacontable_betsur_codigo]"
                                                value="{{ old("salas.$sid.cuentacontable_betsur_codigo", $cfg->cuentacontableBetsur->codigo ?? '') }}"
                                                placeholder="C&oacute;d.">
                                            <div class="small text-muted">{{ \Illuminate\Support\Str::limit($cfg->cuentacontableBetsur->nombre ?? '', 28) }}</div>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm"
                                                name="salas[{{ $sid }}][centrocosto_codigo]"
                                                value="{{ old("salas.$sid.centrocosto_codigo", $cfg->centrocosto->codigo ?? '') }}"
                                                placeholder="C&oacute;d.">
                                            <div class="small text-muted">{{ \Illuminate\Support\Str::limit($cfg->centrocosto->nombre ?? '', 28) }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa fa-save"></i> Guardar configuraci&oacute;n
                    </button>
                </form>

                <form method="post" action="{{ route('sembrar_facturacion_juego_anita') }}" class="mt-3"
                    onsubmit="return confirm('¿Leer impcont/sucursal de Anita y completar cuentas faltantes?');">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-cloud-download"></i> Sembrar cuentas desde Anita
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@include('includes.ventas.modalconsultapuntoventa')
<script src="{{ asset('assets/pages/scripts/ventas/puntoventa/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/puntoventa/consulta.js')) ?: time() }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.activa_eventos_consultapuntoventa === 'function') {
        window.activa_eventos_consultapuntoventa();
    }
});
</script>
@endsection
