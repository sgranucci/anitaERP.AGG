<?php

namespace App\Console\Commands\Stock;

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Stock\ControlFerliOtStockSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ControlFerliOtStockCommand extends Command
{
    protected $signature = 'stock:control-ferli-ot
        {--articulo= : ID de artículo}
        {--combinacion= : ID de combinación}
        {--deposito= : ID de depósito}
        {--desde= : Fecha Y-m-d desde la que se controlan los talles de los movimientos}
        {--ejemplos=20 : Ejemplos por control}
        {--sin-validadores : No compara picking, egresos, consumo de OT ni F1 (más rápido)}
        {--formato=tabla : tabla o json}';

    protected $description = 'Ferli: control de solo lectura del saldo por lote/OT y depósito (Stock por OT, picking, egresos, consumo OT, F1 y datos). Sale con código 1 si hay divergencias.';

    public function handle(ControlFerliOtStockSupport $control): int
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            $this->warn('Solo aplica a Calzados Ferli.');

            return self::SUCCESS;
        }

        $formato = strtolower((string) $this->option('formato'));
        if (! in_array($formato, ['tabla', 'json'], true)) {
            $this->error('Formato inválido: tabla o json.');

            return self::INVALID;
        }
        $desde = trim((string) ($this->option('desde') ?? ''));
        if ($desde !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $this->error('--desde tiene que ser Y-m-d.');

            return self::INVALID;
        }

        ini_set('memory_limit', '2G');
        set_time_limit(0);
        $inicio = microtime(true);

        DB::statement('SET SESSION TRANSACTION READ ONLY');
        DB::beginTransaction();
        try {
            $resultado = $control->ejecutar([
                'articulo_id' => (int) $this->option('articulo'),
                'combinacion_id' => (int) $this->option('combinacion'),
                'deposito_id' => (int) $this->option('deposito'),
                'desde' => $desde !== '' ? $desde : null,
                'ejemplos' => (int) $this->option('ejemplos'),
                'validadores' => ! $this->option('sin-validadores'),
            ]);
        } finally {
            DB::rollBack();
            DB::statement('SET SESSION TRANSACTION READ WRITE');
        }
        $resultado['segundos'] = round(microtime(true) - $inicio, 1);

        if ($formato === 'json') {
            $this->line(json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->imprimirTabla($resultado);
        }

        return $resultado['divergencias'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array{controles: array<string, array{severidad: string, descripcion: string, cantidad: int, pares: float, ejemplos: list<array<string, mixed>>}>, divergencias: int, buckets: int, segundos: float}  $resultado
     */
    private function imprimirTabla(array $resultado): void
    {
        $filas = [];
        foreach ($resultado['controles'] as $clave => $control) {
            $filas[] = [
                $clave,
                $control['severidad'],
                $control['cantidad'],
                number_format($control['pares'], 0, ',', '.'),
                $control['descripcion'],
            ];
        }
        $this->table(['Control', 'Tipo', 'Casos', 'Pares', 'Descripción'], $filas);

        foreach ($resultado['controles'] as $clave => $control) {
            if ($control['cantidad'] === 0 || $control['ejemplos'] === []) {
                continue;
            }
            $this->newLine();
            $this->line('<comment>'.$clave.'</comment> ('.$control['cantidad'].')');
            foreach ($control['ejemplos'] as $ejemplo) {
                $this->line('  '.json_encode($ejemplo, JSON_UNESCAPED_UNICODE));
            }
        }

        $this->newLine();
        $this->info('Buckets: '.$resultado['buckets'].' — divergencias: '.$resultado['divergencias'].' — '.$resultado['segundos'].' s');
    }
}
