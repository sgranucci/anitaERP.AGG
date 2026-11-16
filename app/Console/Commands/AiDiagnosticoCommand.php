<?php

namespace App\Console\Commands;

use App\Support\Ai\AiLlmCadenaExternaSupport;
use App\Support\Ai\AiManualRagSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Chequeo read-only del stack IA / Consulta Inteligente / RAG.
 * Pensado para correr en cada entorno (AGG, Ferli, Crown, …) tras pull o copy de .env.
 */
class AiDiagnosticoCommand extends Command
{
    protected $signature = 'ai:diagnostico
        {--probe : Llama Ollama / Groq / Gemini con un ping mínimo (usa cuota)}
        {--json : Sale JSON en stdout}';

    protected $description = 'Diagnóstico IA/LLM/RAG/Ollama (solo lectura; --probe opcional)';

    public function handle(): int
    {
        $report = $this->armarReporte((bool) $this->option('probe'));

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return ($report['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        $this->imprimir($report);

        return ($report['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<string, mixed>
     */
    private function armarReporte(bool $probe): array
    {
        $checks = [];
        $avisos = [];
        $errores = [];

        $empresa = (string) config('app.empresa', '');
        $url = (string) config('app.url', '');

        $aiOn = filter_var(config('ai.habilitado', false), FILTER_VALIDATE_BOOLEAN);
        $kill = filter_var(config('ai.kill_switch', false), FILTER_VALIDATE_BOOLEAN);
        $checks['ai_habilitado'] = $aiOn;
        $checks['ai_kill_switch'] = $kill;
        if (! $aiOn) {
            $avisos[] = 'AI_HABILITADO=false — copiloto/skills apagados.';
        }
        if ($kill) {
            $errores[] = 'AI_KILL_SWITCH=true — IA cortada.';
        }

        $ollamaUrl = rtrim((string) config('ai.drivers.ollama.url', ''), '/');
        $ollamaModel = (string) config('ai.drivers.ollama.model', '');
        $checks['ollama_url'] = $ollamaUrl;
        $checks['ollama_model_config'] = $ollamaModel;
        $ollamaReach = false;
        $ollamaModels = [];
        $ollamaModelPresente = false;
        try {
            $res = Http::timeout(3)->acceptJson()->get($ollamaUrl.'/api/tags');
            $ollamaReach = $res->successful();
            if ($ollamaReach) {
                foreach ((array) data_get($res->json(), 'models', []) as $m) {
                    $name = (string) ($m['name'] ?? '');
                    if ($name !== '') {
                        $ollamaModels[] = $name;
                    }
                }
                $ollamaModelPresente = in_array($ollamaModel, $ollamaModels, true)
                    || in_array($ollamaModel.':latest', $ollamaModels, true);
            }
        } catch (Throwable $e) {
            $avisos[] = 'Ollama no responde: '.$e->getMessage();
        }
        $checks['ollama_reachable'] = $ollamaReach;
        $checks['ollama_models'] = $ollamaModels;
        $checks['ollama_model_presente'] = $ollamaModelPresente;
        if ($ollamaReach && ! $ollamaModelPresente) {
            $errores[] = "Ollama no tiene el modelo configurado «{$ollamaModel}». Instalados: "
                .(implode(', ', $ollamaModels) ?: '(ninguno)');
            $avisos[] = 'Ajustá AI_OLLAMA_MODEL al que exista (ollama list) o hacé ollama pull.';
        }
        if (! $ollamaReach) {
            $avisos[] = 'Sin Ollama local el copiloto usará failover CI_LLM_* si hay keys.';
        }

        $ciOn = filter_var(config('consulta_inteligente.llm_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $cadena = config('consulta_inteligente.llm_chain', []);
        $checks['ci_llm_enabled'] = $ciOn;
        $checks['ci_llm_chain'] = is_array($cadena) ? $cadena : [];
        if (! $ciOn) {
            $avisos[] = 'CI_LLM_ENABLED=false — Consulta Inteligente NL sin LLM (solo diccionario local).';
        }

        $proveedores = [];
        foreach (['groq', 'gemini', 'primary'] as $id) {
            $p = config("consulta_inteligente.llm_providers.{$id}", []);
            $key = trim((string) ($p['api_key'] ?? ''));
            $proveedores[$id] = [
                'model' => (string) ($p['model'] ?? ''),
                'base_url' => (string) ($p['base_url'] ?? ''),
                'api_key_set' => $key !== '',
                'api_key_len' => strlen($key),
            ];
        }
        $checks['llm_providers'] = $proveedores;

        $activos = AiLlmCadenaExternaSupport::proveedoresActivos();
        $checks['cadena_activa'] = AiLlmCadenaExternaSupport::cadenaActivaNombres();
        $checks['copiloto_failover'] = AiLlmCadenaExternaSupport::habilitadoParaCopiloto();
        if ($activos === []) {
            $errores[] = 'Ningún proveedor CI_LLM_* con API key (cadena vacía).';
        }

        $ragOn = filter_var(config('ai.rag_manuales.habilitado', true), FILTER_VALIDATE_BOOLEAN);
        $ragPath = AiManualRagSupport::rutaIndice();
        $ragExists = is_file($ragPath);
        $ragChunks = 0;
        $ragGenerado = null;
        if ($ragExists) {
            try {
                $j = json_decode((string) file_get_contents($ragPath), true);
                $ragChunks = is_array($j['chunks'] ?? null) ? count($j['chunks']) : 0;
                $ragGenerado = $j['generado_at'] ?? null;
            } catch (Throwable) {
                $avisos[] = 'Índice RAG ilegible.';
            }
        } elseif ($ragOn) {
            $errores[] = 'RAG habilitado pero falta índice: php artisan ai:indexar-manuales';
        }
        $checks['rag'] = [
            'habilitado' => $ragOn,
            'path' => $ragPath,
            'exists' => $ragExists,
            'chunks' => $ragChunks,
            'generado_at' => $ragGenerado,
            'writable_dir' => is_dir(dirname($ragPath)) && is_writable(dirname($ragPath)),
        ];

        $tablas = [
            'ai_copiloto_preferencia',
            'consulta_inteligente_nl_sinonimo',
            'consulta_inteligente_auditoria',
            'buscador_fts_documento',
        ];
        $tablaOk = [];
        foreach ($tablas as $t) {
            try {
                $tablaOk[$t] = Schema::hasTable($t);
            } catch (Throwable) {
                $tablaOk[$t] = false;
            }
            if (! ($tablaOk[$t] ?? false)) {
                $errores[] = "Falta tabla {$t} — correr migraciones.";
            }
        }
        $checks['tablas'] = $tablaOk;

        $fts = null;
        if ($tablaOk['buscador_fts_documento'] ?? false) {
            try {
                $fts = (int) \Illuminate\Support\Facades\DB::table('buscador_fts_documento')->count();
                if ($fts === 0) {
                    $avisos[] = 'buscador_fts_documento vacío — opcional: php artisan buscador:indexar-adjuntos';
                }
            } catch (Throwable) {
                $fts = null;
            }
        }
        $checks['buscador_fts_docs'] = $fts;

        $probes = null;
        if ($probe) {
            $probes = $this->ejecutarProbes($ollamaReach, $ollamaUrl, $ollamaModel, $activos);
            foreach ($probes as $nombre => $p) {
                if (! ($p['ok'] ?? false)) {
                    $avisos[] = "Probe {$nombre}: ".($p['error'] ?? 'falló');
                }
            }
        }

        $ok = $errores === []
            && $aiOn
            && ! $kill
            && ($activos !== [] || $ollamaReach);

        return [
            'ok' => $ok,
            'entorno' => [
                'empresa' => $empresa,
                'url' => $url,
                'env' => (string) config('app.env'),
            ],
            'checks' => $checks,
            'probes' => $probes,
            'avisos' => $avisos,
            'errores' => $errores,
            'acciones_sugeridas' => $this->accionesSugeridas($checks, $errores, $avisos),
        ];
    }

    /**
     * @param  list<array{id: string, nombre: string, api_key: string, base_url: string, model: string, timeout: int}>  $activos
     * @return array<string, array{ok: bool, detail?: string, error?: string}>
     */
    private function ejecutarProbes(bool $ollamaReach, string $ollamaUrl, string $ollamaModel, array $activos): array
    {
        $out = [];

        if ($ollamaReach && $ollamaModel !== '') {
            try {
                $res = Http::timeout(45)->acceptJson()->post($ollamaUrl.'/api/generate', [
                    'model' => $ollamaModel,
                    'prompt' => 'Respondé solo: OK',
                    'stream' => false,
                    'options' => ['num_predict' => 8],
                ]);
                $txt = trim((string) data_get($res->json(), 'response', ''));
                $out['ollama'] = $res->successful()
                    ? ['ok' => true, 'detail' => mb_substr($txt, 0, 40)]
                    : ['ok' => false, 'error' => 'HTTP '.$res->status()];
            } catch (Throwable $e) {
                $out['ollama'] = ['ok' => false, 'error' => $e->getMessage()];
            }
        } else {
            $out['ollama'] = ['ok' => false, 'error' => 'no reachable / sin modelo'];
        }

        foreach ($activos as $prov) {
            $id = $prov['id'];
            try {
                $res = Http::withToken($prov['api_key'])
                    ->timeout(max(8, (int) $prov['timeout']))
                    ->acceptJson()
                    ->post(rtrim($prov['base_url'], '/').'/chat/completions', [
                        'model' => $prov['model'],
                        'messages' => [
                            ['role' => 'user', 'content' => 'Respondé solo la palabra OK'],
                        ],
                        'max_tokens' => 32,
                        'temperature' => 0,
                    ]);
                $content = trim((string) data_get($res->json(), 'choices.0.message.content', ''));
                $apiErr = data_get($res->json(), 'error.message');
                if (! $res->successful()) {
                    $out[$id] = ['ok' => false, 'error' => 'HTTP '.$res->status().' '.((string) $apiErr)];
                } elseif ($content === '') {
                    $out[$id] = [
                        'ok' => false,
                        'error' => 'content vacío (¿modelo reasoning? probá qwen/qwen3.8-27b)',
                    ];
                } else {
                    $out[$id] = ['ok' => true, 'detail' => mb_substr($content, 0, 40)];
                }
            } catch (Throwable $e) {
                $out[$id] = ['ok' => false, 'error' => $e->getMessage()];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $checks
     * @param  list<string>  $errores
     * @param  list<string>  $avisos
     * @return list<string>
     */
    private function accionesSugeridas(array $checks, array $errores, array $avisos): array
    {
        $acc = [];
        if ($errores !== [] || $avisos !== []) {
            $acc[] = 'Revisar .env: AI_*, CI_LLM_* → php artisan config:clear';
        }
        if (! ($checks['ollama_model_presente'] ?? false)) {
            $acc[] = 'ollama list && ollama pull <modelo>  O  AI_OLLAMA_MODEL=<instalado>';
        }
        if (! (($checks['rag']['exists'] ?? false))) {
            $acc[] = 'sudo -u www-data php artisan ai:indexar-manuales';
        }
        if (($checks['buscador_fts_docs'] ?? null) === 0) {
            $acc[] = 'Opcional: php artisan buscador:indexar-adjuntos';
        }
        $acc[] = 'Re-chequear: php artisan ai:diagnostico --probe';

        return $acc;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function imprimir(array $report): void
    {
        $e = $report['entorno'];
        $this->info('Diagnóstico IA — '.$e['empresa'].' ('.$e['url'].') ['.$e['env'].']');
        $this->newLine();

        $c = $report['checks'];
        $this->table(
            ['Check', 'Valor'],
            [
                ['AI_HABILITADO', $c['ai_habilitado'] ? 'true' : 'false'],
                ['AI_KILL_SWITCH', $c['ai_kill_switch'] ? 'true' : 'false'],
                ['Ollama URL', $c['ollama_url']],
                ['Ollama modelo (.env)', $c['ollama_model_config']],
                ['Ollama up', $c['ollama_reachable'] ? 'sí' : 'no'],
                ['Modelo instalado', $c['ollama_model_presente'] ? 'sí' : 'NO'],
                ['Ollama list', implode(', ', $c['ollama_models']) ?: '(vacío)'],
                ['CI_LLM_ENABLED', $c['ci_llm_enabled'] ? 'true' : 'false'],
                ['CI_LLM_CHAIN', implode(',', $c['ci_llm_chain'])],
                ['Cadena activa', implode(' → ', $c['cadena_activa']) ?: '(vacía)'],
                ['Copiloto failover', $c['copiloto_failover'] ? 'sí' : 'no'],
                ['Groq key/model', ($c['llm_providers']['groq']['api_key_set'] ? 'set' : 'VACÍA')
                    .' / '.$c['llm_providers']['groq']['model']],
                ['Gemini key/model', ($c['llm_providers']['gemini']['api_key_set'] ? 'set' : 'VACÍA')
                    .' / '.$c['llm_providers']['gemini']['model']],
                ['RAG índice', ($c['rag']['exists'] ? 'sí ('.$c['rag']['chunks'].' chunks)' : 'NO')
                    .' @ '.$c['rag']['path']],
                ['FTS docs', var_export($c['buscador_fts_docs'], true)],
            ]
        );

        if (! empty($report['probes'])) {
            $this->newLine();
            $this->info('Probes:');
            foreach ($report['probes'] as $nombre => $p) {
                $line = ($p['ok'] ?? false)
                    ? '<info>OK</info> '.($p['detail'] ?? '')
                    : '<error>FAIL</error> '.($p['error'] ?? '');
                $this->line("  {$nombre}: {$line}");
            }
        }

        if ($report['errores'] !== []) {
            $this->newLine();
            $this->error('Errores:');
            foreach ($report['errores'] as $x) {
                $this->line('  - '.$x);
            }
        }
        if ($report['avisos'] !== []) {
            $this->newLine();
            $this->warn('Avisos:');
            foreach ($report['avisos'] as $x) {
                $this->line('  - '.$x);
            }
        }

        $this->newLine();
        $this->info('Acciones sugeridas (AGG / Crown / Ferli):');
        foreach ($report['acciones_sugeridas'] as $a) {
            $this->line('  • '.$a);
        }

        $this->newLine();
        $this->line($report['ok'] ? '<info>RESULTADO: OK</info>' : '<error>RESULTADO: REVISAR</error>');
    }
}
