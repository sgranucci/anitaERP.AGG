<?php

declare(strict_types=1);

namespace App\Support\Ventas\TransferenciaCrown;

use App\Models\Contable\Cuentacontable;
use RuntimeException;

/**
 * Cuentas de p-tr-ven / p-tr-pxsol: la clave vive en shared.impcont y el
 * número se resuelve contra el plan de cuentas de la empresa.
 */
final class TransferenciaCrownCuentas
{
    /** @var array<int, int> clave impcont => número de cuenta Anita */
    private array $impcont = [];

    /** @var array<string, int> empresa|codigo => cuentacontable_id */
    private array $erp = [];

    public function __construct(
        private readonly TransferenciaCrownAnitaConsulta $anita,
    ) {
    }

    /**
     * @param  list<int>  $claves
     */
    public function precargar(array $claves): void
    {
        $faltan = [];
        foreach ($claves as $clave) {
            $clave = (int) $clave;
            if ($clave > 0 && ! isset($this->impcont[$clave])) {
                $faltan[$clave] = $clave;
            }
        }
        if ($faltan === []) {
            return;
        }

        $lista = implode(',', $faltan);
        $filas = $this->anita->listar(
            'shared',
            'impcont',
            'impc_clave, impc_cuenta, impc_empresa',
            ' WHERE impc_clave IN ('.$lista.')',
        );

        $porClave = [];
        foreach ($filas as $fila) {
            $clave = (int) ($fila->impc_clave ?? 0);
            $cuenta = (int) ($fila->impc_cuenta ?? 0);
            $empresa = (int) ($fila->impc_empresa ?? 0);
            if ($clave <= 0 || $cuenta <= 0) {
                continue;
            }
            if (! isset($porClave[$clave]) || $empresa === 1) {
                $porClave[$clave] = $cuenta;
            }
        }
        foreach ($faltan as $clave) {
            $this->impcont[$clave] = (int) ($porClave[$clave] ?? 0);
        }
    }

    public function numeroImpcont(int $clave): int
    {
        $this->precargar([$clave]);

        return (int) ($this->impcont[$clave] ?? 0);
    }

    /**
     * @return array{cuentacontable_id: int, codigo: string, nombre: string}
     */
    public function resolverErp(int $empresaId, int $claveImpcont): array
    {
        $numero = $this->numeroImpcont($claveImpcont);
        if ($numero <= 0) {
            throw new RuntimeException('La cuenta automática '.$claveImpcont.' no está cargada en impcont.');
        }

        return $this->resolverErpPorCodigo($empresaId, $numero, 'impcont '.$claveImpcont);
    }

    /**
     * @return array{cuentacontable_id: int, codigo: string, nombre: string}
     */
    public function resolverErpPorCodigo(int $empresaId, int $codigoAnita, string $origen): array
    {
        $codigo = str_pad((string) $codigoAnita, 9, '0', STR_PAD_LEFT);
        $cacheKey = $empresaId.'|'.$codigo;
        if (isset($this->erp[$cacheKey])) {
            $id = $this->erp[$cacheKey];
            $cuenta = Cuentacontable::query()->find($id);

            return [
                'cuentacontable_id' => $id,
                'codigo' => (string) ($cuenta->codigo ?? $codigo),
                'nombre' => (string) ($cuenta->nombre ?? ''),
            ];
        }

        $cuenta = Cuentacontable::query()
            ->where('empresa_id', $empresaId)
            ->where(function ($q) use ($codigo, $codigoAnita): void {
                $q->where('codigo', $codigo)
                    ->orWhere('codigo', (string) $codigoAnita);
            })
            ->orderBy('id')
            ->first();

        if ($cuenta === null) {
            throw new RuntimeException(
                'No está en el plan de la empresa la cuenta '.$codigo.' ('.$origen.').'
            );
        }

        $this->erp[$cacheKey] = (int) $cuenta->id;

        return [
            'cuentacontable_id' => (int) $cuenta->id,
            'codigo' => (string) $cuenta->codigo,
            'nombre' => (string) $cuenta->nombre,
        ];
    }
}
