<?php

declare(strict_types=1);

namespace App\Console\Commands\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Concepto;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Repositories\Contable\AsientoRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * One-shot: KANDIKO FNS 25967 (Carballeiro) → FIS y REBISCO FNS 2985
 * (Processing Data) → FIB. El IVA sale de 521130001 y entra en 114010002.
 */
final class ReclasificarIva002CarballeiroProcessingCommand extends Command
{
    protected $signature = 'compras:reclasificar-iva-002-carballeiro-processing
                            {--dry-run : Solo muestra el antes y el después}';

    protected $description = 'Pasa Carballeiro 25967 a FIS y Processing Data 2985 a FIB (IVA a 114010002)';

    private const TIPO_FNS = 39;

    private const CONCEPTO_421 = 26;

    private const CONCEPTO_51 = 15;

    /**
     * @var list<array<string, int|float|string|null>>
     */
    private const CASOS = [
        [
            'cp_id' => 34136,
            'asiento_id' => 170098,
            'mov_id' => 549827,
            'linea_iva_id' => 74888,
            'linea_gravado_id' => null,
            'tipo_id' => 22,
            'abrev' => 'FIS',
            'concepto_iva_id' => 37,
            'cuenta_vieja' => 1684,
            'cuenta_nueva' => 1068,
            'monto_iva' => 55440.0,
            'numero' => 25967,
            'etiqueta' => 'KANDIKO Carballeiro FNS 25967 → FIS',
        ],
        [
            'cp_id' => 34446,
            'asiento_id' => 171461,
            'mov_id' => 554396,
            'linea_iva_id' => 75686,
            'linea_gravado_id' => 75684,
            'tipo_id' => 23,
            'abrev' => 'FIB',
            'concepto_iva_id' => 40,
            'cuenta_vieja' => 2608,
            'cuenta_nueva' => 2003,
            'monto_iva' => 2565741.03,
            'numero' => 2985,
            'etiqueta' => 'REBISCO Processing Data FNS 2985 → FIB',
        ],
    ];

