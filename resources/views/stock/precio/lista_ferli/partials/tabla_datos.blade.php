@php
    $esPdf = ! empty($pdf);
    $columnas = 2 + count($listas ?? []);
    $filasTabla = $filas ?? [];
    $puedeVer = ! $esPdf && ! empty($puede_ver_articulo);
@endphp
<table class="{{ $esPdf ? 'data' : 'table table-sm table-bordered table-hover mb-0' }}" @if (! $esPdf) id="tabla-paginada" @endif>
    <thead @if (! $esPdf) style="background:#85C1E9;color:#17202A;" @endif>
        @if ($esPdf)
            @include('includes.reportes.pdf_thead_cabecera', [
                'titulo' => $titulo ?? 'Lista de precios',
                'subtitulo' => $subtitulo ?? '',
                'colspan' => $columnas,
                'logosCabecera' => $logosCabecera ?? [],
                'totalFilas' => is_countable($filasTabla) ? count($filasTabla) : 0,
            ])
        @endif
        <tr class="columnas">
            <th>Artículo</th>
            <th>Descripción</th>
            @foreach ($listas ?? [] as $lista)
                <th class="text-right">{{ $lista['encabezado'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($filasTabla as $fila)
            <tr>
                <td>
                    @if ($puedeVer)
                        <a class="text-primary" target="_blank" rel="noopener"
                           href="{{ route('editar_articulo', ['id' => $fila->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">{{ $fila->sku }}</a>
                    @else
                        {{ $fila->sku }}
                    @endif
                </td>
                <td>{{ $fila->descripcion }}</td>
                @foreach ($listas ?? [] as $lista)
                    <td class="text-right">{{ \App\Support\Stock\PrecioListaFerliConsulta::textoPrecio($fila->precios[$lista['id']] ?? null) }}</td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ $columnas }}">No hay artículos para esos filtros.</td>
            </tr>
        @endforelse
    </tbody>
</table>
