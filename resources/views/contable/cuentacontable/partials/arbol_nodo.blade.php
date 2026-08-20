@php
    use App\Support\Contable\CuentacontableArbolSupport;
    $tipo = (string) ($nodo['tipocuenta'] ?? '');
    $tieneHijos = ! empty($nodo['hijos']);
    $expandido = ! empty($nodo['expandido']);
    $badgeClass = match ($tipo) {
        CuentacontableArbolSupport::TIPO_IMPUTABLE => 'badge-success',
        CuentacontableArbolSupport::TIPO_TITULO => 'badge-info',
        default => 'badge-secondary',
    };
@endphp
<li class="pc-nodo{{ !empty($nodo['coincide']) ? ' pc-nodo--hit' : '' }}{{ CuentacontableArbolSupport::esTotalizadora($tipo) ? ' pc-nodo--total' : '' }}"
    data-id="{{ $nodo['id'] }}"
    data-nivel="{{ $nodo['nivel'] }}"
    data-tipo="{{ $tipo }}"
    data-nombre="{{ $nodo['nombre'] }}"
    data-codigo="{{ $nodo['codigo_fmt'] }}"
    data-rubro-id="{{ $nodo['rubrocontable_id'] }}"
    data-rubro="{{ $nodo['rubro'] }}"
    data-parent-id="{{ $nodo['parent_id'] ?? '' }}"
    data-padre-origen="{{ $nodo['padre_origen'] ?? 'codigo' }}">
    <div class="pc-nodo__row" role="button" tabindex="0">
        @if ($tieneHijos)
            <button type="button" class="pc-nodo__toggle" aria-expanded="{{ $expandido ? 'true' : 'false' }}" title="Expandir / contraer">
                <i class="fa {{ $expandido ? 'fa-caret-down' : 'fa-caret-right' }}"></i>
            </button>
        @else
            <span class="pc-nodo__toggle pc-nodo__toggle--empty"></span>
        @endif
        <span class="pc-nodo__codigo">{{ $nodo['codigo_fmt'] }}</span>
        <span class="pc-nodo__nombre{{ $tipo === CuentacontableArbolSupport::TIPO_TITULO ? ' font-weight-bold' : '' }}">{{ $nodo['nombre'] }}</span>
        <span class="badge {{ $badgeClass }} pc-nodo__tipo">{{ $nodo['tipo_label'] }}</span>
        <span class="pc-nodo__meta text-muted">N{{ $nodo['nivel'] }}@if($nodo['rubro'] !== '') · {{ $nodo['rubro'] }}@endif</span>
        <span class="pc-nodo__acciones">
            @if (can('editar-cuentas-contables', false))
                <a href="{{ route('editar_cuentacontable', ['id' => $nodo['id']] + $retornoListadoQuery) }}"
                   class="btn-accion-tabla tooltipsC pc-nodo__ficha" title="Ficha completa (Anita, c.costo)">
                    <i class="fa fa-file-text-o"></i>
                </a>
            @endif
            @if (can('crear-cuentas-contables', false) && $tipo !== CuentacontableArbolSupport::TIPO_TOTALIZADORA)
                <a href="{{ route('crear_cuentacontable', $retornoListadoQuery + ['empresa_id' => $nodo['empresa_id'], 'padre' => $nodo['codigo']]) }}"
                   class="btn-accion-tabla tooltipsC" title="Agregar cuenta hija">
                    <i class="fa fa-plus-circle text-success"></i>
                </a>
            @endif
            @if (can('borrar-cuentas-contables', false))
                <a href="{{ route('eliminar_cuentacontable', ['id' => $nodo['id']]) }}"
                   class="eliminar-cuentacontable tooltipsC" title="Eliminar">
                    <i class="fa fa-times-circle text-danger"></i>
                </a>
            @endif
        </span>
    </div>
    @if ($tieneHijos)
        <ul class="pc-nodo__hijos" @if(! $expandido) hidden @endif>
            @foreach ($nodo['hijos'] as $hijo)
                @include('contable.cuentacontable.partials.arbol_nodo', ['nodo' => $hijo])
            @endforeach
        </ul>
    @endif
</li>
