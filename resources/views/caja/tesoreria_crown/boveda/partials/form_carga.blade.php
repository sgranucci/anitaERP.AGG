@php
    $monedas = $monedas ?? ['ARS', 'USD', 'EUR'];
    $monedasPesos = $monedasPesos ?? [];
    $fechaJornada = $fechaJornada ?? null;
    $referencia = $referencia ?? '';
    $editable = ! empty($editable);
    $puedeConfirmar = ! empty($puedeConfirmar);
    $cantidades = $cantidades ?? collect();
    $totales = [];
    foreach ($grupos as $grupo) {
        $totalGrupo = 0.0;
        foreach ($grupo['filas'] as $filaTotal) {
            $totalGrupo += (float) $filaTotal->denominacion * (float) ($cantidades[$filaTotal->id] ?? 0);
        }
        $totales[$grupo['codigo']] = $totalGrupo;
    }
@endphp
@include('caja.tesoreria_crown.partials.campo_consulta_boveda_tipo', [
    'editable' => $editable,
    'required' => $editable,
    'tipoId' => $tipoId ?? '',
    'tipoCodigo' => $tipoCodigo ?? '',
    'tipoNombre' => $tipoNombre ?? '',
    'efecto' => $efecto ?? '',
    'monedas' => $monedas,
    'referencia' => $referencia,
])
@php
    $subtipos = $subtipos ?? collect();
    $subtipoId = old('subtipo_id', $subtipoId ?? '');
    $subtipoTexto = old('subtipo_texto', $subtipoTexto ?? '');
@endphp
<div class="form-group row tc-boveda-subtipo {{ $subtipos->isEmpty() ? 'd-none' : '' }}" id="tc-boveda-subtipo-wrap"
     data-url="{{ route('subtipos_tesoreria_crown_kardex_valores') }}">
    <label for="subtipo_id" class="col-lg-3 control-label text-right pr-2{{ $editable ? ' requerido' : '' }}">Subtipo</label>
    <div class="col-lg-8">
        @if ($editable)
            <select name="subtipo_id" id="subtipo_id" class="form-control" {{ $subtipos->isEmpty() ? '' : 'required' }}>
                <option value="">Elegí…</option>
                @foreach ($subtipos as $st)
                    <option value="{{ $st->id }}"
                            data-codigo="{{ $st->codigo }}"
                            data-mueve-billetaje="{{ ($st->mueve_billetaje ?? true) ? '1' : '0' }}"
                            @selected((int) $subtipoId === (int) $st->id)>
                        {{ $st->etiqueta() }}
                    </option>
                @endforeach
            </select>
            <input type="text" name="subtipo_texto" id="subtipo_texto" class="form-control mt-2 {{ strtoupper((string) optional($subtipos->firstWhere('id', (int) $subtipoId))->codigo) === 'OTR' ? '' : 'd-none' }}"
                   maxlength="80" placeholder="Detalle del subtipo (obligatorio si es Otro)"
                   value="{{ $subtipoTexto }}">
            <small id="tc-boveda-subtipo-aviso" class="form-text text-muted d-none">
                Este subtipo no mueve billetaje: queda asentado solo a fines informativos (RRHH).
            </small>
        @else
            @php $stSel = $subtipos->firstWhere('id', (int) $subtipoId); @endphp
            <p class="form-control-plaintext mb-0">
                {{ $stSel?->etiqueta() ?? '—' }}
                @if ($subtipoTexto !== '')
                    · {{ $subtipoTexto }}
                @endif
                @if ($stSel && ! ($stSel->mueve_billetaje ?? true))
                    <span class="d-block text-muted">Sin billetaje (informativo RRHH).</span>
                @endif
            </p>
        @endif
    </div>
</div>
@if ((int) ($empresaLegajoId ?? 0) > 0)
    <input type="hidden" name="empresa_id" value="{{ (int) $empresaLegajoId }}">
@endif
<div class="tc-efeb-ref {{ $referencia === 'legajo' ? '' : 'is-off' }}" data-ref="legajo">
    @include('sueldos.partials.campo_consulta_empleado_sueldos', [
        'prefix' => 'boveda',
        'layout' => 'form_row',
        'label' => 'Legajo',
        'inputName' => 'legajo',
        'idInputName' => '',
        'legajo' => old('legajo', $legajo),
        'empleadoId' => $empleadoId,
        'nombre' => $empleadoNombre,
        'col_label' => 'col-lg-3 control-label text-right pr-2',
        'col_input' => 'col-lg-8',
        'required' => true,
    ])
</div>
<div class="tc-efeb-ref {{ $referencia === 'cliente' ? '' : 'is-off' }}" data-ref="cliente">
    @include('produccion.partials.campo_consulta_cliente', [
        'prefix' => 'boveda',
        'label' => 'Cliente',
        'inputName' => 'cliente_id',
        'inputId' => 'boveda_cliente_id',
        'clienteId' => old('cliente_id', $clienteId),
        'codigo' => $clienteCodigo,
        'descripcion' => $clienteNombre,
        'col_label' => 'col-lg-3 control-label text-right pr-2',
        'col_input' => 'col-lg-8',
        'required' => true,
        'help' => '',
    ])
