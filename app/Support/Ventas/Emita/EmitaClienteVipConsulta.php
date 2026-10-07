<?php

namespace App\Support\Ventas\Emita;

use Illuminate\Support\Facades\Log;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Clientes VIP de Emita con última visita.
 * Por nombre: titular de Wigos o el alias mostrado, y siempre con nombre Wigos.
 * Por alias: alias de la cuenta Wigos o alias sin cuenta.
 */
final class EmitaClienteVipConsulta
{
    public const MODO_NOMBRE = 'nombre';

    public const MODO_ALIAS = 'alias';

    public const LONGITUD_MINIMA = 3;

    public const POR_PAGINA = 20;

    public const TOPE_EXPORTACION = 5000;

    /**
     * @return array{filas: list<array<string, string>>, total: int, truncado: bool}
     */
    public function pagina(string $modo, string $texto, int $pagina, int $porPagina = self::POR_PAGINA): array
    {
        $modo = $this->modo($modo);
        $like = $this->like($texto);
        $porPagina = max(1, min(100, $porPagina));
        $pagina = max(1, $pagina);
        $offset = ($pagina - 1) * $porPagina;

        $pdo = EmitaSqlServer::conectar();
        $total = $this->contar($pdo, $modo, $like);
        $sql = $this->sql($modo, false).$this->orden().' OFFSET '.(int) $offset.' ROWS FETCH NEXT '.(int) $porPagina.' ROWS ONLY';

        return [
            'filas' => $this->ejecutar($pdo, $sql, $modo, $like),
            'total' => $total,
            'truncado' => false,
        ];
    }

    /**
     * @return array{filas: list<array<string, string>>, total: int, truncado: bool}
     */
    public function exportar(string $modo, string $texto): array
    {
        $modo = $this->modo($modo);
        $like = $this->like($texto);
        $pdo = EmitaSqlServer::conectar();
        $total = $this->contar($pdo, $modo, $like);
        $tope = self::TOPE_EXPORTACION;
        $sql = $this->sql($modo, false).$this->orden().' OFFSET 0 ROWS FETCH NEXT '.$tope.' ROWS ONLY';

        return [
            'filas' => $this->ejecutar($pdo, $sql, $modo, $like),
            'total' => $total,
            'truncado' => $total > $tope,
        ];
    }

    public static function textoValido(string $texto): bool
    {
        return mb_strlen(trim($texto)) >= self::LONGITUD_MINIMA;
    }

    private function modo(string $modo): string
    {
        if (! in_array($modo, [self::MODO_NOMBRE, self::MODO_ALIAS], true)) {
            throw new RuntimeException('Modo de consulta inválido.');
        }

        return $modo;
    }

    private function like(string $texto): string
    {
        $texto = trim($texto);
        if (mb_strlen($texto) < self::LONGITUD_MINIMA) {
            throw new RuntimeException('Indique al menos '.self::LONGITUD_MINIMA.' caracteres.');
        }
        if (mb_strlen($texto) > 80) {
            throw new RuntimeException('El texto de búsqueda es demasiado largo.');
        }

        $escapado = str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $texto);

