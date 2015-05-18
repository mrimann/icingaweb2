<?php
/* Icinga Web 2 | (c) 2013-2015 Icinga Development Team | GPLv2+ */

namespace Icinga\Repository;

use Icinga\Application\Logger;
use Icinga\Data\QueryInterface;
use Icinga\Data\Filter\Filter;
use Icinga\Exception\QueryException;

/**
 * Query class supposed to mediate between a repository and its datasource's query
 */
class RepositoryQuery implements QueryInterface
{
    /**
     * The repository being used
     *
     * @var Repository
     */
    protected $repository;

    /**
     * The real query being used
     *
     * @var QueryInterface
     */
    protected $query;

    /**
     * The current target to be queried
     *
     * @var mixed
     */
    protected $target;

    /**
     * Create a new repository query
     *
     * @param   Repository  $repository     The repository to use
     */
    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Return the real query being used
     *
     * @return  QueryInterface
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Set where to fetch which columns
     *
     * This notifies the repository about each desired query column.
     *
     * @param   mixed   $target     The target from which to fetch the columns
     * @param   array   $columns    If null or an empty array, all columns will be fetched
     *
     * @return  $this
     */
    public function from($target, array $columns = null)
    {
        $this->query = $this->repository
            ->getDataSource()
            ->select()
            ->from($target, $this->prepareQueryColumns($target, $columns));
        $this->target = $target;
        return $this;
    }

    /**
     * Return the columns to fetch
     *
     * @return  array
     */
    public function getColumns()
    {
        return $this->query->getColumns();
    }

    /**
     * Set which columns to fetch
     *
     * This notifies the repository about each desired query column.
     *
     * @param   array   $columns    If null or an empty array, all columns will be fetched
     *
     * @return  $this
     */
    public function columns(array $columns)
    {
        $this->query->columns($this->prepareQueryColumns($this->target, $columns));
        return $this;
    }

    /**
     * Resolve the given columns supposed to be fetched
     *
     * This notifies the repository about each desired query column.
     *
     * @param   mixed   $target             The target where to look for each column
     * @param   array   $desiredColumns     Pass null or an empty array to require all query columns
     *
     * @return  array                       The desired columns indexed by their respective alias
     */
    protected function prepareQueryColumns($target, array $desiredColumns = null)
    {
        if (empty($desiredColumns)) {
            $columns = $this->repository->requireAllQueryColumns($target);
        } else {
            $columns = array();
            foreach ($desiredColumns as $customAlias => $columnAlias) {
                $resolvedColumn = $this->repository->requireQueryColumn($target, $columnAlias);
                if ($resolvedColumn !== $columnAlias) {
                    $columns[is_string($customAlias) ? $customAlias : $columnAlias] = $resolvedColumn;
                } elseif (is_string($customAlias)) {
                    $columns[$customAlias] = $columnAlias;
                } else {
                    $columns[] = $columnAlias;
                }
            }
        }

        return $columns;
    }

    /**
     * Filter this query using the given column and value
     *
     * This notifies the repository about the required filter column.
     *
     * @param   string  $column
     * @param   mixed   $value
     *
     * @return  $this
     */
    public function where($column, $value = null)
    {
        $this->query->where(
            $this->repository->requireFilterColumn($this->target, $column),
            $this->repository->persistColumn($column, $value)
        );
        return $this;
    }

    /**
     * Add an additional filter expression to this query
     *
     * This notifies the repository about each required filter column.
     *
     * @param   Filter  $filter
     *
     * @return  $this
     */
    public function applyFilter(Filter $filter)
    {
        return $this->addFilter($filter);
    }

    /**
     * Set a filter for this query
     *
     * This notifies the repository about each required filter column.
     *
     * @param   Filter  $filter
     *
     * @return  $this
     */
    public function setFilter(Filter $filter)
    {
        $this->repository->requireFilter($this->target, $filter);
        $this->query->setFilter($filter);
        return $this;
    }