</div>
<div class="form-group row">
    <label for="comentario" class="col-lg-3 control-label text-right pr-2{{ $editable ? ' requerido' : '' }}">Comentario</label>
    <div class="col-lg-8">
        @if ($editable)
            <input type="text" name="comentario" id="comentario" class="form-control" maxlength="80" required value="{{ old('comentario', $comentario) }}">
        @else
            <p class="form-control-plaintext mb-0">{{ $comentario }}</p>
        @endif
    </div>
</div>
@if ($editable)
    <div class="border rounded px-3 py-2 mb-3 bg-light d-flex flex-wrap align-items-center" style="position:sticky;top:3.5rem;z-index:5;">
        @foreach ($grupos as $grupoBarra)
            <span class="tc-efeb-moneda mr-4 mb-1 {{ in_array($grupoBarra['codigo'], $monedas, true) ? '' : 'is-off' }}" data-mon="{{ $grupoBarra['codigo'] }}">
                <span class="text-muted">{{ $grupoBarra['titulo'] }}</span>
                <strong class="js-total-barra" data-grupo="{{ $grupoBarra['codigo'] }}">{{ number_format($totales[$grupoBarra['codigo']] ?? 0, 2, ',', '.') }}</strong>
            </span>
        @endforeach
        @if (! empty($efectivoPesos))
            <span class="ml-auto mb-1">
                <span class="text-muted">Total final $</span>
                <strong class="js-total-final">{{ number_format((float) $efectivoPesos['total'], 2, ',', '.') }}</strong>
            </span>
        @endif
    </div>
