<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * External webservice functions.
 *
 * @package    local_apiquery
 * @copyright  2026 CESW <cesarmachado443@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_apiquery;

defined('MOODLE_INTERNAL') || die();

// externallib.php must always be loaded explicitly.
// - Moodle < 4.2 : defines external_api, external_function_parameters, external_value, etc.
// - Moodle 4.2+  : defines backward-compatible global aliases for those same classes
//                  (e.g. external_function_parameters → core_external\external_function_parameters).
// Without this require, the global-namespace aliases are unavailable on 4.2+ and the
// classes themselves are unavailable on 4.1, causing "Class not found" errors at runtime.
global $CFG;
require_once($CFG->libdir . '/externallib.php');

// For Moodle 4.1 and earlier, \core_external\external_api does not exist.
// Alias the legacy \external_api so the class declaration below works
// unchanged across all supported versions (4.1 → 5.x).
if (!class_exists('\core_external\external_api')) {
    class_alias('\external_api', '\core_external\external_api');
}

/**
 * Main webservice class.
 * Exposes admin-configured SQL queries as REST endpoints.
 *
 * Compatible with Moodle 4.1+ (shim for \core_external\external_api).
 */
class external extends \core_external\external_api {

    // ─────────────────────────────────────────────────────────────
    // FUNCTION: execute_query
    // ─────────────────────────────────────────────────────────────

