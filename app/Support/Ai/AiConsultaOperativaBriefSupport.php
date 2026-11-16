<?php

namespace App\Support\Ai;

use Illuminate\Support\Facades\Session;

/**
 * Brief de reunión del día (sesión): bloques de KPIs para decidir en 5 minutos.
 */
final class AiConsultaOperativaBriefSupport
{
    public const SESSION_KEY = 'ai_consulta_operativa_brief';

    /**
     * @param  list<array{titulo: string, lineas: list<string>, frase?: string|null, url?: string|null}>  $items
     */
    public static function reemplazar(array $items): void
    {
        Session::put(self::SESSION_KEY, [
            'items' => array_values($items),
            'generado_at' => now()->toDateTimeString(),
        ]);
    }

    public static function limpiar(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * @return array<string,mixed>
     */
    public static function mostrar(): array
    {
        $data = Session::get(self::SESSION_KEY);
        $items = is_array($data) ? ($data['items'] ?? []) : [];
        if (! is_array($items) || $items === []) {
            return [
                'ok' => true,
                'intent' => AiConsultaOperativaSupport::INTENT_BRIEF_DIA,
                'score' => 0.7,
                'parrafos' => [
                    'El brief del día está vacío.',
                    'Escribí «armar brief del día» para juntar compras, aging de cheques y stock crítico.',
                ],
                'links' => [],
                'datos' => ['accion' => 'mostrar', 'vacio' => true],
            ];
        }

        $parrafos = [
            'Brief del día'
               .(! empty($data['generado_at']) ? ' · generado '.$data['generado_at'] : ''),
        ];
        $filas = [];
        $pasos = [];
        $n = 0;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $n++;
            $titulo = (string) ($item['titulo'] ?? 'Bloque '.$n);
            $parrafos[] = $n.'. '.$titulo;
            foreach (array_slice($item['lineas'] ?? [], 0, 4) as $linea) {
                $linea = trim((string) $linea);
                if ($linea !== '') {
                    $parrafos[] = '   · '.$linea;
                }
            }
            $filas[] = [
                'bloque' => (string) $n,
                'titulo' => $titulo,
                'detalle' => implode(' | ', array_slice($item['lineas'] ?? [], 0, 2)),
            ];
            $pasos[] = [
                'etiqueta' => $titulo,
                'frase' => (string) ($item['frase'] ?? ''),
                'url' => ! empty($item['url']) ? (string) $item['url'] : null,
            ];
        }

        return [
            'ok' => true,
            'intent' => AiConsultaOperativaSupport::INTENT_BRIEF_DIA,
            'score' => 0.92,
            'parrafos' => $parrafos,
            'links' => [
                [
                    'etiqueta' => 'PDF brief (1 hoja)',
                    'url' => route('ai_consulta_brief_dia_pdf'),
                ],
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'bloque', 'label' => '#'],
                    ['key' => 'titulo', 'label' => 'Tema'],
                    ['key' => 'detalle', 'label' => 'Resumen'],
                ],
                'filas' => $filas,
            ],
            'datos' => [
                'accion' => 'mostrar',
                'cantidad' => $n,
                'pasos' => $pasos,
                'pdf_url' => route('ai_consulta_brief_dia_pdf'),
            ],
        ];
    }
}
