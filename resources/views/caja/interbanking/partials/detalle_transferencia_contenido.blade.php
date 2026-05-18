@foreach ($secciones as $tituloSeccion => $campos)
    @if ($campos !== [])
        <h6 class="text-primary font-weight-bold mt-3 mb-2">{{ $tituloSeccion }}</h6>
        <table class="table table-sm table-bordered table-striped mb-0 ib-detalle-transferencia-tabla">
            <tbody>
                @foreach ($campos as $etiqueta => $valor)
                    <tr>
                        <th class="ib-detalle-etiqueta">{{ $etiqueta }}</th>
                        <td>{{ $valor }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endforeach
