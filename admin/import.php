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
 * Admin page - import queries from a JSON export file.
 *
 * Flow:
 *   GET              → show upload form
 *   POST step=upload → parse file, show preview + version warnings
 *   POST step=confirm→ execute import and redirect to index
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
$PAGE->set_url(new moodle_url('/local/apiquery/admin/import.php'));

$step = optional_param('step', 'upload', PARAM_ALPHA);

$page_title = get_string('import_queries', 'local_apiquery');
$PAGE->set_title($page_title);
$PAGE->set_heading($page_title);

echo $OUTPUT->header();
echo $OUTPUT->heading($page_title);

echo html_writer::link(
    new moodle_url('/local/apiquery/admin/index.php'),
    get_string('back_to_list', 'local_apiquery'),
    ['class' => 'btn btn-outline-secondary btn-sm mb-4']
);

// ─── STEP 1: Upload form ──────────────────────────────────────────────────────
if ($step === 'upload') {
    $template_data = [
        'sesskey' => sesskey(),
        'import_file_label' => get_string('import_file_label', 'local_apiquery'),
        'import_queries_btn' => get_string('import_queries', 'local_apiquery'),
    ];

    echo $OUTPUT->render_from_template('local_apiquery/import_upload_form', $template_data);
    echo $OUTPUT->footer();
    exit;
}

// ─── Both remaining steps require sesskey ─────────────────────────────────────
confirm_sesskey();

