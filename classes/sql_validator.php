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
 * SQL security validator.
 *
 * @package    local_apiquery
 * @copyright  2026 CESW <cesarmachado443@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_apiquery;

defined('MOODLE_INTERNAL') || die();

/**
 * Validates that a SQL query is safe before it is saved.
 *
 * Allowed without warning  : SELECT
 * Allowed with warning     : INSERT, UPDATE, DELETE, REPLACE
 * Always blocked           : DROP, TRUNCATE, ALTER, CREATE,
 *                            GRANT, REVOKE, EXEC, SLEEP, etc.
 */
class sql_validator {

    /**
     * DML operations that are allowed but trigger a warning.
     * The admin must explicitly confirm before saving.
     */
    private const WARNING_KEYWORDS = [
        'DELETE', 'UPDATE', 'INSERT', 'REPLACE',
    ];

    /**
     * Always-forbidden operations — destructive or system-level.
     * Cannot be saved under any circumstances.
     * Note: keywords with spaces use \s+ to match newlines and multiple spaces.
     */
    private const FORBIDDEN_KEYWORDS = [
        'DROP', 'TRUNCATE', 'CREATE', 'ALTER', 'RENAME',
        'GRANT', 'REVOKE', 'EXEC', 'EXECUTE', 'CALL',
        'INTO\s+OUTFILE', 'LOAD\s+DATA', 'BENCHMARK', 'SLEEP',
    ];

    /**
     * Tables containing sensitive system data that must never be exposed.
     */
    private const FORBIDDEN_TABLES = [
        'config',
        'config_plugins',
        'external_tokens',
        'user_password_history',
        'user_private_key',
        'sessions',
        'oauth2_access_token',
    ];

    /**
     * Validates the SQL and returns errors, warnings, and detected placeholders.
     *
     * - errors       → blocking, query cannot be saved
     * - warnings     → informational, query can be saved with explicit confirmation
     * - placeholders → unique :param names found in the SQL
     *
     * @param  string $sql
     * @return array  ['errors' => [], 'warnings' => [], 'placeholders' => []]
     */
    public static function validate(string $sql): array {
        $errors   = [];
        $warnings = [];

        $sql_trimmed = trim($sql);

        // 1. Check for fully forbidden keywords.
        // Keywords containing \s+ are already regex expressions — do not use preg_quote on them.
        foreach (self::FORBIDDEN_KEYWORDS as $keyword) {
            $pattern = '/\b' . $keyword . '\b/i';
            if (preg_match($pattern, $sql_trimmed)) {
                // Para el mensaje, normalizar el keyword (quitar \s+) para mostrarlo legible.
                $display_keyword = preg_replace('/\\\\s\+/', ' ', $keyword);
                $errors[] = get_string('error_forbidden_keyword', 'local_apiquery', $display_keyword);
            }
        }

        // 2. Check for warning-level keywords (DML allowed but dangerous).
        foreach (self::WARNING_KEYWORDS as $keyword) {
            $pattern = '/\b' . preg_quote($keyword, '/') . '\b/i';
            if (preg_match($pattern, $sql_trimmed)) {
                $warnings[] = get_string('warning_keyword_dml', 'local_apiquery', $keyword);
            }
        }

        // 3. Check for forbidden tables using XMLDB {tablename} notation.
        // Regex is used instead of stripos to avoid substring false positives
        // (e.g. {local_config_backup} must not be blocked just because it contains "config").
        foreach (self::FORBIDDEN_TABLES as $table) {
            $pattern = '/\{' . preg_quote($table, '/') . '\}/i';
            if (preg_match($pattern, $sql_trimmed)) {
                $errors[] = get_string('error_forbidden_table', 'local_apiquery', $table);
            }
        }

        // 4. Disallow multiple statements separated by semicolons.
        $statements = array_filter(array_map('trim', explode(';', $sql_trimmed)), fn($s) => !empty($s));
        if (count($statements) > 1) {
            $errors[] = get_string('error_multiple_statements', 'local_apiquery');
        }

        // 5. Reject positional placeholders (?).
        if (preg_match('/\?(?!\?)/', $sql_trimmed)) {
            $errors[] = get_string('error_positional_params', 'local_apiquery');
        }

        // 6. Detect unique placeholders in the SQL.
        // If :since appears twice it is still one parameter — same value in both positions.
        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql_trimmed, $matches);
        $placeholders = array_unique($matches[1] ?? []);

        return [
            'errors'       => $errors,
            'warnings'     => $warnings,
            'placeholders' => $placeholders,
        ];
    }

    /**
     * Verifies that the placeholders declared as parameters
     * match exactly those used in the SQL.
     *
     * @param  string $sql
     * @param  array  $declared_params  [{name, type, required, default}]
     * @return array  consistency errors
     */
    public static function validate_params_consistency(string $sql, array $declared_params): array {
        $errors = [];

        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches);
        $placeholders_in_sql = array_unique($matches[1] ?? []);
        $declared_names     = array_column($declared_params, 'name');

        foreach (array_diff($placeholders_in_sql, $declared_names) as $name) {
            $errors[] = get_string('error_param_undeclared', 'local_apiquery', $name);
        }

        foreach (array_diff($declared_names, $placeholders_in_sql) as $name) {
            $errors[] = get_string('error_param_notinquery', 'local_apiquery', $name);
        }

        return $errors;
    }
}
