<?php

namespace App\Console\Commands;

use App\Support\Ai\AiManualRagSupport;
use Illuminate\Console\Command;
use Throwable;

class AiIndexarManualesCommand extends Command
{
    protected $signature = 'ai:indexar-manuales';

    protected $description = 'Indexa docs/manual-* para el RAG (léxico + embeddings opcionales)';

    public function handle(): int
    {
        try {
            $res = AiManualRagSupport::indexar();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Índice RAG generado: '.$res['chunks'].' chunk(s).');
        if (isset($res['embeddings'])) {
            $flagOn = filter_var(config('ai.rag_manuales.embeddings_habilitado', false), FILTER_VALIDATE_BOOLEAN);
            $this->line(
                'Embeddings: '.$res['embeddings']
                .($flagOn ? ' (AI_RAG_EMBEDDINGS_HABILITADO=true)' : ' (deshabilitado)')
            );
        }
        $this->line('Módulos: '.implode(', ', $res['modulos']));
        $this->line('Path: '.$res['path']);

        return self::SUCCESS;
    }
}
