<?php 
declare(strict_types=1);

namespace Fawaz\App\Models\Core;

use PDO;

class Model
{
    protected static $db;
    protected static string $table;
    protected static string $orderBy = 'createdat';
    protected static string $limit = '';
    protected array $wheres = [];
    protected array $joins = [];

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
     * protected static string $table must be defined in the child class
     * 
     * @param mixed $id
     * 
     * @return array|false The record as an associative array, or false if not found
     * 
     */
    public static function find($id): array|false
    {
        return static::query()->where('uid', '=', $id)->limit(1)->all()[0] ?? null;
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

        $sql = "SELECT * FROM " . static::$table . " {$joinsSql} {$whereSql} " . static::$orderBy . " " . static::$limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add a JOIN clause to the query
     */
    public function join(string $table, string $firstColumn, string $operator, string $secondColumn, string $type = 'INNER'): static
    {
        $this->joins[] = strtoupper($type) . " JOIN {$table} ON {$firstColumn} {$operator} {$secondColumn}";
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
    public function where(string $column, string $operatorOrValue, $value = null): static
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
        static::$orderBy = "ORDER BY {$column} DESC";
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
        static::$orderBy = "ORDER BY {$column} ASC";
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