    /**
     * Add an additional filter expression to this query
     *
     * This notifies the repository about each required filter column.
     *
     * @param   Filter  $filter
     *
     * @return  $this
     */
    public function addFilter(Filter $filter)
    {
        $this->repository->requireFilter($this->target, $filter);
        $this->query->addFilter($filter);
        return $this;
    }

    /**
     * Return the current filter
     *
     * @return  Filter
     */
    public function getFilter()
    {
        return $this->query->getFilter();
    }

    /**
     * Add a sort rule for this query
     *
     * If called without a specific column, the repository's defaul sort rules will be applied.
     * This notifies the repository about each column being required as filter column.
     *
     * @param   string  $field      The name of the column by which to sort the query's result
     * @param   string  $direction  The direction to use when sorting (asc or desc, default is asc)
     *
     * @return  $this
     */
    public function order($field = null, $direction = null)
    {
        $sortRules = $this->repository->getSortRules();
        if ($field === null) {
            // Use first available sort rule as default
            if (empty($sortRules)) {
                // Return early in case of no sort defaults and no given $field
                return $this;
            }

            $sortColumns = reset($sortRules);
            if (! array_key_exists('columns', $sortColumns)) {
                $sortColumns['columns'] = array(key($sortRules));
            }
            if ($direction !== null || !array_key_exists('order', $sortColumns)) {
                $sortColumns['order'] = $direction ?: static::SORT_ASC;
            }
        } elseif (array_key_exists($field, $sortRules)) {
            $sortColumns = $sortRules[$field];
            if (! array_key_exists('columns', $sortColumns)) {
                $sortColumns['columns'] = array($field);
            }
            if ($direction !== null || !array_key_exists('order', $sortColumns)) {
                $sortColumns['order'] = $direction ?: static::SORT_ASC;
            }
        } else {
            $sortColumns = array(
                'columns'   => array($field),
                'order'     => $direction
            );
        };

        $baseDirection = strtoupper($sortColumns['order']) === static::SORT_DESC ? static::SORT_DESC : static::SORT_ASC;

        foreach ($sortColumns['columns'] as $column) {
            list($column, $specificDirection) = $this->splitOrder($column);

            try {
                $this->query->order(
                    $this->repository->requireFilterColumn($this->target, $column),
                    $direction ? $baseDirection : ($specificDirection ?: $baseDirection)
                );
            } catch (QueryException $_) {
                Logger::info('Cannot order by column "%s" in repository "%s"', $column, $this->repository->getName());
            }
        }

        return $this;
    }

    /**
     * Extract and return the name and direction of the given sort column definition
     *
     * @param   string  $field
     *
     * @return  array               An array of two items: $columnName, $direction
     */
    protected function splitOrder($field)
    {
        $columnAndDirection = explode(' ', $field, 2);
        if (count($columnAndDirection) === 1) {
            $column = $field;
            $direction = null;
        } else {
            $column = $columnAndDirection[0];
            $direction = strtoupper($columnAndDirection[1]) === static::SORT_DESC
                ? static::SORT_DESC
                : static::SORT_ASC;
        }

        return array($column, $direction);
    }

    /**
     * Return whether any sort rules were applied to this query
     *
     * @return  bool
     */
    public function hasOrder()
    {
        return $this->query->hasOrder();
    }

    /**
     * Return the sort rules applied to this query
     *
     * @return  array
     */
    public function getOrder()
    {
        return $this->query->getOrder();
    }

    /**
     * Limit this query's results
     *
     * @param   int     $count      When to stop returning results
     * @param   int     $offset     When to start returning results
     *
     * @return  $this
     */
    public function limit($count = null, $offset = null)
    {
        $this->query->limit($count, $offset);
        return $this;
    }

    /**
     * Return whether this query does not return all available entries from its result
     *
     * @return  bool
     */
    public function hasLimit()
    {
        return $this->query->hasLimit();
    }