// ─── STEP 2: Preview (parse file, show conflicts + version warnings) ──────────
if ($step === 'preview') {

    // Validate file upload.
    // Note: Moodle's File API is designed for permanent storage in file areas.
    // For temporary JSON imports that are immediately processed and discarded,
    // we validate the upload explicitly with is_uploaded_file() for security.
    $upload_error = $_FILES['importfile']['error'] ?? UPLOAD_ERR_NO_FILE;
    $upload_tmp   = $_FILES['importfile']['tmp_name'] ?? '';

    // Verify upload succeeded and is a legitimate PHP upload (prevents path traversal).
    if ($upload_error !== UPLOAD_ERR_OK || empty($upload_tmp) || !is_uploaded_file($upload_tmp)) {
        // Build detailed error message for debugging.
        $error_details = get_string('import_upload_error_code', 'local_apiquery', $upload_error);
        if ($upload_error === UPLOAD_ERR_NO_FILE) {
            $error_details .= ' (' . get_string('import_upload_no_file', 'local_apiquery') . ')';
        } else if ($upload_error === UPLOAD_ERR_INI_SIZE) {
            $error_details .= ' (' . get_string('import_upload_ini_size', 'local_apiquery') . ')';
        } else if ($upload_error === UPLOAD_ERR_FORM_SIZE) {
            $error_details .= ' (' . get_string('import_upload_form_size', 'local_apiquery') . ')';
        }
        $tmp_status = empty($upload_tmp)
            ? get_string('import_upload_tmp_empty', 'local_apiquery')
            : get_string('import_upload_tmp_present', 'local_apiquery');
        $error_details .= '. ' . get_string('import_upload_tmp', 'local_apiquery', $tmp_status);

        $valid_status = is_uploaded_file($upload_tmp)
            ? get_string('import_upload_valid_yes', 'local_apiquery')
            : get_string('import_upload_valid_no', 'local_apiquery');
        $error_details .= '. ' . get_string('import_upload_valid', 'local_apiquery', $valid_status);

        echo $OUTPUT->notification(
            get_string('import_invalid_file', 'local_apiquery') . ' (' . $error_details . ')',
            'error'
        );
        echo $OUTPUT->footer();
        exit;
    }

    // Read and validate JSON content.
    $raw = file_get_contents($upload_tmp);
    if ($raw === false) {
        echo $OUTPUT->notification(get_string('import_invalid_file', 'local_apiquery'), 'error');
        echo $OUTPUT->footer();
        exit;
    }

    $data = json_decode($raw, true);

    // Check for JSON decode errors.
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo $OUTPUT->notification(
            get_string('import_json_decode_error', 'local_apiquery', json_last_error_msg()),
            'error'
        );
        echo $OUTPUT->footer();
        exit;
    }

    // Basic structure validation.
    if (
        !is_array($data)
        || !isset($data['meta'], $data['queries'])
        || ($data['meta']['plugin'] ?? '') !== 'local_apiquery'
        || !is_array($data['queries'])
    ) {
        // Build debug info object with translatable values.
        $debug = new stdClass();
        $debug->is_array = is_array($data)
            ? get_string('import_debug_yes', 'local_apiquery')
            : get_string('import_debug_no', 'local_apiquery');
        $debug->has_meta = isset($data['meta'])
            ? get_string('import_debug_yes', 'local_apiquery')
            : get_string('import_debug_no', 'local_apiquery');
        $debug->has_queries = isset($data['queries'])
            ? get_string('import_debug_yes', 'local_apiquery')
            : get_string('import_debug_no', 'local_apiquery');
        $debug->plugin = $data['meta']['plugin'] ?? get_string('import_debug_missing', 'local_apiquery');

        $debug_string = get_string('import_debug_structure', 'local_apiquery', $debug);

        echo $OUTPUT->notification(
            get_string('import_structure_error', 'local_apiquery', $debug_string),
            'error'
        );
        echo $OUTPUT->footer();
        exit;
    }

    if (empty($data['queries'])) {
        echo $OUTPUT->notification(get_string('import_no_queries', 'local_apiquery'), 'warning');
        echo $OUTPUT->footer();
        exit;
    }

    $meta = $data['meta'];

    // ── Version comparison ────────────────────────────────────────────────────
    $exported_branch = (string) ($meta['moodle_branch'] ?? '');
    $current_branch  = (string) $CFG->branch;
    $exported_release = trim($meta['moodle_release'] ?? $exported_branch);
    $current_release  = trim(explode('(', $CFG->release)[0]);

    $version_warning_level = 0; // 0 = none, 1 = minor, 2 = major
    if ($exported_branch !== '' && $exported_branch !== $current_branch) {
        // Compare major version: first digit of branch (4xx vs 5xx).
        $exported_major = (int) floor((int) $exported_branch / 100);
        $current_major  = (int) floor((int) $current_branch  / 100);
        $version_warning_level = ($exported_major !== $current_major) ? 2 : 1;
    }

    // Show version warnings.
    if ($version_warning_level === 2) {
        $a = (object)['exported' => $exported_release, 'current' => $current_release];
        echo $OUTPUT->notification(
            '<strong>' . get_string('warning_version_major_mismatch', 'local_apiquery') . '</strong><br>' .
            get_string('warning_version_major_mismatch_desc', 'local_apiquery', $a),
            'error'
        );
    } elseif ($version_warning_level === 1) {
        $a = (object)['exported' => $exported_release, 'current' => $current_release];
        echo $OUTPUT->notification(
            '<strong>' . get_string('warning_version_mismatch', 'local_apiquery') . '</strong><br>' .
            get_string('warning_version_mismatch_desc', 'local_apiquery', $a),
            'warning'
        );
    }

    // ── Source info card ─────────────────────────────────────────────────────
    $source_a = (object)[
        'site'    => htmlspecialchars($meta['site_url'] ?? '—'),
        'release' => htmlspecialchars($exported_release),
        'date'    => htmlspecialchars($meta['exported_at'] ?? '—'),
    ];
    echo html_writer::div(
        html_writer::tag('small', get_string('import_source_info', 'local_apiquery', $source_a), ['class' => 'text-muted']),
        'alert alert-light border mb-3 py-2'
    );

    // ── Conflict detection ────────────────────────────────────────────────────
    $existing_shortnames = $DB->get_fieldset_select(
        'local_apiquery_queries', 'shortname', '1=1'
    );
    $existing_set = array_flip($existing_shortnames);

    // ── JS select all / deselect all ─────────────────────────────────────────
    $PAGE->requires->js_call_amd('local_apiquery/import_select', 'init');

    // ── Build queries array for template ──────────────────────────────────────
    $queries = [];
    foreach ($data['queries'] as $q) {
        $shortname = $q['shortname'] ?? '';
        $exists    = isset($existing_set[$shortname]);

        $queries[] = [
            'shortname'   => htmlspecialchars($shortname),
            'displayname' => htmlspecialchars($q['displayname'] ?? ''),
            'exists'      => $exists,
            'badge_class' => $exists ? 'bg-warning text-dark' : 'bg-success text-white',
            'badge_text'  => $exists
                ? get_string('import_status_exists', 'local_apiquery')
                : get_string('import_status_new', 'local_apiquery'),
        ];
    }

    // Store JSON in session cache to avoid corruption via POST param filtering.
    $cache = cache::make('local_apiquery', 'import_data');
    $cache->set('json_raw', $raw);

    $template_data = [
        'preview_title'       => get_string('import_preview_title', 'local_apiquery'),
        'queries'             => $queries,
        'sesskey'             => sesskey(),
        'conflict_mode_label' => get_string('import_conflict_mode', 'local_apiquery'),
        'option_skip_label'   => get_string('import_option_skip', 'local_apiquery'),
        'option_overwrite_label' => get_string('import_option_overwrite', 'local_apiquery'),
        'confirm_btn'         => get_string('import_confirm_btn', 'local_apiquery'),
        'cancel_text'         => get_string('cancel'),
        'cancel_url'          => (new moodle_url('/local/apiquery/admin/index.php'))->out(false),
        'col_shortname'       => get_string('shortname', 'local_apiquery'),
        'col_displayname'     => get_string('displayname', 'local_apiquery'),
        'col_status'          => get_string('import_col_status', 'local_apiquery'),
        'select_all_label'    => get_string('export_select_all', 'local_apiquery'),
    ];

    echo $OUTPUT->render_from_template('local_apiquery/import_preview', $template_data);
    echo $OUTPUT->footer();
    exit;
}

