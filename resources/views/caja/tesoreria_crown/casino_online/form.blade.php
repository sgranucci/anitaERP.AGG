@extends("theme.$theme.layout")

@section('titulo')
    {{ $edicion ? 'Editar casino online diario' : 'Cargar casino online diario' }}
@endsection

@section('styles')
<style>
.co-form .form-group { margin-bottom: .45rem; }
.co-form .control-label { font-size: .85rem; padding-top: .35rem; }
.co-form .card-outline { margin-bottom: .75rem; }
.co-form .card-header { padding: .4rem .75rem; }
.co-form .card-header .card-title { font-size: .95rem; margin: 0; }
.co-form .card-body { padding: .65rem .75rem .35rem; }
.co-form .ro-calc { background: #eef6fb !important; }
.co-kpi { display: flex; flex-wrap: wrap; gap: .5rem; }
.co-kpi .kpi {
    background: #f4f9fc; border: 1px solid #d6eaf8; border-radius: .35rem;
    padding: .35rem .7rem; min-width: 7.5rem;
}
.co-kpi .kpi .lbl { display: block; font-size: .7rem; color: #5d6d7e; text-transform: uppercase; }
.co-kpi .kpi .val { font-weight: 600; font-size: .95rem; color: #1a5276; }
.co-hero-btn {
    background: linear-gradient(135deg, #8e44ad 0%, #2980b9 55%, #16a085 100%);
    border: 0; color: #fff; font-weight: 600;
}
</style>
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
    <script>
    (function () {
        function numero(id) {
            var el = document.getElementById(id);
            if (!el) { return 0; }
            var texto = String(el.value || '').trim();
            if (texto.indexOf(',') >= 0 && texto.indexOf('.') >= 0) {
                texto = texto.replace(/\./g, '').replace(',', '.');
            } else {
                texto = texto.replace(',', '.');
            }
            var valor = parseFloat(texto);
            return isNaN(valor) ? 0 : valor;
        }
        function texto(valor, decimales) {
            return valor.toLocaleString('es-AR', { minimumFractionDigits: decimales, maximumFractionDigits: decimales });
        }
        function setCalc(id, valor, decimales) {
            var el = document.getElementById(id);
            if (el) { el.value = texto(valor, decimales); }
        }
        function setKpi(id, valor, decimales) {
            var el = document.getElementById(id);
            if (el) { el.textContent = texto(valor, decimales); }
        }
        function porcentaje(apostado, resultado) {
            if (Math.abs(apostado) < 0.00001) { return 0; }
            return (resultado / apostado) * 100;
        }
        function bloque(prefijo, apostadoId, pagadoId, resId, pctId, kpiId) {
            var res = numero(apostadoId) - numero(pagadoId);
            var pct = porcentaje(numero(apostadoId), res);
            setCalc(resId, res, 2);
            setCalc(pctId, pct, 2);
            setKpi(kpiId, res, 2);
        }
        function recalcular() {
            bloque('ol', 'apostado', 'pagado', 'resultado_casino', 'porcentaje_casino', 'kpi-del-rio');
            bloque('bs', 'betsur_apostado', 'betsur_pagado', 'betsur_resultado_calc', 'betsur_porcentaje_calc', 'kpi-betsur');
            bloque('ad', 'ad_apostado', 'ad_pagado', 'ad_resultado_calc', 'ad_porcentaje_calc', 'kpi-ad');
        }
        document.querySelectorAll('.js-casino-online, .js-betsur-online, .js-ad-online').forEach(function (el) {
            el.addEventListener('input', recalcular);
        });
        recalcular();
    })();
    </script>
@endsection

@section('contenido')
@include('includes.mensaje')
@include('includes.form-error')
@php
    $delRioIni = (float) ($fila->resultado ?? 0);
    $delRioPctIni = (float) ($fila->porcentaje ?? 0);
    $betsurIni = (float) ($fila->betsur_resultado ?? 0);
    $betsurPctIni = (float) ($fila->betsur_porcentaje ?? 0);
    $adIni = (float) ($fila->ad_resultado ?? 0);
    $adPctIni = (float) ($fila->ad_porcentaje ?? 0);
@endphp
<div class="row">
    <div class="col-12">
        <div class="card card-primary co-form">
            <div class="card-header">
                <h3 class="card-title mb-0">
                    <i class="fa fa-globe mr-1"></i>
                    {{ $edicion ? 'Editar' : 'Cargar' }} casino online diario
                </h3>
                <div class="card-tools">
                    <a href="{{ route('tesoreria_crown_casino_online') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <form method="POST" action="{{ route('guardar_tesoreria_crown_casino_online') }}" class="form-horizontal" data-enter-campos="1">
                @csrf
                <input type="hidden" name="id" value="{{ $edicion ? $fila->id : '' }}">
                <div class="card-body py-2">
                    <div class="d-flex flex-wrap align-items-end justify-content-between mb-2" style="gap:.75rem;">
                        <div>
                            <label for="fecha" class="mb-0 small text-muted d-block">Fecha</label>
                            <input type="date" name="fecha" id="fecha" class="form-control form-control-sm" style="width:10.5rem;" required value="{{ old('fecha', optional($fila->fecha)->format('Y-m-d') ?? $fila->fecha) }}">
                        </div>
                        <div class="co-kpi">
                            <div class="kpi">
                                <span class="lbl">Del Río online</span>
                                <span class="val" id="kpi-del-rio">{{ number_format($delRioIni, 2, ',', '.') }}</span>
                            </div>
                            <div class="kpi">
                                <span class="lbl">Betsur online</span>
                                <span class="val" id="kpi-betsur">{{ number_format($betsurIni, 2, ',', '.') }}</span>
                            </div>
                            <div class="kpi">
                                <span class="lbl">Apuestas deportivas</span>
                                <span class="val" id="kpi-ad">{{ number_format($adIni, 2, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small mb-2">
                        Una sola carga por día para todas las salas. Resultado = apostado − pagado; porcentaje = resultado / apostado × 100.
                    </p>

                    <div class="row">
                        <div class="col-lg-4">
                            <div class="card card-outline card-secondary">
                                <div class="card-header"><h3 class="card-title"><i class="fa fa-globe mr-1"></i> Casinos del Río online</h3></div>
                                <div class="card-body">
                                    <p class="small text-muted mb-2">Sucursal Anita 67.</p>
                                    @foreach ([
                                        'apostado' => 'Apostado',
                                        'pagado' => 'Pagado',
                                        'drop' => 'Drop',
                                        'poder_publico' => 'Poder público',
                                    ] as $campo => $etiqueta)
                                        <div class="form-group row">
                                            <label for="{{ $campo }}" class="col-5 control-label text-right pr-2">{{ $etiqueta }}</label>
                                            <div class="col-7">
                                                <input type="text" inputmode="decimal" name="{{ $campo }}" id="{{ $campo }}" class="form-control form-control-sm text-right js-casino-online" value="{{ old($campo, number_format((float) ($fila->{$campo} ?? 0), 2, ',', '.')) }}">
                                            </div>
                                        </div>
                                    @endforeach
                                    <div class="form-group row">
                                        <label for="attendance" class="col-5 control-label text-right pr-2">Attendance</label>
                                        <div class="col-7">
                                            <input type="number" name="attendance" id="attendance" class="form-control form-control-sm text-right" step="1" min="0" inputmode="numeric" value="{{ old('attendance', (int) ($fila->attendance ?? 0)) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="resultado_casino" class="col-5 control-label text-right pr-2">Resultado</label>
                                        <div class="col-7">
                                            <input type="text" id="resultado_casino" class="form-control form-control-sm text-right ro-calc" readonly tabindex="-1" value="{{ number_format($delRioIni, 2, ',', '.') }}">
                                        </div>
                                    </div>
                                    <div class="form-group row mb-0">
                                        <label for="porcentaje_casino" class="col-5 control-label text-right pr-2">Porcentaje</label>
                                        <div class="col-7">
                                            <input type="text" id="porcentaje_casino" class="form-control form-control-sm text-right ro-calc" readonly tabindex="-1" value="{{ number_format($delRioPctIni, 2, ',', '.') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card card-outline card-warning">
                                <div class="card-header"><h3 class="card-title"><i class="fa fa-globe mr-1"></i> Betsur online</h3></div>
                                <div class="card-body">
                                    <p class="small text-muted mb-2">Sucursal Anita 68.</p>
                                    @foreach ([
                                        'betsur_apostado' => 'Apostado',
                                        'betsur_pagado' => 'Pagado',
                                        'betsur_drop' => 'Drop',
                                        'betsur_poder_publico' => 'Poder público',
                                    ] as $campo => $etiqueta)
                                        <div class="form-group row">
                                            <label for="{{ $campo }}" class="col-5 control-label text-right pr-2">{{ $etiqueta }}</label>
                                            <div class="col-7">
                                                <input type="text" inputmode="decimal" name="{{ $campo }}" id="{{ $campo }}" class="form-control form-control-sm text-right js-betsur-online" value="{{ old($campo, number_format((float) ($fila->{$campo} ?? 0), 2, ',', '.')) }}">
                                            </div>
                                        </div>
                                    @endforeach
                                    <div class="form-group row">
                                        <label for="betsur_attendance" class="col-5 control-label text-right pr-2">Attendance</label>
                                        <div class="col-7">
                                            <input type="number" name="betsur_attendance" id="betsur_attendance" class="form-control form-control-sm text-right" step="1" min="0" inputmode="numeric" value="{{ old('betsur_attendance', (int) ($fila->betsur_attendance ?? 0)) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="betsur_resultado_calc" class="col-5 control-label text-right pr-2">Resultado</label>
                                        <div class="col-7">
                                            <input type="text" id="betsur_resultado_calc" class="form-control form-control-sm text-right ro-calc" readonly tabindex="-1" value="{{ number_format($betsurIni, 2, ',', '.') }}">
                                        </div>
                                    </div>
                                    <div class="form-group row mb-0">
                                        <label for="betsur_porcentaje_calc" class="col-5 control-label text-right pr-2">Porcentaje</label>
                                        <div class="col-7">
                                            <input type="text" id="betsur_porcentaje_calc" class="form-control form-control-sm text-right ro-calc" readonly tabindex="-1" value="{{ number_format($betsurPctIni, 2, ',', '.') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card card-outline card-success">
                                <div class="card-header"><h3 class="card-title"><i class="fa fa-futbol-o mr-1"></i> Apuestas deportivas</h3></div>
                                <div class="card-body">
                                    <p class="small text-muted mb-2">Mismos datos que Casino / Betsur.</p>
                                    @foreach ([
                                        'ad_apostado' => 'Apostado',
                                        'ad_pagado' => 'Pagado',
                                        'ad_drop' => 'Drop',
                                        'ad_poder_publico' => 'Poder público',
                                    ] as $campo => $etiqueta)
                                        <div class="form-group row">
                                            <label for="{{ $campo }}" class="col-5 control-label text-right pr-2">{{ $etiqueta }}</label>
                                            <div class="col-7">
                                                <input type="text" inputmode="decimal" name="{{ $campo }}" id="{{ $campo }}" class="form-control form-control-sm text-right js-ad-online" value="{{ old($campo, number_format((float) ($fila->{$campo} ?? 0), 2, ',', '.')) }}">
                                            </div>
                                        </div>
                                    @endforeach
                                    <div class="form-group row">
                                        <label for="ad_attendance" class="col-5 control-label text-right pr-2">Attendance</label>
                                        <div class="col-7">
                                            <input type="number" name="ad_attendance" id="ad_attendance" class="form-control form-control-sm text-right" step="1" min="0" inputmode="numeric" value="{{ old('ad_attendance', (int) ($fila->ad_attendance ?? 0)) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="ad_resultado_calc" class="col-5 control-label text-right pr-2">Resultado</label>
                                        <div class="col-7">
                                            <input type="text" id="ad_resultado_calc" class="form-control form-control-sm text-right ro-calc" readonly tabindex="-1" value="{{ number_format($adIni, 2, ',', '.') }}">
                                        </div>
                                    </div>
                                    <div class="form-group row mb-0">
                                        <label for="ad_porcentaje_calc" class="col-5 control-label text-right pr-2">Porcentaje</label>
                                        <div class="col-7">
                                            <input type="text" id="ad_porcentaje_calc" class="form-control form-control-sm text-right ro-calc" readonly tabindex="-1" value="{{ number_format($adPctIni, 2, ',', '.') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer py-2">
                    <button type="submit" class="btn btn-sm co-hero-btn"><i class="fa fa-save"></i> Guardar</button>
                    <a href="{{ route('tesoreria_crown_casino_online') }}" class="btn btn-outline-secondary btn-sm">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
