@php
    $lineas = (int) ($data->lineas ?? 4);
    if (! in_array($lineas, [3, 4, 5], true)) {
        $lineas = 4;
    }
    $mini = ! empty($mini);
    $filasMuestra = [
        3 => [
            ['B' => 3, 'I' => 18, 'N' => 32, 'G' => 47, 'O' => 61],
            ['B' => 7, 'I' => 22, 'N' => 38, 'G' => 52, 'O' => 68],
            ['B' => 12, 'I' => 27, 'N' => 41, 'G' => 55, 'O' => 73],
        ],
        4 => [
            ['B' => 2, 'I' => 17, 'N' => 31, 'G' => 46, 'O' => 62],
            ['B' => 8, 'I' => 21, 'N' => 36, 'G' => 50, 'O' => 67],
            ['B' => 11, 'I' => 25, 'N' => 40, 'G' => 54, 'O' => 71],
            ['B' => 14, 'I' => 29, 'N' => 44, 'G' => 58, 'O' => 75],
        ],
        5 => [
            ['B' => 1, 'I' => 16, 'N' => 33, 'G' => 48, 'O' => 63],
            ['B' => 5, 'I' => 19, 'N' => 35, 'G' => 49, 'O' => 66],
            ['B' => 9, 'I' => 23, 'N' => 'FREE', 'G' => 53, 'O' => 70],
            ['B' => 13, 'I' => 28, 'N' => 42, 'G' => 57, 'O' => 74],
            ['B' => 15, 'I' => 30, 'N' => 45, 'G' => 60, 'O' => 72],
        ],
    ];
    $numeros = array_slice($filasMuestra[$lineas], 0, $lineas);
    $claseContenedor = 'bingo-carton-preview';
    if ($mini) {
        $claseContenedor .= ' bingo-carton-preview--mini';
    }
@endphp
<style>
    .bingo-carton-preview {
        display: inline-block;
        border: 2px solid #2c3e50;
        border-radius: 6px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
        overflow: hidden;
        font-family: Arial, Helvetica, sans-serif;
    }
    .bingo-carton-preview--mini {
        transform: scale(0.72);
        transform-origin: top center;
        margin: -6px 0;
    }
    .bingo-carton-preview__header {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        background: #85C1E9;
        color: #17202A;
        font-weight: bold;
        text-align: center;
        letter-spacing: 0.05em;
    }
    .bingo-carton-preview__header span {
        padding: 4px 2px;
        border-right: 1px solid rgba(23, 32, 42, 0.15);
    }
    .bingo-carton-preview__header span:last-child {
        border-right: none;
    }
    .bingo-carton-preview__grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
    }
    .bingo-carton-preview__cell {
        min-width: 34px;
        min-height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-right: 1px solid #d5dce3;
        border-bottom: 1px solid #d5dce3;
        font-size: 12px;
        font-weight: 600;
        color: #1a5276;
        background: #fff;
    }
    .bingo-carton-preview__cell:nth-child(5n) {
        border-right: none;
    }
    .bingo-carton-preview__cell--free {
        background: #fdebd0;
        color: #7d6608;
        font-size: 9px;
        font-weight: bold;
    }
    .bingo-carton-preview--mini .bingo-carton-preview__cell {
        min-width: 24px;
        min-height: 20px;
        font-size: 10px;
    }
    .bingo-carton-preview--mini .bingo-carton-preview__cell--free {
        font-size: 7px;
    }
</style>
<div class="{{ $claseContenedor }}" title="Vista previa de cart&oacute;n ({{ $lineas }} l&iacute;neas)">
    <div class="bingo-carton-preview__header">
        <span>B</span>
        <span>I</span>
        <span>N</span>
        <span>G</span>
        <span>O</span>
    </div>
    <div class="bingo-carton-preview__grid">
        @foreach ($numeros as $fila)
            @foreach (['B', 'I', 'N', 'G', 'O'] as $letra)
                @php
                    $valor = $fila[$letra];
                    $esFree = $valor === 'FREE';
                @endphp
                @if ($esFree)
                    <div class="bingo-carton-preview__cell bingo-carton-preview__cell--free">FREE</div>
                @else
                    <div class="bingo-carton-preview__cell">{{ $valor }}</div>
                @endif
            @endforeach
        @endforeach
    </div>
</div>