// ─── STEP 3: Execute import ───────────────────────────────────────────────────
if ($step === 'confirm') {

    // Retrieve JSON from session cache (stored in preview step).
    $cache = cache::make('local_apiquery', 'import_data');
    $raw = $cache->get('json_raw');
    $cache->delete('json_raw'); // Clean up cache.

    // If cache is empty/expired, redirect back to upload form.
    if ($raw === false || empty($raw)) {
        redirect(
            new moodle_url('/local/apiquery/admin/import.php'),
            get_string('import_invalid_file', 'local_apiquery'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $conflict = optional_param('conflict', 'skip', PARAM_ALPHA);
    $data     = json_decode($raw, true);

    if (!is_array($data) || !isset($data['queries']) || ($data['meta']['plugin'] ?? '') !== 'local_apiquery') {
        redirect(
            new moodle_url('/local/apiquery/admin/import.php'),
            get_string('import_invalid_file', 'local_apiquery'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    // Only import the shortnames the user checked in the preview step.
    $selected_raw = clean_param_array(
        (array) optional_param_array('selected_shortnames', [], PARAM_ALPHANUMEXT),
        PARAM_ALPHANUMEXT
    );
    $selected_set = array_flip($selected_raw);

    // Filter queries to only those selected (if none sent, import all — safety fallback).
    if (!empty($selected_set)) {
        $data['queries'] = array_filter(
            $data['queries'],
            fn($q) => isset($selected_set[$q['shortname'] ?? ''])
        );
    }

    // Preload all existing queries to avoid N+1 query problem.
    // Index by shortname for O(1) lookup in the import loop below.
    $existing_queries = $DB->get_records('local_apiquery_queries', null, '', 'id, shortname, timecreated');
    $existing_map = [];
    foreach ($existing_queries as $existing_query) {
        $existing_map[$existing_query->shortname] = $existing_query;
    }

    $count_imported   = 0;
    $count_skipped    = 0;
    $count_overwritten = 0;
    $now = time();

    foreach ($data['queries'] as $q) {
        $shortname = clean_param($q['shortname'] ?? '', PARAM_ALPHANUMEXT);
        if (empty($shortname)) {
            continue;
        }

        $record               = new stdClass();
        $record->shortname    = $shortname;
        $record->displayname  = clean_param($q['displayname'] ?? '', PARAM_TEXT);
        $record->description  = clean_param($q['description'] ?? '', PARAM_TEXT);
        $record->sqlquery     = $q['sqlquery'] ?? '';
        $record->parameters   = $q['parameters'] ?? '[]';
        $record->enabled      = (int) ($q['enabled'] ?? 1);
        $record->timemodified = $now;
        $record->createdby    = $USER->id;

        if (isset($existing_map[$shortname])) {
            if ($conflict === 'overwrite') {
                // Use preloaded record instead of querying DB again (performance).
                $existing = $existing_map[$shortname];
                $record->id          = $existing->id;
                $record->timecreated = $existing->timecreated;
                $DB->update_record('local_apiquery_queries', $record);
                $count_overwritten++;
            } else {
                $count_skipped++;
            }
        } else {
            $record->timecreated = $now;
            $DB->insert_record('local_apiquery_queries', $record);
            $count_imported++;
        }
    }

    // Build summary message.
    $parts = [];
    if ($count_imported > 0) {
        $parts[] = get_string('import_success',     'local_apiquery', $count_imported);
    }
    if ($count_overwritten > 0) {
        $parts[] = get_string('import_overwritten', 'local_apiquery', $count_overwritten);
    }
    if ($count_skipped > 0) {
        $parts[] = get_string('import_skipped',     'local_apiquery', $count_skipped);
    }

    $message = implode(' ', $parts);
    $type    = ($count_imported + $count_overwritten > 0)
        ? \core\output\notification::NOTIFY_SUCCESS
        : \core\output\notification::NOTIFY_WARNING;

    redirect(new moodle_url('/local/apiquery/admin/index.php'), $message, null, $type);
}

echo $OUTPUT->footer();
