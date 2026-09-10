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
                            {--solo-nuevos : No pisa cabeceras existentes; crea faltantes y trae pesada de Anita solo si el ERP aún no la tiene}
                            {--dry-run : Solo lista qué se importaría, sin grabar}
                            {--ejecutar : Persiste altas y actualizaciones}';

    protected $description = 'Importa pedidos Anita (pendmae/pendmov) al ERP por fecha de entrega y reparto. El cron de las 01:00 crea y puede actualizar; el refresco diurno (--solo-nuevos) solo da de alta pedidos nuevos y completa pesada si el ERP todavía no la tiene, sin tocar cabecera.';

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
        $soloNuevos = (bool) $this->option('solo-nuevos');
        $filtros = [
            'filtro_reparto' => $reparto,
            'fecha_entrega_desde' => $fecha,
            'fecha_entrega_hasta' => $fecha,
        ];

        $this->info(sprintf(
            'Importar pedidos Anita · entrega %s · repartos %s%s',
            $fecha,
            $reparto !== '' ? $reparto : 'todos',
            $soloNuevos ? ' · solo nuevos' : ''
        ));

        try {
            if ($dryRun) {
                return $this->mostrarPreview($service, $filtros, $fecha, $reparto, $soloNuevos);
            }

            return $this->ejecutarImportacion($service, $filtros, $fecha, $reparto, $soloNuevos);
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
        string $reparto,
        bool $soloNuevos = false
    ): int {
        $filas = $service->listarPreview($filtros, $soloNuevos);
        $conteos = [
            'nuevo' => 0,
            'existe' => 0,
            'pesada' => 0,
            'omitido_facturado' => 0,
            'omitido_existente' => 0,
            'omitido_despacho' => 0,
        ];
        foreach ($filas as $fila) {
            $estado = (string) ($fila['estado_erp'] ?? '');
            if (isset($conteos[$estado])) {
                $conteos[$estado]++;
            }
        }

        $this->table(
            ['En Anita', 'Nuevos', 'A actualizar', 'A pesar', 'Omitidos (ya en ERP)', 'Omitidos (facturados)', 'DESPACHO'],
            [[
                count($filas),
                $conteos['nuevo'],
                $conteos['existe'],
                $conteos['pesada'],
                $conteos['omitido_existente'],
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
            'solo_nuevos' => $soloNuevos,
            'total' => count($filas),
            'nuevos' => $conteos['nuevo'],
            'actualizar' => $conteos['existe'],
            'pesadas' => $conteos['pesada'],
            'omitidos_existentes' => $conteos['omitido_existente'],
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
        string $reparto,
        bool $soloNuevos = false
    ): int {
        $resumen = $service->importar($filtros, null, $soloNuevos);

        $this->table(
            ['En Anita', 'Creados', 'Actualizados', 'Pesadas', 'Omitidos', 'DESPACHO cerrados', 'Errores'],
            [[
                $resumen['total'],
                $resumen['creados'],
                $resumen['actualizados'],
                $resumen['pesadas'] ?? 0,
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
            'solo_nuevos' => $soloNuevos,
            'total' => $resumen['total'],
            'creados' => $resumen['creados'],
            'actualizados' => $resumen['actualizados'],
            'pesadas' => $resumen['pesadas'] ?? 0,
            'omitidos' => $resumen['omitidos'],
            'cerrados' => $resumen['cerrados'],
            'errores' => $resumen['errores'],
        ]);

        $this->info(sprintf(
            'Importación finalizada: %d creados, %d actualizados, %d pesadas, %d omitidos, %d DESPACHO, %d con error (total %d).',
            $resumen['creados'],
            $resumen['actualizados'],
            $resumen['pesadas'] ?? 0,
            $resumen['omitidos'],
            $resumen['cerrados'],
            $resumen['errores'],
            $resumen['total']
        ));

        return $resumen['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
