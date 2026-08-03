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
        'inputs.venta_ficha' => 'Venta de fichas',
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

    /**
     * @return array<string, string>
     */
    public static function camposAjustables(): array
    {
        return self::CAMPOS_WIGOS + self::CAMPOS_IMPUESTOS;
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
