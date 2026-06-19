@php
    $eng = $enganche ?? [];
    $cc = $eng['cuentacaja'] ?? [];
    $cont = $eng['contabilidad'] ?? [];
    $ib = $eng['interbanking'] ?? [];
    $completo = (bool) ($eng['enganche_completo'] ?? false);
    $faltantes = $eng['faltantes'] ?? [];
@endphp

<div class="card card-outline {{ $completo ? 'card-success' : 'card-warning' }} mb-3" id="card-enganche-cuenta">
    <div class="card-header py-2">
        <h3 class="card-title mb-0">
            <i class="fa fa-link"></i> Enganche cuenta caja ↔ contabilidad ↔ Interbanking
        </h3>
        @if ($completo)
            <span class="badge badge-success ml-2">Listo para conciliar</span>
        @else
            <span class="badge badge-warning ml-2">Revisar configuración</span>
        @endif
    </div>
    <div class="card-body py-2">
        @if ($faltantes !== [])
            <div class="alert alert-warning py-2 mb-2">
                <ul class="mb-0 pl-3">
                    @foreach ($faltantes as $msg)
                        <li>{{ $msg }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row">
            <div class="col-md-4">
                <h6 class="text-muted text-uppercase small mb-2">Cuenta de caja (Anita)</h6>
                <table class="table table-sm table-borderless mb-0">
                    <tr><th class="pl-0" style="width:35%">Código</th><td>{{ $cc['codigo'] ?? '—' }}</td></tr>
                    <tr><th class="pl-0">Nombre</th><td>{{ $cc['nombre'] ?? '—' }}</td></tr>
                    <tr><th class="pl-0">Banco</th><td>{{ $cc['banco'] ?? '—' }}</td></tr>
                    <tr><th class="pl-0">Moneda</th><td>{{ $cc['moneda'] ?? '—' }}</td></tr>
                    <tr><th class="pl-0">CBU</th><td><code>{{ $cc['cbu'] ?: '—' }}</code></td></tr>
                </table>
            </div>
            <div class="col-md-4">
                <h6 class="text-muted text-uppercase small mb-2">Mayor analítico (contabilidad)</h6>
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <th class="pl-0" style="width:35%">Cuenta</th>
                        <td>
                            <code>{{ $cont['codigo_fmt'] ?? $cont['codigo'] ?? '—' }}</code>
                        </td>
                    </tr>
                    <tr><th class="pl-0">Nombre</th><td>{{ $cont['nombre'] ?? '—' }}</td></tr>
                    <tr><th class="pl-0">Origen</th><td class="small">{{ $cont['origen_mayor'] ?? '' }}</td></tr>
                </table>
            </div>
            <div class="col-md-4">
                <h6 class="text-muted text-uppercase small mb-2">Interbanking (persistido)</h6>
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <th class="pl-0" style="width:40%">Nº cuenta IB</th>
                        <td><code>{{ $ib['account_number'] ?: '—' }}</code></td>
                    </tr>
                    <tr><th class="pl-0">Banco (cód.)</th><td>{{ $ib['bank_number'] ?: '—' }}</td></tr>
                    <tr><th class="pl-0">Movimientos</th><td>{{ number_format($ib['movimientos_persistidos'] ?? 0, 0, ',', '.') }} en BD</td></tr>
                    @if (! empty($ib['ultimo_saldo']))
                        <tr>
                            <th class="pl-0">Último saldo</th>
                            <td>
                                {{ $ib['ultimo_saldo']['fecha'] ?? '' }} —
                                {{ number_format($ib['ultimo_saldo']['countable_balance'] ?? 0, 2, ',', '.') }}
                                {{ $ib['ultimo_saldo']['currency'] ?? '' }}
                            </td>
                        </tr>
                    @endif
                    @if (! empty($ib['ultimo_movimiento']))
                        <tr>
                            <th class="pl-0">Últ. mov.</th>
                            <td class="small">
                                {{ $ib['ultimo_movimiento']['fecha'] ?? '' }}
                                {{ $ib['ultimo_movimiento']['concepto'] ?? '' }}
                                ({{ number_format($ib['ultimo_movimiento']['importe'] ?? 0, 2, ',', '.') }})
                            </td>
                        </tr>
                        <tr>
                            <th class="pl-0">Sync IB</th>
                            <td class="small text-muted">{{ $ib['ultimo_movimiento']['synced_at'] ?? '' }}</td>
                        </tr>
                    @endif
                </table>
            </div>
        </div>

        @if (($ib['account_number'] ?? '') !== '' && ($cont['codigo_fmt'] ?? $cont['codigo'] ?? '') !== '')
            <p class="text-muted small mb-0 mt-2">
                La conciliación cruza el mayor de la cuenta contable
                <strong>{{ $cont['codigo_fmt'] ?? $cont['codigo'] }}</strong>
                contra movimientos IB de la cuenta
                <strong>{{ $ib['account_number'] }}</strong>.
            </p>
        @endif
    </div>
</div>
