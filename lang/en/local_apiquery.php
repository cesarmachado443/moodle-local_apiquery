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
 * English language strings.
 *
 * @package    local_apiquery
 * @copyright  2026 CESW <cesarmachado443@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

// ── General UI ────────────────────────────────────────────────────────────────
$string['pluginname']       = 'Api Query';
$string['manage_queries']   = 'Manage API Queries';
$string['execution_logs']   = 'Execution Logs';
$string['new_query']        = 'New Query';
$string['edit_query']       = 'Edit Query';
$string['create']           = 'Create';
$string['test']             = 'Test';
$string['enable']           = 'Enable';
$string['disable']          = 'Disable';
$string['active']           = 'Active';
$string['inactive']         = 'Inactive';
$string['no_queries']       = 'No queries configured yet. Click "New Query" to create one.';
$string['shortname']        = 'Shortname';
$string['displayname']      = 'Display Name';
$string['description']      = 'Description';
$string['parameters']       = 'Parameters';
$string['status']           = 'Status';
$string['executions']       = 'Executions';
$string['last_execution']   = 'Last Used';
$string['avg_time']         = 'Avg Time';
$string['actions']          = 'Actions';
$string['query_created']    = 'Query created successfully.';
$string['query_updated']    = 'Query updated successfully.';
$string['query_deleted']    = 'Query deleted.';
$string['query_enabled']    = 'Query enabled.';
$string['query_disabled']   = 'Query disabled.';
$string['confirm_delete']   = 'Are you sure you want to delete this query and its logs?';
$string['api_usage']        = 'API Usage';
$string['api_usage_desc']   = 'Call any configured query using the following endpoint:';

// ── Capabilities ──────────────────────────────────────────────────────────────
$string['apiquery:execute'] = 'Execute custom API queries';
$string['apiquery:manage']  = 'Manage custom API queries';

// ── Privacy API ───────────────────────────────────────────────────────────────
$string['privacy:metadata:local_apiquery_logs']             = 'Execution logs of API queries called via webservice token.';
$string['privacy:metadata:local_apiquery_logs:userid']      = 'The ID of the user whose webservice token was used to call the query.';
$string['privacy:metadata:local_apiquery_logs:params_used'] = 'The parameters sent in the API call (may include course IDs or timestamps).';
$string['privacy:metadata:local_apiquery_logs:timecreated'] = 'The date and time when the API call was made.';

$string['privacy:metadata:local_apiquery_queries']            = 'Custom SQL queries created by administrators.';
$string['privacy:metadata:local_apiquery_queries:createdby']  = 'The ID of the user who created this query.';
$string['privacy:metadata:local_apiquery_queries:timecreated'] = 'The date and time when the query was created.';

// ── DML warning (confirmation screen) ─────────────────────────────────────────
$string['warning_dml']          = 'This query contains data modification operations (INSERT/UPDATE/DELETE/REPLACE). Use with caution.';
$string['warning_dml_title']    = '⚠️ This query contains data-modifying operations in Moodle.';
$string['warning_dml_review']   = 'Review the warnings before continuing:';
$string['confirm_dml']          = 'I understand the risks — Save anyway';
$string['back_to_edit']         = '← Back and review';

// ── Validation errors (edit.php) ──────────────────────────────────────────────
$string['error_shortname_required']   = 'The shortname is required.';
$string['error_displayname_required'] = 'The display name is required.';
$string['error_sql_required']         = 'The SQL query is required.';
$string['error_shortname_duplicate']  = 'The shortname \'{$a}\' is already in use by another query.';

// ── Form sections (edit.php) ───────────────────────────────────────────────────
$string['section_identification'] = '1. Function identification';
$string['section_sql']            = '2. SQL Query';
$string['section_params']         = '3. Function parameters';

// ── Form field labels (edit.php) ──────────────────────────────────────────────
$string['field_shortname_hint']    = '(no spaces, only letters/numbers/underscore — will be the function name in the API)';
$string['field_shortname_apicall'] = 'Called as: <code>local_apiquery_execute_query</code> with parameter <code>shortname=<strong>this_value</strong></code>';
$string['field_enabled_label']     = 'Active (available in the API)';

// ── SQL editor (edit.php) ──────────────────────────────────────────────────────
$string['sql_security_title'] = '⚠️ Security rules:';
$string['sql_security_desc']  = 'Only <code>SELECT</code> is allowed. Use <strong>named</strong> parameters (<code>:name</code>), not positional <code>?</code>. The <code>mdl_</code> prefix is applied automatically — write table names without prefix inside braces: <code>{grade_grades}</code>.';

