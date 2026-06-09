@php
    $capturasCfg = config('manual_gastronomia.capturas', []);
    $ids = [];
    foreach ($capturasCfg as $key => $cap) {
        if (($cap['seccion'] ?? '') === ($sec['titulo'] ?? '')) {
            $ids[] = $key;
        }
    }
    if (!empty($sec['captura_id']) && isset($capturasCfg[$sec['captura_id']])) {
        $ids[] = $sec['captura_id'];
    }
    $ids = array_values(array_unique($ids));
    $imgDir = public_path('docs/manual-gastronomia/img');
@endphp
@foreach ($ids as $capKey)
    @php
        $cap = $capturasCfg[$capKey];
        $baseName = preg_replace('/\.(svg|png)$/i', '', $cap['archivo']);
        $imgFile = is_file($imgDir . '/' . $baseName . '.png')
            ? $baseName . '.png'
            : (is_file($imgDir . '/' . $baseName . '.svg') ? $baseName . '.svg' : (is_file($imgDir . '/' . $cap['archivo']) ? $cap['archivo'] : null));
    @endphp
    @if ($imgFile)
    <figure class="mc-figure">
        <img src="{{ asset('docs/manual-gastronomia/img/' . $imgFile) }}" alt="{{ $cap['titulo'] }}" loading="lazy">
        <figcaption>{{ $cap['titulo'] }}</figcaption>
    </figure>
    @endif
@endforeach
