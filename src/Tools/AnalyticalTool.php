<?php

namespace Tobiebenezer\Ai\Tools;

use Tobiebenezer\Ai\Contracts\Tool;
use Tobiebenezer\Ai\Guardrails\GuardrailContext;

abstract class AnalyticalTool implements Tool
{
    abstract protected function modelClass();
    abstract protected function filterableColumns();
    abstract protected function groupableColumns();
    abstract protected function aggregateableColumns();
    abstract protected function defaultSelects();

    protected function joins() { return []; }
    protected function contextQueries($conn) { return []; }
    protected function dateField() { return 'created_at'; }
    protected function columnMappings() { return []; }

    protected function qualifyColumn($column, $table)
    {
        if (str_contains($column, '.')) {
            return $column;
        }

        $mappings = $this->columnMappings();
        if (isset($mappings[$column])) {
            return $mappings[$column];
        }

        return "{$table}.{$column}";
    }
    protected function maxLimit() { return 50; }
    protected function aggregateFunctions() { return ['sum', 'avg', 'count', 'min', 'max']; }
    protected function likeColumns() { return []; }
    protected function customFilterHandlers() { return []; }

    public function profiles() { return ['*']; }
    public function isReadOnly() { return true; }

    public function name()
    {
        $class = static::class;
        $base = substr($class, strrpos($class, '\\') + 1);
        return lcfirst(str_replace('Tool', '', $base));
    }

    protected function conn()
    {
        $class = $this->modelClass();
        return (new $class)->getConnection();
    }

