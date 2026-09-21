<?php

namespace App\Support\Caja;

use Illuminate\Http\Request;

/**
 * Criterios del reporte de cheques emitidos y recibidos (por separado).
 */
class ChequeReporteFiltros
{
    /** @var array<string, string> */
    public const ORDENES = [
        'fechapago' => 'Fecha de cheque',
        'fechaemision' => 'Fecha de emisión / ingreso',
        'numerocheque' => 'Número',
        'monto' => 'Monto',
    ];

    /** @var array<string, string> */
    public const ESTADOS_EMITIDO = [
        '' => 'Todos',
        ' ' => 'Diferido (pendiente)',
        '*' => 'Debitado',
        'A' => 'Anulado',
    ];

    /** @var array<string, string> */
    public const SITUACIONES_RECIBIDO = [
        '' => 'Todos',
        'cartera' => 'En cartera',
        'depositado' => 'Depositados (sin acreditar)',
        'acreditado' => 'Acreditados',
        'rechazado' => 'Rechazados',
        'caucionado' => 'Caucionados',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $tipo = strtoupper(trim((string) $request->input('tipo', '')));
        if (! in_array($tipo, ['E', 'R'], true)) {
            $tipo = 'E';
        }

        $empresaId = $request->filled('empresa_id') ? (int) $request->input('empresa_id') : null;
        if ($empresaId !== null && $empresaId <= 0) {
            $empresaId = null;
        }

        $estado = $request->input('estado');
        $estado = $estado === null ? '' : (string) $estado;
        if (! array_key_exists($estado, self::ESTADOS_EMITIDO)) {
            $estado = '';
        }

        $situacion = (string) $request->input('situacion', '');
        if (! array_key_exists($situacion, self::SITUACIONES_RECIBIDO)) {
            $situacion = '';
        }

        $orden = (string) $request->input('orden', 'fechapago');
        if (! isset(self::ORDENES[$orden])) {
            $orden = 'fechapago';
        }
        $dir = strtolower((string) $request->input('orden_dir', 'asc'));
        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'asc';
        }

        return [
            'tipo' => $tipo,
            'empresa_id' => $empresaId,
            'fecha_doc_desde' => self::fechaYmd($request->input('fecha_doc_desde')),
            'fecha_doc_hasta' => self::fechaYmd($request->input('fecha_doc_hasta')),
            'fecha_cheque_desde' => self::fechaYmd($request->input('fecha_cheque_desde')),
            'fecha_cheque_hasta' => self::fechaYmd($request->input('fecha_cheque_hasta')),
            'estado' => $tipo === 'E' ? $estado : '',
            'situacion' => $tipo === 'R' ? $situacion : '',
            'orden' => $orden,
            'orden_dir' => $dir,
            'consultar' => $request->boolean('consultar'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [
            'consultar' => 1,
            'tipo' => ($filtros['tipo'] ?? 'E') === 'R' ? 'R' : 'E',
            'orden' => (string) ($filtros['orden'] ?? 'fechapago'),
            'orden_dir' => (string) ($filtros['orden_dir'] ?? 'asc'),
        ];
        if (! empty($filtros['empresa_id'])) {
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }
        foreach (['fecha_doc_desde', 'fecha_doc_hasta', 'fecha_cheque_desde', 'fecha_cheque_hasta'] as $clave) {
            $valor = (string) ($filtros[$clave] ?? '');
            if ($valor !== '') {
                $params[$clave] = $valor;
            }
        }
        if ($params['tipo'] === 'E' && ($filtros['estado'] ?? '') !== '') {
            $params['estado'] = (string) $filtros['estado'];
        }
        if ($params['tipo'] === 'R' && ($filtros['situacion'] ?? '') !== '') {
            $params['situacion'] = (string) $filtros['situacion'];
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function subtitulo(array $filtros): string
    {
        $tipo = ($filtros['tipo'] ?? 'E') === 'R' ? 'Recibidos' : 'Emitidos';
        $partes = [$tipo];
        $etiquetaDoc = $tipo === 'Recibidos' ? 'Ingreso' : 'Emisión';
        $doc = self::rango($filtros['fecha_doc_desde'] ?? '', $filtros['fecha_doc_hasta'] ?? '');
        if ($doc !== '') {
            $partes[] = $etiquetaDoc.' '.$doc;
        }
        $cheque = self::rango($filtros['fecha_cheque_desde'] ?? '', $filtros['fecha_cheque_hasta'] ?? '');
        if ($cheque !== '') {
            $partes[] = 'Fecha de cheque '.$cheque;
        }
        if ($tipo === 'Emitidos' && ($filtros['estado'] ?? '') !== '') {
            $partes[] = self::ESTADOS_EMITIDO[$filtros['estado']] ?? '';
        }
        if ($tipo === 'Recibidos' && ($filtros['situacion'] ?? '') !== '') {
            $partes[] = self::SITUACIONES_RECIBIDO[$filtros['situacion']] ?? '';
        }
        $orden = self::ORDENES[$filtros['orden'] ?? 'fechapago'] ?? 'Fecha de cheque';
        $dir = ($filtros['orden_dir'] ?? 'asc') === 'desc' ? 'descendente' : 'ascendente';
        $partes[] = 'Orden: '.$orden.' '.$dir;

        return implode(' · ', array_filter($partes, static fn ($p) => $p !== ''));
    }

    private static function fechaYmd(mixed $valor): string
    {
        $f = trim((string) $valor);
        if ($f !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f)) {
            return $f;
        }

        return '';
    }

    private static function rango(string $desde, string $hasta): string
    {
        $d = $desde !== '' ? ChequeDepositoComprobanteSupport::fechaDmy($desde) : '';
        $h = $hasta !== '' ? ChequeDepositoComprobanteSupport::fechaDmy($hasta) : '';
        if ($d !== '' && $h !== '') {
            return $d.' a '.$h;
        }
        if ($d !== '') {
            return 'desde '.$d;
        }
        if ($h !== '') {
            return 'hasta '.$h;
        }

        return '';
    }
}
