<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Models\Stock\Categoria;
use App\Models\Stock\Linea;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Mventa;
use App\Models\Stock\Subcategoria;
use App\Models\Stock\Unidadmedida;
use App\Services\Stock\DepmaeAnitaSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class SincronizarStockMaestrosDesdeAnita extends Command
{
    protected $signature = 'stock:sincronizar-maestros-anita
                            {--solo= : Ejecutar solo un maestro (unidadmedida,categoria,subcategoria,listaprecio,linea,mventa,depmae)}
                            {--usuario= : ID usuario para altas que requieren Auth (default: primer usuario)}';

    protected $description = 'Importa tablas maestras de stock desde Anita (solo altas faltantes; depmae también actualiza). Ejecutar antes de articulo:sincronizar-anita --resync.';

    /** @var array<string, array{label: string, tabla_anita: string, sync: string}> */
    private const MAESTROS = [
        'unidadmedida' => ['label' => 'Unidades de medida', 'tabla_anita' => 'stkumd', 'sync' => 'modelo'],
        'categoria' => ['label' => 'Categorías', 'tabla_anita' => 'stkagr', 'sync' => 'modelo'],
        'subcategoria' => ['label' => 'Subcategorías', 'tabla_anita' => 'subcategoria', 'sync' => 'modelo'],
        'listaprecio' => ['label' => 'Listas de precio', 'tabla_anita' => 'premae', 'sync' => 'modelo_auth'],
        'linea' => ['label' => 'Líneas', 'tabla_anita' => 'linmae', 'sync' => 'modelo'],
        'mventa' => ['label' => 'Marcas', 'tabla_anita' => 'marmae', 'sync' => 'modelo'],
        'depmae' => ['label' => 'Depósitos', 'tabla_anita' => 'depmae', 'sync' => 'depmae_service'],
    ];

    public function handle(DepmaeAnitaSyncService $depmaeSync): int
    {
        $usuarioId = (int) ($this->option('usuario') ?: (Usuario::query()->orderBy('id')->value('id') ?? 1));
        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error('Usuario inválido para sincronización.');

            return self::FAILURE;
        }

        $solo = strtolower(trim((string) ($this->option('solo') ?? '')));
        $maestros = self::MAESTROS;
        if ($solo !== '') {
            if (! isset($maestros[$solo])) {
                $this->error('Maestro desconocido. Opciones: '.implode(', ', array_keys($maestros)));

                return self::FAILURE;
            }
            $maestros = [$solo => $maestros[$solo]];
        }

        $this->info('Bridge: '.(\App\ApiAnita::urlBridge()));
        $this->info('Empresa: '.config('app.empresa'));
        $this->newLine();

        $filas = [];
        foreach ($maestros as $clave => $cfg) {
            $antes = $this->conteoErp($clave);
            $this->line("→ {$cfg['label']} (Anita: {$cfg['tabla_anita']})…");

            try {
                $detalle = $this->ejecutarSync($clave, $cfg['sync'], $depmaeSync);
            } catch (\Throwable $e) {
                $this->error("  Error: {$e->getMessage()}");
                $filas[] = [$cfg['label'], (string) $antes, '—', 'ERROR', $e->getMessage()];

                continue;
            }

            $despues = $this->conteoErp($clave);
            $filas[] = [$cfg['label'], (string) $antes, (string) $despues, 'OK', $detalle];
            $this->info("  {$detalle} (ERP: {$antes} → {$despues})");
        }

        $this->newLine();
        $this->table(['Maestro', 'ERP antes', 'ERP después', 'Estado', 'Detalle'], $filas);
        $this->line('Precios: php artisan precio:sincronizar-anita (upsert stkpre desde '.config('stock.precio_anita_sync_desde', '20250101').').');

        return self::SUCCESS;
    }

    private function conteoErp(string $clave): int
    {
        return match ($clave) {
            'unidadmedida' => (int) Unidadmedida::query()->count(),
            'categoria' => (int) Categoria::query()->count(),
            'subcategoria' => (int) Subcategoria::query()->count(),
            'listaprecio' => (int) Listaprecio::query()->count(),
            'linea' => (int) Linea::query()->count(),
            'mventa' => (int) Mventa::query()->count(),
            'depmae' => (int) \App\Models\Stock\Depmae::query()->count(),
            default => 0,
        };
    }

    private function ejecutarSync(string $clave, string $tipo, DepmaeAnitaSyncService $depmaeSync): string
    {
        if ($tipo === 'depmae_service') {
            $ret = $depmaeSync->sincronizarConAnita();

            return "Anita {$ret['en_anita']}; importados {$ret['importados']}; actualizados {$ret['actualizados']}; omitidos {$ret['omitidos']}";
        }

        $modelo = match ($clave) {
            'unidadmedida' => new Unidadmedida,
            'categoria' => new Categoria,
            'subcategoria' => new Subcategoria,
            'listaprecio' => new Listaprecio,
            'linea' => new Linea,
            'mventa' => new Mventa,
            default => throw new \InvalidArgumentException("Sin modelo para {$clave}"),
        };

        $modelo->sincronizarConAnita();

        return 'Sync insert-only ejecutado (solo altas faltantes)';
    }
}
