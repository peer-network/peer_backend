<?php

declare(strict_types=1);

namespace Fawaz\App\Models\Core;

use PDO;

abstract class Model
{
    protected static $db;
    protected static string $orderBy = 'ORDER BY createdat DESC';
    protected static string $limit   = '';
    protected array $wheres          = [];
    protected array $joins           = [];
    protected array $selects         = [];
    protected array $orderBys        = [];

    abstract protected static function table(): string;

    /**
     * Set the PDO database connection.
     */
    public static function setDB(\PDO $db)
    {
        static::$db = $db;
    }

    /**
     * Get the PDO database connection.
     */
    protected static function getDB()
    {
        return static::$db;
    }

    /**
     * Get the first record matching the query.
     */
    public function first(): array|false
    {
        $db = static::getDB();

        $params = [];

        $joinsSql = $this->buildJoins();
        $whereSql = $this->buildWhere($params);
        $orderSql = $this->buildOrderBy();

        $sql = 'SELECT * FROM '.static::table()." {$joinsSql} {$whereSql} {$orderSql} LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (\count($result) > 0) {
            return $result[0];
        } else {
            return [];
        }
    }

    /**
     * Count records matching the query.
     */
    public function count(): int
    {
        $db = static::getDB();

        $params = [];

        $joinsSql = $this->buildJoins();
        $whereSql = $this->buildWhere($params);

        $sql = 'SELECT COUNT(*) as count FROM '.static::table()." {$joinsSql} {$whereSql}";

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return (int) ($result['count'] ?? 0);
        } catch (\PDOException $e) {
            throw new \PDOException('Failed to count() . error:'.$e->getMessage(), 40302);
        }
    }

    /**
     * Check if any record exists matching the query.
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Entry point for query builder.
     */
    public static function query(): static
    {
        $class = static::class;

        return new $class();
    }

    /**
     * Get all records from the table
     * protected static string $table must be defined in the child class.
     *
     * @return array An array of associative arrays representing all records
     */
    public function all(): array
    {
        $db = static::getDB();

        $params = [];

        $joinsSql = $this->buildJoins();
        $whereSql = $this->buildWhere($params);
        $orderSql = $this->buildOrderBy();

        $sql = 'SELECT * FROM '.static::table()." {$joinsSql} {$whereSql} {$orderSql} ".static::$limit;

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new \PDOException('Failed to all() . error:'.$e->getMessage(), 40302);
        }
    }

    /**
     * Paginate results.
     *
     * Note: This method accepts offset and limit (not page/perPage).
     * Some call sites pass offset/limit directly (e.g., ModerationMapper).
     *
     * @param int $offset Zero-based offset of the first record to return
     * @param int $limit  Number of records to return
     *
     * @return array An array containing 'data', 'total', 'per_page', 'current_page', 'last_page'
     */
    public function paginate(int $offset = 0, int $limit = 10): array
    {
        $db     = static::getDB();
        $params = [];

        $joinsSql = $this->buildJoins();
        $whereSql = $this->buildWhere($params);

        $orderSql = $this->buildOrderBy();

        // Determine columns to select
        $selectColumns = !empty($this->selects)
        ? implode(', ', $this->selects)
        : static::table().'.*';

        // Fetch data with limit and offset
        // Inline LIMIT/OFFSET to avoid driver limitations with bound params
        $safeLimit   = max(0, (int) $limit);
        $inputOffset = max(0, (int) $offset);

        // Heuristic: some callsites may pass page index instead of record offset.
        // If page = 0, we want offset 0. If page > 0 but still less than limit,
        // interpret it as a zero-based page index and convert to record offset.
        // This keeps existing true-offset usages (large values) unaffected.
        if ($safeLimit > 0 && $inputOffset > 0 && $inputOffset < $safeLimit) {
            $safeOffset = $inputOffset * $safeLimit; // treat as page index
        } else {
            $safeOffset = $inputOffset; // treat as absolute record offset
        }
        $sql = "SELECT {$selectColumns} FROM ".static::table()." {$joinsSql} {$whereSql} {$orderSql} LIMIT {$safeLimit} OFFSET {$safeOffset}";

        try {
            $stmt = $db->prepare($sql);

            // Bind where parameters (if any)
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }

            $stmt->execute();
            $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Get total count
            $total       = $this->count();
            $perPage     = $safeLimit > 0 ? $safeLimit : 1;
            $currentPage = (int) floor($safeOffset / $perPage) + 1;
            $lastPage    = (int) ceil($total / $perPage);

            return [
                'data'         => $data,
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $currentPage,
                'last_page'    => $lastPage,
            ];
        } catch (\PDOException $e) {
            throw new \PDOException('Failed to paginate() . Error:'.$e->getMessage(), 40302);
        }
    }

    /**
     * Specify which columns to select.
     *
     * @param string ...$columns One or more column names
     */
    public function select(string ...$columns): static
    {
        if (empty($columns)) {
            $this->selects = ['*'];
        } else {
            $this->selects = $columns;
        }

        return $this;
    }

    /**
     * Order results by a specific column.
     */
    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $direction   = 'DESC' === strtoupper($direction) ? 'DESC' : 'ASC';
        $aliasColumn = str_contains($column, '.') ? $column : static::table().'.'.$column;

        $this->orderBys[] = "{$aliasColumn} {$direction}";

        return $this;
    }

    /**
     * Order results by specific values using CASE WHEN for custom ordering.
     */
    public function orderByValue(string $column, string $direction = 'ASC', array $values = []): static
    {
        $direction   = 'DESC' === strtoupper($direction) ? 'DESC' : 'ASC';
        $aliasColumn = str_contains($column, '.') ? $column : static::table().'.'.$column;

        if (!empty($values)) {
            $cases = [];

            foreach ($values as $index => $value) {
                $cases[] = "WHEN '{$value}' THEN {$index}";
            }
            $this->orderBys[] = "CASE {$aliasColumn} ".implode(' ', $cases)." ELSE 999 END {$direction}";
        } else {
            $this->orderBys[] = "{$aliasColumn} {$direction}";
        }

        return $this;
    }

    /**
     * Add a JOIN clause to the query.
     */
    public function join(string $table, string $firstColumn, string $operator, string $secondColumn, string $type = 'LEFT'): static
    {
        $this->joins[] = strtoupper($type)."  JOIN {$table} ON {$firstColumn} {$operator} {$secondColumn}";

        return $this;
    }

    /**
     * Build the WHERE clause and populate parameters.
     */
    protected function buildWhere(&$params): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $conditions = [];

        foreach ($this->wheres as $i => [$type, $col, $op, $val]) {
            if ('IN' === $op) {
                $placeholders = [];

                foreach ($val as $j => $v) {
                    $param          = ":w{$i}_{$j}";
                    $placeholders[] = $param;
                    $params[$param] = $v;
                }
                $conditions[] = (0 === $i ? '' : $type.' ')."{$col} IN (".implode(', ', $placeholders).')';
            } elseif ('NULL' === $op || 'NOT NULL' === $op) {
                $conditions[] = (0 === $i ? '' : $type.' ')."{$col} IS {$op}";
            } else {
                $param          = ":w{$i}";
                $conditions[]   = (0 === $i ? '' : $type.' ')."{$col} {$op} {$param}";
                $params[$param] = $val;
            }
        }

        return 'WHERE '.implode(' ', $conditions);
    }

    /**
     * Build the JOIN clauses.
     */
    protected function buildJoins(): string
    {
        return $this->joins ? implode(' ', $this->joins) : '';
    }

    /**
     * Build the ORDER BY clause for the query.
     */
    protected function buildOrderBy(): string
    {
        if (empty($this->orderBys)) {
            return static::$orderBy ?: ''; // fallback if none defined
        }

        // Support multiple orders, including CASE expressions
        $clauses = array_map(fn ($order) => str_starts_with(trim($order), 'CASE') ? $order : $order, $this->orderBys);

        return 'ORDER BY '.implode(', ', $clauses);
    }

    /**
     * Add a where condition to the query.
     *
     * @param string $column          The column name
     * @param string $operatorOrValue The operator or value to compare against
     * @param mixed  $value           The value to compare against (optional)
     *
     * Examples:
     * Model::where('status', 'active') // 2 args, defaults to '=' operator
     */
    public function where(string $column, string|int|bool|float $operatorOrValue, $value = null): static
    {
        if (null === $value) {
            $this->wheres[] = ['AND', $column, '=', $operatorOrValue];
        } else {
            $this->wheres[] = ['AND', $column, $operatorOrValue, $value];
        }

        return $this;
    }

    /**
     * Add an OR where condition to the query.
     *
     * @param string $column          The column name
     * @param string $operatorOrValue The operator or value to compare against
     * @param mixed  $value           The value to compare against (optional)
     *                                Examples:
     *                                Model::orWhere('status', 'active') // 2 args
     */
    public function orWhere(string $column, string $operatorOrValue, $value = null): static
    {
        if (null === $value) {
            $this->wheres[] = ['OR', $column, '=', $operatorOrValue];
        } else {
            $this->wheres[] = ['OR', $column, $operatorOrValue, $value];
        }

        return $this;
    }

    /**
     * Insert a new record into the table.
     */
    public static function insert(array $data): bool
    {
        $db = static::getDB();

        $columns      = array_keys($data);
        $placeholders = array_map(fn ($col) => ":{$col}", $columns);

        $sql = 'INSERT INTO '.static::table().' ('.implode(', ', $columns).') VALUES ('.implode(', ', $placeholders).')';

        try {
            $stmt = $db->prepare($sql);

            return $stmt->execute($data);
        } catch (\PDOException $e) {
            throw new \PDOException('Failed to insert record. Error:'.$e->getMessage(), 40302);
        }
    }

    /**
     * Update records matching the query.
     */
    public function updateColumns(array $data): bool
    {
        $db         = static::getDB();
        $params     = [];
        $setClauses = [];

        foreach ($data as $col => $val) {
            $param          = ":set_{$col}";
            $setClauses[]   = "{$col} = {$param}";
            $params[$param] = $val;
        }
        // $joinsSql = $this->buildJoins();
        $whereSql = $this->buildWhere($params);

        $sql = 'UPDATE '.static::table().' SET '.implode(', ', $setClauses)." {$whereSql}";

        try {
            $stmt = $db->prepare($sql);

            return $stmt->execute($params);
        } catch (\PDOException $e) {
            throw new \PDOException('Failed to update record. Error:'.$e->getMessage(), 40302);
        }
    }

    /**
     * Add a WHERE IN condition to the query.
     *
     * @param string $column The column name
     * @param array  $values The array of values for the IN clause
     *
     * @return static The current instance for method chaining
     */
    public function whereIn(string $column, array $values): static
    {
        $placeholders = [];

        foreach ($values as $i => $value) {
            $param          = ":in{$i}";
            $placeholders[] = $param;
            // Add to params
            $this->wheres[] = ['AND', $column, 'IN', $value];
        }

        return $this;
    }

    /**
     * Add an OR WHERE IN condition to the query.
     *
     * @param string $column The column name
     * @param array  $values The array of values for the IN clause
     *
     * @return static The current instance for method chaining
     */
    public function orWhereIn(string $column, array $values): static
    {
        $placeholders = [];

        foreach ($values as $i => $value) {
            $param          = ":in{$i}";
            $placeholders[] = $param;
            $this->wheres[] = ['OR', $column, 'IN', $value];
        }

        return $this;
    }

    /**
     * Add a WHERE NULL condition to the query.
     *
     * @param string $column The column name
     *
     * @return static The current instance for method chaining
     */
    public function whereNull(string $column): static
    {
        $this->wheres[] = ['AND', $column, 'IS', 'NULL'];

        return $this;
    }

    /**
     * Order results by the latest entries based on a specified column.
     *
     * @param string $column The column to order by (default is 'createdat')
     */
    public function latest(string $column = 'createdat'): static
    {
        $aliasColumn      = str_contains($column, '.') ? $column : static::table().'.'.$column;
        $this->orderBys[] = "{$aliasColumn} DESC";

        return $this;
    }

    /**
     * Order results by the oldest entries based on a specified column.
     *
     * @param string $column The column to order by (default is 'createdat')
     */
    public function oldest(string $column = 'createdat'): static
    {
        $aliasColumn      = str_contains($column, '.') ? $column : static::table().'.'.$column;
        $this->orderBys[] = "{$aliasColumn} ASC";

        return $this;
    }

    /**
     * Limit results.
     */
    public function limit(int $n): static
    {
        static::$limit = "LIMIT {$n}";

        return $this;
    }
}