// ── Parameter table headers (edit.php) ────────────────────────────────────────
$string['param_col_name']     = 'Parameter name';
$string['param_col_type']     = 'Type';
$string['param_col_required'] = 'Required?';
$string['param_col_default']  = 'Default value';
$string['add_param']          = '+ Add parameter';

// ── Form input placeholder examples (edit.php) ───────────────────────────────
$string['placeholder_shortname_ex']   = 'Ex: get_grades_since';
$string['placeholder_displayname_ex'] = 'Ex: Grades modified since date';
$string['placeholder_description_ex'] = 'What this query returns and when to use it...';
$string['placeholder_sql_ex']         = 'SELECT gg.userid, gg.finalgrade, gg.timemodified, gi.itemmodule
FROM {grade_grades} gg
JOIN {grade_items} gi ON gi.id = gg.itemid
WHERE gg.timemodified > :since
AND gi.courseid IN (:courseids)';
$string['placeholder_no_default']     = 'Empty = no default';
$string['placeholder_param_name']     = 'param_name';

// ── JS placeholder hints (edit.php) ───────────────────────────────────────────
$string['hint_placeholders_detected'] = 'Placeholders detected: ';
$string['hint_placeholder_repeated']  = 'appears more than once — the same value will be used in all positions';
$string['hint_declare_once']          = '— declare each one ONCE as a parameter below.';

// ── Test page (test.php) ──────────────────────────────────────────────────────
$string['test_title']           = 'Test: {$a}';
$string['test_heading']         = '🧪 Test: {$a}';
$string['back_to_list']         = '← Back to list';
$string['test_params_card']     = 'Test parameters';
$string['no_params_declared']   = 'This query has no declared parameters.';
$string['param_required_label'] = 'Required';
$string['param_optional_label'] = 'Optional';
$string['execute_btn']          = '▶️ Execute';
$string['test_success']         = 'Query executed in {$a->ms} ms — {$a->rows} row(s) returned.';
$string['no_rows_returned']     = 'The query returned no rows with the given parameters.';
$string['showing_first_rows']   = 'Showing the first 100 rows of {$a} total.';
$string['sql_card_title']       = 'Query SQL';
$string['dml_success']          = 'DML executed successfully';
$string['error_param_required'] = 'Required parameter \'{$a}\' has no value.';

// ── Logs page (logs.php) ──────────────────────────────────────────────────────
$string['stat_total']   = 'Total executions';
$string['stat_avgtime'] = 'Average time';
$string['stat_rows']    = 'Rows returned';
$string['stat_errors']  = 'Errors';
$string['no_logs_yet']  = 'No executions recorded yet.';
$string['col_function'] = 'Function';
$string['col_params']   = 'Parameters used';
$string['col_rows']     = 'Rows';
$string['col_time']     = 'Time';
$string['col_status']   = 'Status';
$string['col_date']     = 'Date';

