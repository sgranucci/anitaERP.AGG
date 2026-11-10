<?php

namespace App\Services\Ventas\GastronomiaCrown;

use App\Models\Ventas\GastronomiaCrown\GastroCrownCuenta;
use InvalidArgumentException;

/**
 * Saneamiento simple Crown: listar cuentas abiertas/en cobro de la sala
 * y cerrarlas sin facturar (con confirmación textual).
 */
final class GastronomiaCrownSaneamientoService
{
    public const PREFIJO_CONFIRMACION = 'CERRAR-';

    public function __construct(
        private readonly GastronomiaCrownPosService $posService,
    ) {
    }

    public static function textoConfirmacion(int $cantidad): string
    {
        return self::PREFIJO_CONFIRMACION.$cantidad;
    }

    /**
     * @return array{
     *   total:int,
     *   con_items:int,
     *   vacias:int,
     *   confirmacion:?string,
     *   cuentas:list<array<string, mixed>>
     * }
     */
    public function diagnostico(int $empresaId, ?int $salaId = null): array
    {
        if ($empresaId <= 0) {
            return [
                'total' => 0,
                'con_items' => 0,
                'vacias' => 0,
                'confirmacion' => null,
                'cuentas' => [],
            ];
        }

        $cuentas = GastroCrownCuenta::query()
            ->with([
                'mesa:id,codigo,nombre',
                'mozo:id,codigo,nombre',
                'lineas' => fn ($q) => $q->where('es_separador', false)->orderBy('orden'),
            ])
            ->where('empresa_id', $empresaId)
            ->whereIn('estado', [GastroCrownCuenta::ESTADO_ABIERTA, GastroCrownCuenta::ESTADO_COBRANDO])
            ->when($salaId !== null && $salaId > 0, fn ($q) => $q->where('sala_id', $salaId))
            ->orderBy('id')
            ->get();

        $filas = [];
        $conItems = 0;
        $vacias = 0;

        foreach ($cuentas as $cuenta) {
            $lineasConsumo = $cuenta->lineas->count();
            $totales = $this->posService->totalesCuenta($cuenta);
            $tieneItems = $lineasConsumo > 0;
            if ($tieneItems) {
                $conItems++;
            } else {
                $vacias++;
            }

            $filas[] = [
                'id' => (int) $cuenta->id,
                'estado' => (string) $cuenta->estado,
                'mesa' => $cuenta->mesa
                    ? trim((string) ($cuenta->mesa->codigo ?? '').' '.($cuenta->mesa->nombre ?? ''))
                    : 'Barra / sin mesa',
                'mozo' => $cuenta->mozo
                    ? trim((string) ($cuenta->mozo->codigo ?? '').' — '.($cuenta->mozo->nombre ?? ''))
                    : '—',
                'identificador_pc' => (string) ($cuenta->identificador_pc ?? ''),
                'abierta_en' => $cuenta->created_at?->format('d/m/Y H:i') ?? '',
                'cubiertos' => (int) ($cuenta->cubiertos ?? 0),
                'lineas' => $lineasConsumo,
                'total' => (float) ($totales['total'] ?? 0),
                'con_items' => $tieneItems,
            ];
        }

        $total = count($filas);

        return [
            'total' => $total,
            'con_items' => $conItems,
            'vacias' => $vacias,
            'confirmacion' => $total > 0 ? self::textoConfirmacion($total) : null,
            'cuentas' => $filas,
        ];
    }

    /**
     * Cierra todas las cuentas abiertas/en cobro de empresa (+ sala).
     *
     * @return array{cerradas:int, confirmacion:string}
     */
    public function cerrarCuentas(int $empresaId, ?int $salaId, string $confirmacion): array
    {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Debe indicar la empresa.');
        }

        $diag = $this->diagnostico($empresaId, $salaId);
        if ($diag['total'] <= 0) {
            return ['cerradas' => 0, 'confirmacion' => self::textoConfirmacion(0)];
        }

        $esperado = (string) $diag['confirmacion'];
        if (trim($confirmacion) !== $esperado) {
            throw new InvalidArgumentException(
                'Confirmación incorrecta. Debe escribir exactamente: '.$esperado
            );
        }

        $n = 0;
        foreach ($diag['cuentas'] as $fila) {
            $cuenta = GastroCrownCuenta::query()->find((int) $fila['id']);
            if ($cuenta === null) {
                continue;
            }
            try {
                $this->posService->cerrarSinFacturar($cuenta);
                $n++;
            } catch (\Throwable) {
                // sigue con las demás
            }
        }

        return [
            'cerradas' => $n,
            'confirmacion' => $esperado,
        ];
    }

    /**
     * Cierra una sola cuenta abierta/en cobro de la empresa (+ sala).
     *
     * @return array{cerradas:int}
     */
    public function cerrarCuenta(int $empresaId, ?int $salaId, int $cuentaId): array
    {
        if ($empresaId <= 0 || $cuentaId <= 0) {
            throw new InvalidArgumentException('Datos incompletos para cerrar la cuenta.');
        }

        $cuenta = GastroCrownCuenta::query()
            ->whereKey($cuentaId)
            ->where('empresa_id', $empresaId)
            ->whereIn('estado', [GastroCrownCuenta::ESTADO_ABIERTA, GastroCrownCuenta::ESTADO_COBRANDO])
            ->when($salaId !== null && $salaId > 0, fn ($q) => $q->where('sala_id', $salaId))
            ->first();

        if ($cuenta === null) {
            throw new InvalidArgumentException('La cuenta no está abierta en esta empresa/sala.');
        }

        $this->posService->cerrarSinFacturar($cuenta);

        return ['cerradas' => 1];
    }
}
