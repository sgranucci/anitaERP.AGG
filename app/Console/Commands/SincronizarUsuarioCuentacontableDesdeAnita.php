<?php

namespace App\Console\Commands;

use App\Services\Contable\UsuarioCuentacontableAnitaSyncService;
use Illuminate\Console\Command;

class SincronizarUsuarioCuentacontableDesdeAnita extends Command
{
    protected $signature = 'contable:sincronizar-cuentas-usuario-anita
                            {--aplicar : Persiste el reemplazo; sin esta opción solo muestra la vista previa}';

    protected $description = 'Sincroniza usuario_cuentacontable desde Anita ctamusu, vinculando usuarios por logname.';

    public function handle(UsuarioCuentacontableAnitaSyncService $sync): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $this->info($aplicar
            ? 'Sincronizando cuentas por usuario desde Anita…'
            : 'Calculando vista previa (sin escribir)…');

        try {
            $resultado = $sync->sincronizar($aplicar);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Métrica', 'Cantidad'], [
            ['Filas ctamusu', $resultado['filas_ctamusu']],
            ['Usuarios Anita', $resultado['usuarios_anita']],
            ['Usuarios con match', $resultado['usuarios_con_match']],
            ['Usuarios sin match', $resultado['usuarios_sin_match']],
            ['Filas omitidas (cuenta/empresa sin match)', $resultado['filas_omitidas']],
            ['Usuarios con cambios', $resultado['usuarios_con_cambios']],
            ['Usuarios sin cambios', $resultado['usuarios_iguales']],
            ['Relaciones a agregar', $resultado['relaciones_agregadas']],
            ['Relaciones a quitar', $resultado['relaciones_quitadas']],
        ]);

        foreach ($resultado['advertencias'] as $advertencia) {
            $this->warn($advertencia);
        }

        $this->info('Resultado: '.$resultado['modo']);

        return self::SUCCESS;
    }
}
