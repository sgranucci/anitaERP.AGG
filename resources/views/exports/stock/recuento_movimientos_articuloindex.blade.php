@php
    use App\Support\Stock\RecuentoMovimientosArticuloSupport;

    $art = $contexto['articulo'] ?? [];
    $dep = $contexto['deposito'] ?? [];
    $modoTodos = (bool) ($modoTodosDepositos ?? ($contexto['modo_todos_depositos'] ?? false));
    $depEtiqueta = $modoTodos
        ? 'Todos los depósitos'
        : RecuentoMovimientosArticuloSupport::etiquetaDepositoConEmpresa($dep, $dep['empresa_nombre'] ?? '');
    $colspan = $modoTodos ? 8 : 7;
    $tituloArticulo = trim(($art['sku'] ?? '').' '.($art['descripcion'] ?? ''));
    $tituloColumnaDeposito = \App\Support\Stock\MovimientosArticuloDepositoSupport::mostrarEmpresaEnListados()
        ? 'Depósito / Empresa'
        : 'Depósito';
    $sufijoUm = \App\Support\Stock\MovimientosArticuloDepositoSupport::sufijoColumnaCantidad($art['unidad_medida'] ?? '');
@endphp
<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="{{ $colspan }}">
                <h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Movimientos de stock por artículo</h2>
            </td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}">
                Artículo: {{ $tituloArticulo }}
                | Depósito: {{ $depEtiqueta }}
                | UM: {{ ! empty($art['unidad_medida']) ? $art['unidad_medida'] : '—' }}
                | {{ $modoTodos ? 'Saldo total' : 'Saldo actual' }}: {{ $contexto['saldo_fmt'] ?? '0' }}{{ $sufijoUm }}
                | Generado: {{ date('d/m/Y H:i') }}
                | Registros: {{ $movimientos->count() }}
            </td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>Fecha</th>
            @if ($modoTodos)
            <th>{{ $tituloColumnaDeposito }}</th>
            @endif
            <th>Tipo</th>
            <th>Entrada{{ $sufijoUm }}</th>
            <th>Salida{{ $sufijoUm }}</th>
            <th>Concepto</th>
            <th>Mov. stock</th>
            <th>Leyenda mov.</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($movimientos as $m)
            <tr>
                <td>{{ $m->fecha ? \Carbon\Carbon::parse($m->fecha)->format('d/m/Y') : '' }}</td>
                @if ($modoTodos)
                <td>{{ $m->deposito_etiqueta ?? '' }}</td>
                @endif
                <td>{{ $m->tipo ?? '' }}</td>
                <td>@if ($m->entrada !== null){{ $m->entrada }}@endif</td>
                <td>@if ($m->salida !== null){{ $m->salida }}@endif</td>
                <td>{{ $m->concepto_display ?? $m->concepto ?? '' }}</td>
                <td>{{ $m->movimiento_codigo ?: ($m->movimientostock_id ?? '') }}</td>
                <td>{{ $m->movimiento_leyenda ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
