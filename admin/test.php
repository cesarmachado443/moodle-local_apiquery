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
 * Admin page - test a query interactively.
 *
 * @package    local_apiquery
 * @copyright  2026 CESW <cesarmachado443@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('local/apiquery:manage', context_system::instance());

admin_externalpage_setup('local_apiquery_queries');

$query_id = required_param('id', PARAM_INT);
$query   = $DB->get_record('local_apiquery_queries', ['id' => $query_id], '*', MUST_EXIST);

// Override the page URL set by admin_externalpage_setup (which defaults to the index page).
$PAGE->set_url(new moodle_url('/local/apiquery/admin/test.php', ['id' => $query_id]));
$params  = json_decode($query->parameters ?? '[]', true) ?: [];

$results    = null;
$exec_error  = null;
$exec_ms     = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {

    $sql_params = [];
    foreach ($params as $param) {
        $value = optional_param('tp_' . $param['name'], $param['default'] ?? null, PARAM_TEXT);

        if ($value === null || $value === '') {
            if ($param['required']) {
                $exec_error = get_string('error_param_required', 'local_apiquery', $param['name']);
                break;
            }
            continue;
        }

        $sql_params[$param['name']] = match($param['type']) {
            'int'   => (int) $value,
            'float' => (float) $value,
            'bool'  => (bool) $value,
            default => (string) $value,
        };
    }

    if (!$exec_error) {
        try {
            $start = microtime(true);

            // Expand duplicate placeholders: if :since appears twice,
            // Moodle needs 2 values — rename the 2nd occurrence to :since_dup2.
            $expanded_params  = $sql_params;
            $occurrence_count = [];
            $expanded_sql = preg_replace_callback(
                '/:([a-zA-Z_][a-zA-Z0-9_]*)/',
                function ($matches) use (&$occurrence_count, &$expanded_params, $sql_params) {
                    $name = $matches[1];
                    if (!isset($occurrence_count[$name])) {
                        $occurrence_count[$name] = 1;
                        if (array_key_exists($name, $sql_params)) {
                            $expanded_params[$name] = $sql_params[$name];
                        }
                        return ':' . $name;
                    } else {
                        $occurrence_count[$name]++;
                        $dup_name = $name . '_dup' . $occurrence_count[$name];
                        if (array_key_exists($name, $sql_params)) {
                            $expanded_params[$dup_name] = $sql_params[$name];
                        }
                        return ':' . $dup_name;
                    }
                },
                $query->sqlquery
            );

            // Detect DML vs SELECT to use the correct Moodle database method.
            $is_dml = (bool) preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE)\b/i', $expanded_sql);
            if ($is_dml) {
                $DB->execute($expanded_sql, $expanded_params);
                $records = [['result' => get_string('dml_success', 'local_apiquery')]];
            } else {
                $recordset = $DB->get_recordset_sql($expanded_sql, $expanded_params);
                $records = [];
                foreach ($recordset as $r) { $records[] = (array) $r; }
                $recordset->close();
            }
            $results = $records;
            $exec_ms  = (int)((microtime(true) - $start) * 1000);
        } catch (Exception $e) {
            $exec_error = $e->getMessage();
        }
    }
}

$PAGE->set_title(get_string('test_title', 'local_apiquery', $query->displayname));
$PAGE->set_heading(get_string('test_title', 'local_apiquery', $query->displayname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('test_heading', 'local_apiquery', $query->displayname));

echo html_writer::link(
    new moodle_url('/local/apiquery/admin/index.php'),
    get_string('back_to_list', 'local_apiquery'),
    ['class' => 'btn btn-outline-secondary btn-sm mb-3']
);

// Build parameters array for template.
$params_data = [];
foreach ($params as $p) {
    $params_data[] = [
        'name'           => htmlspecialchars($p['name']),
        'type'           => htmlspecialchars($p['type']),
        'required'       => (bool) $p['required'],
        'default'        => htmlspecialchars($p['default'] ?? ''),
        'value'          => htmlspecialchars(optional_param('tp_' . $p['name'], $p['default'] ?? '', PARAM_TEXT)),
        'required_label' => $p['required']
            ? get_string('param_required_label', 'local_apiquery')
            : get_string('param_optional_label', 'local_apiquery'),
        'default_text'   => !empty($p['default']) ? "default: {$p['default']}" : '',
    ];
}

$params_form_data = [
    'card_title'    => get_string('test_params_card', 'local_apiquery'),
    'sesskey'       => sesskey(),
    'has_params'    => !empty($params),
    'params'        => $params_data,
    'no_params_msg' => get_string('no_params_declared', 'local_apiquery'),
    'execute_btn'   => get_string('execute_btn', 'local_apiquery'),
];

echo $OUTPUT->render_from_template('local_apiquery/test_params_form', $params_form_data);

// Build results display data.
if ($exec_error || $results !== null) {
    $results_data = [];

    if ($exec_error) {
        $results_data['has_error'] = true;
        $results_data['error_msg'] = htmlspecialchars($exec_error);
    } else if ($results !== null) {
        $success_obj = new stdClass();
        $success_obj->ms   = $exec_ms;
        $success_obj->rows = count($results);

        $results_data['has_results'] = true;
        $results_data['success_msg'] = get_string('test_success', 'local_apiquery', $success_obj);
        $results_data['has_rows']    = !empty($results);
        $results_data['no_rows_msg'] = get_string('no_rows_returned', 'local_apiquery');

        if (!empty($results)) {
            $results_data['columns'] = array_keys($results[0]);

            $rows_data = [];
            foreach (array_slice($results, 0, 100) as $row) {
                $cells = [];
                foreach ($row as $val) {
                    $cells[] = htmlspecialchars((string)($val ?? 'NULL'));
                }
                $rows_data[] = ['cells' => $cells];
            }
            $results_data['rows'] = $rows_data;

            if (count($results) > 100) {
                $results_data['showing_first_msg'] = get_string('showing_first_rows', 'local_apiquery', count($results));
            }
        }
    }

    echo $OUTPUT->render_from_template('local_apiquery/test_results', $results_data);
}

// SQL display card.
$sql_display_data = [
    'card_title' => get_string('sql_card_title', 'local_apiquery'),
    'sqlquery'   => htmlspecialchars($query->sqlquery),
];
echo $OUTPUT->render_from_template('local_apiquery/test_sql_display', $sql_display_data);

echo $OUTPUT->footer();
