<?php

namespace App\Console\Commands;

use App\Models\Caja\AperturaGasto;
use App\Models\Caja\AperturaGastoEmpresa;
use App\Models\Contable\Cuentacontable;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Copia cuentas de apertura de gasto desde empresa origen a destinos,
 * resolviendo cuentacontable / contrapartida por código en cada empresa.
 * Omite filas que ya existen (ej. las cargadas a mano).
 */
class ReplicarAperturaGastoCuentasEmpresas extends Command
{
    protected $signature = 'apertura-gasto:replicar-cuentas-empresas
        {--origen=1 : empresa_id de origen}
        {--destinos=2,3 : empresa_id destino (coma)}
        {--aplicar : Inserta filas (sin esto solo informa)}';

    protected $description = 'Copia cuentas de apertura_gasto (empresa origen) a otras empresas por código de cuenta';

    public function __construct(
        private readonly CuentacontableRepositoryInterface $cuentacontableRepository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $origenId = (int) $this->option('origen');
        $destinos = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $this->option('destinos'))
        ), fn (int $id) => $id > 0 && $id !== $origenId));

        $aplicar = (bool) $this->option('aplicar');

        if ($origenId <= 0 || $destinos === []) {
            $this->error('Origen/destinos inválidos.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Origen empresa %d → destinos [%s]%s',
            $origenId,
            implode(', ', $destinos),
            $aplicar ? ' (APLICAR)' : ' (solo informe; usar --aplicar)'
        ));

        $origenes = AperturaGastoEmpresa::query()
            ->with([
                'aperturaGasto:id,codigo,nombre',
                'cuentacontable:id,codigo,nombre',
                'cuentacontableContrapartida:id,codigo,nombre',
                'centrocosto:id,codigo,nombre',
            ])
            ->where('empresa_id', $origenId)
            ->orderBy('apertura_gasto_id')
            ->get();

        if ($origenes->isEmpty()) {
            $this->warn('No hay filas de apertura_gasto_empresa para la empresa origen.');

            return self::FAILURE;
        }

        $creadas = 0;
        $omitidas = 0;
        $errores = 0;
        $filasTabla = [];

        foreach ($origenes as $origen) {
            $gasto = $origen->aperturaGasto;
            $codigoGasto = (int) ($gasto->codigo ?? 0);
            $nombreGasto = (string) ($gasto->nombre ?? '');
            $codigoCuenta = (string) ($origen->cuentacontable->codigo ?? '');
            $codigoContrap = (string) ($origen->cuentacontableContrapartida->codigo ?? '');

            foreach ($destinos as $destinoId) {
                $yaExiste = AperturaGastoEmpresa::query()
                    ->where('apertura_gasto_id', $origen->apertura_gasto_id)
                    ->where('empresa_id', $destinoId)
                    ->exists();

                if ($yaExiste) {
                    $omitidas++;
                    $filasTabla[] = [
                        $codigoGasto,
                        $nombreGasto,
                        $destinoId,
                        'omitido',
                        'ya existe (manual u anterior)',
                    ];
                    continue;
                }

                if ($codigoCuenta === '') {
                    $errores++;
                    $filasTabla[] = [
                        $codigoGasto,
                        $nombreGasto,
                        $destinoId,
                        'error',
                        'origen sin código de cuenta',
                    ];
                    continue;
                }

                $cuentaDestino = $this->resolverCuenta($destinoId, $codigoCuenta);
                if ($cuentaDestino === null) {
                    $errores++;
                    $filasTabla[] = [
                        $codigoGasto,
                        $nombreGasto,
                        $destinoId,
                        'error',
                        "sin cuenta {$codigoCuenta}",
                    ];
                    continue;
                }

                $contrapId = null;
                if ($codigoContrap !== '') {
                    $contrapDestino = $this->resolverCuenta($destinoId, $codigoContrap);
                    if ($contrapDestino === null) {
                        $errores++;
                        $filasTabla[] = [
                            $codigoGasto,
                            $nombreGasto,
                            $destinoId,
                            'error',
                            "sin contrapartida {$codigoContrap}",
                        ];
                        continue;
                    }
                    $contrapId = (int) $contrapDestino->id;
                }

                $payload = [
                    'apertura_gasto_id' => (int) $origen->apertura_gasto_id,
                    'empresa_id' => $destinoId,
                    'cuentacontable_id' => (int) $cuentaDestino->id,
                    'cuentacontable_contrapartida_id' => $contrapId,
                    'centrocosto_id' => $origen->centrocosto_id
                        ? (int) $origen->centrocosto_id
                        : null,
                ];

                if ($aplicar) {
                    DB::transaction(function () use ($payload) {
                        AperturaGastoEmpresa::query()->create($payload);
                    });
                }

                $creadas++;
                $filasTabla[] = [
                    $codigoGasto,
                    $nombreGasto,
                    $destinoId,
                    $aplicar ? 'creado' : 'pendiente',
                    sprintf(
                        'cta %s → id %d%s',
                        $codigoCuenta,
                        (int) $cuentaDestino->id,
                        $contrapId ? (", contrap id {$contrapId}") : ''
                    ),
                ];
            }
        }

        $this->table(
            ['Código', 'Nombre', 'Emp dest', 'Estado', 'Detalle'],
            $filasTabla
        );

        $this->info(sprintf(
            'Resumen: %d a crear/creadas, %d omitidas (ya existían), %d errores.',
            $creadas,
            $omitidas,
            $errores
        ));

        if (! $aplicar && $creadas > 0) {
            $this->comment('Para grabar: php artisan apertura-gasto:replicar-cuentas-empresas --aplicar');
        }

        return $errores > 0 && $creadas === 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolverCuenta(int $empresaId, string $codigo): ?Cuentacontable
    {
        $cuenta = $this->cuentacontableRepository->findPorCodigo($empresaId, $codigo);
        if ($cuenta !== null) {
            return $cuenta;
        }

        // Fallback por si el código está guardado numérico vs string.
        return Cuentacontable::query()
            ->where('empresa_id', $empresaId)
            ->where(function ($q) use ($codigo) {
                $q->where('codigo', $codigo)
                    ->orWhere('codigo', (string) ((int) $codigo));
            })
            ->orderBy('id')
            ->first();
    }
}
