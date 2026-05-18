@if (!empty($sec['herramientas_grupos']))
    @foreach ($sec['herramientas_grupos'] as $grupo)
        @if (!empty($grupo['items']))
        <div class="mc-herramientas">
            <h3 class="mc-herramientas-title">{{ $grupo['titulo'] }}</h3>
            <div class="mc-table-wrap">
                <table class="mc-herramientas-table">
                    <thead>
                        <tr>
                            <th>Herramienta</th>
                            <th>Ubicación</th>
                            <th>Qué hace</th>
                            <th>Permiso</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grupo['items'] as $h)
                        <tr>
                            <td><strong>{{ $h['herramienta'] }}</strong></td>
                            <td>{{ $h['ubicacion'] }}</td>
                            <td>{{ $h['accion'] }}</td>
                            <td><code class="mc-perm">{{ $h['permiso'] }}</code></td>
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
        <h3 class="mc-herramientas-title">Herramientas de la pantalla</h3>
        <div class="mc-table-wrap">
            <table class="mc-herramientas-table">
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
                        <td><code class="mc-perm">{{ $h['permiso'] }}</code></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
