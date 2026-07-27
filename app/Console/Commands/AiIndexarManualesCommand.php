<?php

namespace App\Console\Commands;

use App\Support\Ai\AiManualRagSupport;
use Illuminate\Console\Command;
use Throwable;

class AiIndexarManualesCommand extends Command
{
    protected $signature = 'ai:indexar-manuales';

    protected $description = 'Indexa docs/manual-* para el RAG léxico del panel IA';

    public function handle(): int
    {
        try {
            $res = AiManualRagSupport::indexar();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Índice RAG generado: '.$res['chunks'].' chunk(s).');
        $this->line('Módulos: '.implode(', ', $res['modulos']));
        $this->line('Path: '.$res['path']);

        return self::SUCCESS;
    }
}
