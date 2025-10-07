<?php 
declare(strict_types=1);

namespace Fawaz\App\Models\Core;

use PDO;

abstract class Model
{
    protected static $db;
    protected static string $orderBy = 'createdat';
    protected static string $limit = '';
    protected array $wheres = [];
    protected array $joins = [];
    protected array $selects = [];

    abstract protected static function table(): string;

    /**
     * Set the PDO database connection
     */
    public static function setDB(PDO $db)
    {
        static::$db = $db;
    }

    /**
     * Get the PDO database connection
     */
    protected static function getDB()
    {
        return static::$db;
    }

    /**
     * Find a record by its ID
     * protected static string static::table() must be defined in the child class
     * 
     * @param mixed $id
     * 
     * @return array|false The record as an associative array, or false if not found
     * 
     */
    public static function find($id): array|false
    {
        $result = static::query()->where('uid', '=', $id)->limit(1)->all()[0] ?? null;

        return $result ?: false;
    }

    /**
     * Get the first record matching the query
     */
    public function first(): array|false
    {
        $db = static::getDB();

        $params = [];

        $joinsSql = $this->buildJoins();
        $whereSql = $this->buildWhere($params);

        $this->latest();

        $sql = "SELECT * FROM " . static::table() . " {$joinsSql} {$whereSql} " . static::$orderBy . " LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }

    /**
     * Count records matching the query
     */
    public function count(): int
    {
        $db = static::getDB();

        $params = [];

        $joinsSql = $this->buildJoins();
        $whereSql = $this->buildWhere($params);

        $sql = "SELECT COUNT(*) as count FROM " . static::table() . " {$joinsSql} {$whereSql}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)($result['count'] ?? 0);
    }

    /**
     * Check if any record exists matching the query
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Entry point for query builder
     */
    public static function query(): static
    {
        return new static();
    }

    /**
     * Get all records from the table
     * protected static string $table must be defined in the child class
     * 
     * @return array An array of associative arrays representing all records
     */
    public function all(): array
    {
        $db = static::getDB();

        $params = [];

        $joinsSql = $this->buildJoins();
        $whereSql = $this->buildWhere($params);

        $sql = "SELECT * FROM " . static::table() . " {$joinsSql} {$whereSql} " . static::$orderBy . " " . static::$limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Paginate results
     * 
     * @param int $page The current page (1-based)
     * @param int $perPage Number of records per page
     * 
     * @return array An array containing 'data', 'total', 'per_page', 'current_page', 'last_page'
     */
    public function paginate(int $page = 1, int $perPage = 10): array
    {
        $db = static::getDB();
        $params = [];

        $joinsSql = $this->buildJoins();
        $whereSql = $this->buildWhere($params);

        $this->latest();
        // Calculate offset
        $offset = ($page - 1) * $perPage;

        // Determine columns to select
        $selectColumns = !empty($this->selects)
        ? implode(', ', $this->selects)
        : static::table() . '.*';

        // Fetch data with limit and offset
        $sql = "SELECT {$selectColumns} FROM " . static::table() . " {$joinsSql} {$whereSql} " . static::$orderBy . " LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($sql);

        // Bind limit and offset
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        // Bind other where parameters
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }

        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get total count
        $total = $this->count();
        $lastPage = (int) ceil($total / $perPage);

        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $lastPage,
        ];
    }

