<?php

namespace App\Console\Commands\Compras;

use App\Models\Seguridad\Usuario;
use App\Services\Compras\ProveedorCuentacorrienteAplicacionAsientoBackfillService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Throwable;

class AplicacionCcProveedorAsientoBackfillCommand extends Command
{
    protected $signature = 'compras:aplicacion-cc-asiento-backfill
                            {--aplicacion=* : IDs de proveedor_cuentacorriente_aplicacion a procesar (default: todas las candidatas)}
                            {--desde= : Fecha desde (Y-m-d) de la aplicación}
                            {--hasta= : Fecha hasta (Y-m-d) de la aplicación}
                            {--usuario= : ID usuario para usuario_id del asiento (default: primer usuario)}
                            {--incluir-dc : También las aplicaciones que solo necesitan diferencia de cambio (histórico ya contabilizado en Anita)}
                            {--ejecutar : Graba el asiento en anitaERP y en ctamov de Anita}';

    protected $description = 'Genera el asiento faltante de aplicaciones de cuenta corriente de proveedores (reclasificación de anticipos y diferencia de cambio). Sin --ejecutar solo informa.';

    public function handle(ProveedorCuentacorrienteAplicacionAsientoBackfillService $servicio): int
    {
        $usuarioId = $this->option('usuario');
        $usuarioId = ($usuarioId !== null && $usuarioId !== '')
            ? (int) $usuarioId
            : (int) (Usuario::query()->orderBy('id')->value('id') ?? 0);

        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error("No existe usuario id {$usuarioId}.");

            return self::FAILURE;
        }

        $ejecutar = (bool) $this->option('ejecutar');
        $ids = array_values(array_filter(array_map('intval', (array) $this->option('aplicacion'))));

        try {
            $candidatos = $servicio->analizar(
                $ids,
                $this->opcionFecha('desde'),
                $this->opcionFecha('hasta'),
                (bool) $this->option('incluir-dc')
            );
        } catch (Throwable $e) {
            $this->error('No se pudo analizar: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($candidatos === []) {
            $this->info('No hay aplicaciones sin asiento que requieran uno.');

            return self::SUCCESS;
        }

        $this->line($ejecutar ? 'MODO EJECUCIÓN' : 'MODO ANÁLISIS (sin escribir)');
        $this->newLine();

        $grabables = array_values(array_filter($candidatos, fn ($c) => ($c['error'] ?? null) === null));
        $conProblema = array_values(array_filter($candidatos, fn ($c) => ($c['error'] ?? null) !== null));

        $errores = 0;
        foreach ($grabables as $candidato) {
            $this->mostrar($servicio, $candidato);

            if (! $ejecutar) {
                continue;
            }

            try {
                $asientoId = $servicio->generar($candidato);
                $this->info('  → asiento ERP id '.$asientoId.' grabado (y replicado en ctamov de Anita).');
            } catch (Throwable $e) {
                $errores++;
                $this->error('  → no se pudo grabar: '.$e->getMessage());
            }
            $this->newLine();
        }

        if ($conProblema !== []) {
            $this->warn('Aplicaciones que necesitan asiento pero no se pueden armar:');
            foreach ($conProblema as $candidato) {
                $this->line(sprintf(
                    '  aplicación %d/%d · %s · crédito cc %d %s / deuda cc %d %s',
                    $candidato['aplicacion_deuda_id'],
                    $candidato['aplicacion_credito_id'],
                    $candidato['fecha'],
                    (int) $candidato['credito']->id,
                    $servicio::etiqueta($candidato['credito'], 'credito'),
                    (int) $candidato['deuda']->id,
                    $servicio::etiqueta($candidato['deuda'], 'deuda')
                ));
                $this->line('    '.$candidato['error']);
            }
            $this->newLine();
        }

        $this->line('Asientos a grabar: '.count($grabables).' · con problema: '.count($conProblema));
        if (! $ejecutar) {
            $this->warn('Nada se escribió. Repetir con --ejecutar para grabar.');
        }

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $candidato
     */
    private function mostrar(
        ProveedorCuentacorrienteAplicacionAsientoBackfillService $servicio,
        array $candidato,
    ): void {
        $deuda = $candidato['deuda'];
        $credito = $candidato['credito'];

        $this->line(sprintf(
            'aplicación %d/%d · empresa %d · %s · %s',
            $candidato['aplicacion_deuda_id'],
            $candidato['aplicacion_credito_id'],
            (int) ($deuda->empresa_id ?: $credito->empresa_id),
            $candidato['fecha'],
            $candidato['preview']['reclasifica'] ? 'reclasificación' : 'solo diferencia de cambio'
        ));
        $this->line(sprintf(
            '  crédito cc %d %s   deuda cc %d %s',
            (int) $credito->id,
            $servicio::etiqueta($credito, 'credito'),
            (int) $deuda->id,
            $servicio::etiqueta($deuda, 'deuda')
        ));

        $filas = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        foreach ($candidato['lineas'] as $linea) {
            $totalDebe += $linea['debe'];
            $totalHaber += $linea['haber'];
            $filas[] = [
                $linea['codigo'],
                $linea['nombre'],
                $linea['debe'] > 0 ? number_format($linea['debe'], 2, ',', '.') : '',
                $linea['haber'] > 0 ? number_format($linea['haber'], 2, ',', '.') : '',
            ];
        }
        $filas[] = [
            '',
            'TOTAL',
            number_format($totalDebe, 2, ',', '.'),
            number_format($totalHaber, 2, ',', '.'),
        ];

        $this->table(['Cuenta', 'Nombre', 'Debe', 'Haber'], $filas);
    }

    private function opcionFecha(string $nombre): ?string
    {
        $valor = $this->option($nombre);
        $valor = is_string($valor) ? trim($valor) : '';

        return $valor !== '' ? substr($valor, 0, 10) : null;
    }
}
