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

$pageTitle = get_string('import_queries', 'local_apiquery');
$PAGE->set_title($pageTitle);
$PAGE->set_heading($pageTitle);

echo $OUTPUT->header();
echo $OUTPUT->heading($pageTitle);

echo html_writer::link(
    new moodle_url('/local/apiquery/admin/index.php'),
    get_string('back_to_list', 'local_apiquery'),
    ['class' => 'btn btn-outline-secondary btn-sm mb-4']
);

// ─── STEP 1: Upload form ──────────────────────────────────────────────────────
if ($step === 'upload') {
    ?>
    <div class="card" style="max-width:560px">
      <div class="card-body">
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="sesskey" value="<?= sesskey() ?>">
          <input type="hidden" name="step"    value="preview">

          <div class="mb-3">
            <label for="importfile" class="form-label fw-semibold">
              <?= get_string('import_file_label', 'local_apiquery') ?>
            </label>
            <input type="file" id="importfile" name="importfile"
                   class="form-control" accept=".json" required>
          </div>

          <button type="submit" class="btn btn-primary">
            <?= get_string('import_queries', 'local_apiquery') ?>
          </button>
        </form>
      </div>
    </div>
    <?php
    echo $OUTPUT->footer();
    exit;
}

// ─── Both remaining steps require sesskey ─────────────────────────────────────
confirm_sesskey();

