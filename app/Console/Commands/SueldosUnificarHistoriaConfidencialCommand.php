<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sueldos\ConfidencialHistoriaUnificarService;
use Illuminate\Console\Command;

/**
 * Unifica cabecera e historia de ingresos/egresos de los legajos confidenciales con /usr2/conf.
 *
 * Sin --confirmar: solo dry-run (no escribe).
 * Con --confirmar=HASH: graba solo si el plan coincide con el dry-run revisado.
 */
class SueldosUnificarHistoriaConfidencialCommand extends Command
{
    protected $signature = 'sueldos:unificar-historia-confidencial
        {--legajo=* : Limita a estos legajos (documento). Repetible}
        {--empresa=* : Limita a estos códigos de empresa Anita (1 Biyemas, 2 Kandiko, 3 Rebisco, 4 Byson). Repetible}
        {--confirmar= : Hash del plan revisado en el dry-run. Sin esta opción no se escribe nada}';

    protected $description = 'Unifica la historia de los legajos confidenciales con /usr2/conf (dry-run por defecto).';

    public function handle(ConfidencialHistoriaUnificarService $servicio): int
    {
        $legajos = array_map('intval', (array) $this->option('legajo'));
        $empresas = array_map('intval', (array) $this->option('empresa'));
        $plan = $servicio->planificar($legajos, $empresas);

        foreach ($plan['ops'] as $op) {
            $this->line('');
            $this->info($op['empresa'].' · legajo '.$op['legajo'].' · '.$op['nombre'].' (id '.$op['empleado_id'].')');
            if ($op['cabecera'] !== null) {
                $this->line('  CABECERA  '.$this->fmt($op['cabecera_antes']).'  →  '.$this->fmt($op['cabecera']));
            }
            foreach ($op['actualizar'] as $u) {
                $this->line('  FILA #'.$u['id'].'  '.$this->fmtFila($u['antes']).'  →  '.$this->fmtFila($u['despues']));
            }
            foreach ($op['borrar'] as $b) {
                $this->line('  BORRAR #'.$b['id'].'  '.($b['ing'] ?? '?').' → '.($b['egr'] ?? '—').' '.($b['coment'] ?? '').'  (duplicado fusionado)');
            }
            foreach ($op['crear'] as $c) {
                $this->line('  CREAR  '.$this->fmtFila($c));
            }
        }

        $r = $plan['resumen'];
        $this->line('');
        $this->info('Legajos confidenciales revisados: '.$r['legajos_conf'].' · sin cambios: '.$r['sin_cambios'].' · con cambios: '.$r['con_cambios']);
        $this->line('Cabeceras a actualizar: '.$r['cabeceras_actualizar'].' · filas a actualizar: '.$r['filas_actualizar']
            .' · filas a borrar (duplicados): '.$r['filas_borrar'].' · filas a crear: '.$r['filas_crear']);
        $this->line('Hash del plan: '.$plan['hash']);

        $confirmar = (string) $this->option('confirmar');
        if ($confirmar === '') {
            $this->warn('DRY-RUN: no se escribió nada. Esta corrida no queda en audits (consola).');

            return self::SUCCESS;
        }

        if (! hash_equals($plan['hash'], $confirmar)) {
            $this->error('El plan cambió desde el dry-run revisado. No se escribió nada. Vuelva a correr el dry-run.');

            return self::FAILURE;
        }

        $res = $servicio->ejecutar($plan['ops']);
        $this->info('Grabado: cabeceras '.$res['cabeceras'].' · filas actualizadas '.$res['actualizadas']
            .' · borradas '.$res['borradas'].' · creadas '.$res['creadas']);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private function fmt(array $c): string
    {
        return '['.$c['estado'].' ing '.($c['fecha_ingreso'] ?? '—').' egr '.($c['fecha_egreso'] ?? '—')
            .' mot '.($c['motivoegreso_id'] ?? '—').($c['comentario_baja'] ? ' "'.$c['comentario_baja'].'"' : '').']';
    }

    /**
     * @param  array<string, mixed>  $f
     */
    private function fmtFila(array $f): string
    {
        return '['.$f['tipo'].' '.$f['ing'].' → '.($f['egr'] ?? '—').' mot '.($f['motivo'] ?? '—')
            .(! empty($f['coment']) ? ' "'.$f['coment'].'"' : '').']';
    }
}
