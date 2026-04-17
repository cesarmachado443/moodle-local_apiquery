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
 * Admin page - list of configured queries.
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

// Direct actions (enable/disable/delete without a form).
$action   = optional_param('action', '', PARAM_ALPHA);
$query_id  = optional_param('id', 0, PARAM_INT);

if ($action && $query_id) {
    require_sesskey();

    switch ($action) {
        case 'enable':
            $DB->set_field('local_apiquery_queries', 'enabled', 1, ['id' => $query_id]);
            redirect(new moodle_url('/local/apiquery/admin/index.php'), get_string('query_enabled', 'local_apiquery'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;

        case 'disable':
            $DB->set_field('local_apiquery_queries', 'enabled', 0, ['id' => $query_id]);
            redirect(new moodle_url('/local/apiquery/admin/index.php'), get_string('query_disabled', 'local_apiquery'), null, \core\output\notification::NOTIFY_WARNING);
            break;

        case 'delete':
            $DB->delete_records('local_apiquery_logs', ['query_id' => $query_id]);
            $DB->delete_records('local_apiquery_queries', ['id' => $query_id]);
            redirect(new moodle_url('/local/apiquery/admin/index.php'), get_string('query_deleted', 'local_apiquery'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;
    }
}

// Fetch all queries with usage stats.
$queries = $DB->get_records_sql("
    SELECT q.*,
           COUNT(l.id)        AS total_executions,
           MAX(l.timecreated) AS last_execution,
           AVG(l.execution_ms) AS avg_ms
    FROM {local_apiquery_queries} q
    LEFT JOIN {local_apiquery_logs} l ON l.query_id = q.id
    GROUP BY q.id, q.shortname, q.displayname, q.description, q.sqlquery,
             q.parameters, q.enabled, q.timecreated, q.timemodified, q.createdby
    ORDER BY q.shortname ASC
");

$PAGE->set_title(get_string('manage_queries', 'local_apiquery'));
$PAGE->set_heading(get_string('manage_queries', 'local_apiquery'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage_queries', 'local_apiquery'));

// Action bar: New / Export / Import.
$create_url  = new moodle_url('/local/apiquery/admin/edit.php');
$export_url  = new moodle_url('/local/apiquery/admin/export.php');
$import_url  = new moodle_url('/local/apiquery/admin/import.php');

echo html_writer::div(
    html_writer::link($create_url, '+ ' . get_string('new_query',       'local_apiquery'), ['class' => 'btn btn-primary']) .
    ' ' .
    html_writer::link($export_url, '↑ ' . get_string('export_all',      'local_apiquery'), ['class' => 'btn btn-outline-secondary']) .
    ' ' .
    html_writer::link($import_url, '↓ ' . get_string('import_queries',  'local_apiquery'), ['class' => 'btn btn-outline-secondary']),
    'd-flex gap-2 mb-4'
);

if (empty($queries)) {
    echo $OUTPUT->notification(get_string('no_queries', 'local_apiquery'), 'info');
} else {
    // Queries table.
    $table            = new html_table();
    $table->head      = [
        get_string('shortname', 'local_apiquery'),
        get_string('displayname', 'local_apiquery'),
        get_string('parameters', 'local_apiquery'),
        get_string('status', 'local_apiquery'),
        get_string('executions', 'local_apiquery'),
        get_string('last_execution', 'local_apiquery'),
        get_string('avg_time', 'local_apiquery'),
        get_string('actions', 'local_apiquery'),
    ];
    $table->attributes = ['class' => 'generaltable table table-hover'];

    foreach ($queries as $q) {
        $params      = json_decode($q->parameters ?? '[]', true) ?: [];
        $param_names  = implode(', ', array_map(fn($p) => ':' . $p['name'], $params));
        $status_badge = $q->enabled
            ? html_writer::span(get_string('active', 'local_apiquery'), 'badge badge-success bg-success text-white')
            : html_writer::span(get_string('inactive', 'local_apiquery'), 'badge badge-secondary bg-secondary text-white');

        $sesskey = sesskey();

        // Row actions.
        $edit_url    = new moodle_url('/local/apiquery/admin/edit.php', ['id' => $q->id]);
        $test_url    = new moodle_url('/local/apiquery/admin/test.php', ['id' => $q->id]);
        $toggle_url  = new moodle_url('/local/apiquery/admin/index.php', [
            'action' => $q->enabled ? 'disable' : 'enable',
            'id'     => $q->id,
            'sesskey'=> $sesskey,
        ]);
        $delete_url  = new moodle_url('/local/apiquery/admin/index.php', [
            'action' => 'delete',
            'id'     => $q->id,
            'sesskey'=> $sesskey,
        ]);

        $actions = implode(' ', [
            html_writer::link($edit_url,   '✏️ ' . get_string('edit'),   ['class' => 'btn btn-sm btn-outline-primary']),
            html_writer::link($test_url,   '▶️ ' . get_string('test', 'local_apiquery'),   ['class' => 'btn btn-sm btn-outline-info']),
            html_writer::link($toggle_url, $q->enabled ? '⏸ ' . get_string('disable', 'local_apiquery') : '▶ ' . get_string('enable', 'local_apiquery'), ['class' => 'btn btn-sm btn-outline-warning']),
            html_writer::link($delete_url, '🗑 ' . get_string('delete'), ['class' => 'btn btn-sm btn-outline-danger', 'onclick' => "return confirm('" . get_string('confirm_delete', 'local_apiquery') . "')"]),
        ]);

        $table->data[] = [
            html_writer::tag('code', $q->shortname),
            $q->displayname . html_writer::tag('small', '<br>' . ($q->description ?? ''), ['class' => 'text-muted']),
            $param_names ?: html_writer::span('—', 'text-muted'),
            $status_badge,
            number_format($q->total_executions),
            $q->last_execution ? userdate($q->last_execution) : '—',
            $q->avg_ms ? round($q->avg_ms) . ' ms' : '—',
            $actions,
        ];
    }

    echo html_writer::table($table);
}

// API usage instructions section.
echo $OUTPUT->box_start('generalbox mt-4');
echo $OUTPUT->heading(get_string('api_usage', 'local_apiquery'), 4);

$site_url = $CFG->wwwroot;
echo html_writer::tag('p', get_string('api_usage_desc', 'local_apiquery'));
echo html_writer::tag('pre',
    "POST {$site_url}/webservice/rest/server.php\n\n" .
    "wsfunction=local_apiquery_execute_query\n" .
    "wstoken=YOUR_TOKEN_HERE\n" .
    "moodlewsrestformat=json\n" .
    "shortname=your_query_shortname\n" .
    "params[0][name]=since\n" .
    "params[0][value]=1738000000\n" .
    "params[1][name]=courseids\n" .
    "params[1][value]=45,67,89",
    ['class' => 'bg-dark text-light p-3 rounded']
);
echo $OUTPUT->box_end();

echo $OUTPUT->footer();
