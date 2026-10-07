<?php

declare(strict_types=1);

namespace App\Console\Commands\Ventas;

use App\Services\Ventas\FacturacionHuecoArcaCargaService;
use Illuminate\Console\Command;

class FacturacionCargarHuecosArca extends Command
{
    protected $signature = 'facturacion:cargar-huecos-arca
                            {--dry-run : Consulta ARCA y muestra qué cargaría, sin grabar ni avisar}
                            {--dias= : Días hacia atrás para buscar huecos}
                            {--sin-mail : Graba pero no envía el aviso}
                            {--puntoventa= : Código de punto de venta, por ejemplo 12}
                            {--tipo= : Código AFIP, por ejemplo 3 para nota de crédito A}
                            {--numero= : Un solo número. Exige --puntoventa y --tipo}
                            {--nombre= : Nombre del receptor si el documento de ARCA no está en clientes}';

    protected $description = 'Carga comprobantes autorizados en ARCA que el ERP no tiene y avisa por mail.';

    public function handle(FacturacionHuecoArcaCargaService $service): int
    {
        $numero = (int) $this->option('numero');
        $tipo = (int) $this->option('tipo');
        $pv = trim((string) $this->option('puntoventa'));
        if ($numero > 0 && ($tipo <= 0 || $pv === '')) {
            $this->error('Para un número hay que indicar --puntoventa y --tipo.');

            return self::FAILURE;
        }

        $diasOpcion = $this->option('dias');
        $resultado = $service->ejecutar([
            'dry_run' => (bool) $this->option('dry-run'),
            'sin_mail' => (bool) $this->option('sin-mail'),
            'dias' => $diasOpcion !== null && $diasOpcion !== '' ? (int) $diasOpcion : null,
            'puntoventa' => $pv !== '' ? $pv : null,
            'tipo' => $tipo > 0 ? $tipo : null,
            'numero' => $numero > 0 ? $numero : null,
            'nombre' => trim((string) $this->option('nombre')),
        ]);

        $filas = [];
        foreach ($resultado['resultados'] as $fila) {
            $filas[] = [
                $fila['puntoventa'] ?? '',
                $fila['tipo'] ?? '',
                $fila['numero'] ?? '',
                $fila['estado'] ?? '',
                $fila['detalle'] ?? '',
            ];
        }

        if ($filas === []) {
            $this->info('No hay huecos para revisar.');
        } else {
            $this->table(['PV', 'Tipo', 'Número', 'Estado', 'Detalle'], $filas);
        }

        if ((int) $resultado['pendientes'] > 0) {
            $this->warn('Quedaron '.$resultado['pendientes'].' huecos para la próxima corrida.');
        }
        $this->line('Mail: '.$resultado['mail']);

        return self::SUCCESS;
    }
}
