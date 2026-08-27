<?php

namespace App\Support\Compras\AnitaSync\Requisicion;

/**
 * Snapshot de aprobación ERP → Anita aprobcomp (l-proy columna AA).
 *
 * No reproduce el árbol completo (Generado/Enviado). Solo deja una fila Aprobado
 * cuando Anita todavía no tiene REQ para ese número, para no chocar con a-reqmae.
 */
final class RequisicionAnitaAprobcompMapper
{
    public const TABLA = 'aprobcomp';

    public const TIPO_REQ = 'REQ';

    public const ESTADO_APROBADO = 3;

    public const SECUENCIAL_SNAPSHOT = 1;

    public const MOTIVO_ERP = 'ERP';

    /** @var list<string> */
    public const ESTADOS_ANITA_VIVO = [
        'PENDIENTE',
        'PROVISORIO',
        'EN ARBOL APROBACION',
    ];

    /**
     * @return list<string>
     */
    public static function camposInsert(): array
    {
        return [
            'aprobc_nro_int_ap',
            'aprobc_secuencial',
            'aprobc_estado',
            'aprobc_fecha_envio',
            'aprobc_hora_envio',
            'aprobc_hash_aprob',
            'aprobc_hash_rech',
            'aprobc_fecha_modif',
            'aprobc_hora_modif',
            'aprobc_empresa',
            'aprobc_proveedor',
            'aprobc_tipo',
            'aprobc_letra',
            'aprobc_sucursal',
            'aprobc_nro',
            'aprobc_nro_interno',
            'aprobc_usuario',
            'aprobc_cod_usuario',
            'aprobc_motivo',
        ];
    }

    /**
     * @param  array{
     *     nro_int_ap: int,
     *     numerorequisicion: int,
     *     empresa: int,
     *     proveedor: string,
     *     usuario_anita: int,
     *     usuario_nombre: string,
     *     fecha_ymd: int,
     *     hora_hm: string
     * }  $datos
     */
    public static function valoresInsert(array $datos): string
    {
        $fecha = AnitaSqlLiteral::int((int) $datos['fecha_ymd']);
        $hora = AnitaSqlLiteral::string(self::horaHm((string) $datos['hora_hm']), 5);

        return implode(', ', [
            AnitaSqlLiteral::int((int) $datos['nro_int_ap']),
            AnitaSqlLiteral::int(self::SECUENCIAL_SNAPSHOT),
            AnitaSqlLiteral::int(self::ESTADO_APROBADO),
            $fecha,
            $hora,
            AnitaSqlLiteral::string('', 16),
            AnitaSqlLiteral::string('', 16),
            $fecha,
            $hora,
            AnitaSqlLiteral::int((int) $datos['empresa']),
            AnitaSqlLiteral::string(self::proveedorPadded((string) $datos['proveedor']), 6),
            AnitaSqlLiteral::string(self::TIPO_REQ, 3),
            AnitaSqlLiteral::char(' ', 1),
            AnitaSqlLiteral::int(0),
            AnitaSqlLiteral::int((int) $datos['numerorequisicion']),
            AnitaSqlLiteral::int(0),
            AnitaSqlLiteral::string(self::nombreUsuarioAnita((string) $datos['usuario_nombre']), 15),
            AnitaSqlLiteral::int((int) $datos['usuario_anita']),
            AnitaSqlLiteral::string(self::MOTIVO_ERP, 30),
        ]);
    }

    /**
     * @param  array{
     *     nro_int_ap: int,
     *     usuario_nombre: string,
     *     fecha_ymd: int,
     *     hora_hm: string
     * }  $datos
     */
    public static function valoresUpdateIncompleto(array $datos): string
    {
        $fecha = AnitaSqlLiteral::int((int) $datos['fecha_ymd']);
        $hora = AnitaSqlLiteral::string(self::horaHm((string) $datos['hora_hm']), 5);

        return implode(', ', [
            'aprobc_nro_int_ap = '.AnitaSqlLiteral::int((int) $datos['nro_int_ap']),
            'aprobc_fecha_envio = '.$fecha,
            'aprobc_hora_envio = '.$hora,
            'aprobc_fecha_modif = '.$fecha,
            'aprobc_hora_modif = '.$hora,
            'aprobc_usuario = '.AnitaSqlLiteral::string(self::nombreUsuarioAnita((string) $datos['usuario_nombre']), 15),
        ]);
    }

