<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * Database query builder. See [Query Builder](/database/query/builder) for usage and examples.
 *
 * @package    Kohana/Database
 * @category   Query
 * @author     Kohana Team
 * @copyright  (c) 2008-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */
abstract class Kohana_Database_Query_Builder extends Database_Query {

	/**
	 * @var  array  operators that where(), having() and on() accept, in upper
	 *              case. The operator goes into the SQL unquoted. Add the
	 *              operators of your database here, or pass a Database_Expression.
	 */
	public static $operators = array(
		'=', '==', '!=', '<>', '<', '>', '<=', '>=', '<=>',
		'LIKE', 'NOT LIKE', 'LIKE BINARY', 'ILIKE', 'NOT ILIKE',
		'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'IS', 'IS NOT',
		'IS DISTINCT FROM', 'IS NOT DISTINCT FROM', 'EXISTS', 'NOT EXISTS',
		'REGEXP', 'NOT REGEXP', 'REGEXP BINARY', 'RLIKE', 'NOT RLIKE', 'SOUNDS LIKE',
		'GLOB', 'NOT GLOB', 'MATCH', 'SIMILAR TO', 'NOT SIMILAR TO',
		'~', '~*', '!~', '!~*', '@@', '@>', '<@', '&&', '&', '|', '^',
	);

	/**
	 * Compiles an array of JOIN statements into an SQL partial.
	 *
	 * @param   object  $db     Database instance
	 * @param   array   $joins  join statements
	 * @return  string
	 */
	protected function _compile_join(Database $db, array $joins)
	{
		$statements = array();

		foreach ($joins as $join)
		{
			// Compile each of the join statements
			$statements[] = $join->compile($db);
		}

		return implode(' ', $statements);
	}

	/**
	 * Compiles an array of conditions into an SQL partial. Used for WHERE
	 * and HAVING.
	 *
	 * @param   object  $db          Database instance
	 * @param   array   $conditions  condition statements
	 * @return  string
	 */
	protected function _compile_conditions(Database $db, array $conditions)
	{
		$last_condition = NULL;

		$sql = '';
		foreach ($conditions as $group)
		{
			// Process groups of conditions
			foreach ($group as $logic => $condition)
			{
				if ($condition === '(')
				{
					if ( ! empty($sql) AND $last_condition !== '(')
					{
						// Include logic operator
						$sql .= ' '.$logic.' ';
					}

					$sql .= '(';
				}
				elseif ($condition === ')')
				{
					$sql .= ')';
				}
				else
				{
					if ( ! empty($sql) AND $last_condition !== '(')
					{
						// Add the logic operator
						$sql .= ' '.$logic.' ';
					}

					// Split the condition
					list($column, $op, $value) = $condition;

					if ($value === NULL)
					{
						if ($op === '=')
						{
							// Convert "val = NULL" to "val IS NULL"
							$op = 'IS';
						}
						elseif ($op === '!=' OR $op === '<>')
						{
							// Convert "val != NULL" to "valu IS NOT NULL"
							$op = 'IS NOT';
						}
					}

					$op = $this->_compile_operator($db, $op);

					if (($op === 'BETWEEN' OR $op === 'NOT BETWEEN') AND is_array($value))
					{
						// BETWEEN always has exactly two arguments
						list($min, $max) = $value;

						if ((is_string($min) AND array_key_exists($min, $this->_parameters)) === FALSE)
						{
							// Quote the value, it is not a parameter
							$min = $db->quote($min);
						}

						if ((is_string($max) AND array_key_exists($max, $this->_parameters)) === FALSE)
						{
							// Quote the value, it is not a parameter
							$max = $db->quote($max);
						}

						// Quote the min and max value
						$value = $min.' AND '.$max;
					}
					elseif ((is_string($value) AND array_key_exists($value, $this->_parameters)) === FALSE)
					{
						// Quote the value, it is not a parameter
						$value = $db->quote($value);
					}

					if ($column)
					{
						if (is_array($column))
						{
							// Use the column name
							$column = $db->quote_identifier(reset($column));
						}
						else
						{
							// Apply proper quoting to the column
							$column = $db->quote_column($column);
						}
					}

					// Append the statement to the query
					$sql .= trim($column.' '.$op.' '.$value);
				}

				$last_condition = $condition;
			}
		}

		return $sql;
	}