        return '%'.$escapado.'%';
    }

    private function contar(PDO $pdo, string $modo, string $like): int
    {
        try {
            $stmt = $pdo->prepare($this->sql($modo, true));
            $stmt->execute($this->parametros($modo, $like));
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int) ($fila['c'] ?? 0);
        } catch (Throwable $e) {
            Log::warning('Conteo VIP Emita falló', ['mensaje' => $e->getMessage()]);

            throw new RuntimeException('No se pudo consultar la base de clientes VIP de Emita.');
        }
    }

    /**
     * @return list<array<string, string>>
     */
    private function ejecutar(PDO $pdo, string $sql, string $modo, string $like): array
    {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($this->parametros($modo, $like));
            $filas = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (is_array($row)) {
                    $filas[] = $this->mapear($row);
                }
            }

            return $filas;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('Consulta VIP Emita falló', ['mensaje' => $e->getMessage()]);

            throw new RuntimeException('No se pudo consultar la base de clientes VIP de Emita.');
        }
    }

    /**
     * @return list<string>
     */
    private function parametros(string $modo, string $like): array
    {
        if ($modo === self::MODO_ALIAS) {
            return [$like, $like, $like];
        }

        return [$like, $like, $like, $like, $like];
    }

    /**
     * Cuentas con nombre y número Wigos primero. Alias sin cliente Wigos al final.
     */
    private function orden(): string
    {
        return " ORDER BY CASE"
            ." WHEN NULLIF(LTRIM(RTRIM(nombre_apellido)), '') IS NOT NULL"
            ." AND NULLIF(LTRIM(RTRIM(CONVERT(varchar(40), cuenta_wigos))), '') IS NOT NULL THEN 0"
            ." ELSE 1 END, sala, alias";
    }

    private function sql(string $modo, bool $contar): string
    {
        if ($modo === self::MODO_ALIAS) {
            return $this->sqlAlias($contar);
        }

        return $this->sqlNombre($contar);
    }

    private function sqlNombre(bool $contar): string
    {
        $select = $contar
            ? 'SELECT COUNT(*) AS c'
            : <<<'SQL'
SELECT
    s.nombre AS sala,
    'Wigos' AS origen,
    c.numero_cuenta AS cuenta_wigos,
    c.titular AS nombre_apellido,
    c.documento AS documento,
    COALESCE(NULLIF(LTRIM(RTRIM(c.alias)), ''), av.alias) AS alias,
    kv.nivel AS nivel_tarjeta,
    CASE c.vip WHEN 1 THEN N'Sí' WHEN 0 THEN N'No' END AS vip,
    CONVERT(varchar(10), kv.ultima_visita, 103) AS ultima_visita
SQL;

        return <<<SQL
WITH alias_hit AS (
    SELECT ta.cliente_id
    FROM dbo.tracking_manual_alias ta
    WHERE ta.cliente_id IS NOT NULL
      AND ta.alias COLLATE Latin1_General_CI_AI LIKE ?
),
candidatos AS (
    SELECT c.id
    FROM dbo.clientes c
    WHERE NULLIF(LTRIM(RTRIM(c.titular)), '') IS NOT NULL
      AND (
          c.titular COLLATE Latin1_General_CI_AI LIKE ?
          OR ISNULL(c.alias, '') COLLATE Latin1_General_CI_AI LIKE ?
          OR c.id IN (SELECT cliente_id FROM alias_hit)
      )
),
kpi_vigente AS (
    SELECT
        k.cliente_id,
        k.nivel,
        k.ultima_visita,
        ROW_NUMBER() OVER (
            PARTITION BY k.cliente_id
            ORDER BY
                CASE WHEN k.anio IS NOT NULL THEN 0 ELSE 1 END,
                COALESCE(k.fecha_hasta, k.fecha_corte) DESC
        ) AS rn
    FROM dbo.kpi_clientes k
    INNER JOIN candidatos ca ON ca.id = k.cliente_id
),
alias_vinculado AS (
    SELECT
        ta.cliente_id,
        ta.alias,
        ROW_NUMBER() OVER (
            PARTITION BY ta.cliente_id
            ORDER BY ta.fecha_creacion DESC, ta.id DESC
        ) AS rn
    FROM dbo.tracking_manual_alias ta
    INNER JOIN candidatos ca ON ca.id = ta.cliente_id
    WHERE ta.cliente_id IS NOT NULL
)
{$select}
FROM dbo.clientes c
INNER JOIN candidatos ca ON ca.id = c.id
INNER JOIN dbo.salas s ON s.id = c.sala_id
LEFT JOIN alias_vinculado av ON av.cliente_id = c.id AND av.rn = 1
LEFT JOIN kpi_vigente kv ON kv.cliente_id = c.id AND kv.rn = 1
WHERE (NULLIF(LTRIM(RTRIM(c.alias)), '') IS NOT NULL OR av.cliente_id IS NOT NULL)
  AND kv.ultima_visita IS NOT NULL
  AND (
      c.titular COLLATE Latin1_General_CI_AI LIKE ?
      OR COALESCE(NULLIF(LTRIM(RTRIM(c.alias)), ''), av.alias) COLLATE Latin1_General_CI_AI LIKE ?
  )
SQL;
    }

    private function sqlAlias(bool $contar): string
    {
        $select = $contar
            ? 'SELECT COUNT(*) AS c'
            : 'SELECT sala, origen, cuenta_wigos, nombre_apellido, documento, alias, nivel_tarjeta, vip, ultima_visita';

        return <<<SQL
WITH alias_hit AS (
    SELECT ta.id, ta.cliente_id, ta.sala_id, ta.alias, ta.documento
    FROM dbo.tracking_manual_alias ta
    WHERE ta.alias COLLATE Latin1_General_CI_AI LIKE ?
),
candidatos AS (
    SELECT c.id
    FROM dbo.clientes c
    WHERE ISNULL(c.alias, '') COLLATE Latin1_General_CI_AI LIKE ?
       OR c.id IN (SELECT cliente_id FROM alias_hit WHERE cliente_id IS NOT NULL)
),
kpi_vigente AS (
    SELECT
        k.cliente_id,
        k.nivel,
        k.ultima_visita,
        ROW_NUMBER() OVER (
            PARTITION BY k.cliente_id
            ORDER BY
                CASE WHEN k.anio IS NOT NULL THEN 0 ELSE 1 END,
                COALESCE(k.fecha_hasta, k.fecha_corte) DESC
        ) AS rn
    FROM dbo.kpi_clientes k
    INNER JOIN candidatos ca ON ca.id = k.cliente_id
),
alias_vinculado AS (
    SELECT
        ta.cliente_id,
        ta.alias,
        ROW_NUMBER() OVER (
            PARTITION BY ta.cliente_id
            ORDER BY ta.fecha_creacion DESC, ta.id DESC
        ) AS rn
    FROM dbo.tracking_manual_alias ta
    INNER JOIN candidatos ca ON ca.id = ta.cliente_id
    WHERE ta.cliente_id IS NOT NULL
),
base AS (
    SELECT
        s.nombre AS sala,
        'Wigos' AS origen,
        c.numero_cuenta AS cuenta_wigos,
        c.titular AS nombre_apellido,
        c.documento AS documento,
        COALESCE(NULLIF(LTRIM(RTRIM(c.alias)), ''), av.alias) AS alias,
        kv.nivel AS nivel_tarjeta,
        CASE c.vip WHEN 1 THEN N'Sí' WHEN 0 THEN N'No' END AS vip,
        CONVERT(varchar(10), kv.ultima_visita, 103) AS ultima_visita
    FROM dbo.clientes c
    INNER JOIN candidatos ca ON ca.id = c.id
    INNER JOIN dbo.salas s ON s.id = c.sala_id
    LEFT JOIN alias_vinculado av ON av.cliente_id = c.id AND av.rn = 1
    LEFT JOIN kpi_vigente kv ON kv.cliente_id = c.id AND kv.rn = 1
    WHERE (NULLIF(LTRIM(RTRIM(c.alias)), '') IS NOT NULL OR av.cliente_id IS NOT NULL)
      AND kv.ultima_visita IS NOT NULL

    UNION ALL

    SELECT
        s.nombre AS sala,
        'Solo alias' AS origen,
        NULL AS cuenta_wigos,
        NULL AS nombre_apellido,
        ta.documento AS documento,
        ta.alias AS alias,
        NULL AS nivel_tarjeta,
        NULL AS vip,
        CONVERT(varchar(10), (
            SELECT MAX(r.fecha)
            FROM dbo.tracking_manual_registros r
            WHERE r.alias_id = ta.id
        ), 103) AS ultima_visita
    FROM alias_hit ta
    INNER JOIN dbo.salas s ON s.id = ta.sala_id
    WHERE ta.cliente_id IS NULL
)
{$select}
FROM base
WHERE ultima_visita IS NOT NULL
  AND alias COLLATE Latin1_General_CI_AI LIKE ?
SQL;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private function mapear(array $row): array
    {
        $cuenta = $row['cuenta_wigos'] ?? null;
        $fecha = $row['ultima_visita'] ?? null;
        if ($fecha instanceof \DateTimeInterface) {
            $fechaTexto = $fecha->format('d/m/Y');
        } else {
            $fechaTexto = trim((string) $fecha);
        }

        return [
            'sala' => trim((string) ($row['sala'] ?? '')),
            'origen' => trim((string) ($row['origen'] ?? '')),
            'cuenta_wigos' => $cuenta === null || $cuenta === '' ? '' : (string) $cuenta,
            'nombre_apellido' => trim((string) ($row['nombre_apellido'] ?? '')),
            'documento' => trim((string) ($row['documento'] ?? '')),
            'alias' => trim((string) ($row['alias'] ?? '')),
            'nivel_tarjeta' => trim((string) ($row['nivel_tarjeta'] ?? '')),
            'vip' => trim((string) ($row['vip'] ?? '')),
            'ultima_visita' => $fechaTexto,
        ];
    }
}
