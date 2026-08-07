<?php

namespace App\Console\Commands;

use App\Models\Ventas\ViandaConsumo;
use App\Models\Ventas\ViandaUsuario;
use App\Support\Ventas\Vianda\ViandaCentrocostoEmpleadoSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Completa el centro de costo de los usuarios de vianda que Anita dejó sin imputar,
 * tomándolo del legajo de sueldos (el código de usuario de vianda es el documento).
 * Arrastra el centro de costo a los consumos ya marchados que quedaron sin él.
 */
class CompletarCentrocostoViandaDesdeEmpleado extends Command
{
    protected $signature = 'vianda:completar-centrocosto-empleado
        {--empresa= : Limitar a una empresa (1=Biyemas, 2=Kandiko, 3=Rebisco)}
        {--aplicar : Graba los cambios; sin esta opción solo simula}';

    protected $description = 'Completa el centro de costo de usuarios de vianda sin imputar usando el legajo de sueldos (por documento)';

    public function handle(): int
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $aplicar = (bool) $this->option('aplicar');
        $empresaOpt = $this->option('empresa');

        $usuarios = ViandaUsuario::query()
            ->where(function ($q) {
                $q->whereNull('centrocosto_id')->orWhere('centrocosto_id', 0);
            })
            ->when($empresaOpt !== null && $empresaOpt !== '', fn ($q) => $q->where('empresa_id', (int) $empresaOpt))
            ->orderBy('empresa_id')
            ->orderBy('nombre')
            ->get(['id', 'empresa_id', 'codigo_usuario', 'nombre', 'centrocosto_id']);

        if ($usuarios->isEmpty()) {
            $this->info('No hay usuarios de vianda sin centro de costo.');

            return self::SUCCESS;
        }

        $this->info(($aplicar ? 'Completando' : 'SIMULACIÓN (sin --aplicar) sobre ')
            .' '.$usuarios->count().' usuarios de vianda sin centro de costo…');

        $resueltos = 0;
        $sinResolver = [];
        $consumosActualizados = 0;
        $filas = [];

        foreach ($usuarios as $usuario) {
            $empleado = ViandaCentrocostoEmpleadoSupport::empleadoPorDocumento(
                $usuario->codigo_usuario,
                (int) $usuario->empresa_id,
            );

            if ($empleado === null) {
                $sinResolver[] = sprintf('%d - %s (empresa %d)', $usuario->codigo_usuario, $usuario->nombre, $usuario->empresa_id);

                continue;
            }

            $centrocostoId = (int) $empleado->centrocosto_id;
            $resueltos++;
            $filas[] = [
                $usuario->empresa_id,
                $usuario->codigo_usuario,
                mb_substr((string) $usuario->nombre, 0, 30),
                $empleado->legajo,
                $empleado->empresa_id,
                $centrocostoId,
            ];

            if (! $aplicar) {
                continue;
            }

            DB::transaction(function () use ($usuario, $centrocostoId, &$consumosActualizados) {
                $usuario->centrocosto_id = $centrocostoId;
                $usuario->save();

                $consumosActualizados += ViandaConsumo::query()
                    ->where('vianda_usuario_id', (int) $usuario->id)
                    ->where(function ($q) {
                        $q->whereNull('centrocosto_id')->orWhere('centrocosto_id', 0);
                    })
                    ->update(['centrocosto_id' => $centrocostoId]);
            });
        }

        $this->table(['Emp. vianda', 'Documento', 'Nombre', 'Legajo', 'Emp. legajo', 'C. costo'], $filas);

        $this->line('Resueltos por legajo: '.$resueltos);
        $this->line('Sin resolver: '.count($sinResolver));
        if ($aplicar) {
            $this->line('Consumos ya marchados imputados: '.$consumosActualizados);
        } else {
            $this->warn('Simulación: no se grabó nada. Repetir con --aplicar para confirmar.');
        }

        foreach ($sinResolver as $detalle) {
            $this->warn('Sin legajo con centro de costo: '.$detalle);
        }

        return self::SUCCESS;
    }
}
