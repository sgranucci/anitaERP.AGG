<div class="mc-herramientas">
    <h3>{{ $grupo['titulo'] ?? 'Herramientas' }}</h3>
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
            @foreach ($grupo['items'] as $herramienta)
                <tr>
                    <td><strong>{{ $herramienta['herramienta'] ?? '' }}</strong></td>
                    <td>{{ $herramienta['ubicacion'] ?? '' }}</td>
                    <td>{{ $herramienta['accion'] ?? '' }}</td>
                    <td><code>{{ $herramienta['permiso'] ?? '' }}</code></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
