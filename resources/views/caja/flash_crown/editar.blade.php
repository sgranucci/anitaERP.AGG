@extends("theme.$theme.layout")
@section('titulo')
    Editar Flash
@endsection

@section('styles')
<style>
.fc-edit .form-group { margin-bottom: 0; }
.fc-edit .card-outline { margin-bottom: .75rem; }
.fc-edit .card-header { padding: .4rem .75rem; }
.fc-edit .card-header .card-title { font-size: .9rem; margin: 0; }
.fc-edit .card-body { padding: .4rem .55rem; }
.fc-edit table { margin-bottom: 0; font-size: .82rem; }
.fc-edit table td, .fc-edit table th { padding: .22rem .4rem; vertical-align: middle; }
.fc-edit .form-control-sm { height: calc(1.5em + .4rem + 2px); padding: .15rem .35rem; font-size: .82rem; }
.fc-edit .online { color: #7f8c8d; font-size: .75rem; white-space: nowrap; }
.fc-kpi { display: flex; flex-wrap: wrap; gap: .5rem; }
.fc-kpi .kpi {
    background: #f4f9fc; border: 1px solid #d6eaf8; border-radius: .35rem;
    padding: .35rem .75rem; min-width: 8rem;
}
.fc-kpi .kpi .lbl { display: block; font-size: .7rem; color: #5d6d7e; text-transform: uppercase; }
.fc-kpi .kpi .val { font-weight: 600; font-size: 1rem; color: #1a5276; }
.fc-kpi .kpi.ok { border-color: #abebc6; background: #eafaf1; }
.fc-kpi .kpi.ok .val { color: #196f3d; }
</style>
@endsection

@section('contenido')
@php
    $grupos = [
        'mesas' => [
            'titulo' => 'Mesas',
            'icono' => 'fa-th',
            'campos' => [
                'att' => 'Attendance',
                'cashdrop' => 'Drop mesas',
                'cant_mesas' => 'Cant. mesas',
                'caja_empleado' => 'Caja empleados',
                'wheel_r' => 'Wheel',
                'baccarat_r' => 'Baccarat',
                'blackjack_r' => 'Blackjack',
                'multi_r' => 'Multi color',
                'crap_r' => 'Craps',
                'caribbean_r' => 'Caribbean',
                'poker_r' => 'Poker',
                'seven_r' => 'Siete y medio',
                'super_peek_r' => 'Super Peek',
            ],
        ],
        'slots' => [
            'titulo' => 'Slots / Ruleta el.',
            'icono' => 'fa-desktop',
            'campos' => [
                'coin_in' => 'Coin in',
                'coin_out' => 'Coin out',
                'pago_manual' => 'Pago manual',
                'slot_r' => 'Win slots',
                'slot_d' => 'Drop / soft slots',
                'soft_count' => 'Soft count',
                'cant_slots' => 'Cant. slots',
                'cash_less' => 'Cash less',
                'ruleta_el_d' => 'Ruleta el. drop',
                'ruleta_el_r' => 'Ruleta el. win',
            ],
        ],
        'online' => [
            'titulo' => 'Casinos del Río online',
            'icono' => 'fa-globe',
            'campos' => [
                'ol_att' => 'Attendance',
                'ol_drop' => 'Drop',
                'ol_coin_in' => 'Coin in (apostado)',
                'ol_cage_bal' => 'Cage bal. (pagado)',
                'ol_win' => 'Win',
            ],
        ],
        'betsur' => [
            'titulo' => 'Betsur online',
            'icono' => 'fa-globe',
            'campos' => [
                'bs_att' => 'Attendance',
                'bs_coin_in' => 'Coin in (apostado)',
                'bs_cage_bal' => 'Cage bal. (pagado)',
                'bs_win' => 'Win',
            ],
        ],
        'otros' => [
            'titulo' => 'Torneos / Hospitality',
            'icono' => 'fa-cutlery',
            'campos' => [
                'torneo_poker' => 'Torneo THP',
                'master_cup' => 'Master Cup',
                'inscripcion' => 'Inscripciones',
                'premio' => 'Premios',
                'ayb' => 'F&B',
                'hotel' => 'Hotel',
            ],
        ],
    ];
    $enteros = ['att', 'cant_mesas', 'cant_slots', 'cant_rul_el', 'ol_att', 'bs_att'];
@endphp
<div class="row fc-edit">
    <div class="col-12">
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title mb-0">
                    Flash —
                    {{ $flash->sala->codigo ?? '' }} {{ $flash->sala->nombre ?? '' }}
                    — {{ $flash->fecha->format('d/m/Y') }}
                    @if ($flash->estaValidado())
                        <span class="badge badge-success ml-1">Validado</span>
                    @endif
                </h3>
                <div class="card-tools">
                    <a href="{{ route('flash_crown', ['fecha' => $flash->fecha->format('Y-m-d'), 'consultar' => 1]) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <div class="card-body py-2">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-2" style="gap:.5rem;">
                    <div class="fc-kpi">
                        <div class="kpi ok">
                            <span class="lbl">Combined</span>
                            <span class="val">{{ number_format((float) ($metricas['combined'] ?? 0), 2, ',', '.') }}</span>
                        </div>
                        <div class="kpi">
                            <span class="lbl">Año ant. mismo día</span>
                            <span class="val">{{ number_format((float) ($metricas['combined_anterior'] ?? 0), 2, ',', '.') }}</span>
                        </div>
                        <div class="kpi">
                            <span class="lbl">Variación</span>
                            <span class="val">{{ number_format((float) ($metricas['variacion_anterior'] ?? 0), 2, ',', '.') }}%</span>
                        </div>
                    </div>
                    <div class="text-muted small">
                        Online = cálculo vivo · Override = valor manual (check)
                    </div>
                </div>

                @foreach (($live['avisos'] ?? []) as $aviso)
                    <div class="alert alert-warning py-1 mb-2">{{ $aviso }}</div>
                @endforeach

                <form method="POST" action="{{ route('actualizar_flash_crown', $flash->id) }}" id="form-flash-crown" class="form-horizontal" data-enter-campos="1">
                    @csrf
                    @method('PUT')
                    <div class="row">
                        @foreach ($grupos as $grupo)
                            <div class="col-lg-4">
                                <div class="card card-outline card-info">
                                    <div class="card-header">
                                        <h3 class="card-title"><i class="fa {{ $grupo['icono'] }} mr-1"></i> {{ $grupo['titulo'] }}</h3>
                                    </div>
                                    <div class="card-body p-0">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead style="background:#85C1E9;color:#17202A;">
                                                <tr>
                                                    <th>Campo</th>
                                                    <th class="text-right">Online</th>
                                                    <th class="text-right" style="width:7.5rem;">Valor</th>
                                                    <th class="text-center" style="width:2.2rem;" title="Override">O</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($grupo['campos'] as $key => $label)
                                                    @php
                                                        $liveVal = $live[$key] ?? 0;
                                                        $val = old($key, $flash->{$key});
                                                        $esOverride = array_key_exists($key, $overrides);
                                                        $esEntero = in_array($key, $enteros, true);
                                                    @endphp
                                                    <tr>
                                                        <td>{{ $label }}</td>
                                                        <td class="text-right online">
                                                            {{ $esEntero || is_int($liveVal) ? number_format((int) $liveVal, 0, ',', '.') : number_format((float) $liveVal, 2, ',', '.') }}
                                                        </td>
                                                        <td>
                                                            <input type="text" name="{{ $key }}" class="form-control form-control-sm text-right"
                                                                   value="{{ is_numeric($val) ? ($esEntero ? (int) $val : number_format((float) $val, 2, '.', '')) : $val }}">
                                                        </td>
                                                        <td class="text-center">
                                                            <input type="checkbox" name="marcar_{{ $key }}" value="1" {{ $esOverride ? 'checked' : '' }} title="Override manual">
                                                            <input type="hidden" name="override_{{ $key }}" value="{{ $esOverride ? '1' : '' }}">
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="form-group row mt-1 mb-0">
                        <label for="comentario" class="col-lg-1 col-form-label text-right pr-2 small">Comentario</label>
                        <div class="col-lg-11">
                            <input type="text" name="comentario" id="comentario" class="form-control form-control-sm" maxlength="80" value="{{ old('comentario', $flash->comentario) }}">
                        </div>
                    </div>
                </form>
            </div>
            <div class="card-footer py-2">
                <button type="submit" form="form-flash-crown" class="btn btn-primary btn-sm">
                    <i class="fa fa-save"></i> Guardar overrides
                </button>
                <form method="post" action="{{ route('recalcular_flash_crown', $flash->id) }}" class="d-inline" onsubmit="return confirm('¿Recalcular y borrar overrides?');">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Recalcular online</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('#form-flash-crown input[type=checkbox][name^="marcar_"]').forEach(function (cb) {
    cb.addEventListener('change', function () {
        var key = cb.name.replace('marcar_', '');
        var hidden = document.querySelector('input[name="override_' + key + '"]');
        if (hidden) hidden.value = cb.checked ? '1' : '';
    });
});
</script>
@endsection
