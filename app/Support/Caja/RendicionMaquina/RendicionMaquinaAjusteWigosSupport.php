<?php

namespace App\Support\Caja\RendicionMaquina;

use App\Models\Caja\RendicionMaquinaAjusteWigos;
use App\Models\Seguridad\Usuario;
use Illuminate\Support\Collection;

/**
 * Log de ajustes WIGOS (tabla rendicion_maquina_ajuste_wigos).
 *
 * No hay campo aparte «ajuste_wigosd»: se edita el dato y el delta queda en este log.
 */
final class RendicionMaquinaAjusteWigosSupport
{
    public const PERMISO_AJUSTAR = 'ajustar-wigos-rendicion-maquina';

    public const PERMISO_LISTAR = 'listar-ajustes-wigos-rendicion-maquina';

    /**
     * Datos WIGOS editables (pantalla, bloque principal).
     *
     * @var array<string, string> campo => etiqueta UI
     */
    public const CAMPOS_WIGOS = [
        'inputs.drop_billete_bruto' => 'Drop billetes rodillo bruto WIGOS',
        'inputs.drop_billete' => 'Drop billetes rodillo (neto Anita)',
        'inputs.drop_ruleta' => 'Drop billetes ruleta',
        'inputs.drop_bill_ant' => 'Drop billetes rodillo anterior',
        'inputs.drop_rul_ant' => 'Drop billetes ruleta anterior',
        'inputs.dropqr_rodillo' => 'Drop QR rodillo',
        'inputs.dropqr_ruleta' => 'Drop QR ruleta',
        'inputs.venta_ficha' => 'Venta de fichas (slots)',
        'inputs.venta_ruleta' => 'Venta ruletas',
        'inputs.tito' => 'Tito rodillos',
        'inputs.tito_ruleta' => 'Tito ruletas',
        'inputs.salida_ruleta' => 'Salidas ruleta',
        'inputs.pago_manual' => 'Pagos manuales',
    ];

    /**
     * Impuestos (pantalla, bloque aparte).
     *
     * @var array<string, string>
     */
    public const CAMPOS_IMPUESTOS = [
        'inputs.impuesto_drop' => 'Impuesto drop',
        'inputs.impuesto_venta' => 'Impuesto venta',
        'inputs.impuesto_qr' => 'Impuesto QR',
        'inputs.impuesto_pago' => 'Impuesto / canje gastronomía',
    ];

    public const CAMPO_TOTALCOIN_QR_MAQUINAS = 'valores.totalcoin_qr_maquinas';

    /**
     * Líneas de arqueo que también van al log (no son campo amarillo WIGOS).
     *
     * @var array<string, string>
     */
    public const CAMPOS_VALORES = [
        self::CAMPO_TOTALCOIN_QR_MAQUINAS => 'TotalCoin QR Máquinas',
    ];

    /**
     * @return array<string, string>
     */
    public static function camposAjustables(): array
    {
        return self::CAMPOS_WIGOS + self::CAMPOS_IMPUESTOS + self::CAMPOS_VALORES;
    }

    public static function requierePermisoAjustar(string $campo): bool
    {
        return isset(self::CAMPOS_WIGOS[$campo]) || isset(self::CAMPOS_IMPUESTOS[$campo]);
    }