    public function schema()
    {
        $filterProperties = [];
        foreach ($this->filterableColumns() as $key) {
            $filterProperties[$key] = [
                'type' => ['string', 'number', 'array', 'null'],
                'description' => "Filter by {$key}.",
            ];
        }

        $groupEnum = $this->groupableColumns();
        $aggEnum = [];
        foreach ($this->aggregateableColumns() as $col) {
            foreach ($this->aggregateFunctions() as $fn) {
                $aggEnum[] = "{$fn}({$col})";
            }
        }

        return [
            'type' => 'object',
            'properties' => [
                'filters' => [
                    'type' => 'object',
                    'description' => 'Key-value pairs to filter results by. Use exact values or arrays for IN queries.',
                    'properties' => $filterProperties,
                    'additionalProperties' => false,
                ],
                'date_from' => [
                    'type' => 'string',
                    'format' => 'date',
                    'description' => "Start date for {$this->dateField()} filter (Y-m-d).",
                ],
                'date_to' => [
                    'type' => 'string',
                    'format' => 'date',
                    'description' => "End date for {$this->dateField()} filter (Y-m-d).",
                ],
                'group_by' => [
                    'type' => 'string',
                    'enum' => $groupEnum,
                    'description' => 'Column to group results by.',
                ],
                'aggregate' => [
                    'type' => 'string',
                    'enum' => $aggEnum,
                    'description' => 'Aggregate function (e.g., sum(amount)). Required when group_by is set.',
                ],
                'order_by' => [
                    'type' => 'string',
                    'description' => 'Column to order results by.',
                ],
                'order_dir' => [
                    'type' => 'string',
                    'enum' => ['asc', 'desc'],
                    'description' => 'Sort direction (default: asc).',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => "Maximum results to return (max {$this->maxLimit()}).",
                ],
                'offset' => [
                    'type' => 'integer',
                    'description' => 'Number of results to skip for pagination.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, GuardrailContext $context)
    {
        $class = $this->modelClass();
        $table = (new $class)->getTable();
        $filters = isset($arguments['filters']) && is_array($arguments['filters']) ? $arguments['filters'] : [];
        $dateFrom = isset($arguments['date_from']) ? $arguments['date_from'] : null;
        $dateTo = isset($arguments['date_to']) ? $arguments['date_to'] : null;
        $groupBy = isset($arguments['group_by']) ? $arguments['group_by'] : null;
        $aggregate = isset($arguments['aggregate']) ? $arguments['aggregate'] : null;
        $orderBy = isset($arguments['order_by']) ? $arguments['order_by'] : null;
        $orderDir = isset($arguments['order_dir']) && $arguments['order_dir'] === 'desc' ? 'desc' : 'asc';
        $limit = isset($arguments['limit']) ? (int) $arguments['limit'] : 10;
        $offset = isset($arguments['offset']) ? (int) $arguments['offset'] : 0;

        if ($limit < 1) $limit = 1;
        if ($limit > $this->maxLimit()) $limit = $this->maxLimit();

        $this->validateFilters($filters);
        if ($groupBy) $this->validateGroupBy($groupBy);
        if ($aggregate) $this->parseAggregate($aggregate);

        $conn = $this->conn();

        $totalCount = $this->countQuery(
            $this->buildFilteredQuery($conn, $table, $filters, $dateFrom, $dateTo),
            $conn, $table, $groupBy
        );

        $resultQuery = $this->buildFilteredQuery($conn, $table, $filters, $dateFrom, $dateTo);

        $selects = $this->defaultSelects();
        $hasAggregate = false;

        if ($groupBy && $aggregate) {
            [$fn, $aggCol] = $this->parseAggregate($aggregate);
            $fullGroupCol = $this->qualifyColumn($groupBy, $table);
            $fullAggCol = $this->qualifyColumn($aggCol, $table);
            $resultQuery->select([
                $conn->raw("{$fullGroupCol} as group_value"),
                $conn->raw("{$fn}({$fullAggCol}) as aggregate_value"),
            ]);
            $resultQuery->groupBy($conn->raw($fullGroupCol));
            $hasAggregate = true;
        } else {
            $resultQuery->select($selects);
        }

        if ($orderBy) {
            if ($hasAggregate && $orderBy === $aggregate) {
                $resultQuery->orderBy($conn->raw("{$fn}({$fullAggCol})"), $orderDir);
            } else {
                $fullOrderCol = $this->qualifyColumn($orderBy, $table);
                $resultQuery->orderBy($fullOrderCol, $orderDir);
            }
        } elseif ($hasAggregate) {
            $resultQuery->orderBy($conn->raw("{$fn}({$fullAggCol})"), 'desc');
        } else {
            $resultQuery->orderBy("{$table}.id", 'desc');
        }

        $rows = $resultQuery->offset($offset)->limit($limit)->get()->toArray();

        $summary = [
            'total_count' => $totalCount,
            'returned_count' => count($rows),
            'limit' => $limit,
            'offset' => $offset,
        ];

        if ($dateFrom || $dateTo) {
            $summary['date_range'] = [$dateFrom ?: null, $dateTo ?: null];
        }

        if ($groupBy && $aggregate) {
            [$fn, $aggCol] = $this->parseAggregate($aggregate);
            $summary['grouped_by'] = $groupBy;
            $summary['aggregate'] = "{$fn}({$aggCol})";
        }

        $context = [];
        foreach ($this->contextQueries($conn) as $key => $value) {
            $context[$key] = $value;
        }

        return [
            'summary' => $summary,
            'results' => $rows,
            'context' => $context,
        ];
    }

    protected function buildFilteredQuery($conn, $table, array $filters, $dateFrom, $dateTo)
    {
        $query = $conn->table($table)->from($table);

        foreach ($this->joins() as $join) {
            $query->leftJoin($join[0], $join[1], $join[2], $join[3]);
        }

        $likeColumns = $this->likeColumns();
        $customHandlers = $this->customFilterHandlers();

        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') continue;

            if (isset($customHandlers[$key])) {
                $customHandlers[$key]($query, $value, $table);
                continue;
            }

            if (in_array($key, $likeColumns, true)) {
                $query->where($this->qualifyColumn($key, $table), 'like', "%{$value}%");
            } elseif (is_array($value)) {
                $query->whereIn($this->qualifyColumn($key, $table), $value);
            } else {
                $query->where($this->qualifyColumn($key, $table), $value);
            }
        }

        $dateField = $this->dateField();
        if ($dateFrom) {
            $query->where("{$table}.{$dateField}", '>=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo) {
            $query->where("{$table}.{$dateField}", '<=', $dateTo . ' 23:59:59');
        }

        return $query;
    }

    protected function countQuery($query, $conn, $table, $groupBy)
    {
        if ($groupBy) {
            $fullGroupCol = $this->qualifyColumn($groupBy, $table);
            return $query->distinct()->count($conn->raw($fullGroupCol));
        }

        return $query->count();
    }

    protected function validateFilters(array $filters)
    {
        $allowed = $this->filterableColumns();
        foreach ($filters as $key => $value) {
            if (! in_array($key, $allowed, true)) {
                throw new \InvalidArgumentException("Unknown filter key: {$key}. Allowed: " . implode(', ', $allowed));
            }
        }
    }

    protected function validateGroupBy($groupBy)
    {
        $allowed = $this->groupableColumns();
        if (! in_array($groupBy, $allowed, true)) {
            throw new \InvalidArgumentException("Cannot group by: {$groupBy}. Allowed: " . implode(', ', $allowed));
        }
    }

    protected function parseAggregate($aggregate)
    {
        if (! preg_match('/^(sum|avg|count|min|max)\((\w+)\)$/i', $aggregate, $m)) {
            throw new \InvalidArgumentException(
                "Invalid aggregate format: {$aggregate}. Use function(column), e.g. sum(amount)."
            );
        }
        $fn = strtolower($m[1]);
        $col = $m[2];
        if (! in_array($fn, $this->aggregateFunctions(), true)) {
            throw new \InvalidArgumentException("Unknown aggregate function: {$fn}.");
        }
        if (! in_array($col, $this->aggregateableColumns(), true)) {
            throw new \InvalidArgumentException("Column '{$col}' is not aggregateable.");
        }
        return [$fn, $col];
    }
}
