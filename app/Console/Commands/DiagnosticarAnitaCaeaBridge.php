<?php

namespace App\Console\Commands;

use App\ApiAnita;
use Illuminate\Console\Command;

class DiagnosticarAnitaCaeaBridge extends Command
{
    protected $signature = 'arca:diagnosticar-anita-caea';

    protected $description = 'Prueba la consulta tabla caea vía bridge HTTP Anita y muestra la respuesta cruda';

    public function handle(): int
    {
        $this->info('Config Anita ERP:');
        $this->line('  ANITA_IP: '.config('anita.ip'));
        $this->line('  ANITA_BDD_PATH: '.config('anita.bdd_path'));
        $this->line('  ANITA_BDD: '.config('anita.bdd'));
        $this->line('  IFX_SERVER: '.config('anita.ifx_server'));
        $this->line('  Bridge URL: '.ApiAnita::urlBridge());

        $api = new ApiAnita();
        $payload = [
            'acc' => 'list',
            'tabla' => 'caea',
            'campos' => 'caea_serial, caea_cuit, caea_nro_caea, caea_desde_fecha, caea_hasta_fecha, caea_fecha_tope',
            'whereArmado' => ' WHERE caea_hasta_fecha >= 20260101',
            'orderBy' => 'caea_serial',
        ];

        $this->info('Consultando caea (desde 20260101)…');
        $raw = (string) $api->apiCall($payload);

        $err = ApiAnita::extraerMensajeError($raw);
        $filas = ApiAnita::decodificarListaFilas($raw);

        $this->line('Longitud respuesta: '.strlen($raw));
        $this->line('Filas decodificadas: '.count($filas));
        if ($err !== null) {
            $this->error('Error detectado: '.$err);
        }

        $this->newLine();
        $this->line('--- Respuesta (primeros 1200 caracteres) ---');
        $this->line(substr($raw, 0, 1200));

        if ($filas !== []) {
            $this->newLine();
            $this->info('Primera fila:');
            $this->line(json_encode($filas[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        if ($filas === [] && $err === null) {
            $this->warn('Lista vacía sin error JSON. Si hay HTML Warning, copie apiERP.php corregido a /usr2/www/htdocs/ en el servidor Anita.');

            return self::FAILURE;
        }

        return ($err !== null) ? self::FAILURE : self::SUCCESS;
    }
}
