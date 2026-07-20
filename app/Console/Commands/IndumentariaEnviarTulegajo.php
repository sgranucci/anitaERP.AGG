<?php

namespace App\Console\Commands;

use App\Models\Sueldos\Entrega_Prenda_Sueldos;
use App\Services\Sueldos\TuLegajoClient;
use Illuminate\Console\Command;

class IndumentariaEnviarTulegajo extends Command
{
    protected $signature = 'indumentaria:enviar-tulegajo
        {--desde= : Fecha mínima de la entrega (YYYY-MM-DD)}
        {--hasta= : Fecha máxima de la entrega (YYYY-MM-DD)}
        {--empresa= : ID de empresa ERP para filtrar empleados}
        {--reintentar : Reenviar también las que quedaron en ERROR}
        {--limite=0 : Máximo de comprobantes a enviar (0 = sin límite)}
        {--dry-run : Solo listar las entregas que se enviarían}';

    protected $description = 'Sube a TuLegajo los comprobantes de entrega de indumentaria pendientes.';

    public function handle(TuLegajoClient $cliente): int
    {
        if (! $cliente->habilitado()) {
            $this->error('TuLegajo no está habilitado o falta la API KEY (config/tulegajo.php).');

            return self::FAILURE;
        }

        $query = Entrega_Prenda_Sueldos::query()->orderBy('id');

        if ($this->option('reintentar')) {
            // Todo lo que no esté enviado (pendiente o con error).
            $query->where(fn ($q) => $q->whereNull('tulegajo_estado')->orWhere('tulegajo_estado', '!=', 'ENVIADO'));
        } else {
            // Solo lo que nunca se intentó.
            $query->whereNull('tulegajo_estado');
        }

        if ($this->option('desde')) {
            $query->whereDate('fecha', '>=', $this->option('desde'));
        }
        if ($this->option('hasta')) {
            $query->whereDate('fecha', '<=', $this->option('hasta'));
        }
        if ($this->option('empresa')) {
            $empresaId = (int) $this->option('empresa');
            $query->whereHas('empleado', fn ($q) => $q->where('empresa_id', $empresaId));
        }

        $limite = (int) $this->option('limite');
        if ($limite > 0) {
            $query->limit($limite);
        }

        $entregas = $query->get();
        if ($entregas->isEmpty()) {
            $this->info('No hay entregas pendientes de enviar.');

            return self::SUCCESS;
        }

        $this->info('Entregas a procesar: '.$entregas->count().($this->option('dry-run') ? ' (dry-run)' : ''));

        $ok = 0;
        $err = 0;
        $filas = [];
        foreach ($entregas as $e) {
            if ($this->option('dry-run')) {
                $filas[] = [$e->id, optional($e->fecha)->format('Y-m-d'), $e->empleado_id, $e->tulegajo_estado ?? 'PENDIENTE'];
                continue;
            }

            $r = $cliente->subirComprobanteEntrega($e);
            if ($r['ok']) {
                $ok++;
            } else {
                $err++;
            }
            $filas[] = [$e->id, optional($e->fecha)->format('Y-m-d'), $e->empleado_id, $r['ok'] ? 'ENVIADO' : 'ERROR: '.$r['mensaje']];
        }

        $this->table(['Entrega', 'Fecha', 'Empleado', 'Resultado'], $filas);

        if (! $this->option('dry-run')) {
            $this->info("Enviadas: {$ok} | Errores: {$err}");
        }

        return self::SUCCESS;
    }
}
