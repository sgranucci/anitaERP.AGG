<?php

declare(strict_types=1);

namespace App\Support\Caja;

use Illuminate\Http\Request;

/**
 * Filtros del informe de movimientos de caja (Anita l-movim.c).
 */
final class MovimientosCajaReporteFiltros
{
    public const TIPO_TODOS = 'todos';

    public const TIPO_COBRO = 'cobro';

    public const TIPO_PAGO = 'pago';

    public const TIPO_IEV = 'iev';

    public const ESTADO_ACTIVOS = 'activos';

    public const ESTADO_ANULADOS = 'anulados';

    public const ESTADO_TODOS = 'todos';

    public const FORMATO_RESUMIDO = 'resumido';

    public const FORMATO_COMPLETO = 'completo';

    public const ORDEN_FECHA = 'fecha';

    public const ORDEN_NUMERO = 'numero';

    public const ORDEN_TIPO = 'tipo';

    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return [
            'empresa_ids' => [],
            'consolidar_empresas' => true,
            'fecha_desde' => date('Y-m-01'),
            'fecha_hasta' => date('Y-m-d'),
            'tipo_listado' => self::TIPO_TODOS,
            'estado' => self::ESTADO_ACTIVOS,
            'formato' => self::FORMATO_RESUMIDO,
            'orden' => self::ORDEN_FECHA,
            'cuentacaja_id' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $base = self::filtrosVacios();

        $empresaIds = collect($request->input('empresa_ids', []))
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $tipo = (string) $request->input('tipo_listado', $base['tipo_listado']);
        if (! in_array($tipo, [self::TIPO_TODOS, self::TIPO_COBRO, self::TIPO_PAGO, self::TIPO_IEV], true)) {
            $tipo = self::TIPO_TODOS;
        }

        $estado = (string) $request->input('estado', $base['estado']);
        if (! in_array($estado, [self::ESTADO_ACTIVOS, self::ESTADO_ANULADOS, self::ESTADO_TODOS], true)) {
            $estado = self::ESTADO_ACTIVOS;
        }

        $formato = (string) $request->input('formato', $base['formato']);
        if (! in_array($formato, [self::FORMATO_RESUMIDO, self::FORMATO_COMPLETO], true)) {
            $formato = self::FORMATO_RESUMIDO;
        }

        $orden = (string) $request->input('orden', $base['orden']);
        if (! in_array($orden, [self::ORDEN_FECHA, self::ORDEN_NUMERO, self::ORDEN_TIPO], true)) {
            $orden = self::ORDEN_FECHA;
        }

        $desde = (string) $request->input('fecha_desde', $base['fecha_desde']);
        $hasta = (string) $request->input('fecha_hasta', $base['fecha_hasta']);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $desde = $base['fecha_desde'];
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $hasta = $base['fecha_hasta'];
        }
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [
            'empresa_ids' => $empresaIds,
            'consolidar_empresas' => $request->input('consolidar_empresas', '1') === '1'
                || $request->boolean('consolidar_empresas'),
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'tipo_listado' => $tipo,
            'estado' => $estado,
            'formato' => $formato,
            'orden' => $orden,
            'cuentacaja_id' => max(0, (int) $request->input('cuentacaja_id', 0)),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros, bool $consultar = false): array
    {
        $out = [
            'fecha_desde' => (string) ($filtros['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($filtros['fecha_hasta'] ?? ''),
            'tipo_listado' => (string) ($filtros['tipo_listado'] ?? self::TIPO_TODOS),
            'estado' => (string) ($filtros['estado'] ?? self::ESTADO_ACTIVOS),
            'formato' => (string) ($filtros['formato'] ?? self::FORMATO_RESUMIDO),
            'orden' => (string) ($filtros['orden'] ?? self::ORDEN_FECHA),
            'cuentacaja_id' => (int) ($filtros['cuentacaja_id'] ?? 0),
            'consolidar_empresas' => ! empty($filtros['consolidar_empresas']) ? '1' : '0',
        ];
        if ((int) $out['cuentacaja_id'] <= 0) {
            unset($out['cuentacaja_id']);
        }
        $empresaIds = array_values(array_map('intval', $filtros['empresa_ids'] ?? []));
        if ($empresaIds !== []) {
            $out['empresa_ids'] = $empresaIds;
        }
        if ($consultar) {
            $out['consultar'] = 1;
        }

        return array_filter($out, static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $nombresEmpresa
     */
    public static function subtitulo(array $filtros, array $nombresEmpresa): string
    {
        $partes = [];
        $desde = self::fechaDmy((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = self::fechaDmy((string) ($filtros['fecha_hasta'] ?? ''));
        if ($desde !== '' && $hasta !== '') {
            $partes[] = 'Desde '.$desde.' hasta '.$hasta;
        }
        if ($nombresEmpresa !== []) {
            $partes[] = count($nombresEmpresa) === 1
                ? 'Empresa: '.$nombresEmpresa[0]
                : 'Empresas: '.implode(', ', $nombresEmpresa);
        }
        $partes[] = match ((string) ($filtros['tipo_listado'] ?? '')) {
            self::TIPO_COBRO => 'Cobros',
            self::TIPO_PAGO => 'Pagos',
            self::TIPO_IEV => 'Ingresos y egresos varios',
            default => 'Todos los tipos',
        };
        $partes[] = match ((string) ($filtros['estado'] ?? '')) {
            self::ESTADO_ANULADOS => 'Anulados / revertidos',
            self::ESTADO_TODOS => 'Todos los estados',
            default => 'Activos',
        };
        $partes[] = ((string) ($filtros['formato'] ?? '') === self::FORMATO_COMPLETO)
            ? 'Formato completo'
            : 'Formato resumido';

        return implode(' · ', $partes);
    }

    private static function fechaDmy(string $ymd): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
            return '';
        }

        return $m[3].'/'.$m[2].'/'.$m[1];
    }
}