// ─── STEP 2: Preview (parse file, show conflicts + version warnings) ──────────
if ($step === 'preview') {

    // Validate upload.
    if (empty($_FILES['importfile']['tmp_name']) || $_FILES['importfile']['error'] !== UPLOAD_ERR_OK) {
        echo $OUTPUT->notification(get_string('import_invalid_file', 'local_apiquery'), 'error');
        echo $OUTPUT->footer();
        exit;
    }

    $raw  = file_get_contents($_FILES['importfile']['tmp_name']);
    $data = json_decode($raw, true);

    // Basic structure validation.
    if (
        !is_array($data)
        || !isset($data['meta'], $data['queries'])
        || ($data['meta']['plugin'] ?? '') !== 'local_apiquery'
        || !is_array($data['queries'])
    ) {
        echo $OUTPUT->notification(get_string('import_invalid_file', 'local_apiquery'), 'error');
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
    $exportedBranch = (string) ($meta['moodle_branch'] ?? '');
    $currentBranch  = (string) $CFG->branch;
    $exportedRelease = trim($meta['moodle_release'] ?? $exportedBranch);
    $currentRelease  = trim(explode('(', $CFG->release)[0]);

    $versionWarningLevel = 0; // 0 = none, 1 = minor, 2 = major
    if ($exportedBranch !== '' && $exportedBranch !== $currentBranch) {
        // Compare major version: first digit of branch (4xx vs 5xx).
        $exportedMajor = (int) floor((int) $exportedBranch / 100);
        $currentMajor  = (int) floor((int) $currentBranch  / 100);
        $versionWarningLevel = ($exportedMajor !== $currentMajor) ? 2 : 1;
    }

    // Show version warnings.
    if ($versionWarningLevel === 2) {
        $a = (object)['exported' => $exportedRelease, 'current' => $currentRelease];
        echo $OUTPUT->notification(
            '<strong>' . get_string('warning_version_major_mismatch', 'local_apiquery') . '</strong><br>' .
            get_string('warning_version_major_mismatch_desc', 'local_apiquery', $a),
            'error'
        );
    } elseif ($versionWarningLevel === 1) {
        $a = (object)['exported' => $exportedRelease, 'current' => $currentRelease];
        echo $OUTPUT->notification(
            '<strong>' . get_string('warning_version_mismatch', 'local_apiquery') . '</strong><br>' .
            get_string('warning_version_mismatch_desc', 'local_apiquery', $a),
            'warning'
        );
    }

    // ── Source info card ─────────────────────────────────────────────────────
    $sourceA = (object)[
        'site'    => htmlspecialchars($meta['site_url'] ?? '—'),
        'release' => htmlspecialchars($exportedRelease),
        'date'    => htmlspecialchars($meta['exported_at'] ?? '—'),
    ];
    echo html_writer::div(
        html_writer::tag('small', get_string('import_source_info', 'local_apiquery', $sourceA), ['class' => 'text-muted']),
        'alert alert-light border mb-3 py-2'
    );

    // ── Conflict detection ────────────────────────────────────────────────────
    $existingShortnames = $DB->get_fieldset_select(
        'local_apiquery_queries', 'shortname', '1=1'
    );
    $existingSet = array_flip($existingShortnames);

    // ── JS select all / deselect all ─────────────────────────────────────────
    $PAGE->requires->js_amd_inline("
require(['jquery'], function(\$) {
    \$('#import-check-all').on('change', function() {
        \$('.import-qcheck').prop('checked', this.checked);
    });
});
");

    // ── Preview table with checkboxes ─────────────────────────────────────────
    $table             = new html_table();
    $table->head       = [
        html_writer::tag('input', '', [
            'type'    => 'checkbox',
            'id'      => 'import-check-all',
            'class'   => 'form-check-input',
            'checked' => true,
            'title'   => get_string('export_select_all', 'local_apiquery'),
        ]),
        get_string('shortname',         'local_apiquery'),
        get_string('displayname',       'local_apiquery'),
        get_string('import_col_status', 'local_apiquery'),
    ];
    $table->attributes = ['class' => 'generaltable table table-sm table-hover'];

    foreach ($data['queries'] as $q) {
        $shortname = htmlspecialchars($q['shortname'] ?? '');
        $exists    = isset($existingSet[$q['shortname'] ?? '']);
        $badge     = $exists
            ? html_writer::span(get_string('import_status_exists', 'local_apiquery'), 'badge bg-warning text-dark')
            : html_writer::span(get_string('import_status_new',    'local_apiquery'), 'badge bg-success text-white');

        $table->data[] = [
            html_writer::tag('input', '', [
                'type'    => 'checkbox',
                'name'    => 'selected_shortnames[]',
                'value'   => $shortname,
                'class'   => 'form-check-input import-qcheck',
                'checked' => true,
            ]),
            html_writer::tag('code', $shortname),
            htmlspecialchars($q['displayname'] ?? ''),
            $badge,
        ];
    }

    echo html_writer::tag('h5', get_string('import_preview_title', 'local_apiquery'), ['class' => 'mb-3']);
    echo html_writer::table($table);

    // ── Confirm form ──────────────────────────────────────────────────────────
    ?>
    <form method="post">
      <input type="hidden" name="sesskey"   value="<?= sesskey() ?>">
      <input type="hidden" name="step"      value="confirm">
      <input type="hidden" name="jsondata"  value="<?= htmlspecialchars($raw, ENT_QUOTES) ?>">
      <!-- selected_shortnames[] checkboxes are rendered inside the table above -->

      <div class="card mb-4" style="max-width:440px">
        <div class="card-body">
          <p class="fw-semibold mb-2"><?= get_string('import_conflict_mode', 'local_apiquery') ?></p>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="conflict" id="conflict_skip" value="skip" checked>
            <label class="form-check-label" for="conflict_skip">
              <?= get_string('import_option_skip', 'local_apiquery') ?>
            </label>
          </div>
          <div class="form-check mt-1">
            <input class="form-check-input" type="radio" name="conflict" id="conflict_overwrite" value="overwrite">
            <label class="form-check-label" for="conflict_overwrite">
              <?= get_string('import_option_overwrite', 'local_apiquery') ?>
            </label>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <?= get_string('import_confirm_btn', 'local_apiquery') ?>
        </button>
        <a href="<?= new moodle_url('/local/apiquery/admin/index.php') ?>"
           class="btn btn-outline-secondary">
          <?= get_string('cancel') ?>
        </a>
      </div>
    </form>
    <?php
    echo $OUTPUT->footer();
    exit;
}

// ─── STEP 3: Execute import ───────────────────────────────────────────────────
if ($step === 'confirm') {

    $raw      = required_param('jsondata', PARAM_RAW);
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
    $selectedRaw = clean_param_array(
        (array) optional_param_array('selected_shortnames', [], PARAM_ALPHANUMEXT),
        PARAM_ALPHANUMEXT
    );
    $selectedSet = array_flip($selectedRaw);

    // Filter queries to only those selected (if none sent, import all — safety fallback).
    if (!empty($selectedSet)) {
        $data['queries'] = array_filter(
            $data['queries'],
            fn($q) => isset($selectedSet[$q['shortname'] ?? ''])
        );
    }

    $existingShortnames = $DB->get_fieldset_select('local_apiquery_queries', 'shortname', '1=1');
    $existingMap        = array_flip($existingShortnames);

    $countImported   = 0;
    $countSkipped    = 0;
    $countOverwritten = 0;
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

        if (isset($existingMap[$shortname])) {
            if ($conflict === 'overwrite') {
                $existing = $DB->get_record('local_apiquery_queries', ['shortname' => $shortname]);
                $record->id          = $existing->id;
                $record->timecreated = $existing->timecreated;
                $DB->update_record('local_apiquery_queries', $record);
                $countOverwritten++;
            } else {
                $countSkipped++;
            }
        } else {
            $record->timecreated = $now;
            $DB->insert_record('local_apiquery_queries', $record);
            $countImported++;
        }
    }

    // Build summary message.
    $parts = [];
    if ($countImported > 0) {
        $parts[] = get_string('import_success',     'local_apiquery', $countImported);
    }
    if ($countOverwritten > 0) {
        $parts[] = get_string('import_overwritten', 'local_apiquery', $countOverwritten);
    }
    if ($countSkipped > 0) {
        $parts[] = get_string('import_skipped',     'local_apiquery', $countSkipped);
    }

    $message = implode(' ', $parts);
    $type    = ($countImported + $countOverwritten > 0)
        ? \core\output\notification::NOTIFY_SUCCESS
        : \core\output\notification::NOTIFY_WARNING;

    redirect(new moodle_url('/local/apiquery/admin/index.php'), $message, null, $type);
}

echo $OUTPUT->footer();