    /**
     * @param  array<string, mixed>  $wigosJson
     */
    public static function valorOriginalTotalCoinDesdeWigosJson(array $wigosJson): ?float
    {
        if (array_key_exists(self::CAMPO_TOTALCOIN_QR_MAQUINAS, $wigosJson)
            && $wigosJson[self::CAMPO_TOTALCOIN_QR_MAQUINAS] !== null
            && $wigosJson[self::CAMPO_TOTALCOIN_QR_MAQUINAS] !== '') {
            return round((float) $wigosJson[self::CAMPO_TOTALCOIN_QR_MAQUINAS], 2);
        }

        $drop = (float) ($wigosJson['inputs.dropqr_rodillo'] ?? $wigosJson['dropqr_rodillo'] ?? 0);
        $impuesto = (float) ($wigosJson['inputs.impuesto_qr'] ?? $wigosJson['impuesto_qr'] ?? 0);
        if (abs($drop) < 0.005 && abs($impuesto) < 0.005) {
            return null;
        }

        return round($drop + $impuesto, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $ajustes
     * @return list<array<string, mixed>>
     */
    public static function fusionarAjusteTotalCoin(array $ajustes, ?float $valorOriginal, ?float $valorActual): array
    {
        if ($valorOriginal === null || $valorActual === null) {
            return $ajustes;
        }

        $original = round($valorOriginal, 2);
        $actual = round($valorActual, 2);
        if (abs($original - $actual) < 0.005) {
            return $ajustes;
        }

        foreach ($ajustes as $ajuste) {
            if ((string) ($ajuste['campo'] ?? '') === self::CAMPO_TOTALCOIN_QR_MAQUINAS) {
                return $ajustes;
            }
        }

        $ajustes[] = [
            'campo' => self::CAMPO_TOTALCOIN_QR_MAQUINAS,
            'valor_wigos' => $original,
            'valor_ajustado' => $actual,
            'motivo' => null,
        ];

        return $ajustes;
    }

    /**
     * @param  array{
     *   rendicion_maquina_id?: int|null,
     *   empresa_id: int,
     *   fecha: string,
     *   turno: string,
     *   nro_oper?: int|null,
     *   campo: string,
     *   valor_wigos: float,
     *   valor_ajustado: float,
     *   motivo?: string|null,
     *   usuario_id: int
     * }  $data
     */
    public static function registrar(array $data): ?RendicionMaquinaAjusteWigos
    {
        $campo = (string) $data['campo'];
        $ajustables = self::camposAjustables();
        if (! isset($ajustables[$campo])) {
            throw new \InvalidArgumentException("Campo WIGOS no ajustable: {$campo}");
        }

        $wigos = round((float) $data['valor_wigos'], 2);
        $ajustado = round((float) $data['valor_ajustado'], 2);
        if (abs($wigos - $ajustado) < 0.005) {
            return null;
        }

        $turno = RendicionMaquinaTurno::normalizar((string) $data['turno']);

        return RendicionMaquinaAjusteWigos::query()->create([
            'rendicion_maquina_id' => $data['rendicion_maquina_id'] ?? null,
            'empresa_id' => (int) $data['empresa_id'],
            'fecha' => $data['fecha'],
            'turno' => $turno,
            'nro_oper' => $data['nro_oper'] ?? null,
            'campo' => $campo,
            'etiqueta' => $ajustables[$campo],
            'valor_wigos' => $wigos,
            'valor_ajustado' => $ajustado,
            'delta' => round($ajustado - $wigos, 2),
            'motivo' => $data['motivo'] ?? null,
            'usuario_id' => (int) $data['usuario_id'],
        ]);
    }

    /**
     * @return Collection<int, RendicionMaquinaAjusteWigos>
     */
    public static function listarPorRendicion(?int $rendicionId, ?int $empresaId = null, ?string $fecha = null, ?string $turno = null): Collection
    {
        $q = RendicionMaquinaAjusteWigos::query()
            ->with(['usuario:id,nombre', 'empresa:id,nombre'])
            ->orderByDesc('id');

        if ($rendicionId !== null && $rendicionId > 0) {
            $q->where('rendicion_maquina_id', $rendicionId);
        } else {
            if ($empresaId) {
                $q->where('empresa_id', $empresaId);
            }
            if ($fecha) {
                $q->whereDate('fecha', $fecha);
            }
            if ($turno) {
                $q->where('turno', RendicionMaquinaTurno::normalizar($turno));
            }
        }

        return $q->get();
    }

    public static function usuarioPuedeAjustar(?Usuario $usuario = null): bool
    {
        return can(self::PERMISO_AJUSTAR, false);
    }

    public static function usuarioPuedeVerLog(): bool
    {
        return can(self::PERMISO_LISTAR, false) || can(self::PERMISO_AJUSTAR, false);
    }
}