    /**
     * Specify which columns to select
     * 
     * @param string ...$columns One or more column names
     * @return static
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
     * Order results by a specific column
     */
    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $aliasColumn = str_contains($column, '.') ? $column : static::table() . '.' . $column;
        static::$orderBy = "ORDER BY {$aliasColumn} {$direction}";
        return $this;
    }


    /**
     * Order results by values
     */
    public function orderByValue(string $column, string $direction = 'ASC', array $values = []): static
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $aliasColumn = str_contains($column, '.') ? $column : static::table() . '.' . $column;

        if (!empty($values)) {
            // Build CASE WHEN expression for custom ordering
            $cases = [];
            foreach ($values as $index => $value) {
                $cases[] = "WHEN '{$value}' THEN {$index}";
            }
            // ORDER BY CASE status WHEN 'active' THEN 0 WHEN 'pending' THEN 1 WHEN 'archived' THEN 2 ELSE 999 END ASC
            static::$orderBy = "ORDER BY CASE {$aliasColumn} " . implode(' ', $cases) . " ELSE 999 END {$direction}";
        } else {
            static::$orderBy = "ORDER BY {$aliasColumn} {$direction}";
        }
        return $this;
    }


    /**
     * Add a JOIN clause to the query
     */
    public function join(string $table, string $firstColumn, string $operator, string $secondColumn, string $type = 'LEFT'): static
    {
        $this->joins[] = strtoupper($type) . "  JOIN {$table} ON {$firstColumn} {$operator} {$secondColumn}";
        return $this;
    }


    /**
     * Build the WHERE clause and populate parameters
     */
    protected function buildWhere(&$params): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $conditions = [];

        foreach ($this->wheres as $i => [$type, $col, $op, $val]) {
            if ($op === 'IN') {
                $placeholders = [];
                foreach ($val as $j => $v) {
                    $param = ":w{$i}_{$j}";
                    $placeholders[] = $param;
                    $params[$param] = $v;
                }
                $conditions[] = ($i === 0 ? "" : $type . " ") . "{$col} IN (" . implode(", ", $placeholders) . ")";
            } elseif ($op === 'NULL' || $op === 'NOT NULL') {
                $conditions[] = ($i === 0 ? "" : $type . " ") . "{$col} IS {$op}";
            } else {
                $param = ":w{$i}";
                $conditions[] = ($i === 0 ? "" : $type . " ") . "{$col} {$op} {$param}";
                $params[$param] = $val;
            }
        }

        return "WHERE " . implode(" ", $conditions);
    }

    /**
     * Build the JOIN clauses
     */
    protected function buildJoins(): string
    {
        return $this->joins ? implode(' ', $this->joins) : '';
    }



    /**
     * Add a where condition to the query
     * @param string $column The column name
     * @param string $operatorOrValue The operator or value to compare against
     * @param mixed $value The value to compare against (optional)
     * 
     * Examples:
     * Model::where('status', 'active') // 2 args, defaults to '=' operator
     */
    public function where(string $column, string|int|bool|float $operatorOrValue, $value = null): static
    {
        if ($value === null) {
            $this->wheres[] = ['AND', $column, '=', $operatorOrValue];
        } else {
            $this->wheres[] = ['AND', $column, $operatorOrValue, $value];
        }
        return $this;
    }

    /**
     * Add an OR where condition to the query
     * @param string $column The column name
    *  @param string $operatorOrValue The operator or value to compare against
    *  @param mixed $value The value to compare against (optional)
    *  Examples:
    *  Model::orWhere('status', 'active') // 2 args
    */
    public function orWhere(string $column, string $operatorOrValue, $value = null): static
    {
        if ($value === null) {
            $this->wheres[] = ['OR', $column, '=', $operatorOrValue];
        } else {
            $this->wheres[] = ['OR', $column, $operatorOrValue, $value];
        }
        return $this;
    }


    /**
     * Insert a new record into the table
     */
    public static function insert(array $data): bool
    {
        $db = static::getDB();

        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":{$col}", $columns);

        $sql = "INSERT INTO " . static::table() . " (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";

        $stmt = $db->prepare($sql);

        return $stmt->execute($data);
    }

    /**
     * Update records matching the query
     */
    public function updateColumns(array $data): bool
    {
        $db = static::getDB();
        $params = [];
        $setClauses = [];

        foreach ($data as $col => $val) {
            $param = ":set_{$col}";
            $setClauses[] = "{$col} = {$param}";
            $params[$param] = $val;
        }
        $joinsSql = $this->buildJoins();
        $whereSql = $this->buildWhere($params);

        $sql = "UPDATE " . static::table() . " SET " . implode(", ", $setClauses) . " {$whereSql}";
        $stmt = $db->prepare($sql);

        return $stmt->execute($params);
    }


    /**
     * Add a WHERE IN condition to the query
     * 
     * @param string $column The column name
     * @param array $values The array of values for the IN clause
     * 
     * @return static The current instance for method chaining
     */
    public function whereIn(string $column, array $values): static
    {
        $placeholders = [];
        foreach ($values as $i => $value) {
            $param = ":in{$i}";
            $placeholders[] = $param;
            // Add to params
            $this->wheres[] = ['AND', $column, 'IN', $value];
        }
        return $this;
    }

    /**
     * Add an OR WHERE IN condition to the query
     * 
     * @param string $column The column name
     * @param array $values The array of values for the IN clause
     * 
     * @return static The current instance for method chaining
     */
    public function orWhereIn(string $column, array $values): static
    {
        $placeholders = [];
        foreach ($values as $i => $value) {
            $param = ":in{$i}";
            $placeholders[] = $param;
            $this->wheres[] = ['OR', $column, 'IN', $value];
        }
        return $this;
    }

    /**
     * Add a WHERE NULL condition to the query
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
     * Order results by the latest entries based on a specified column
     * 
     * @param string $column The column to order by (default is 'createdat')
     * 
     * @return static The current instance for method chaining
     */
    public function latest(string $column = 'createdat'): static
    {
        $aliasColumn = str_contains($column, '.') ? $column : static::table() . '.' . $column;
        static::$orderBy = "ORDER BY {$aliasColumn} DESC";
        return $this;
    }

    /**
     * Order results by the oldest entries based on a specified column
     * 
     * @param string $column The column to order by (default is 'createdat')
     * 
     * @return static The current instance for method chaining
     */
    public function oldest(string $column = 'createdat'): static
    {
        $aliasColumn = str_contains($column, '.') ? $column : static::table() . '.' . $column;

        static::$orderBy = "ORDER BY {$aliasColumn} ASC";
        return $this;
    }

    /**
     * Limit results
     */
    public function limit(int $n): static
    {
        static::$limit = "LIMIT {$n}";
        return $this;
    }

}