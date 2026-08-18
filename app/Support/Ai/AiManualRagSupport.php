<?php

namespace App\Support\Ai;

use Illuminate\Support\Facades\File;
use Throwable;

/**
 * RAG léxico sobre docs/manual-* (sin embeddings).
 * Index: storage/app/ai/manual_rag_index.json
 */
final class AiManualRagSupport
{
    public static function habilitado(): bool
    {
        return filter_var(config('ai.rag_manuales.habilitado', true), FILTER_VALIDATE_BOOLEAN)
            && ! filter_var(config('ai.kill_switch', false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function rutaIndice(): string
    {
        return storage_path('app/'.ltrim((string) config('ai.rag_manuales.index_path', 'ai/manual_rag_index.json'), '/'));
    }

    /**
     * @return array{chunks: int, modulos: list<string>, path: string}
     */
    public static function indexar(): array
    {
        $chunks = [];
        $modulos = [];
        $base = base_path('docs');
        if (! is_dir($base)) {
            throw new \RuntimeException('No existe docs/');
        }

        foreach (File::directories($base) as $dir) {
            $nombreDir = basename($dir);
            if (! str_starts_with($nombreDir, 'manual-')) {
                continue;
            }
            $contenidoPath = $dir.'/contenido.php';
            if (! is_file($contenidoPath)) {
                continue;
            }
            try {
                /** @var mixed $data */
                $data = include $contenidoPath;
            } catch (Throwable) {
                continue;
            }
            if (! is_array($data) || ! is_array($data['secciones'] ?? null)) {
                continue;
            }
            $modulo = (string) ($data['subtitulo'] ?? $nombreDir);
            $modulos[] = $nombreDir;
            foreach ($data['secciones'] as $i => $sec) {
                if (! is_array($sec)) {
                    continue;
                }
                $titulo = trim((string) ($sec['titulo'] ?? ('Sección '.($i + 1))));
                $partes = [];
                foreach ((array) ($sec['parrafos'] ?? []) as $p) {
                    $partes[] = trim((string) $p);
                }
                foreach ((array) ($sec['items'] ?? []) as $item) {
                    $partes[] = trim((string) $item);
                }
                if (! empty($sec['herramientas_clave'])) {
                    $partes[] = 'Herramientas: '.(string) $sec['herramientas_clave'];
                }
                $texto = trim(implode("\n", array_filter($partes)));
                if ($texto === '') {
                    continue;
                }
                $chunks[] = [
                    'id' => $nombreDir.'#'.$i,
                    'modulo' => $modulo,
                    'manual' => $nombreDir,
                    'titulo' => $titulo,
                    'texto' => mb_substr($texto, 0, 4000),
                    'url' => self::urlManual($nombreDir),
                ];
            }
        }

        $payload = [
            'generado_at' => now()->toIso8601String(),
            'chunks' => $chunks,
        ];
        $path = self::rutaIndice();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0775, true);
        }
        File::put($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return [
            'chunks' => count($chunks),
            'modulos' => array_values(array_unique($modulos)),
            'path' => $path,
        ];
    }

    /**
     * @return list<array{id: string, modulo: string, manual: string, titulo: string, texto: string, url: ?string, score: float}>
     */
    public static function buscar(string $consulta, ?int $topK = null): array
    {
        if (! self::habilitado()) {
            return [];
        }

        $topK = $topK ?? max(1, (int) config('ai.rag_manuales.top_k', 5));
        $chunks = self::cargarChunks();
        if ($chunks === []) {
            try {
                self::indexar();
                $chunks = self::cargarChunks();
            } catch (Throwable) {
                return [];
            }
        }

        $tokens = self::tokenizar($consulta);
        if ($tokens === []) {
            return [];
        }

        $scored = [];
        foreach ($chunks as $chunk) {
            $haystack = self::normalizar(($chunk['titulo'] ?? '').' '.($chunk['texto'] ?? '').' '.($chunk['modulo'] ?? ''));
            $score = 0.0;
            foreach ($tokens as $tok) {
                if ($tok === '') {
                    continue;
                }
                if (str_contains($haystack, $tok)) {
                    $score += mb_strlen($tok) >= 5 ? 2.0 : 1.0;
                    if (str_contains(self::normalizar((string) ($chunk['titulo'] ?? '')), $tok)) {
                        $score += 1.5;
                    }
                }
            }
            if ($score <= 0) {
                continue;
            }
            $chunk['score'] = $score;
            $scored[] = $chunk;
        }

        usort($scored, fn ($a, $b) => ($b['score'] <=> $a['score']));

        return array_slice($scored, 0, $topK);
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array{
     *   ok: bool,
     *   intent: string,
     *   score: float,
     *   parrafos: list<string>,
     *   links: list<array{etiqueta: string, url: string}>,
     *   datos: array<string,mixed>,
     *   tabla?: array{columnas: list<array{key: string, label: string}>, filas: list<array<string, string>>},
     *   error?: string
     * }
     */
    public static function consultar(array $params): array
    {
        $intent = AiConsultaOperativaSupport::INTENT_CONSULTAR_MANUAL;
        if (! self::habilitado()) {
            return [
                'ok' => false,
                'intent' => $intent,
                'score' => 0,
                'parrafos' => [],
                'links' => [],
                'datos' => [],
                'error' => 'RAG de manuales deshabilitado (AI_RAG_MANUALES_HABILITADO).',
            ];
        }

        $q = trim((string) ($params['valor'] ?? $params['pregunta'] ?? $params['texto'] ?? ''));
        if ($q === '') {
            return [
                'ok' => false,
                'intent' => $intent,
                'score' => 0,
                'parrafos' => [],
                'links' => [],
                'datos' => [],
                'error' => 'Indique qué busca en los manuales (ej. “cómo cargar una OC”).',
            ];
        }

        $hits = self::buscar($q);
        if ($hits === []) {
            return [
                'ok' => false,
                'intent' => $intent,
                'score' => 0.2,
                'parrafos' => ['No encontré pasajes relevantes en los manuales. Probá con otras palabras o abrí Ayuda → Manuales.'],
                'links' => [],
                'datos' => ['consulta' => $q],
                'error' => 'Sin resultados en el índice de manuales.',
            ];
        }

        $parrafos = ['Resultados del manual (RAG léxico) para: “'.$q.'”.'];
        $links = [];
        $filas = [];
        foreach ($hits as $hit) {
            $extracto = mb_substr((string) $hit['texto'], 0, 320);
            $parrafos[] = '• '.$hit['titulo'].' — '.$hit['modulo'].': '.$extracto
                .(mb_strlen((string) $hit['texto']) > 320 ? '…' : '');
            if (! empty($hit['url'])) {
                $links[] = [
                    'etiqueta' => $hit['manual'].' / '.$hit['titulo'],
                    'url' => (string) $hit['url'],
                ];
            }
            $filas[] = [
                'modulo' => (string) $hit['modulo'],
                'seccion' => (string) $hit['titulo'],
                'score' => number_format((float) $hit['score'], 1, ',', ''),
                'extracto' => $extracto,
            ];
        }

        return [
            'ok' => true,
            'intent' => $intent,
            'score' => min(1.0, 0.4 + (0.1 * count($hits))),
            'parrafos' => $parrafos,
            'links' => $links,
            'datos' => [
                'consulta' => $q,
                'hits' => count($hits),
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'modulo', 'label' => 'Módulo'],
                    ['key' => 'seccion', 'label' => 'Sección'],
                    ['key' => 'score', 'label' => 'Score'],
                    ['key' => 'extracto', 'label' => 'Extracto'],
                ],
                'filas' => $filas,
            ],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function cargarChunks(): array
    {
        $path = self::rutaIndice();
        if (! is_file($path)) {
            return [];
        }
        try {
            $data = json_decode((string) File::get($path), true);
        } catch (Throwable) {
            return [];
        }
        if (! is_array($data) || ! is_array($data['chunks'] ?? null)) {
            return [];
        }

        return $data['chunks'];
    }

    /**
     * @return list<string>
     */
    private static function tokenizar(string $texto): array
    {
        $norm = self::normalizar($texto);
        $partes = preg_split('/[^a-z0-9]+/u', $norm) ?: [];
        $stop = ['de', 'la', 'el', 'los', 'las', 'un', 'una', 'y', 'o', 'en', 'del', 'al', 'para', 'por', 'con', 'como', 'que', 'se', 'su', 'es', 'manual', 'ayuda', 'anita', 'erp'];
        $out = [];
        foreach ($partes as $p) {
            if (mb_strlen($p) < 3 || in_array($p, $stop, true)) {
                continue;
            }
            $out[] = $p;
        }

        return array_values(array_unique($out));
    }

    private static function normalizar(string $texto): string
    {
        $t = mb_strtolower($texto, 'UTF-8');

        return strtr($t, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }

    private static function urlManual(string $manualDir): ?string
    {
        $map = [
            'manual-compras' => 'manual_compras',
            'manual-stock' => 'manual_stock',
            'manual-recepcion-movstock' => 'manual_recepcion_movstock',
            'manual-stock-gastronomia' => 'manual_stock_gastronomia',
            'manual-gastronomia' => 'manual_gastronomia',
            'manual-ventas' => 'manual_ventas',
            'manual-vending' => 'manual_vending',
            'manual-canjes-marketing' => 'manual_canjes_marketing',
            'manual-solicitudpago' => 'manual_solicitudpago',
            'manual-caja' => 'manual_caja',
            'manual-contable' => 'manual_contable',
            'manual-cierres-rendiciones' => 'manual_cierres_rendiciones',
            'manual-reporte-definible' => 'manual_reporte_definible',
            'manual-propuesta-pago' => 'manual_propuesta_pago',
            'manual-uif' => 'manual_uif',
            'manual-ia' => 'manual_ia',
        ];
        $name = $map[$manualDir] ?? null;
        if ($name === null || ! \Route::has($name)) {
            return null;
        }
        try {
            return route($name);
        } catch (Throwable) {
            return null;
        }
    }
}