    /**
     * Return the limit when to stop returning results
     *
     * @return  int
     */
    public function getLimit()
    {
        return $this->query->getLimit();
    }

    /**
     * Return whether this query does not start returning results at the very first entry
     *
     * @return  bool
     */
    public function hasOffset()
    {
        return $this->query->hasOffset();
    }

    /**
     * Return the offset when to start returning results
     *
     * @return  int
     */
    public function getOffset()
    {
        return $this->query->getOffset();
    }

    /**
     * Fetch and return the first column of this query's first row
     *
     * @return  mixed
     */
    public function fetchOne()
    {
        if (! $this->hasOrder()) {
            $this->order();
        }

        $result = $this->query->fetchOne();
        if ($this->repository->providesValueConversion()) {
            $columns = $this->getColumns();
            $column = isset($columns[0]) ? $columns[0] : key($columns);
            return $this->repository->retrieveColumn($column, $result);
        }

        return $result;
    }

    /**
     * Fetch and return the first row of this query's result
     *
     * @return  object
     */
    public function fetchRow()
    {
        if (! $this->hasOrder()) {
            $this->order();
        }

        $result = $this->query->fetchRow();
        if ($this->repository->providesValueConversion()) {
            foreach ($this->getColumns() as $alias => $column) {
                if (! is_string($alias)) {
                    $alias = $column;
                }

                $result->$alias = $this->repository->retrieveColumn($alias, $result->$alias);
            }
        }

        return $result;
    }

    /**
     * Fetch and return a column of all rows of the result set as an array
     *
     * @param   int     $columnIndex    Index of the column to fetch
     *
     * @return  array
     */
    public function fetchColumn($columnIndex = 0)
    {
        if (! $this->hasOrder()) {
            $this->order();
        }

        $results = $this->query->fetchColumn($columnIndex);
        if ($this->repository->providesValueConversion()) {
            $columns = $this->getColumns();
            $aliases = array_keys($columns);
            $column = is_int($aliases[$columnIndex]) ? $columns[$columnIndex] : $aliases[$columnIndex];
            foreach ($results as & $value) {
                $value = $this->repository->retrieveColumn($column, $value);
            }
        }

        return $results;
    }

    /**
     * Fetch and return all rows of this query's result set as an array of key-value pairs
     *
     * The first column is the key, the second column is the value.
     *
     * @return  array
     */
    public function fetchPairs()
    {
        if (! $this->hasOrder()) {
            $this->order();
        }

        $results = $this->query->fetchPairs();
        if ($this->repository->providesValueConversion()) {
            $columns = $this->getColumns();
            $aliases = array_keys($columns);
            $newResults = array();
            foreach ($results as $colOneValue => $colTwoValue) {
                $colOne = $aliases[0] !== 0 ? $aliases[0] : $columns[0];
                $colTwo = count($aliases) < 2 ? $colOne : ($aliases[1] !== 1 ? $aliases[1] : $columns[1]);
                $colOneValue = $this->repository->retrieveColumn($colOne, $colOneValue);
                $newResults[$colOneValue] = $this->repository->retrieveColumn($colTwo, $colTwoValue);
            }

            $results = $newResults;
        }

        return $results;
    }

    /**
     * Fetch and return all results of this query
     *
     * @return  array
     */
    public function fetchAll()
    {
        if (! $this->hasOrder()) {
            $this->order();
        }

        $results = $this->query->fetchAll();
        if ($this->repository->providesValueConversion()) {
            $columns = $this->getColumns();
            foreach ($results as $row) {
                foreach ($columns as $alias => $column) {
                    if (! is_string($alias)) {
                        $alias = $column;
                    }

                    $row->$alias = $this->repository->retrieveColumn($alias, $row->$alias);
                }
            }
        }

        return $results;
    }

    /**
     * Count all results of this query
     *
     * @return  int
     */
    public function count()
    {
        return $this->query->count();
    }
}