// ── Export / Import ───────────────────────────────────────────────────────────
$string['export_queries']            = 'Export queries';
$string['export_json_encode_error']  = 'JSON encoding error: {$a}';
$string['export_select_title']       = 'Select queries to export';
$string['export_select_all']         = 'Select all';
$string['export_deselect_all']       = 'Deselect all';
$string['export_selected_btn']       = 'Export selected';
$string['export_none_selected']      = 'Select at least one query to export.';
$string['export_col_select']         = 'Export';
$string['import_col_select']         = 'Import';
$string['export_all']                = 'Export all';
$string['import_queries']            = 'Import queries';
$string['import_file_label']         = 'Select export file (.json)';
$string['import_preview_title']      = 'Import preview';
$string['import_confirm_btn']        = 'Confirm import';
$string['import_option_skip']        = 'Skip — keep the existing query';
$string['import_option_overwrite']   = 'Overwrite — replace with the imported version';
$string['import_conflict_mode']      = 'When a shortname already exists:';
$string['import_success']            = '{$a} query/queries imported successfully.';
$string['import_skipped']            = '{$a} query/queries skipped (shortname already exists).';
$string['import_overwritten']        = '{$a} query/queries overwritten.';
$string['import_invalid_file']       = 'Invalid file. Please upload a valid JSON export from this plugin.';
$string['import_json_decode_error']  = 'Invalid JSON file. Error: {$a}';
$string['import_structure_error']    = 'Invalid file structure. {$a}';
$string['import_debug_structure']    = 'is_array={$a->is_array}, has_meta={$a->has_meta}, has_queries={$a->has_queries}, plugin={$a->plugin}';
$string['import_debug_yes']          = 'yes';
$string['import_debug_no']           = 'no';
$string['import_debug_missing']      = 'missing';
$string['import_no_queries']         = 'The file contains no queries.';
$string['import_upload_error_code']  = 'Upload error code: {$a}';
$string['import_upload_no_file']     = 'No file uploaded';
$string['import_upload_ini_size']    = 'File exceeds upload_max_filesize';
$string['import_upload_form_size']   = 'File exceeds MAX_FILE_SIZE';
$string['import_upload_tmp']         = 'Tmp: {$a}';
$string['import_upload_tmp_empty']   = 'empty';
$string['import_upload_tmp_present'] = 'present';
$string['import_upload_valid']       = 'Valid upload: {$a}';
$string['import_upload_valid_yes']   = 'yes';
$string['import_upload_valid_no']    = 'no';
$string['import_source_info']        = 'Source: {$a->site} &mdash; Moodle {$a->release} &mdash; Exported on {$a->date}';
$string['import_col_status']         = 'Import status';
$string['import_status_new']         = 'New';
$string['import_status_exists']      = 'Already exists';
$string['warning_version_mismatch']         = 'Moodle version mismatch';
$string['warning_version_mismatch_desc']    = 'This file was exported from Moodle {$a->exported} but this site runs Moodle {$a->current}. Between versions, Moodle may rename tables, move columns, or change data structures. Review each imported query carefully and test it before enabling it in production.';
$string['warning_version_major_mismatch']      = 'Major version difference detected';
$string['warning_version_major_mismatch_desc'] = 'The file was exported from Moodle {$a->exported} and this site runs Moodle {$a->current}. This is a significant version difference. SQL queries may reference tables or columns that no longer exist or have been restructured. Strongly recommended: import as disabled and test each query individually.';

// ── Webservice API runtime messages (external.php) ───────────────────────────
$string['error_query_execute']           = 'Error executing query: {$a}';
$string['error_required_param_missing']  = 'Required parameter \'{$a}\' was not sent.';
$string['error_sql_validation_failed']   = 'SQL validation failed at execution time:';

// ── Webservice parameter/return descriptions (external.php) ──────────────────
$string['ws_shortname_desc']     = 'Shortname of the query to execute';
$string['ws_param_name_desc']    = 'Parameter name';
$string['ws_param_value_desc']   = 'Parameter value';
$string['ws_params_desc']        = 'Query parameters';
$string['ws_ret_success']        = 'Whether the execution was successful';
$string['ws_ret_shortname']      = 'Name of the executed query';
$string['ws_ret_rows_count']     = 'Number of rows returned';
$string['ws_ret_exec_ms']        = 'Execution time in milliseconds';
$string['ws_ret_field_key']      = 'Field name';
$string['ws_ret_field_value']    = 'Field value';
$string['ws_ret_rows']           = 'Result rows — each row is an array of {key, value}';
$string['ws_ret_error']          = 'Error message if success=false';
$string['ws_list_shortname']     = 'Query identifier';
$string['ws_list_param_type']    = 'Type: int, text, float, bool';
$string['ws_list_param_required']= 'Whether required';

// ── SQL Validator messages (sql_validator.php) ────────────────────────────────
$string['error_forbidden_keyword']   = 'Forbidden operation: \'{$a}\'. This operation can irreversibly damage system data.';
$string['warning_keyword_dml']       = '⚠️ The query contains \'{$a}\' — this operation modifies data in Moodle. Make sure it is intentional and test it in a development environment first.';
$string['error_forbidden_table']     = 'Access to table \'{$a}\' is not allowed. This table contains sensitive system data.';
$string['error_multiple_statements'] = 'Only one operation per function is allowed. Do not use semicolons (;) to chain multiple statements.';
$string['error_positional_params']   = 'Use named parameters (:name) instead of positional ?. Example: WHERE id = :userid';
$string['error_param_undeclared']    = 'The placeholder \':{$a}\' is in the SQL but is not declared as a parameter.';
$string['error_param_notinquery']    = 'The parameter \'{$a}\' is declared but does not appear in the SQL.';
