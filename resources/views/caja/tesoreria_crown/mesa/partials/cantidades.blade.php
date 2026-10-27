@if (! empty($filas))
    <h6 class="mt-3">{{ $titulo }}</h6>
    <div class="table-responsive">
        <table class="table table-sm table-bordered">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th>Valor</th>
                    <th class="text-right">Cantidad</th>
                    <th class="text-right">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $fila)
                    <tr>
                        <td>{{ $fila['valor']->descripcion }}</td>
                        <td class="text-right">{{ number_format($fila['cantidad'], 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($fila['monto'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
