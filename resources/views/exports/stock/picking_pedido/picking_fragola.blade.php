@php
    $desdeMedida = (int) ($desdeMedida ?? config('consprod.DESDE_MEDIDA'));
    $hastaMedida = (int) ($hastaMedida ?? config('consprod.HASTA_MEDIDA'));
    $conFoto = (bool) ($conFoto ?? true);
    $totalColumnas = (int) ($totalColumnas ?? (($conFoto ? 1 : 0) + 5 + ($hastaMedida - $desdeMedida + 1) + 9));
    $lineasMeta = $lineasMeta ?? [];
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="{{ $totalColumnas }}" style="height: 52px;">&#160;</td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ $totalColumnas }}">
            <strong style="font-size: 16pt;">{{ $titulo ?? 'PICKING' }}</strong>
        </td>
    </tr>
    <tr>
        <td colspan="{{ $totalColumnas }}" style="font-size: 10pt; color: #444;">
            Generado {{ date('d/m/Y H:i') }}
        </td>
    </tr>
    @foreach ($lineasMeta as $lineaMeta)
        <tr>
            <td colspan="{{ $totalColumnas }}" style="font-size: 10pt; color: #444;">
                {{ $lineaMeta }}
            </td>
        </tr>
    @endforeach
    <thead>
        <tr>
            @if ($conFoto)
                <th>Foto</th>
            @endif
            <th>Linea</th>
            <th>Art</th>
            <th>Descripcion</th>
            <th>Cliente</th>
            <th>Pedido</th>
            @for ($ii = $desdeMedida; $ii <= $hastaMedida; $ii++)
                <th>{{ $ii }}</th>
            @endfor
            <th>T</th>
            <th>Q M</th>
            <th>TT</th>
            <th>Precio</th>
            <th>SITUACION</th>
            <th>NUMERO OT</th>
            <th>deposito</th>
            <th>Observacion</th>
            <th>Bultos</th>
        </tr>
    </thead>
    <tbody>
        @php
            $sumaModulos = 0;
            $sumaPares = 0;
        @endphp
        @foreach ($filas as $fila)
            @php
                // Talles = curva de un módulo. Q M = cuántos módulos. TT = pares totales.
                $medidas = $fila['medidas'] ?? [];
                $t = $fila['pares_modulo'] ?? ($fila['total'] ?? 0);
                $qm = $fila['cantidad_modulos'] ?? 1;
                $tt = $fila['total'] ?? 0;
                $sumaModulos += (float) $qm;
                $sumaPares += (float) $tt;
            @endphp
            <tr>
                @if ($conFoto)
                    <td>&#160;</td>
                @endif
                <td>{{ $fila['nombrelinea'] ?? '' }}</td>
                <td>{{ $fila['sku'] ?? '' }}</td>
                <td>{{ $fila['descripcion'] ?? '' }}</td>
                <td>{{ $fila['cliente'] ?? '' }}</td>
                <td>{{ $fila['pedido_codigo'] ?? '' }}</td>
                @for ($ii = $desdeMedida; $ii <= $hastaMedida; $ii++)
                    @php $cant = $medidas[(string) $ii] ?? ($medidas[$ii] ?? null); @endphp
                    <td>
                        @if ($cant !== null && (float) $cant != 0.0)
                            {{ (float) $cant }}
                        @else
                            &#160;
                        @endif
                    </td>
                @endfor
                <td>{{ $t }}</td>
                <td>{{ $qm }}</td>
                <td>{{ $tt }}</td>
                <td>{{ $fila['precio'] ?? '' }}</td>
                <td>{{ $fila['situacion'] ?? 'ENTREGA INMEDIATA' }}</td>
                <td>{{ $fila['numero_ot'] ?? '' }}</td>
                <td>{{ $fila['deposito'] ?? '' }}</td>
                <td>{{ $fila['observacion'] ?? '' }}</td>
                <td>&#160;</td>
            </tr>
        @endforeach
        <tr>
            @if ($conFoto)
                <td>&#160;</td>
            @endif
            <td>&#160;</td>
            <td>&#160;</td>
            <td>&#160;</td>
            <td>&#160;</td>
            <td>&#160;</td>
            @for ($ii = $desdeMedida; $ii <= $hastaMedida; $ii++)
                <td>&#160;</td>
            @endfor
            <td>&#160;</td>
            <td><strong>{{ $sumaModulos }}</strong></td>
            <td><strong>{{ $sumaPares }}</strong></td>
            <td>&#160;</td>
            <td>&#160;</td>
            <td>&#160;</td>
            <td>&#160;</td>
            <td>&#160;</td>
            <td>&#160;</td>
        </tr>
    </tbody>
</table>