	/**
	 * Compiles an array of set values into an SQL partial. Used for UPDATE.
	 *
	 * @param   object  $db      Database instance
	 * @param   array   $values  updated values
	 * @return  string
	 */
	protected function _compile_set(Database $db, array $values)
	{
		$set = array();
		foreach ($values as $group)
		{
			// Split the set
			list ($column, $value) = $group;

			// Quote the column name
			$column = $db->quote_column($column);

			if ((is_string($value) AND array_key_exists($value, $this->_parameters)) === FALSE)
			{
				// Quote the value, it is not a parameter
				$value = $db->quote($value);
			}

			$set[$column] = $column.' = '.$value;
		}

		return implode(', ', $set);
	}

	/**
	 * Compiles an array of GROUP BY columns into an SQL partial.
	 *
	 * @param   object  $db       Database instance
	 * @param   array   $columns
	 * @return  string
	 */
	protected function _compile_group_by(Database $db, array $columns)
	{
		$group = array();

		foreach ($columns as $column)
		{
			if (is_array($column))
			{
				// Use the column alias
				$column = $db->quote_identifier(end($column));
			}
			else
			{
				// Apply proper quoting to the column
				$column = $db->quote_column($column);
			}

			$group[] = $column;
		}

		return 'GROUP BY '.implode(', ', $group);
	}

	/**
	 * Compiles an array of ORDER BY statements into an SQL partial.
	 *
	 * @param   object  $db       Database instance
	 * @param   array   $columns  sorting columns
	 * @return  string
	 */
	protected function _compile_order_by(Database $db, array $columns)
	{
		$sort = array();
		foreach ($columns as $group)
		{
			list ($column, $direction) = $group;

			if (is_array($column))
			{
				// Use the column alias
				$column = $db->quote_identifier(end($column));
			}
			else
			{
				// Apply proper quoting to the column
				$column = $db->quote_column($column);
			}

			$sort[] = $column.$this->_compile_direction($db, $direction);
		}

		return 'ORDER BY '.implode(', ', $sort);
	}

	/**
	 * Compiles a where(), having() or on() operator. See
	 * [Database_Query_Builder::$operators] for the valid operators. A
	 * comparison operator can have ANY, ALL or SOME after it.
	 *
	 * @param   object  $db  Database instance
	 * @param   mixed   $op  operator string or Database_Expression
	 * @return  string  upper case operator, or "" for an empty operator
	 * @throws  Database_Exception
	 */
	protected function _compile_operator(Database $db, $op)
	{
		if ($op instanceof Database_Expression)
			return $op->compile($db);

		if ($op === NULL OR $op === '')
			return '';

		if (is_string($op))
		{
			$op = strtoupper(trim(preg_replace('/\s+/', ' ', $op)));

			if (in_array($op, array_map('strtoupper', Database_Query_Builder::$operators), TRUE)
				OR preg_match('/^(?:=|!=|<>|<|>|<=|>=) (?:ANY|ALL|SOME)$/D', $op))
			{
				return $op;
			}
		}

		throw new Database_Exception('Invalid SQL operator :op', array(
			':op' => is_string($op) ? '"'.$op.'"' : gettype($op),
		));
	}

	/**
	 * Compiles an ORDER BY direction. Only ASC and DESC are valid, because
	 * the direction goes into the SQL unquoted (CVE-2019-8979). Use a
	 * [Database_Expression] for other raw SQL, for example "DESC NULLS LAST".
	 *
	 * @param   object  $db         Database instance
	 * @param   mixed   $direction  NULL, "ASC", "DESC" or Database_Expression
	 * @return  string  the direction with a leading space, or ""
	 * @throws  Database_Exception
	 */
	protected function _compile_direction(Database $db, $direction)
	{
		if ($direction instanceof Database_Expression)
			return ' '.$direction->compile($db);

		if ($direction === NULL OR $direction === '')
			return '';

		if (is_string($direction))
		{
			$direction = strtoupper(trim($direction));

			if ($direction === 'ASC' OR $direction === 'DESC')
				return ' '.$direction;
		}

		throw new Database_Exception('Invalid ORDER BY direction, use ASC or DESC');
	}

	/**
	 * Reset the current builder status.
	 *
	 * @return  $this
	 */
	abstract public function reset();

} // End Database_Query_Builder