    public function handle(AsientoRepositoryInterface $asientos): int
    {
        $dry = (bool) $this->option('dry-run');

        try {
            foreach (self::CASOS as $caso) {
                $this->assertCaso($caso);
                $this->line($caso['etiqueta'].' — listo para aplicar.');
            }
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($dry) {
            $this->comment('No se grabó nada.');

            return self::SUCCESS;
        }

        DB::transaction(function () {
            foreach (self::CASOS as $caso) {
                $this->aplicar($caso);
            }
        });

        foreach (self::CASOS as $caso) {
            $this->sincronizarAnita($asientos, $caso);
            $this->info('Anita ctamov '.$caso['abrev'].' A 2-'.$caso['numero'].' reescrito.');
        }

        $this->info('Reclasificación aplicada.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int|float|string|null>  $caso
     */
    private function assertCaso(array $caso): void
    {
        $cp = Comprobante_Proveedor::query()->find((int) $caso['cp_id']);
        if ($cp === null || (string) $cp->estado !== 'CONTABILIZADO') {
            throw new RuntimeException('Comprobante '.$caso['cp_id'].' no está contabilizado.');
        }
        if ((int) $cp->asiento_id !== (int) $caso['asiento_id']) {
            throw new RuntimeException('El asiento de '.$caso['cp_id'].' cambió.');
        }
        if ((int) $cp->tipotransaccion_compra_id === (int) $caso['tipo_id']) {
            throw new RuntimeException($caso['etiqueta'].' ya está reclasificado.');
        }
        if ((int) $cp->tipotransaccion_compra_id !== self::TIPO_FNS) {
            throw new RuntimeException('Comprobante '.$caso['cp_id'].' ya no es FNS.');
        }
        if ((int) $cp->sucursal !== 2 || (int) $cp->numerocomprobante !== (int) $caso['numero']) {
            throw new RuntimeException('Número de comprobante inesperado en '.$caso['cp_id'].'.');
        }

        $iva = Comprobante_Proveedor_Concepto::query()->find((int) $caso['linea_iva_id']);
        if ($iva === null
            || (int) $iva->comprobante_proveedor_id !== (int) $caso['cp_id']
            || (int) $iva->concepto_ivacompra_id !== self::CONCEPTO_421
            || abs((float) $iva->monto - (float) $caso['monto_iva']) > 0.02
            || (int) $iva->cuentacontabledebe_id !== (int) $caso['cuenta_vieja']
        ) {
            throw new RuntimeException('La línea de IVA de '.$caso['cp_id'].' no es el 421 esperado.');
        }

        if ($caso['linea_gravado_id'] !== null) {
            $gravado = Comprobante_Proveedor_Concepto::query()->find((int) $caso['linea_gravado_id']);
            if ($gravado === null
                || (int) $gravado->comprobante_proveedor_id !== (int) $caso['cp_id']
                || (int) $gravado->concepto_ivacompra_id !== self::CONCEPTO_51
            ) {
                throw new RuntimeException('El gravado de '.$caso['cp_id'].' no es el concepto 51.');
            }
        }

        $mov = Asiento_Movimiento::query()->find((int) $caso['mov_id']);
        if ($mov === null
            || (int) $mov->asiento_id !== (int) $caso['asiento_id']
            || (int) $mov->cuentacontable_id !== (int) $caso['cuenta_vieja']
            || abs((float) $mov->monto - (float) $caso['monto_iva']) > 0.02
        ) {
            throw new RuntimeException('El movimiento de IVA del asiento '.$caso['asiento_id'].' no coincide.');
        }

        $suma = round((float) Asiento_Movimiento::query()->where('asiento_id', (int) $caso['asiento_id'])->sum('monto'), 2);
        if (abs($suma) > 0.02) {
            throw new RuntimeException('El asiento '.$caso['asiento_id'].' no balancea ('.$suma.').');
        }

        $asiento = Asiento::query()->find((int) $caso['asiento_id']);
        if ($asiento === null || (string) $asiento->anita_tipo !== 'FNS') {
            throw new RuntimeException('El asiento '.$caso['asiento_id'].' no está marcado FNS en Anita.');
        }
    }

    /**
     * @param  array<string, int|float|string|null>  $caso
     */
    private function aplicar(array $caso): void
    {
        $this->assertCaso($caso);

        $ahora = now();

        Comprobante_Proveedor::query()
            ->whereKey((int) $caso['cp_id'])
            ->update([
                'tipotransaccion_compra_id' => (int) $caso['tipo_id'],
                'updated_at' => $ahora,
            ]);

        Comprobante_Proveedor_Concepto::query()
            ->whereKey((int) $caso['linea_iva_id'])
            ->update([
                'concepto_ivacompra_id' => (int) $caso['concepto_iva_id'],
                'cuentacontabledebe_id' => (int) $caso['cuenta_nueva'],
                'updated_at' => $ahora,
            ]);

        if ($caso['linea_gravado_id'] !== null) {
            Comprobante_Proveedor_Concepto::query()
                ->whereKey((int) $caso['linea_gravado_id'])
                ->update([
                    'concepto_ivacompra_id' => 14,
                    'updated_at' => $ahora,
                ]);
        }

        Asiento_Movimiento::query()
            ->whereKey((int) $caso['mov_id'])
            ->update([
                'cuentacontable_id' => (int) $caso['cuenta_nueva'],
                'updated_at' => $ahora,
            ]);

        Asiento::query()
            ->whereKey((int) $caso['asiento_id'])
            ->update([
                'anita_tipo' => (string) $caso['abrev'],
                'updated_at' => $ahora,
            ]);

        $suma = round((float) Asiento_Movimiento::query()->where('asiento_id', (int) $caso['asiento_id'])->sum('monto'), 2);
        if (abs($suma) > 0.02) {
            throw new RuntimeException('El asiento '.$caso['asiento_id'].' quedó desbalanceado ('.$suma.').');
        }
    }

    /**
     * @param  array<string, int|float|string|null>  $caso
     */
    private function sincronizarAnita(AsientoRepositoryInterface $asientos, array $caso): void
    {
        $asiento = Asiento::query()->with('asiento_movimientos')->find((int) $caso['asiento_id']);
        if ($asiento === null) {
            throw new RuntimeException('Asiento '.$caso['asiento_id'].' no encontrado para Anita.');
        }

        $payload = $asientos->armarPayloadAnitaDesdeModelo($asiento);
        $payload['tipo'] = (string) $caso['abrev'];
        $payload['letra'] = 'A';
        $payload['sucursal'] = 2;
        $payload['nro'] = (int) $caso['numero'];
        $payload['sistema_ctav'] = (string) ($asiento->anita_sistema ?: 'C');
        $fecha = $asiento->fecha instanceof \DateTimeInterface
            ? $asiento->fecha->format('Y-m-d')
            : substr((string) $asiento->fecha, 0, 10);
        $payload['fecha'] = $fecha;

        $asientos->sincronizarCtamovAnita($payload);
    }
}
