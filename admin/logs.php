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
 * Admin page - execution logs.
 *
 * @package    local_apiquery
 * @copyright  2026 CESW <cesarmachado443@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('local/apiquery:manage', context_system::instance());

admin_externalpage_setup('local_apiquery_logs');

$PAGE->set_title(get_string('execution_logs', 'local_apiquery'));
$PAGE->set_heading(get_string('execution_logs', 'local_apiquery'));

// Filters.
$filter_query = optional_param('query_id', 0, PARAM_INT);
$filter_page  = optional_param('page', 0, PARAM_INT);
$per_page     = 50;

$where_clause = $filter_query ? 'WHERE l.query_id = :qid' : '';
$count_params = $filter_query ? ['qid' => $filter_query] : [];

$total = $DB->count_records_sql(
    "SELECT COUNT(*) FROM {local_apiquery_logs} l $where_clause",
    $count_params
);

$logs = $DB->get_records_sql("
    SELECT l.*, q.shortname, q.displayname
    FROM {local_apiquery_logs} l
    LEFT JOIN {local_apiquery_queries} q ON q.id = l.query_id
    $where_clause
    ORDER BY l.timecreated DESC
", $count_params, $filter_page * $per_page, $per_page);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('execution_logs', 'local_apiquery'));

// Stats summary.
$stats = $DB->get_record_sql("
    SELECT COUNT(*) AS total,
           AVG(execution_ms) AS avg_ms,
           SUM(rows_returned) AS total_rows,
           COUNT(CASE WHEN error IS NOT NULL THEN 1 END) AS errors
    FROM {local_apiquery_logs}
    " . ($filter_query ? 'WHERE query_id = :qid' : ''),
    $count_params
);

echo html_writer::start_div('row g-3 mb-4');
foreach ([
    [get_string('stat_total',   'local_apiquery'), number_format($stats->total), 'primary'],
    [get_string('stat_avgtime', 'local_apiquery'), round($stats->avg_ms ?? 0) . ' ms', 'info'],
    [get_string('stat_rows',    'local_apiquery'), number_format($stats->total_rows ?? 0), 'success'],
    [get_string('stat_errors',  'local_apiquery'), number_format($stats->errors ?? 0), 'danger'],
] as [$label, $value, $color]) {
    echo html_writer::div(
        html_writer::div(
            html_writer::div($value, "h3 text-{$color} mb-0") .
            html_writer::tag('small', $label, ['class' => 'text-muted']),
            'card-body text-center py-3'
        ),
        'col-md-3',
        ['class' => 'col']
    );
    // Simplified to avoid deeply nested html_writer calls.
}
echo html_writer::end_div();

if (empty($logs)) {
    echo $OUTPUT->notification(get_string('no_logs_yet', 'local_apiquery'), 'info');
} else {
    $table             = new html_table();
    $table->head       = [
        get_string('col_function', 'local_apiquery'),
        get_string('col_params',   'local_apiquery'),
        get_string('col_rows',     'local_apiquery'),
        get_string('col_time',     'local_apiquery'),
        get_string('col_status',   'local_apiquery'),
        get_string('col_date',     'local_apiquery'),
    ];
    $table->attributes = ['class' => 'generaltable table table-sm table-hover'];

    foreach ($logs as $log) {
        $params = json_decode($log->params_used ?? '{}', true);
        $param_str = '';
        if ($params) {
            $parts = [];
            foreach ($params as $k => $v) $parts[] = "$k=$v";
            $param_str = implode(', ', $parts);
        }

        $status = empty($log->error)
            ? html_writer::span('✅ OK', 'text-success')
            : html_writer::span('❌ Error', 'text-danger') .
              html_writer::tag('small', '<br>' . htmlspecialchars(substr($log->error, 0, 80) . '...'), ['class' => 'text-muted']);

        $table->data[] = [
            html_writer::tag('code', htmlspecialchars($log->shortname ?? '—')),
            html_writer::tag('small', htmlspecialchars($param_str ?: '—'), ['class' => 'font-monospace']),
            number_format($log->rows_returned ?? 0),
            ($log->execution_ms ?? '—') . ' ms',
            $status,
            userdate($log->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
        ];
    }

    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $filter_page, $per_page, new moodle_url('/local/apiquery/admin/logs.php', ['query_id' => $filter_query]));
}

echo $OUTPUT->footer();
