<?php

namespace App\Console\Commands\Stock;

use App\Support\Stock\ArticuloIndumentariaTipoSupport;
use Illuminate\Console\Command;

class CorregirTipoIndumentariaArticuloCommand extends Command
{
    protected $signature = 'stock:corregir-tipo-indumentaria
                            {--aplicar : Graba tipo INDUMENTARIA en ERP (y Anita si --anita)}
                            {--anita : Además actualiza stkm_tipo_articulo en Anita}';

    protected $description = 'Pasa artículos (y categorías) de indumentaria de U/E/OTRO a tipo INDUMENTARIA. Deja SERVICIO.';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $anita = (bool) $this->option('anita');

        if (! $aplicar) {
            $this->warn('Simulación: no se graba. Use --aplicar (y --anita para stkmae).');
        }

        try {
            $r = ArticuloIndumentariaTipoSupport::corregir($aplicar, $aplicar && $anita);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Categorías: %d | Artículos: %d | Anita ok=%d fail=%d skip=%d',
            $r['categorias'],
            $r['articulos'],
            $r['anita_ok'],
            $r['anita_fail'],
            $r['anita_skip']
        ));

        return $r['anita_fail'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