@endif
<div class="card card-outline card-info mb-3">
    <div class="card-header py-2">
        <h3 class="card-title mb-0">Billetes</h3>
    </div>
    <div class="card-body pb-2">
        <div class="row">
            @foreach ($grupos as $grupo)
                @php
                    $monedaPesos = $monedasPesos[$grupo['codigo']] ?? null;
                    $tasaTexto = '';
                    if ($monedaPesos && $monedaPesos['extranjera'] && ! $monedaPesos['falta']) {
                        $tasaNumero = (float) $monedaPesos['tasa'];
                        $decimalesTasa = abs($tasaNumero - round($tasaNumero, 2)) < 0.0000001 ? 2 : 4;
                        $tasaTexto = number_format($tasaNumero, $decimalesTasa, ',', '.');
                    }
                @endphp
                <div class="col-lg-4 col-md-6 mb-3 tc-efeb-moneda {{ in_array($grupo['codigo'], $monedas, true) ? '' : 'is-off' }}" data-mon="{{ $grupo['codigo'] }}">
                    <div class="card card-outline card-info h-100 mb-0 js-panel-valor" data-grupo="{{ $grupo['codigo'] }}" data-en-pesos="{{ $monedaPesos ? '1' : '0' }}" data-tasa="{{ $monedaPesos ? number_format((float) $monedaPesos['tasa'], 6, '.', '') : '0' }}">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0">{{ $grupo['titulo'] }}</h3>
                            <strong class="js-total-valor">{{ number_format($totales[$grupo['codigo']] ?? 0, 2, ',', '.') }}</strong>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Denominación</th>
                                        <th>Tipo</th>
                                        <th style="width:110px;">Cantidad</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($grupo['filas'] as $valor)
                                        @php
                                            $denominacion = (float) $valor->denominacion;
                                            $denominacionTexto = fmod($denominacion, 1.0) === 0.0
                                                ? number_format($denominacion, 0, ',', '.')
                                                : number_format($denominacion, 2, ',', '.');
                                            $cantidad = $cantidades[$valor->id] ?? null;
                                            $cantidadValor = old('cantidad.'.$valor->id, $cantidad === null || $cantidad === '' ? '' : (int) $cantidad);
                                        @endphp
                                        <tr>
                                            <td class="text-right">{{ $denominacionTexto }}</td>
                                            <td>{{ $valor->descripcion }}</td>
                                            <td>
                                                @if ($editable)
                                                    <input type="number" name="cantidad[{{ $valor->id }}]" class="form-control form-control-sm text-right" min="0" step="1" inputmode="numeric" data-denom="{{ $denominacion }}" value="{{ $cantidadValor }}" {{ in_array($grupo['codigo'], $monedas, true) ? '' : 'disabled' }}>
                                                @elseif ($cantidadValor !== '' && $cantidadValor !== null && (int) $cantidadValor !== 0)
                                                    {{ number_format((int) $cantidadValor, 0, ',', '.') }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer py-2 text-right">
                            Total <strong class="js-total-valor">{{ number_format($totales[$grupo['codigo']] ?? 0, 2, ',', '.') }}</strong>
                            @if ($monedaPesos && $monedaPesos['extranjera'])
                                <div class="text-muted small mt-1">
                                    @if ($monedaPesos['falta'])
                                        Sin cotización de venta para el {{ $fecha }}
                                    @else
                                        Cambio {{ $tasaTexto }}
                                        @if ($monedaPesos['fecha'] === $fechaJornada)
                                            del día
                                        @else
                                            del {{ \Carbon\Carbon::parse($monedaPesos['fecha'])->format('d/m/Y') }}
                                        @endif
                                        · En pesos <strong class="js-total-en-pesos" data-grupo="{{ $grupo['codigo'] }}">{{ number_format($monedaPesos['pesos'], 2, ',', '.') }}</strong>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@if (! empty($efectivoPesos))
    <div class="card card-outline card-primary mb-3">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Total en pesos</h3>
            <strong class="js-total-pesos">{{ number_format((float) $efectivoPesos['total'], 2, ',', '.') }}</strong>
        </div>
        <div class="card-body py-2">
            <p class="text-muted small mb-2">Pesos al nominal. Dólares y euros al cambio de venta de la jornada {{ $fecha }}.</p>
            @foreach ($efectivoPesos['monedas'] as $monedaLinea)
                <div class="d-flex justify-content-between align-items-baseline mb-1 tc-efeb-moneda {{ in_array($monedaLinea['codigo'], $monedas, true) ? '' : 'is-off' }}" data-mon="{{ $monedaLinea['codigo'] }}">
                    <span>
                        {{ $monedaLinea['titulo'] }}
                        @if ($monedaLinea['extranjera'] && ! $monedaLinea['falta'])
                            @php
                                $tasaLinea = (float) $monedaLinea['tasa'];
                                $decimalesLinea = abs($tasaLinea - round($tasaLinea, 2)) < 0.0000001 ? 2 : 4;
                            @endphp
                            <span class="text-muted small">
                                <span class="js-pesos-origen" data-grupo="{{ $monedaLinea['codigo'] }}">{{ number_format($monedaLinea['origen'], 2, ',', '.') }}</span>
                                × {{ number_format($tasaLinea, $decimalesLinea, ',', '.') }}
                                @if ($monedaLinea['fecha'] !== $fechaJornada)
                                    ({{ \Carbon\Carbon::parse($monedaLinea['fecha'])->format('d/m/Y') }})
                                @endif
                            </span>
                        @elseif ($monedaLinea['falta'])
                            <span class="text-danger small">Sin cotización de venta</span>
                        @endif
                    </span>
                    <strong class="js-pesos-linea" data-grupo="{{ $monedaLinea['codigo'] }}">{{ number_format($monedaLinea['pesos'], 2, ',', '.') }}</strong>
                </div>
            @endforeach
            @if (! $efectivoPesos['completo'])
                <p class="text-danger small mb-0 mt-2">Falta la cotización de una moneda extranjera. Esa moneda no entra en el total.</p>
            @endif
        </div>
    </div>
    <div class="border rounded px-3 py-2 mb-3 d-flex flex-wrap justify-content-between align-items-center" style="background:#EAF2F8;">
        <div class="mr-3">
            <strong>Total final $</strong>
            <div class="text-muted small">Pesos más dólares y euros al cambio de la jornada.</div>
        </div>
        <strong class="js-total-final h5 mb-0">{{ number_format((float) $efectivoPesos['total'], 2, ',', '.') }}</strong>
    </div>
@endif
@if ($editable && ! empty($ofrecerEmitir))
    <div class="form-group row">
        <div class="col-lg-8 offset-lg-3">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" name="emitir" id="emitir_comprobante" value="1" {{ old('emitir') ? 'checked' : '' }}>
                <label class="form-check-label" for="emitir_comprobante">Emitir comprobante al grabar</label>
            </div>
        </div>
    </div>
@endif
@if ($editable && $puedeConfirmar)
    <div class="form-group row">
        <label for="firma_usuario" class="col-lg-3 control-label text-right pr-2 requerido">Usuario</label>
        <div class="col-lg-3">
            <input type="text" name="firma_usuario" id="firma_usuario" class="form-control" autocomplete="off" required value="{{ old('firma_usuario') }}">
        </div>
    </div>
    <div class="form-group row">
        <label for="firma_password" class="col-lg-3 control-label text-right pr-2 requerido">Clave</label>
        <div class="col-lg-3">
            <input type="password" name="firma_password" id="firma_password" class="form-control" autocomplete="new-password" required>
        </div>
    </div>
    <div class="form-group row">
        <div class="col-lg-8 offset-lg-3">
            @if (! empty($urlConfirmar))
                <button type="submit" class="btn btn-primary btn-sm" formaction="{{ $urlConfirmar }}">Grabar</button>
            @else
                <button type="submit" class="btn btn-primary btn-sm">Grabar</button>
            @endif
        </div>
    </div>
@elseif ($editable)
    <div class="form-group row">
        <div class="col-lg-8 offset-lg-3">
            <button type="submit" class="btn btn-primary btn-sm">Guardar borrador</button>
        </div>
    </div>
@endif
