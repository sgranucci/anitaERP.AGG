@php
    $notas = $notas ?? [];
    $urlAdmin = $notas_url_admin ?? null;
@endphp
@if (!empty($notas))
    <div class="card card-outline card-info mt-3">
        <div class="card-header py-2">
            <h3 class="card-title" style="font-size:14px;">
                <i class="fa fa-sticky-note text-info"></i> Notas al pie
            </h3>
            @if ($urlAdmin)
                <div class="card-tools">
                    <a href="{{ $urlAdmin }}" class="btn btn-outline-info btn-sm" target="_blank" rel="noopener">
                        <i class="fa fa-edit"></i> Administrar notas
                    </a>
                </div>
            @endif
        </div>
        <div class="card-body py-2">
            <ol class="mb-0 pl-3" style="font-size:12px;">
                @foreach ($notas as $nota)
                    <li value="{{ (int) $nota['marca'] }}" class="mb-1">
                        @if (!empty($nota['codigo_linea']))
                            <span class="badge badge-light border mr-1">{{ $nota['codigo_linea'] }}</span>
                        @endif
                        {{ $nota['texto'] }}
                        @if (!empty($nota['vigencia_texto']) && $nota['vigencia_texto'] !== 'Siempre')
                            <span class="text-muted">({{ $nota['vigencia_texto'] }})</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
@endif
