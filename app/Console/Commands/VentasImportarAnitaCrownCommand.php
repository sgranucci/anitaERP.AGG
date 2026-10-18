<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Seguridad\Usuario;
use App\Services\Ventas\Crown\VentaAnitaCrownImportService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Crown: importa ventas Anita  (venta + detalle opcional) para reimpresión en el ERP.
 */
class VentasImportarAnitaCrownCommand extends Command
{
    protected $signature = 'ventas:importar-anita-crown
                            {--desde=2026-01-01 : Fecha inicial Y-m-d}
                            {--hasta= : Fecha final Y-m-d (default: hoy)}
                            {--usuario= : usuario_id de auditoría}
                            {--tipos= : Abreviaturas separadas por coma (default: set Crown)}
                            {--dry-run : Solo analiza (default si no hay --ejecutar)}
                            {--ejecutar : Persiste en ERP (no escribe Anita)}';

    protected $description = 'CROWN: importa ventas Anita (venta/vengrav/venibr/vencae/comprob/compaux) para reimprimir. Dry-run por defecto.';

    public function handle(VentaAnitaCrownImportService $service): int
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            $this->error('Este comando solo aplica a EMPRESA=CROWN.');

            return self::FAILURE;
        }
        if ((bool) $this->option('ejecutar') && (bool) $this->option('dry-run')) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }

        $ejecutar = (bool) $this->option('ejecutar');
        $usuarioId = (int) ($this->option('usuario') ?: Usuario::query()->orderBy('id')->value('id') ?? 1);
        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error('Usuario inválido.');

            return self::FAILURE;
        }

        $desde = trim((string) $this->option('desde'));
        $hasta = trim((string) ($this->option('hasta') ?: date('Y-m-d')));
        $tiposRaw = trim((string) $this->option('tipos'));
        $tipos = $tiposRaw === ''
            ? null
            : array_values(array_filter(array_map('trim', explode(',', $tiposRaw))));

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Ventas Crown %s → %s | %s | usuario_id=%d',
            $desde,
            $hasta,
            $ejecutar ? 'EJECUTAR' : 'DRY-RUN',
            $usuarioId
        ));

        try {
            $stats = $service->importar($desde, $hasta, ! $ejecutar, $usuarioId, $tipos);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $cab = $stats['cabecera'] ?? [];
        $det = $stats['detalle_anita'] ?? [];
        $emi = $stats['emisiones'] ?? [];
        $imp = $stats['impuestos_vengrav'] ?? [];

        $this->newLine();
        $this->info('Cabeceras (venta + vencae)');
        $this->table(['Concepto', 'Valor'], [
            ['Anita en rango', (string) ($cab['anita_rango'] ?? 0)],
            ['Anita tipos OK', (string) ($cab['anita_venta'] ?? 0)],
            ['Omitidas por tipo', (string) ($cab['omitidas_tipo'] ?? 0)],
            ['Ya en ERP', (string) ($cab['ya_en_erp'] ?? 0)],
            ['A crear', (string) ($cab['a_crear'] ?? 0)],
            ['Creadas', (string) ($cab['creadas'] ?? 0)],
            ['Sin cliente', (string) ($cab['sin_cliente'] ?? 0)],
            ['Sin tipo', (string) ($cab['sin_tipo'] ?? 0)],
            ['Sin PV', (string) ($cab['sin_puntoventa'] ?? 0)],
        ]);

        $this->info('Detalle Anita (si existe)');
        $this->table(['Tabla', 'Filas', 'Docs'], [
            ['vencae', (string) ($det['vencae'] ?? 0), (string) ($det['docs_vencae'] ?? 0)],
            ['vengrav', (string) ($det['vengrav'] ?? 0), (string) ($det['docs_vengrav'] ?? 0)],
            ['venibr', (string) ($det['venibr'] ?? 0), (string) ($det['docs_venibr'] ?? 0)],
            ['comprob', (string) ($det['comprob'] ?? 0), (string) ($det['docs_comprob'] ?? 0)],
            ['compaux', (string) ($det['compaux'] ?? 0), (string) ($det['docs_compaux'] ?? 0)],
        ]);

        if (! empty($det['errores_tablas'])) {
            foreach ($det['errores_tablas'] as $err) {
                $this->warn('  '.$err);
            }
        }

        $this->info('Emisiones (renglones para PDF)');
        $this->table(['Concepto', 'Valor'], [
            ['Desde compaux', (string) ($emi['a_crear_desde_compaux'] ?? $emi['creadas_compaux'] ?? 0)],
            ['Sintéticas (solo cabecera)', (string) ($emi['a_crear_sinteticas'] ?? $emi['creadas_sinteticas'] ?? 0)],
            ['Creadas compaux', (string) ($emi['creadas_compaux'] ?? 0)],
            ['Creadas sintéticas', (string) ($emi['creadas_sinteticas'] ?? 0)],
            ['Ya tenían líneas', (string) ($emi['ya_tenian'] ?? 0)],
            ['Vengrav enriquecidas', (string) ($imp['enriquecidas'] ?? 0)],
        ]);

        $muestra = $cab['muestra'] ?? [];
        if (is_array($muestra) && $muestra !== []) {
            $this->newLine();
            $this->comment('Muestra (hasta 25):');
            foreach ($muestra as $fila) {
                $this->line(sprintf(
                    '  %s | %s | $%s | CAE %s',
                    $fila['etiqueta'] ?? '',
                    $fila['fecha'] ?? '',
                    $fila['total'] ?? '',
                    $fila['cae'] ?? '—'
                ));
            }
        }

        foreach (array_merge($cab['errores'] ?? [], $emi['errores'] ?? [], $imp['errores'] ?? []) as $error) {
            $this->error('  '.$error);
        }

        if (! $ejecutar) {
            $this->newLine();
            $this->comment('Dry-run: no se persistió nada.');
            $this->comment('Para grabar: php artisan ventas:importar-anita-crown --desde='.$desde.' --hasta='.$hasta.' --ejecutar');
        }

        $hayErrores = ($cab['errores'] ?? []) !== []
            || ($emi['errores'] ?? []) !== []
            || ($imp['errores'] ?? []) !== [];

        return $hayErrores ? self::FAILURE : self::SUCCESS;
    }
}
