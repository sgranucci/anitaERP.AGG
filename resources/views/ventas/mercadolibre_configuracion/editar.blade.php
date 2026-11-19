@extends("theme.$theme.layout")

@section('titulo')
    Configuración Mercado Libre
@endsection

@section('contenido')
<div class="card card-primary">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Configuración Mercado Libre</h3>
        <a href="{{ route('mercadolibre_pedidos') }}" class="btn btn-outline-light btn-sm">
            <i class="fa fa-reply-all"></i> Pedidos
        </a>
    </div>
    <div class="card-body">
        @include('includes.mensaje')

        <h5>App</h5>
        <dl class="row">
            <dt class="col-sm-3">Nombre</dt>
            <dd class="col-sm-9">Ferli ERP Facturacion</dd>
            <dt class="col-sm-3">App ID</dt>
            <dd class="col-sm-9"><code>{{ $clientId ?: '—' }}</code></dd>
            <dt class="col-sm-3">Redirect URI</dt>
            <dd class="col-sm-9"><code>{{ $redirectUri }}</code></dd>
            <dt class="col-sm-3">Notifications URI</dt>
            <dd class="col-sm-9"><code>{{ $notificationsUri }}</code></dd>
        </dl>

        <div class="alert alert-info">
            En developers.mercadolibre.com.ar cargá estas URLs (reemplazan la provisional de ferli.com.ar),
            activá topics <strong>orders_v2</strong> y Post Purchase (claims), validá con QR y autorizá con
            <strong>FERLIFRAGOLA</strong>.
        </div>

        <h5>OAuth</h5>
        @if ($oauth['autorizado'] ?? false)
            <div class="alert alert-success">
                Autorizado — seller {{ $oauth['seller_id'] ?? '—' }}
                @if ($oauth['expires_at'] ?? null)
                    — vence {{ $oauth['expires_at'] }}
                @endif
                @if (! ($oauth['vigente'] ?? true))
                    <span class="badge badge-warning">token vencido / a refrescar</span>
                @endif
            </div>
        @else
            <div class="alert alert-warning">Todavía no hay token. Autorizá la app.</div>
        @endif

        @if ($puedeAuth && $appOk)
            <a href="{{ route('mercadolibre_oauth_autorizar') }}" class="btn btn-primary btn-sm mb-3">
                Autorizar con Mercado Libre
            </a>
        @elseif (! $appOk)
            <div class="alert alert-danger">Completá MERCADOLIBRE_CLIENT_ID y MERCADOLIBRE_CLIENT_SECRET en .env.</div>
        @endif

        <h5>Defaults de facturación</h5>
        <table class="table table-sm table-bordered" style="max-width:640px;">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th>Canal</th>
                    <th>PV</th>
                    <th>Depósito</th>
                    <th>Cuenta caja</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Mercadolibre</td>
                    <td>{{ $defaults['puntoventa_id'] ?: '—' }} ({{ config('mercadolibre.puntoventa_codigo') }})</td>
                    <td>{{ $defaults['deposito_id'] ?: '—' }} ({{ config('mercadolibre.deposito_codigo') }})</td>
                    <td>{{ $defaults['cuentacaja_id'] ?: '—' }} ({{ config('mercadolibre.cuentacaja_codigo') }})</td>
                </tr>
                <tr>
                    <td>Full</td>
                    <td>{{ $defaultsFull['puntoventa_id'] ?: '—' }} ({{ config('mercadolibre.full_puntoventa_codigo') }})</td>
                    <td>{{ $defaultsFull['deposito_id'] ?: '—' }} ({{ config('mercadolibre.full_deposito_codigo') }})</td>
                    <td>{{ $defaultsFull['cuentacaja_id'] ?: '—' }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
