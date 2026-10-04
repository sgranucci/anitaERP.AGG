<?php

namespace App\Console\Commands;

use App\Services\Arca\ArcaCertificadoCsrService;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Arca\ArcaCertificadoPantallaSupport;
use App\Support\Arca\ArcaCertificadoVencimientoAvisoSupport;
use Illuminate\Console\Command;

class AvisarVencimientoCertificadoArca extends Command
{
    protected $signature = 'arca:avisar-vencimiento-certificados
                            {--simular : Lista los certificados sin encolar el aviso}';

    protected $description = 'Avisa certificados ARCA desde 30 días antes del vencimiento, día por medio, vía el módulo de avisos.';

    public function handle(ArcaCertificadoCsrService $certificados, ModuloAvisoService $avisos): int
    {
        $diasAntes = max(1, (int) config('arca.certificado_aviso.dias_antes', 30));
        $cada = max(1, (int) config('arca.certificado_aviso.cada_dias', 2));
        $simular = (bool) $this->option('simular');

        $inventario = $certificados->filtrar(
            $certificados->inventariar(),
            ArcaCertificadoPantallaSupport::serviciosVisibles(),
            null,
        );
        $filas = ArcaCertificadoVencimientoAvisoSupport::seleccionar(
            $inventario,
            $diasAntes,
            $cada,
        );

        if ($filas === []) {
            $this->info('Sin certificados para avisar hoy (ventana '.$diasAntes.' días, cada '.$cada.').');

            return self::SUCCESS;
        }

        $this->table(
            ['Empresa', 'Servicio', 'Alias', 'Vence', 'Estado'],
            array_map(static fn (array $f) => [
                $f['empresa'],
                $f['servicios'],
                $f['alias'],
                $f['vence'],
                $f['estado'],
            ], $filas)
        );

        if ($simular) {
            $this->warn('Simulación: no se encoló el aviso ('.count($filas).' certificado(s)).');

            return self::SUCCESS;
        }

        $avisos->enviar('arca', 'certificado_vencimiento', 0, [
            'certificados' => $filas,
        ]);
        $this->info('Aviso encolado para '.count($filas).' certificado(s).');

        return self::SUCCESS;
    }
}
