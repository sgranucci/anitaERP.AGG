<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Services\Sueldos\ImportarAuxconfLiquidacionService;
use App\Support\Sueldos\LiquidacionConfidencialSeguridadSupport;
use Illuminate\Console\Command;

/**
 * Importa nómina confidencial (auxconf/auxconfh) a una corrida ERP.
 * Dry-run por defecto; --ejecutar exige --confirmar=<plan_hash> y --usuario=.
 */
class SueldosImportarAuxhistLiquidacionCommand extends Command
{
    protected $signature = 'sueldos:importar-auxhist-liquidacion
                            {--liquidacion= : ID liquidacion_sueldos ERP}
                            {--empresa-anita= : Código empresa Anita (default: mapeo desde ERP)}
                            {--fuente=auto : auto|auxconf|auxconfh}
                            {--usuario= : ID usuario que autoriza (obligatorio con --ejecutar)}
                            {--confirmar= : Hash del dry-run a confirmar}
                            {--eliminar-ausentes : Borra recibos importados que ya no están en Anita}
                            {--ejecutar : Persiste; sin este flag solo analiza}';

    protected $description = 'Importa auxconf/auxconfh a recibos confidenciales de una corrida (dry-run por defecto)';

    public function handle(ImportarAuxconfLiquidacionService $import): int
    {
        $liqId = (int) $this->option('liquidacion');
        if ($liqId <= 0) {
            $this->error('Requiere --liquidacion=ID');

            return self::FAILURE;
        }

        $liq = Liquidacion_Sueldos::query()->find($liqId);
        if (! $liq) {
            $this->error('Liquidación ERP no encontrada');

            return self::FAILURE;
        }

        $fuente = (string) $this->option('fuente');
        $empresaAnitaOpt = $this->option('empresa-anita');
        $empresaAnita = $empresaAnitaOpt !== null && $empresaAnitaOpt !== ''
            ? (int) $empresaAnitaOpt
            : null;

        try {
            $plan = $import->analizar(
                $liq,
                $fuente,
                $empresaAnita,
                (bool) $this->option('eliminar-ausentes')
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Liquidación ERP #%d (empresa %d) | Anita %d | fuente %s | %s',
            $liqId,
            (int) $liq->empresa_id,
            (int) $plan['liquidacion_anita'],
            $plan['fuente'],
            $this->option('ejecutar') ? 'EJECUCIÓN' : 'DRY-RUN'
        ));
        $this->line(sprintf('  Filas leídas: %d | Empleados: %d', $plan['filas_leidas'], $plan['empleados']));
        $this->line(sprintf(
            '  Crear: %d | Actualizar: %d | Iguales: %d | Marcar conf.: %d',
            $plan['recibos_crear'],
            $plan['recibos_actualizar'],
            $plan['recibos_iguales'],
            $plan['empleados_marcar_confidencial']
        ));
        if ((int) ($plan['recibos_eliminar'] ?? 0) > 0) {
            $this->warn(sprintf(
                '  Recibos importados preexistentes a eliminar: %d (IDs: %s)',
                $plan['recibos_eliminar'],
                implode(', ', $plan['recibo_ids_eliminar'])
            ));
        }
        $this->line('  plan_hash: '.$plan['plan_hash']);

        if (($plan['bloqueantes'] ?? []) !== []) {
            $this->warn('Bloqueantes:');
            foreach ($plan['bloqueantes'] as $b) {
                $this->line('  - '.$b);
            }
        }

        if (! $this->option('ejecutar')) {
            $this->comment('Sin --ejecutar no se persistió nada. Para ejecutar:');
            $this->line(sprintf(
                '  php artisan sueldos:importar-auxhist-liquidacion --liquidacion=%d --fuente=%s --usuario=ID --confirmar=%s%s --ejecutar',
                $liqId,
                $plan['fuente'],
                $plan['plan_hash'],
                $this->option('eliminar-ausentes') ? ' --eliminar-ausentes' : ''
            ));

            return self::SUCCESS;
        }

        $usuarioId = (int) $this->option('usuario');
        $hash = (string) $this->option('confirmar');
        if ($usuarioId <= 0 || $hash === '') {
            $this->error('--ejecutar requiere --usuario=ID y --confirmar=<plan_hash del dry-run>');

            return self::FAILURE;
        }
        if ($hash !== $plan['plan_hash']) {
            $this->error('El --confirmar no coincide con el plan actual. Corra dry-run de nuevo.');

            return self::FAILURE;
        }

        $usuario = Usuario::query()->find($usuarioId);
        if (! $usuario) {
            $this->error('Usuario no encontrado');

            return self::FAILURE;
        }
        if (! LiquidacionConfidencialSeguridadSupport::usuarioPuedeImportar($usuario, (int) $liq->empresa_id)) {
            $this->error('El usuario no tiene permiso/empresa para importar confidencial.');

            return self::FAILURE;
        }

        try {
            $res = $import->ejecutar($liq, $plan, $usuario, (bool) $this->option('eliminar-ausentes'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Persistido: creados=%d actualizados=%d iguales=%d marcados=%d',
            $res['recibos_creados'],
            $res['recibos_actualizados'],
            $res['recibos_iguales'],
            $res['empleados_marcados']
        ));

        return self::SUCCESS;
    }
}
