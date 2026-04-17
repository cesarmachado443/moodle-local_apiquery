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
 * Admin page - export selected queries as a JSON file.
 *
 * Flow:
 *   GET              → selection form listing all queries with checkboxes
 *   POST step=download → validate, build JSON with selected IDs, trigger download
 *
 * @package    local_apiquery
 * @copyright  2026 CESW <cesarmachado443@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('local/apiquery:manage', context_system::instance());

$step = optional_param('step', 'select', PARAM_ALPHA);

// ─── STEP 2: Download — output file directly ──────────────────────────────────
if ($step === 'download') {
    confirm_sesskey();

    // Read selected IDs (array of ints).
    $selected_ids = array_filter(
        array_map('intval', (array) optional_param_array('qids', [], PARAM_INT))
    );

    if (empty($selected_ids)) {
        // No IDs selected — redirect back with error.
        redirect(
            new moodle_url('/local/apiquery/admin/export.php'),
            get_string('export_none_selected', 'local_apiquery'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    [$insql, $inparams] = $DB->get_in_or_equal($selected_ids, SQL_PARAMS_NAMED);
    $queries = $DB->get_records_select(
        'local_apiquery_queries',
        "id $insql",
        $inparams,
        'shortname ASC'
    );

    $export = [
        'meta' => [
            'plugin'         => 'local_apiquery',
            'plugin_version' => get_config('local_apiquery', 'version') ?: '1.1.0',
            'moodle_version' => (int) $CFG->version,
            'moodle_branch'  => (string) $CFG->branch,
            'moodle_release' => trim(explode('(', $CFG->release)[0]),
            'exported_at'    => date('c'),
            'site_url'       => $CFG->wwwroot,
            'queries_count'  => count($queries),
        ],
        'queries' => [],
    ];

    foreach ($queries as $q) {
        $export['queries'][] = [
            'shortname'   => $q->shortname,
            'displayname' => $q->displayname,
            'description' => $q->description ?? '',
            'sqlquery'    => $q->sqlquery,
            'parameters'  => $q->parameters ?? '[]',
            'enabled'     => (int) $q->enabled,
        ];
    }

    $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Verify JSON encoding succeeded.
    if ($json === false || json_last_error() !== JSON_ERROR_NONE) {
        redirect(
            new moodle_url('/local/apiquery/admin/export.php'),
            get_string('export_json_encode_error', 'local_apiquery', json_last_error_msg()),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $filename = 'apiquery_queries_' . date('Ymd_His') . '.json';

    // Clean output buffer to prevent any previous output from corrupting the JSON.
    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($json));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    echo $json;
    exit;
}

// ─── STEP 1: Selection form ───────────────────────────────────────────────────
admin_externalpage_setup('local_apiquery_queries');
$PAGE->set_url(new moodle_url('/local/apiquery/admin/export.php'));

$queries = $DB->get_records('local_apiquery_queries', null, 'shortname ASC');

$page_title = get_string('export_queries', 'local_apiquery');
$PAGE->set_title($page_title);
$PAGE->set_heading($page_title);

echo $OUTPUT->header();
echo $OUTPUT->heading($page_title);

echo html_writer::link(
    new moodle_url('/local/apiquery/admin/index.php'),
    get_string('back_to_list', 'local_apiquery'),
    ['class' => 'btn btn-outline-secondary btn-sm mb-4']
);

if (empty($queries)) {
    echo $OUTPUT->notification(get_string('no_queries', 'local_apiquery'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// JS for select-all / deselect-all.
$PAGE->requires->js_call_amd('local_apiquery/export_select', 'init');

// Build queries array for template.
$queries_data = [];
foreach ($queries as $q) {
    $params      = json_decode($q->parameters ?? '[]', true) ?: [];
    $param_names = implode(', ', array_map(fn($p) => ':' . $p['name'], $params));

    $queries_data[] = [
        'id'          => $q->id,
        'shortname'   => htmlspecialchars($q->shortname),
        'displayname' => htmlspecialchars($q->displayname),
        'description' => htmlspecialchars($q->description ?? ''),
        'param_names' => $param_names,
        'enabled'     => (bool) $q->enabled,
        'badge_class' => $q->enabled ? 'bg-success text-white' : 'bg-secondary text-white',
        'badge_text'  => $q->enabled
            ? get_string('active', 'local_apiquery')
            : get_string('inactive', 'local_apiquery'),
    ];
}

$template_data = [
    'sesskey'          => sesskey(),
    'select_all_btn'   => get_string('export_select_all', 'local_apiquery'),
    'deselect_all_btn' => get_string('export_deselect_all', 'local_apiquery'),
    'queries'          => $queries_data,
    'col_shortname'    => get_string('shortname', 'local_apiquery'),
    'col_displayname'  => get_string('displayname', 'local_apiquery'),
    'col_parameters'   => get_string('parameters', 'local_apiquery'),
    'col_status'       => get_string('status', 'local_apiquery'),
    'export_btn'       => get_string('export_selected_btn', 'local_apiquery'),
];

echo $OUTPUT->render_from_template('local_apiquery/export_form', $template_data);
echo $OUTPUT->footer();
