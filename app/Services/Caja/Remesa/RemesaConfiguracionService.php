<?php

declare(strict_types=1);

namespace App\Services\Caja\Remesa;

use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Usocuentacaja;
use App\Support\Caja\Remesa\RemesaSupport;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Preview y mantenimiento del pivot cuentacaja ↔ uso remesa
 * (sin tocar el ABM de uso ni la cuenta más que su vínculo de uso).
 */
final class RemesaConfiguracionService
{
    /**
     * @return list<array{
     *   clave: string,
     *   uso_id: int,
     *   uso_nombre: string,
     *   titulo: string,
     *   descripcion: string,
     *   genera_asiento: bool,
     *   cuentas: list<array<string, mixed>>
     * }>
     */
    public function grupos(): array
    {
        $out = [];
        foreach (RemesaSupport::usosConfiguracion() as $meta) {
            $usoId = (int) (Usocuentacaja::query()->where('nombre', $meta['nombre'])->value('id') ?? 0);
            $out[] = [
                'clave' => $meta['clave'],
                'uso_id' => $usoId,
                'uso_nombre' => $meta['nombre'],
                'titulo' => $meta['titulo'],
                'descripcion' => $meta['descripcion'],
                'genera_asiento' => (bool) $meta['genera_asiento'],
                'cuentas' => $usoId > 0 ? $this->cuentasDelUso($usoId) : [],
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cuentasDelUso(int $usoId): array
    {
        if ($usoId <= 0) {
            return [];
        }

        $cuentas = Cuentacaja::query()
            ->with([
                'empresas:id,nombre',
                'monedas:id,abreviatura,nombre',
                'cuentacontables:id,codigo,nombre,empresa_id',
            ])
            ->whereHas('usocuentacajas', fn ($q) => $q->where('usocuentacaja.id', $usoId))
            ->orderByRaw('CASE WHEN cuentacaja.empresa_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('cuentacaja.empresa_id')
            ->orderBy('cuentacaja.codigo')
            ->get();

        $filas = [];
        foreach ($cuentas as $cuenta) {
            $cc = $cuenta->cuentacontables;
            $filas[] = [
                'cuentacaja_id' => (int) $cuenta->id,
                'codigo' => (string) $cuenta->codigo,
                'nombre' => $cuenta->etiquetaOperaciones(),
                'nombre_maestro' => (string) ($cuenta->nombre ?? ''),
                'empresa_id' => $cuenta->empresa_id !== null ? (int) $cuenta->empresa_id : null,
                'empresa_nombre' => $cuenta->empresa_id === null
                    ? 'Todas (compartida)'
                    : (string) ($cuenta->empresas->nombre ?? ('#'.$cuenta->empresa_id)),
                'moneda_id' => (int) ($cuenta->moneda_id ?? 0),
                'moneda_abrev' => (string) ($cuenta->monedas->abreviatura ?? ''),
                'cuentacontable_id' => $cc ? (int) $cc->id : 0,
                'cuentacontable_codigo' => $cc ? (string) $cc->codigo : '',
                'cuentacontable_nombre' => $cc ? (string) $cc->nombre : '',
                'tiene_cuentacontable' => $cc !== null,
            ];
        }

        return $filas;
    }

    public function agregar(string $claveUso, int $cuentacajaId): void
    {
        $usoId = $this->resolverUsoId($claveUso);
        $cuenta = Cuentacaja::query()->find($cuentacajaId);
        if ($cuenta === null) {
            throw new InvalidArgumentException('No se encontró la cuenta de caja #'.$cuentacajaId.'.');
        }

        $cuenta->usocuentacajas()->syncWithoutDetaching([$usoId]);
    }

    public function agregarPorCodigo(string $claveUso, string $codigo, ?int $empresaId = null): Cuentacaja
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            throw new InvalidArgumentException('Indique el código de la cuenta de caja.');
        }

        $query = Cuentacaja::query()->where('codigo', $codigo);
        if ($empresaId !== null && $empresaId > 0) {
            $query->paraEmpresa($empresaId);
        }

        $cuenta = $query->orderByRaw('CASE WHEN empresa_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('id')
            ->first();

        if ($cuenta === null) {
            throw new InvalidArgumentException('No se encontró la cuenta de caja con código '.$codigo.'.');
        }

        $this->agregar($claveUso, (int) $cuenta->id);

        return $cuenta;
    }

    public function quitar(string $claveUso, int $cuentacajaId): void
    {
        $usoId = $this->resolverUsoId($claveUso);
        if ($cuentacajaId <= 0) {
            throw new InvalidArgumentException('Cuenta de caja inválida.');
        }

        DB::table('cuentacaja_usocuentacaja')
            ->where('usocuentacaja_id', $usoId)
            ->where('cuentacaja_id', $cuentacajaId)
            ->delete();
    }

    private function resolverUsoId(string $claveUso): int
    {
        $meta = RemesaSupport::usoConfiguracionPorClave($claveUso);
        if ($meta === null) {
            throw new InvalidArgumentException('Uso de remesa inválido.');
        }

        $usoId = (int) (Usocuentacaja::query()->where('nombre', $meta['nombre'])->value('id') ?? 0);
        if ($usoId <= 0) {
            throw new InvalidArgumentException(
                'No existe el uso de cuenta «'.$meta['nombre'].'». Créelo en Uso cuenta de caja.'
            );
        }

        return $usoId;
    }
}
