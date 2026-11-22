@php
    $alerta = ! empty($informe['requiere_alerta']);
    $alertaFicha = ! empty($informe['alerta_ficha']);
    $alertaDelta = ! empty($informe['alerta_delta']);
    $alertaIe = ! empty($informe['alerta_ie']);
    $delta = $informe['delta_mayor'] ?? [];
    $cuentasDelta = $delta['cuentas'] ?? [];
    $cuentasAnticipo = $delta['anticipos'] ?? [];
    $ieFilas = $informe['ie_cuentas_controladas'] ?? [];
    $empresa = trim((string) ($informe['empresa_nombre'] ?? ''));
    $fecha = (string) ($informe['fecha_fmt'] ?? $informe['fecha'] ?? '');
    $expresion = (string) ($informe['expresion'] ?? 'origen');
    $grupos = $informe['grupos'] ?? [];
    $resumen = $informe['resumen'] ?? [];
    $bannerBg = $alerta ? '#fadbd8' : '#d5f5e3';
    $bannerBorder = $alerta ? '#c0392b' : '#1e8449';
    $bannerText = $alerta ? '#7b241c' : '#145a32';
    $fmt = static function ($n) {
        return number_format((float) $n, 2, ',', '.');
    };
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $empresa }} — Deuda vs ficha {{ $fecha }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px; margin:0; padding:16px;">
<div style="background:{{ $bannerBg }}; border:2px solid {{ $bannerBorder }}; color:{{ $bannerText }}; padding:14px 16px; border-radius:6px; margin-bottom:18px;">
    <div style="font-size:20px; font-weight:700; margin:0 0 4px 0;">{{ $empresa }}</div>
    <div style="font-size:15px; margin:0;">
        @php
            $msgs = [];
            if ($alertaFicha) {
                $msgs[] = 'la ficha no cuadra';
            }
            if ($alertaDelta) {
                $msgs[] = 'el delta conjunto se movió';
            }
            if ($alertaIe) {
                $msgs[] = 'hay I/E sobre cuentas controladas';
            }
        @endphp
        @if ($msgs !== [])
            ALERTA — {{ implode(' y ', $msgs) }} al {{ $fecha }}
        @else
            @if (! empty($delta['hay_base_anterior']))
                OK — ficha cuadrada y delta conjunto estable al {{ $fecha }}
            @else
                OK — ficha cuadrada. El delta conjunto queda como base al {{ $fecha }}
            @endif
        @endif
    </div>
</div>

<p style="margin:0 0 12px 0;">
    Control <strong>deuda vs ficha</strong> y delta conjunto de <strong>proveedores + anticipos</strong>
    · Corte <strong>{{ $fecha }}</strong>
    · Expresión <strong>{{ $expresion }}</strong>
    · La deuda es la verdad: facturas impagas, notas de crédito sin aplicar y OPA sin aplicar.
    Aplicar una OPA a una factura debita una cuenta y acredita la otra por el mismo importe: el delta conjunto no se mueve.
    Los <strong>ingresos/egresos</strong> no entran en el delta; si imputan una cuenta controlada (p. ej. proveedores del exterior) se listan abajo.
    Facturas anticipadas y recepciones no entran acá: las controla
    <code>compras:auditar-factura-anticipada-recepcion</code>.
</p>

<h3 style="margin:16px 0 6px 0;">Resumen</h3>
<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
    <tr style="background:#85C1E9; color:#17202A;">
        <th align="left">Control</th>
        <th align="right">Valor</th>
    </tr>
    <tr>
        <td>Movimientos leídos</td>
        <td align="right">{{ (int) ($resumen['movimientos'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Proveedores / monedas con diferencia</td>
        <td align="right">{{ (int) ($informe['grupos_total'] ?? count($grupos)) }}</td>
    </tr>
    @if ($expresion === 'origen')
        <tr>
            <td>Suma diferencia origen (deuda − ficha)</td>
            <td align="right">{{ $fmt($resumen['suma_diferencia_origen'] ?? 0) }}</td>
        </tr>
    @else
        <tr>
            <td>Suma diferencia pesos</td>
            <td align="right">{{ $fmt($resumen['suma_diferencia_pesos'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Suma diferencia dólares</td>
            <td align="right">{{ $fmt($resumen['suma_diferencia_dolares'] ?? 0) }}</td>
        </tr>
    @endif
</table>

<h3 style="margin:18px 0 6px 0;">Delta conjunto</h3>
<p style="margin:0 0 8px 0; color:#555; font-size:13px;">
    Suma del delta de proveedores y el de anticipos (OPA). Es el número que se controla.
    Una aplicación puede mover una cuenta y compensarla en la otra.
    @if (! empty($delta['hay_base_anterior']))
        Avisa si la variación pasa de {{ $fmt($delta['tolerancia'] ?? 1) }} respecto del {{ $delta['fecha_anterior_fmt'] ?? ($delta['fecha_anterior'] ?? '') }}.
    @else
        Primera medición: el conjunto queda como base.
    @endif
</p>
<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
    <tr style="background:#85C1E9; color:#17202A;">
        <th align="left">Control</th>
        <th align="right">Valor</th>
    </tr>
    <tr>
        <td>Delta proveedores</td>
        <td align="right">{{ $fmt($delta['delta_proveedores'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Delta anticipos</td>
        <td align="right">{{ $fmt($delta['delta_anticipos'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Delta conjunto</td>
        <td align="right"><strong>{{ $fmt($delta['delta_conjunto'] ?? 0) }}</strong></td>
    </tr>
    <tr>
        <td>Conjunto anterior</td>
        <td align="right">
            @if (($delta['delta_conjunto_anterior'] ?? null) === null)
                —
            @else
                {{ $fmt($delta['delta_conjunto_anterior']) }}
            @endif
        </td>
    </tr>
    <tr
        @if ($alertaDelta)
            style="background:#fadbd8;"
        @endif
    >
        <td>Variación del conjunto</td>
        <td align="right">
            @if (($delta['variacion_conjunto'] ?? null) === null)
                —
            @else
                <strong>{{ $fmt($delta['variacion_conjunto']) }}</strong>
            @endif
        </td>
    </tr>
</table>
@if ($alertaDelta)
    <p style="margin:8px 0 0 0; color:#7b241c; font-size:13px;">
        El delta conjunto cambió. Abajo está el detalle por cuenta.
    </p>
@elseif (! empty($delta['hay_base_anterior']))
    <p style="margin:8px 0 0 0; color:#145a32; font-size:13px;">
        El delta conjunto no se movió respecto del {{ $delta['fecha_anterior_fmt'] ?? $delta['fecha_anterior'] }}.
    </p>
@endif
@if ((int) ($delta['cuentas_sin_base'] ?? 0) > 0)
    <p style="margin:8px 0 0 0; color:#555; font-size:13px;">
        {{ (int) $delta['cuentas_sin_base'] }} cuenta(s) entran hoy como base y no forman parte de la variación.
    </p>
@endif

@if ($alertaFicha)
    <h3 style="margin:18px 0 6px 0;">Diferencias proveedor por proveedor</h3>
    <p style="margin:0 0 8px 0; color:#555; font-size:13px;">
        Revisar motivos posibles: importes multi-moneda, renglones de ficha sin deuda abierta,
        o ajustes previos. Las aplicaciones posteriores al corte ya no bajan la deuda.
    </p>
    <table cellpadding="5" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; width:100%;">
        <tr style="background:#f5b7b1; color:#17202A;">
            <th align="left">Código</th>
            <th align="left">Proveedor</th>
            <th align="left">Moneda</th>
            @if ($expresion === 'origen')
                <th align="right">Deuda</th>
                <th align="right">Ficha</th>
                <th align="right">Diferencia</th>
            @else
                <th align="right">Deuda $</th>
                <th align="right">Ficha $</th>
                <th align="right">Dif. $</th>
                <th align="right">Dif. USD</th>
            @endif
            <th align="right">Ajuste propuesto</th>
        </tr>
        @foreach ($grupos as $g)
            <tr>
                <td>{{ $g['proveedor_codigo'] ?? '' }}</td>
                <td>{{ $g['proveedor_nombre'] ?? '' }}</td>
                <td>{{ $g['moneda_abreviatura'] ?? '' }}</td>
                @if ($expresion === 'origen')
                    <td align="right">{{ $fmt($g['deuda_origen'] ?? 0) }}</td>
                    <td align="right">{{ $fmt($g['ficha_origen'] ?? 0) }}</td>
                    <td align="right"><strong>{{ $fmt($g['diferencia_origen'] ?? 0) }}</strong></td>
                @else
                    <td align="right">{{ $fmt($g['deuda_pesos'] ?? 0) }}</td>
                    <td align="right">{{ $fmt($g['ficha_pesos'] ?? 0) }}</td>
                    <td align="right"><strong>{{ $fmt($g['diferencia_pesos'] ?? 0) }}</strong></td>
                    <td align="right">{{ $fmt($g['diferencia_usd'] ?? 0) }}</td>
                @endif
                <td align="right">{{ $fmt($g['ajuste_propuesto'] ?? 0) }}</td>
            </tr>
        @endforeach
    </table>
    @if ((int) ($informe['grupos_truncados'] ?? 0) > 0)
        <p style="margin:8px 0 0 0; color:#666; font-size:12px;">
            Se omitieron {{ (int) $informe['grupos_truncados'] }} fila(s) por límite de mail.
            Correr
            <code>php artisan compras:cuadrar-deuda-ficha-proveedor --fecha={{ $informe['fecha'] ?? '' }} --expresion={{ $expresion }} --empresa={{ (int) ($informe['empresa_id'] ?? 0) }}</code>
            para el detalle completo.
        </p>
    @endif
@else
    <p style="margin:18px 0 0 0; padding:12px; background:#d5f5e3; border:1px solid #1e8449; color:#145a32; border-radius:4px;">
        No hay diferencias entre la deuda abierta y la ficha de cuenta corriente para
        <strong>{{ $empresa }}</strong> al corte <strong>{{ $fecha }}</strong>.
    </p>
@endif

@php
    $mayorAnitaIncluido = ! empty($delta['mayor_anita_incluido']);
    $mayorAnitaError = trim((string) ($delta['mayor_anita_error'] ?? ''));
@endphp
<h3 style="margin:22px 0 6px 0;">Ingresos / egresos sobre cuentas controladas</h3>
<p style="margin:0 0 8px 0; color:#555; font-size:13px;">
    Asientos de I/E (<code>caja_movimiento</code>) que tocaron una cuenta del control
    (proveedores / anticipos) desde el último snapshot. No entran en el delta conjunto;
    se avisan para que administración revise (p. ej. OPP por I/E contra proveedores del exterior).
</p>
@if ($ieFilas === [])
    <p style="margin:0; padding:12px; background:#d5f5e3; border:1px solid #1e8449; color:#145a32; border-radius:4px;">
        No hay I/E nuevos sobre cuentas controladas.
    </p>
@else
    <table cellpadding="5" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; width:100%;">
        <tr style="background:#f5b7b1; color:#17202A;">
            <th align="left">Fecha</th>
            <th align="left">I/E</th>
            <th align="left">Tipo</th>
            <th align="left">Cuenta</th>
            <th align="left">Proveedor</th>
            <th align="left">Usuario</th>
            <th align="left">Detalle</th>
            <th align="right">Efecto mayor</th>
        </tr>
        @foreach ($ieFilas as $ie)
            <tr>
                <td>{{ $ie['fecha_fmt'] ?? $ie['fecha'] ?? '' }}</td>
                <td>{{ $ie['numerotransaccion'] ?? '' }} (#{{ $ie['caja_movimiento_id'] ?? '' }})</td>
                <td>{{ $ie['tipo'] ?? '' }}</td>
                <td>{{ $ie['cuenta_codigo'] ?? '' }} {{ $ie['cuenta_nombre'] ?? '' }}</td>
                <td>{{ trim(($ie['proveedor_codigo'] ?? '').' '.($ie['proveedor_nombre'] ?? '')) }}</td>
                <td>{{ $ie['usuario'] ?? '' }}</td>
                <td>{{ $ie['detalle'] ?? '' }}</td>
                <td align="right"><strong>{{ $fmt($ie['efecto_mayor'] ?? 0) }}</strong></td>
            </tr>
        @endforeach
    </table>
    <p style="margin:8px 0 0 0; color:#7b241c; font-size:13px;">
        {{ (int) ($informe['ie_cuentas_controladas_total'] ?? count($ieFilas)) }} movimiento(s)
        · suma efecto mayor {{ $fmt($informe['ie_suma_efecto_mayor'] ?? 0) }}
        (signo del mayor: negativo = débito).
    </p>
    @if ((int) ($informe['ie_cuentas_controladas_truncadas'] ?? 0) > 0)
        <p style="margin:4px 0 0 0; color:#666; font-size:12px;">
            Se omitieron {{ (int) $informe['ie_cuentas_controladas_truncadas'] }} fila(s) por límite de mail.
        </p>
    @endif
@endif

<h3 style="margin:22px 0 6px 0;">Delta de proveedores</h3>
<p style="margin:0 0 8px 0; color:#555; font-size:13px;">
    En pesos, con el mismo signo del mayor (negativo = débito). Delta = mayor − deuda.
    La deuda es una sola: facturas impagas, notas de crédito sin aplicar y OPA sin aplicar que no están en anticipos.
    Entran las cuentas de rubro Pasivo del proveedor. Anticipos no entra en esta tabla.
    El mayor del control excluye asientos de ingresos/egresos.
    El aviso no sale de una fila: sale de la suma con anticipos.
    @if ($mayorAnitaIncluido)
        Mayor Anita = subdiario + ctamov (neto Haber−Debe), contraste con el mayor ERP.
    @endif
</p>
@if ($mayorAnitaError !== '')
    <p style="margin:0 0 8px 0; color:#922b21; font-size:13px;">Mayor Anita no disponible: {{ $mayorAnitaError }}</p>
@endif
@if ($cuentasDelta === [])
    <p style="margin:0;">No hay saldo de deuda ni de mayor en cuentas de proveedores.</p>
@else
    <table cellpadding="5" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; width:100%;">
        <tr style="background:#85C1E9; color:#17202A;">
            <th align="left">Cuenta</th>
            <th align="right">Deuda</th>
            <th align="right">Mayor ERP</th>
            @if ($mayorAnitaIncluido)
                <th align="right">Mayor Anita</th>
                <th align="right">ERP−Anita</th>
            @endif
            <th align="right">Delta</th>
            <th align="right">Delta anterior</th>
            <th align="right">Variación</th>
        </tr>
        @foreach ($cuentasDelta as $c)
            <tr
                @if (! empty($c['movio']) || ! empty($c['erp_anita_difiere']))
                    style="background:#fadbd8;"
                @endif
            >
                <td>{{ $c['codigo'] ?? '' }} {{ $c['nombre'] ?? '' }}</td>
                <td align="right">{{ $fmt($c['deuda'] ?? 0) }}</td>
                <td align="right">{{ $fmt($c['mayor'] ?? 0) }}</td>
                @if ($mayorAnitaIncluido)
                    <td align="right">{{ $fmt($c['mayor_anita'] ?? 0) }}</td>
                    <td align="right"><strong>{{ $fmt($c['diff_erp_anita'] ?? 0) }}</strong></td>
                @endif
                <td align="right"><strong>{{ $fmt($c['delta'] ?? 0) }}</strong></td>
                <td align="right">{{ $c['delta_anterior'] === null ? '—' : $fmt($c['delta_anterior']) }}</td>
                <td align="right">
                    @if ($c['variacion'] === null)
                        —
                    @else
                        <strong>{{ $fmt($c['variacion']) }}</strong>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
    @if ((int) ($delta['variaciones'] ?? 0) > 0)
        <p style="margin:8px 0 0 0; color:#555; font-size:13px;">
            {{ (int) ($delta['variaciones'] ?? 0) }} cuenta(s) de proveedores cambiaron su delta. El control es la suma con anticipos.
        </p>
    @endif
    @if ($mayorAnitaIncluido && (int) ($delta['diferencias_erp_anita'] ?? 0) > 0)
        <p style="margin:8px 0 0 0; color:#922b21; font-size:13px;">
            {{ (int) $delta['diferencias_erp_anita'] }} cuenta(s) difieren entre mayor ERP y mayor Anita.
        </p>
    @endif
@endif

<h3 style="margin:22px 0 6px 0;">Anticipos a proveedores (solo OPA)</h3>
<p style="margin:0 0 8px 0; color:#555; font-size:13px;">
    Deuda = OPA abiertas imputadas a anticipos (incluye sobrante de OPP).
    Mayor = asientos de esa cuenta sin facturas anticipadas ni recepciones.
    Aplicar esa OPA acredita esta cuenta y debita proveedores: el conjunto no se mueve si ambas piernas se compensan.
</p>
@if ($cuentasAnticipo === [])
    <p style="margin:0;">No hay saldo en la cuenta de anticipos.</p>
@else
    <table cellpadding="5" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; width:100%;">
        <tr style="background:#85C1E9; color:#17202A;">
            <th align="left">Cuenta</th>
            <th align="right">OPA / OP adelantadas</th>
            <th align="right">Mayor ERP</th>
            @if ($mayorAnitaIncluido)
                <th align="right">Mayor Anita</th>
                <th align="right">ERP−Anita</th>
            @endif
            <th align="right">Delta</th>
            <th align="right">Anterior</th>
            <th align="right">Variación</th>
        </tr>
        @foreach ($cuentasAnticipo as $c)
            <tr
                @if (! empty($c['movio']) || ! empty($c['erp_anita_difiere']))
                    style="background:#fadbd8;"
                @endif
            >
                <td>{{ $c['codigo'] ?? '' }} {{ $c['nombre'] ?? '' }}</td>
                <td align="right">{{ $fmt($c['opa'] ?? $c['deuda'] ?? 0) }}</td>
                <td align="right">{{ $fmt($c['mayor'] ?? 0) }}</td>
                @if ($mayorAnitaIncluido)
                    <td align="right">{{ $fmt($c['mayor_anita'] ?? 0) }}</td>
                    <td align="right"><strong>{{ $fmt($c['diff_erp_anita'] ?? 0) }}</strong></td>
                @endif
                <td align="right"><strong>{{ $fmt($c['delta'] ?? 0) }}</strong></td>
                <td align="right">{{ $c['delta_anterior'] === null ? '—' : $fmt($c['delta_anterior']) }}</td>
                <td align="right">
                    @if ($c['variacion'] === null)
                        —
                    @else
                        <strong>{{ $fmt($c['variacion']) }}</strong>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
    @if ((int) ($delta['variaciones_anticipos'] ?? 0) > 0 && ! $alertaDelta)
        <p style="margin:8px 0 0 0; color:#555; font-size:13px;">
            Esta cuenta se movió y la variación quedó compensada en el delta conjunto.
        </p>
    @endif
@endif

<p style="margin-top:22px; color:#666; font-size:12px;">
    Generado {{ now()->format('d/m/Y H:i') }} —
    <code>php artisan compras:auditar-deuda-ficha-proveedor</code>
    · Destinatario: <code>COMPRAS_DEUDA_FICHA_AUDITOR_EMAIL</code>
    · Empresas: <code>COMPRAS_DEUDA_FICHA_AUDITOR_EMPRESAS</code>
    · Desactivar: <code>COMPRAS_DEUDA_FICHA_AUDITOR_HABILITADA=false</code>
</p>
</body>
</html>