    public static function execute_query_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'shortname' => new \external_value(
                PARAM_ALPHANUMEXT,
                get_string('ws_shortname_desc', 'local_apiquery'),
                VALUE_REQUIRED
            ),
            'params' => new \external_multiple_structure(
                new \external_single_structure([
                    'name'  => new \external_value(PARAM_ALPHANUMEXT, get_string('ws_param_name_desc',  'local_apiquery')),
                    'value' => new \external_value(PARAM_RAW,         get_string('ws_param_value_desc', 'local_apiquery')),
                ]),
                get_string('ws_params_desc', 'local_apiquery'),
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    public static function execute_query(string $shortname, array $params = []): array {
        global $DB, $USER;

        $validatedParams = self::validate_parameters(
            self::execute_query_parameters(),
            ['shortname' => $shortname, 'params' => $params]
        );

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/apiquery:execute', $context);

        $startTime = microtime(true);
        $logEntry  = ['query_id' => null, 'userid' => $USER->id, 'timecreated' => time()];

        try {
            $queryRecord = $DB->get_record(
                'local_apiquery_queries',
                ['shortname' => $validatedParams['shortname'], 'enabled' => 1],
                '*',
                MUST_EXIST
            );

            $logEntry['query_id'] = $queryRecord->id;

            $sqlParams = self::build_sql_params($queryRecord, $validatedParams['params']);

            // Expand duplicates: if :since appears twice in the SQL, Moodle
            // needs 2 entries in the params array. expand_duplicate_params renames
            // the 2nd occurrence to :since_dup2 in the SQL and duplicates the value.
            $expanded  = self::expand_duplicate_params($queryRecord->sqlquery, $sqlParams);
            $finalSql  = $expanded['sql'];
            $finalParams = $expanded['params'];

            $logEntry['params_used'] = json_encode($finalParams);

            // Detect if query is DML (INSERT/UPDATE/DELETE/REPLACE) or SELECT.
            $sqlTrimmed = ltrim($finalSql);
            $isDml = (bool) preg_match('/^(INSERT|UPDATE|DELETE|REPLACE)\b/i', $sqlTrimmed);

            $rows = [];
            if ($isDml) {
                // DML: use execute() — returns true/false, not rows.
                $DB->execute($finalSql, $finalParams);
                $rows = [['affected' => get_string('dml_success', 'local_apiquery')]];
            } else {
                // SELECT: use get_recordset_sql which does not require a unique first column.
                $recordset = $DB->get_recordset_sql($finalSql, $finalParams);
                foreach ($recordset as $record) {
                    $rows[] = (array) $record;
                }
                $recordset->close();
            }

            // Serialize to [{key, value}] format required by Moodle webservices.
            $serializedRows = self::serialize_rows($rows);

            $executionMs = (int)((microtime(true) - $startTime) * 1000);
            $logEntry['rows_returned'] = count($rows);
            $logEntry['execution_ms']  = $executionMs;
            self::write_log($logEntry);

            return [
                'success'      => true,
                'shortname'    => $queryRecord->shortname,
                'rows_count'   => count($rows),
                'execution_ms' => $executionMs,
                'rows'         => $serializedRows,
                'error'        => '',
            ];

        } catch (\Exception $e) {
            $logEntry['error'] = $e->getMessage();
            self::write_log($logEntry);

            return [
                'success'      => false,
                'shortname'    => $validatedParams['shortname'],
                'rows_count'   => 0,
                'execution_ms' => 0,
                'rows'         => [],
                'error'        => get_string('error_query_execute', 'local_apiquery', $e->getMessage()),
            ];
        }
    }

    public static function execute_query_returns(): \external_single_structure {
        return new \external_single_structure([
            'success'      => new \external_value(PARAM_BOOL, get_string('ws_ret_success',   'local_apiquery')),
            'shortname'    => new \external_value(PARAM_TEXT, get_string('ws_ret_shortname', 'local_apiquery')),
            'rows_count'   => new \external_value(PARAM_INT,  get_string('ws_ret_rows_count','local_apiquery')),
            'execution_ms' => new \external_value(PARAM_INT,  get_string('ws_ret_exec_ms',   'local_apiquery')),
            'rows'         => new \external_multiple_structure(
                new \external_multiple_structure(
                    new \external_single_structure([
                        'key'   => new \external_value(PARAM_TEXT, get_string('ws_ret_field_key',   'local_apiquery')),
                        'value' => new \external_value(PARAM_RAW,  get_string('ws_ret_field_value', 'local_apiquery'), VALUE_OPTIONAL, null),
                    ])
                ),
                get_string('ws_ret_rows', 'local_apiquery')
            ),
            'error' => new \external_value(PARAM_TEXT, get_string('ws_ret_error', 'local_apiquery'), VALUE_OPTIONAL, ''),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // FUNCTION: list_queries
    // ─────────────────────────────────────────────────────────────

    public static function list_queries_parameters(): \external_function_parameters {
        return new \external_function_parameters([]);
    }

    public static function list_queries(): array {
        global $DB;

        self::validate_parameters(self::list_queries_parameters(), []);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/apiquery:execute', $context);

        $records = $DB->get_records('local_apiquery_queries', ['enabled' => 1], 'shortname ASC');

        $result = [];
        foreach ($records as $record) {
            $declaredParams = json_decode($record->parameters ?? '[]', true) ?: [];
            $result[] = [
                'shortname'   => $record->shortname,
                'displayname' => $record->displayname,
                'description' => $record->description ?? '',
                'parameters'  => array_map(fn($p) => [
                    'name'     => $p['name'],
                    'type'     => $p['type'],
                    'required' => (bool)($p['required'] ?? false),
                    'default'  => $p['default'] ?? '',
                ], $declaredParams),
            ];
        }

        return $result;
    }

    public static function list_queries_returns(): \external_multiple_structure {
        return new \external_multiple_structure(
            new \external_single_structure([
                'shortname'   => new \external_value(PARAM_TEXT, get_string('ws_list_shortname',      'local_apiquery')),
                'displayname' => new \external_value(PARAM_TEXT, get_string('displayname',            'local_apiquery')),
                'description' => new \external_value(PARAM_TEXT, get_string('description',            'local_apiquery')),
                'parameters'  => new \external_multiple_structure(
                    new \external_single_structure([
                        'name'     => new \external_value(PARAM_TEXT, get_string('ws_param_name_desc',    'local_apiquery')),
                        'type'     => new \external_value(PARAM_TEXT, get_string('ws_list_param_type',    'local_apiquery')),
                        'required' => new \external_value(PARAM_BOOL, get_string('ws_list_param_required','local_apiquery')),
                        'default'  => new \external_value(PARAM_RAW,  get_string('param_col_default',     'local_apiquery'), VALUE_OPTIONAL, ''),
                    ])
                ),
            ])
        );
    }

    // ─────────────────────────────────────────────────────────────
    // PRIVATE METHODS
    // ─────────────────────────────────────────────────────────────

    /**
     * Builds the SQL params array by resolving values and applying type casting.
     *
     * Problem: Moodle converts :placeholders to positional ? internally.
     * If :since appears twice in the SQL, it expects 2 entries in the params array.
     * Solution: rename duplicates in the SQL before executing.
     *   :since (1st occurrence) → stays as :since
     *   :since (2nd occurrence) → renamed to :since_dup2 in SQL + params
     *   :since (3rd occurrence) → renamed to :since_dup3
     * This way the admin declares :since once and the value is repeated automatically.
     */
    private static function build_sql_params(\stdClass $queryRecord, array $sentParams): array {
        $declared = json_decode($queryRecord->parameters ?? '[]', true) ?: [];

        // Index sent values by name.
        $sentByName = [];
        foreach ($sentParams as $p) {
            $sentByName[$p['name']] = $p['value'];
        }

        // Resolve value and type for each declared parameter.
        $resolvedValues = [];
        foreach ($declared as $decl) {
            $name     = $decl['name'];
            $type     = $decl['type'] ?? 'text';
            $required = (bool)($decl['required'] ?? false);
            $default  = $decl['default'] ?? null;

            if (array_key_exists($name, $sentByName)) {
                $value = $sentByName[$name];
            } elseif (!$required && $default !== null) {
                $value = $default;
            } elseif ($required) {
                throw new \invalid_parameter_exception(
                    get_string('error_required_param_missing', 'local_apiquery', $name)
                );
            } else {
                $value = null;
            }

            $resolvedValues[$name] = match($type) {
                'int'   => (int) $value,
                'float' => (float) $value,
                'bool'  => (bool) $value,
                default => (string) $value,
            };
        }

        return $resolvedValues;
    }

    /**
     * Expands the SQL and params array to handle duplicate placeholders.
     * Moodle converts :name to positional ? — if :since appears twice
     * it needs 2 entries in the params array.
     * This method renames the 2nd, 3rd... occurrence in the SQL and duplicates the value.
     *
     * Input:  SQL with "WHERE x > :since AND y IN (SELECT ... WHERE z > :since)"
     * Output: SQL with "WHERE x > :since AND y IN (SELECT ... WHERE z > :since_dup2)"
     *         params: ['since' => 1738000000, 'since_dup2' => 1738000000]
     */
    private static function expand_duplicate_params(string $sql, array $params): array {
        $occurrenceCount = [];
        $expandedParams  = [];

        // Find all :placeholder occurrences in order of appearance.
        // preg_replace_callback processes each occurrence in document order.
        $expandedSql = preg_replace_callback(
            '/:([a-zA-Z_][a-zA-Z0-9_]*)/',
            function ($matches) use (&$occurrenceCount, &$expandedParams, $params) {
                $name = $matches[1];

                if (!isset($occurrenceCount[$name])) {
                    $occurrenceCount[$name] = 1;
                } else {
                    $occurrenceCount[$name]++;
                }

                $count = $occurrenceCount[$name];

                if ($count === 1) {
                    // First occurrence: keep original placeholder and value.
                    if (array_key_exists($name, $params)) {
                        $expandedParams[$name] = $params[$name];
                    }
                    return ':' . $name;
                } else {
                    // Duplicate occurrence: rename placeholder and copy the value.
                    $dupName = $name . '_dup' . $count;
                    if (array_key_exists($name, $params)) {
                        $expandedParams[$dupName] = $params[$name]; // same value
                    }
                    return ':' . $dupName;
                }
            },
            $sql
        );

        // Add params that were not in the SQL (should not happen, but kept as a safety net).
        foreach ($params as $key => $value) {
            if (!isset($expandedParams[$key])) {
                $expandedParams[$key] = $value;
            }
        }

        return ['sql' => $expandedSql, 'params' => $expandedParams];
    }

    /**
     * Converts rows to the [{key, value}] format required by Moodle webservices.
     * Moodle does not allow arbitrary associative arrays in responses, hence key/value pairs.
     */
    private static function serialize_rows(array $rows): array {
        return array_map(function ($row) {
            $pairs = [];
            foreach ($row as $key => $value) {
                $pairs[] = [
                    'key'   => (string) $key,
                    'value' => $value !== null ? (string) $value : null,
                ];
            }
            return $pairs;
        }, $rows);
    }

    /**
     * Saves an execution record to local_apiquery_logs.
     */
    private static function write_log(array $entry): void {
        global $DB;
        try {
            $record                = new \stdClass();
            $record->query_id      = $entry['query_id'] ?? null;
            $record->userid        = $entry['userid'] ?? null;
            $record->params_used   = $entry['params_used'] ?? null;
            $record->rows_returned = $entry['rows_returned'] ?? null;
            $record->execution_ms  = $entry['execution_ms'] ?? null;
            $record->timecreated   = $entry['timecreated'] ?? time();
            $record->error         = $entry['error'] ?? null;
            $DB->insert_record('local_apiquery_logs', $record);
        } catch (\Exception $e) {
            // Do not fail the main response due to a logging error.
        }
    }
}
