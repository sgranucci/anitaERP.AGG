@php
    use App\Support\Caja\Flash\FlashCrownInformeSupport;

    $excel = ! empty($modo_excel);
    $secciones = isset($seccion) && is_array($seccion)
        ? [$seccion]
        : ($informe['secciones'] ?? []);
    $conSolapas = ! empty($con_solapas) && ! $excel && count($secciones) > 1;

    $armarGruposDaily = static function (array $columnas): array {
        $grupos = [];
        foreach ($columnas as $col) {
            $g = (string) ($col['grupo'] ?? '');
            if ($g === '') {
                $grupos[] = ['titulo' => '', 'span' => 1];
                continue;
            }
            $ultimo = $grupos[count($grupos) - 1] ?? null;
            if ($ultimo && ($ultimo['titulo'] ?? null) === $g) {
                $grupos[count($grupos) - 1]['span']++;
            } else {
                $grupos[] = ['titulo' => $g, 'span' => 1];
            }
        }

        return $grupos;
    };
@endphp

@if ($conSolapas)
    @include('includes.tabs-activas-estilos')
    <div class="tabs-activas mb-2">
        <ul class="nav nav-tabs" id="tabs-daily-flash" role="tablist">
            @foreach ($secciones as $idx => $sec)
                @php
                    $etiqueta = trim((string) ($sec['codigo'] ?? ''));
                    if ($etiqueta === '' || strcasecmp($etiqueta, 'RESUMEN') === 0) {
                        $etiqueta = strcasecmp((string) ($sec['codigo'] ?? ''), 'RESUMEN') === 0
                            ? 'Resumen'
                            : (string) ($sec['titulo'] ?? ('Sala '.($idx + 1)));
                    }
                @endphp
                <li class="nav-item">
                    <a class="nav-link {{ $idx === 0 ? 'active' : '' }}"
                       id="tab-daily-{{ $idx }}-tab"
                       data-toggle="tab"
                       href="#tab-daily-{{ $idx }}"
                       role="tab"
                       aria-controls="tab-daily-{{ $idx }}"
                       aria-selected="{{ $idx === 0 ? 'true' : 'false' }}"
                       title="{{ $sec['titulo'] ?? $etiqueta }}">
                        {{ $etiqueta }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
    <div class="tab-content" id="tabs-daily-flash-content">
        @foreach ($secciones as $idx => $sec)
            @php
                $columnasSec = $sec['columnas']
                    ?? FlashCrownInformeSupport::columnasDailyParaSeccion($sec['codigo'] ?? null);
                $gruposSec = $armarGruposDaily($columnasSec);
            @endphp
            <div class="tab-pane fade {{ $idx === 0 ? 'show active' : '' }}"
                 id="tab-daily-{{ $idx }}"
                 role="tabpanel"
                 aria-labelledby="tab-daily-{{ $idx }}-tab">
                <p class="font-weight-bold mb-2">{{ $sec['titulo'] ?? '' }}</p>
                @include('caja.flash_crown.reporte.partials.tabla_daily_seccion', [
                    'seccion' => $sec,
                    'columnas' => $columnasSec,
                    'grupos' => $gruposSec,
                    'excel' => false,
                ])
            </div>
        @endforeach
    </div>
@else
    @foreach ($secciones as $sec)
        @php
            $columnasSec = $sec['columnas']
                ?? FlashCrownInformeSupport::columnasDailyParaSeccion($sec['codigo'] ?? null);
            $gruposSec = $armarGruposDaily($columnasSec);
        @endphp
        @if (! $excel)
            <h6 class="mt-3 mb-1 font-weight-bold">{{ $sec['titulo'] ?? '' }}</h6>
        @endif
        @include('caja.flash_crown.reporte.partials.tabla_daily_seccion', [
            'seccion' => $sec,
            'columnas' => $columnasSec,
            'grupos' => $gruposSec,
            'excel' => $excel,
        ])
    @endforeach
@endif
