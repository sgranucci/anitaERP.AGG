<?php

namespace App\Console\Commands;

use App\Services\Ventas\PedidoImportarDesdeAnitaService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\ListadoRepartoFechaEntregaSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportarPedidoAnitaCommand extends Command
{
    protected $signature = 'ventas:importar-pedido-anita
                            {--fecha= : Fecha de entrega Y-m-d (default: hoy)}
                            {--reparto= : Repartos (lista 101,95 o rango 10/20; vacío = todos)}
                            {--dry-run : Solo lista qué se importaría, sin grabar}
                            {--ejecutar : Persiste altas y actualizaciones}';

    protected $description = 'Importa pedidos Anita (pendmae/pendmov) al ERP por fecha de entrega y reparto, incluida la pesada (penv_kilos_reales). El cron diario usa hoy y todos los repartos; el refresco diurno actualiza pesadas.';

    public function handle(PedidoImportarDesdeAnitaService $service): int
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            $this->warn('La importación de pedidos Anita solo aplica a EL BIERZO.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $ejecutar = (bool) $this->option('ejecutar');

        if ($dryRun && $ejecutar) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }

        if (! $dryRun && ! $ejecutar) {
            $this->warn('Sin --dry-run ni --ejecutar no se escribe. Use --dry-run para ver el impacto.');
            $dryRun = true;
        }

        $fecha = trim((string) $this->option('fecha'));
        if ($fecha === '') {
            $fecha = ListadoRepartoFechaEntregaSupport::fechaHoy();
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $this->error('Fecha inválida. Use Y-m-d (ej. 2026-08-29).');

            return self::FAILURE;
        }

        $reparto = trim((string) $this->option('reparto'));
        $filtros = [
            'filtro_reparto' => $reparto,
            'fecha_entrega_desde' => $fecha,
            'fecha_entrega_hasta' => $fecha,
        ];

        $this->info(sprintf(
            'Importar pedidos Anita · entrega %s · repartos %s',
            $fecha,
            $reparto !== '' ? $reparto : 'todos'
        ));

        try {
            if ($dryRun) {
                return $this->mostrarPreview($service, $filtros, $fecha, $reparto);
            }

            return $this->ejecutarImportacion($service, $filtros, $fecha, $reparto);
        } catch (\Throwable $e) {
            Log::error('pedido.importar_anita.fallo', [
                'fecha' => $fecha,
                'reparto' => $reparto,
                'mensaje' => $e->getMessage(),
            ]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  array{filtro_reparto: string, fecha_entrega_desde: string, fecha_entrega_hasta: string}  $filtros
     */
    private function mostrarPreview(
        PedidoImportarDesdeAnitaService $service,
        array $filtros,
        string $fecha,
        string $reparto
    ): int {
        $filas = $service->listarPreview($filtros);
        $conteos = [
            'nuevo' => 0,
            'existe' => 0,
            'omitido_facturado' => 0,
            'omitido_despacho' => 0,
        ];
        foreach ($filas as $fila) {
            $estado = (string) ($fila['estado_erp'] ?? '');
            if (isset($conteos[$estado])) {
                $conteos[$estado]++;
            }
        }

        $this->table(
            ['En Anita', 'Nuevos', 'A actualizar', 'Omitidos (facturados)', 'DESPACHO'],
            [[
                count($filas),
                $conteos['nuevo'],
                $conteos['existe'],
                $conteos['omitido_facturado'],
                $conteos['omitido_despacho'],
            ]]
        );

        if ($filas !== []) {
            $this->table(
                ['Código', 'Cliente', 'Entrega', 'Reparto', 'Estado ERP'],
                array_map(static function (array $fila): array {
                    $cliente = trim((string) ($fila['codigo_cliente'] ?? ''));
                    $nombre = trim((string) ($fila['nombre_cliente'] ?? ''));

                    return [
                        $fila['codigo'] ?? '',
                        trim($cliente.($nombre !== '' ? ' '.$nombre : '')),
                        $fila['fecha_entrega'] ?? '',
                        $fila['reparto'] ?? '',
                        $fila['estado_erp'] ?? '',
                    ];
                }, $filas)
            );
        }

        Log::info('pedido.importar_anita.dry_run', [
            'fecha' => $fecha,
            'reparto' => $reparto !== '' ? $reparto : 'todos',
            'total' => count($filas),
            'nuevos' => $conteos['nuevo'],
            'actualizar' => $conteos['existe'],
            'omitidos_facturados' => $conteos['omitido_facturado'],
            'despacho' => $conteos['omitido_despacho'],
        ]);

        $this->comment('Dry-run: no se persistió nada. Para grabar: php artisan ventas:importar-pedido-anita --ejecutar');

        return self::SUCCESS;
    }

    /**
     * @param  array{filtro_reparto: string, fecha_entrega_desde: string, fecha_entrega_hasta: string}  $filtros
     */
    private function ejecutarImportacion(
        PedidoImportarDesdeAnitaService $service,
        array $filtros,
        string $fecha,
        string $reparto
    ): int {
        $resumen = $service->importar($filtros);

        $this->table(
            ['En Anita', 'Creados', 'Actualizados', 'Omitidos', 'DESPACHO cerrados', 'Errores'],
            [[
                $resumen['total'],
                $resumen['creados'],
                $resumen['actualizados'],
                $resumen['omitidos'],
                $resumen['cerrados'],
                $resumen['errores'],
            ]]
        );

        $errores = array_values(array_filter(
            $resumen['detalle'],
            static fn (array $d): bool => ($d['estado'] ?? '') === 'error'
        ));
        if ($errores !== []) {
            $this->warn('Errores:');
            foreach (array_slice($errores, 0, 20) as $error) {
                $this->line('  '.($error['codigo'] ?? '').': '.($error['mensaje'] ?? 'error'));
            }
            if (count($errores) > 20) {
                $this->line('  … y '.(count($errores) - 20).' más.');
            }
        }

        Log::info('pedido.importar_anita.ejecutado', [
            'fecha' => $fecha,
            'reparto' => $reparto !== '' ? $reparto : 'todos',
            'total' => $resumen['total'],
            'creados' => $resumen['creados'],
            'actualizados' => $resumen['actualizados'],
            'omitidos' => $resumen['omitidos'],
            'cerrados' => $resumen['cerrados'],
            'errores' => $resumen['errores'],
        ]);

        $this->info(sprintf(
            'Importación finalizada: %d creados, %d actualizados, %d omitidos, %d DESPACHO, %d con error (total %d).',
            $resumen['creados'],
            $resumen['actualizados'],
            $resumen['omitidos'],
            $resumen['cerrados'],
            $resumen['errores'],
            $resumen['total']
        ));

        return $resumen['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
