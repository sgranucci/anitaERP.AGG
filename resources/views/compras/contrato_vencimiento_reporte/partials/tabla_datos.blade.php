@php
    use App\Support\Compras\OrdencompraContratoVencimientoSupport as CtoSupport;

    $paraExcel = (bool) ($para_excel ?? false);
    $puedeVerOc = (bool) ($puede_ver_ordencompra ?? false);

    $num = function ($valor, int $decimales = 2) use ($paraExcel) {
        $valor = (float) $valor;

        return $paraExcel ? $valor : CtoSupport::fmtNumero($valor, $decimales);
    };
@endphp

<table class="table table-sm table-bordered table-hover data" id="tabla-paginada">
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th>Empresa</th>
            <th>OC</th>
            <th>Proveedor</th>
            <th>Estado</th>
            <th>Vig. desde</th>
            <th>Vig. hasta</th>
            <th class="text-right">Días</th>
            <th>Auto-renov.</th>
            <th>Límite preaviso</th>
            <th class="text-right">Monto tope</th>
            <th class="text-right">Recibido</th>
            <th class="text-right">Facturado</th>
            <th class="text-right">Consumido</th>
            <th>Origen</th>
            <th class="text-right">Disponible</th>
            <th class="text-right">% consum.</th>
            <th>Responsable</th>
            <th>Situación</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $fila)
            @php
                $dias = (int) $fila['dias_para_vencer'];
                $tieneVigencia = $fila['vigencia_hasta'] !== null;
                $claseFila = '';
                if ($tieneVigencia && $dias < 0) {
                    $claseFila = 'table-danger';
                } elseif ($tieneVigencia && $dias <= 30) {
                    $claseFila = 'table-warning';
                }
            @endphp
            <tr class="{{ $claseFila }}">
                <td>{{ $fila['empresa'] }}</td>
                <td>
                    @if ($puedeVerOc && ! empty($fila['id']))
                        <a class="text-primary" target="_blank" rel="noopener"
                            href="{{ route('editar_ordencompra', ['id' => $fila['id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">
                            {{ $fila['numero'] }}
                        </a>
                    @else
                        {{ $fila['numero'] }}
                    @endif
                </td>
                <td>{{ $fila['proveedor'] }}</td>
                <td>{{ $fila['estado'] }}</td>
                <td>{{ CtoSupport::fmtFecha($fila['vigencia_desde']) }}</td>
                <td>{{ CtoSupport::fmtFecha($fila['vigencia_hasta']) }}</td>
                <td class="text-right">{{ $tieneVigencia ? $dias : '' }}</td>
                <td>{{ $fila['auto_renovable'] ? 'Sí' : 'No' }}</td>
                <td>{{ CtoSupport::fmtFecha($fila['fecha_limite_preaviso']) }}</td>
                <td class="text-right">{{ $fila['monto_tope'] > 0 ? $num($fila['monto_tope']) : '' }}</td>
                <td class="text-right">{{ $num($fila['monto_recibido'] ?? 0) }}</td>
                <td class="text-right">{{ $num($fila['monto_facturado']) }}</td>
                <td class="text-right">{{ $num($fila['monto_consumido'] ?? 0) }}</td>
                <td>{{ $fila['origen_consumo'] ?? '' }}</td>
                <td class="text-right">{{ $fila['monto_tope'] > 0 ? $num($fila['monto_disponible']) : '' }}</td>
                <td class="text-right">{{ $fila['monto_tope'] > 0 ? $num($fila['porcentaje_consumido'], 1) : '' }}</td>
                <td>{{ $fila['responsable'] !== '' ? $fila['responsable'] : 'sin asignar' }}</td>
                <td>{{ $fila['motivo'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="18" class="text-center text-muted">Sin contratos para los filtros seleccionados.</td>
            </tr>
        @endforelse
    </tbody>
</table>
