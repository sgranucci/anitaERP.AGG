@if (!empty($sec['herramientas_grupos']))
    @foreach ($sec['herramientas_grupos'] as $bloque)
        @if (!empty($bloque['items']))
        <div class="mc-herramientas">
            <h3>{{ $bloque['titulo'] ?? 'Herramientas' }}</h3>
            <div class="mc-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Herramienta</th>
                            <th>Ubicación</th>
                            <th>Qué hace</th>
                            <th>Permiso</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bloque['items'] as $h)
                        <tr>
                            <td><strong>{{ $h['herramienta'] }}</strong></td>
                            <td>{{ $h['ubicacion'] }}</td>
                            <td>{{ $h['accion'] }}</td>
                            <td><code>{{ $h['permiso'] }}</code></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    @endforeach
@elseif (!empty($sec['herramientas']))
    <div class="mc-herramientas">
        <h3>Herramientas de la pantalla</h3>
        <div class="mc-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Herramienta</th>
                        <th>Ubicación</th>
                        <th>Qué hace</th>
                        <th>Permiso</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sec['herramientas'] as $h)
                    <tr>
                        <td><strong>{{ $h['herramienta'] }}</strong></td>
                        <td>{{ $h['ubicacion'] }}</td>
                        <td>{{ $h['accion'] }}</td>
                        <td><code>{{ $h['permiso'] }}</code></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