    public static function whereReq(int $numerorequisicion): string
    {
        return " WHERE aprobc_tipo MATCHES 'R*' AND aprobc_nro = ".(int) $numerorequisicion;
    }

    /**
     * @param  list<int>  $nros
     */
    public static function whereReqIn(array $nros): string
    {
        $limpios = array_values(array_unique(array_filter(array_map('intval', $nros), static fn (int $n) => $n > 0)));
        if ($limpios === []) {
            return ' WHERE 1=0';
        }

        return " WHERE aprobc_tipo MATCHES 'R*' AND aprobc_nro IN (".implode(',', $limpios).')';
    }

    public static function whereSnapshotErp(int $numerorequisicion): string
    {
        return self::whereReq($numerorequisicion)
            .' AND aprobc_motivo = '.AnitaSqlLiteral::string(self::MOTIVO_ERP, 30);
    }

    public static function whereSnapshotsErpIncompletos(?int $numerorequisicion = null): string
    {
        $where = ' WHERE aprobc_tipo MATCHES \'R*\''
            .' AND aprobc_motivo = '.AnitaSqlLiteral::string(self::MOTIVO_ERP, 30)
            .' AND (aprobc_nro_int_ap = 0 OR aprobc_nro_int_ap IS NULL'
            .' OR aprobc_fecha_envio = 0 OR aprobc_fecha_envio IS NULL)';

        if ($numerorequisicion !== null && $numerorequisicion > 0) {
            $where .= ' AND aprobc_nro = '.(int) $numerorequisicion;
        }

        return $where;
    }

    public static function correspondeSnapshot(string $estadoActual, bool $tieneHistoriaAprobada): bool
    {
        if (! $tieneHistoriaAprobada) {
            return false;
        }

        return ! in_array(trim($estadoActual), self::ESTADOS_ANITA_VIVO, true);
    }

    public static function proveedorPadded(?string $codigo): string
    {
        $codigo = ltrim(trim((string) $codigo), '0');
        if ($codigo === '') {
            $codigo = '0';
        }

        return str_pad($codigo, 6, '0', STR_PAD_LEFT);
    }

    public static function nombreUsuarioAnita(?string $nombre): string
    {
        return mb_substr(trim((string) $nombre), 0, 15);
    }

    public static function horaHm(?string $hora): string
    {
        $trim = trim((string) $hora);
        if (preg_match('/^(\d{1,2}):(\d{2})/', $trim, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return '00:00';
    }

    public static function snapshotIncompleto(?string $nroIntAp, ?string $fechaEnvio): bool
    {
        return (int) $nroIntAp <= 0 || (int) $fechaEnvio <= 0;
    }

    /**
     * Último firmante: historia APROBADA, si no último movimiento Aprobado del árbol.
     *
     * @param  array{usuario_id: int}|null  $ultimaAprobada
     * @param  array{destinatario_id: int, enviador_id: int}|null  $ultimoArbol
     */
    public static function autorizanteErpId(?array $ultimaAprobada, ?array $ultimoArbol): int
    {
        $deHistoria = (int) ($ultimaAprobada['usuario_id'] ?? 0);
        if ($deHistoria > 0) {
            return $deHistoria;
        }

        $destino = (int) ($ultimoArbol['destinatario_id'] ?? 0);
        if ($destino > 0) {
            return $destino;
        }

        return (int) ($ultimoArbol['enviador_id'] ?? 0);
    }
}
