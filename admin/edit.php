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
 * Admin page - create or edit a query.
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

$query_id  = optional_param('id', 0, PARAM_INT);
$is_edit   = $query_id > 0;

// Override the page URL set by admin_externalpage_setup (which defaults to the index page).
$PAGE->set_url(new moodle_url('/local/apiquery/admin/edit.php', $is_edit ? ['id' => $query_id] : []));

// Load the existing record when editing.
$existing = $is_edit ? $DB->get_record('local_apiquery_queries', ['id' => $query_id], '*', MUST_EXIST) : null;

// Process submitted form.
$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {

    $shortname   = trim(required_param('shortname', PARAM_ALPHANUMEXT));
    $displayname = trim(required_param('displayname', PARAM_TEXT));
    $description = trim(optional_param('description', '', PARAM_TEXT));
    $sqlquery    = trim(required_param('sqlquery', PARAM_TEXT));
    $enabled     = optional_param('enabled', 0, PARAM_INT);

    // Read declared parameters from the form.
    // optional_param_array() is the secure Moodle method for handling array inputs.
    $param_names    = optional_param_array('param_name',    [], PARAM_ALPHANUMEXT);
    $param_types    = optional_param_array('param_type',    [], PARAM_ALPHA);
    $param_required = optional_param_array('param_required', [], PARAM_INT);
    $param_defaults = optional_param_array('param_default', [], PARAM_TEXT);

    $declared_params = [];
    foreach ($param_names as $i => $name) {
        $name = trim($name);
        if (empty($name)) continue;
        $declared_params[] = [
            'name'     => $name,
            'type'     => $param_types[$i]    ?? 'text',
            'required' => isset($param_required[$i]) ? 1 : 0,
            'default'  => $param_defaults[$i] ?? '',
        ];
    }

    // Basic field validation.
    if (empty($shortname))   $errors[] = get_string('error_shortname_required',   'local_apiquery');
    if (empty($displayname)) $errors[] = get_string('error_displayname_required', 'local_apiquery');
    if (empty($sqlquery))    $errors[] = get_string('error_sql_required',         'local_apiquery');

    // Validate shortname uniqueness (skip when editing the same record).
    if (!empty($shortname)) {
        $existing_check = $DB->get_record('local_apiquery_queries', ['shortname' => $shortname]);
        if ($existing_check && (int)$existing_check->id !== (int)$query_id) {
            $errors[] = get_string('error_shortname_duplicate', 'local_apiquery', $shortname);
        }
    }

    // Validate SQL with the security validator.
    $sql_warnings = [];
    if (!empty($sqlquery)) {
        $validation   = \local_apiquery\sql_validator::validate($sqlquery);
        $errors       = array_merge($errors, $validation['errors']);
        $sql_warnings  = $validation['warnings'] ?? [];

        // If there are warnings and the admin has not confirmed, block saving to force confirmation.
        $confirm_warnings = optional_param('confirm_warnings', 0, PARAM_INT);
        if (!empty($sql_warnings) && !$confirm_warnings) {
            // Not a blocking error — just waiting for explicit confirmation.
            // The confirmation form is shown instead of saving.
        }

        // Validate consistency between declared parameters and SQL placeholders.
        if (empty($validation['errors']) && !empty($declared_params)) {
            $consistency_errors = \local_apiquery\sql_validator::validate_params_consistency($sqlquery, $declared_params);
            $errors = array_merge($errors, $consistency_errors);
        }
    }

    // If there are warnings and they have not been confirmed, show the confirmation screen.
    $confirm_warnings = optional_param('confirm_warnings', 0, PARAM_INT);
    if (empty($errors) && !empty($sql_warnings) && !$confirm_warnings) {
        // Do not save yet — show warnings for the admin to confirm.
        $needs_confirmation = true;
    } else {
        $needs_confirmation = false;
    }

    if (empty($errors) && !$needs_confirmation) {
        $record = new stdClass();
        $record->shortname    = $shortname;
        $record->displayname  = $displayname;
        $record->description  = $description;
        $record->sqlquery     = $sqlquery;
        $record->parameters   = json_encode(array_values($declared_params));
        $record->enabled      = $enabled;
        $record->timemodified = time();
        $record->createdby    = $USER->id;

        if ($is_edit) {
            $record->id = $query_id;
            $DB->update_record('local_apiquery_queries', $record);
            $message = get_string('query_updated', 'local_apiquery');
        } else {
            $record->timecreated = time();
            $DB->insert_record('local_apiquery_queries', $record);
            $message = get_string('query_created', 'local_apiquery');
        }

        redirect(new moodle_url('/local/apiquery/admin/index.php'), $message, null, \core\output\notification::NOTIFY_SUCCESS);
    } // end if empty($errors) && !$needs_confirmation

    // On errors, re-populate with submitted data to avoid losing work.
    if ($existing === null) $existing = new stdClass();
    $existing->shortname   = $shortname;
    $existing->displayname = $displayname;
    $existing->description = $description;
    $existing->sqlquery    = $sqlquery;
    $existing->enabled     = $enabled;
    $existing->parameters  = json_encode($declared_params);
}

$needs_confirmation = $needs_confirmation ?? false;
$sql_warnings       = $sql_warnings ?? [];
$page_title = $is_edit ? get_string('edit_query', 'local_apiquery') : get_string('new_query', 'local_apiquery');
$PAGE->set_title($page_title);
$PAGE->set_heading($page_title);

// Minimal JS for the SQL editor and dynamic parameter rows.
$PAGE->requires->js_call_amd('local_apiquery/edit_params', 'init', [[
    'detected' => get_string('hint_placeholders_detected', 'local_apiquery'),
    'repeated' => get_string('hint_placeholder_repeated', 'local_apiquery'),
    'declare' => get_string('hint_declare_once', 'local_apiquery'),
    'placeholder_param_name' => get_string('placeholder_param_name', 'local_apiquery'),
    'placeholder_no_default' => get_string('placeholder_no_default', 'local_apiquery'),
]]);

echo $OUTPUT->header();
echo $OUTPUT->heading($page_title);

// ── DML WARNING CONFIRMATION SCREEN ──────────────────────────────────────
// If the SQL contains DML (DELETE, UPDATE, INSERT, REPLACE) show warnings
// and require explicit confirmation before saving.
if (!empty($needs_confirmation) && empty($errors)):

    echo $OUTPUT->notification(
        '<strong>' . get_string('warning_dml_title', 'local_apiquery') . '</strong><br>' .
        get_string('warning_dml_review', 'local_apiquery'),
        'warning'
    );

    // Build params array for template.
    $current_params = json_decode($existing->parameters ?? '[]', true) ?: [];
    $params_array = [];
    foreach ($current_params as $i => $p) {
        $params_array[] = [
            'index'    => $i,
            'name'     => htmlspecialchars($p['name'] ?? ''),
            'type'     => htmlspecialchars($p['type'] ?? 'text'),
            'required' => (int)($p['required'] ?? 0),
            'default'  => htmlspecialchars($p['default'] ?? ''),
        ];
    }

    $confirmation_data = [
        'warnings'     => array_map('htmlspecialchars', $sql_warnings),
        'form_action'  => (new moodle_url('/local/apiquery/admin/edit.php', $is_edit ? ['id' => $query_id] : []))->out(false),
        'sesskey'      => sesskey(),
        'shortname'    => htmlspecialchars($existing->shortname ?? ''),
        'displayname'  => htmlspecialchars($existing->displayname ?? ''),
        'description'  => htmlspecialchars($existing->description ?? ''),
        'sqlquery'     => htmlspecialchars($existing->sqlquery ?? ''),
        'enabled'      => (int)($existing->enabled ?? 1),
        'params'       => $params_array,
        'confirm_btn'  => get_string('confirm_dml', 'local_apiquery'),
        'back_btn'     => get_string('back_to_edit', 'local_apiquery'),
        'back_url'     => (new moodle_url('/local/apiquery/admin/edit.php', $is_edit ? ['id' => $query_id] : []))->out(false),
    ];

    echo $OUTPUT->render_from_template('local_apiquery/edit_dml_confirmation', $confirmation_data);
    echo $OUTPUT->footer();
    exit;
endif;
// ── END DML WARNING SCREEN ────────────────────────────────────────────────

// Display validation errors.
foreach ($errors as $error) {
    echo $OUTPUT->notification($error, 'error');
}

// Build params array for template.
$current_params = json_decode($existing->parameters ?? '[]', true) ?: [];
$params_data = [];
foreach ($current_params as $i => $param) {
    $type = $param['type'] ?? 'text';
    $params_data[] = [
        'index'            => $i,
        'name'             => htmlspecialchars($param['name']),
        'type'             => htmlspecialchars($type),
        'is_int'           => $type === 'int',
        'is_text'          => $type === 'text',
        'is_float'         => $type === 'float',
        'is_bool'          => $type === 'bool',
        'required'         => ($param['required'] ?? 0) ? '1' : '0',
        'required_checked' => (bool)($param['required'] ?? 0),
        'default'          => htmlspecialchars($param['default'] ?? ''),
    ];
}

$form_data = [
    'form_action'           => (new moodle_url('/local/apiquery/admin/edit.php', $is_edit ? ['id' => $query_id] : []))->out(false),
    'sesskey'               => sesskey(),
    'section_identification'=> get_string('section_identification', 'local_apiquery'),
    'section_sql'           => get_string('section_sql', 'local_apiquery'),
    'section_params'        => get_string('section_params', 'local_apiquery'),
    'section_status'        => get_string('section_status', 'local_apiquery'),
    'label_shortname'       => get_string('shortname', 'local_apiquery'),
    'label_displayname'     => get_string('displayname', 'local_apiquery'),
    'label_description'     => get_string('description', 'local_apiquery'),
    'label_sqlquery'        => 'SQL',
    'label_enabled'         => get_string('field_enabled_label', 'local_apiquery'),
    'shortname'             => htmlspecialchars($existing->shortname ?? ''),
    'displayname'           => htmlspecialchars($existing->displayname ?? ''),
    'description'           => htmlspecialchars($existing->description ?? ''),
    'sqlquery'              => htmlspecialchars($existing->sqlquery ?? ''),
    'enabled'               => (bool)($existing->enabled ?? 1),
    'params'                => $params_data,
    'col_name'              => get_string('param_col_name', 'local_apiquery'),
    'col_type'              => get_string('param_col_type', 'local_apiquery'),
    'col_required'          => get_string('param_col_required', 'local_apiquery'),
    'col_default'           => get_string('param_col_default', 'local_apiquery'),
    'col_actions'           => '',
    'add_param_btn'         => get_string('add_param', 'local_apiquery'),
    'save_btn'              => $is_edit ? '💾 ' . get_string('savechanges') : '✅ ' . get_string('create', 'local_apiquery'),
    'cancel_text'           => get_string('cancel'),
    'cancel_url'            => (new moodle_url('/local/apiquery/admin/index.php'))->out(false),
    'help_shortname'        => get_string('field_shortname_hint', 'local_apiquery'),
    'apicall_shortname'     => get_string('field_shortname_apicall', 'local_apiquery'),
    'help_sqlquery'         => get_string('sql_security_desc', 'local_apiquery'),
    'sql_security_title'    => get_string('sql_security_title', 'local_apiquery'),
    'parameters_intro'      => get_string('parameters', 'local_apiquery'),
    'placeholder_shortname' => get_string('placeholder_shortname_ex', 'local_apiquery'),
    'placeholder_displayname' => get_string('placeholder_displayname_ex', 'local_apiquery'),
    'placeholder_description' => get_string('placeholder_description_ex', 'local_apiquery'),
    'placeholder_sql'       => get_string('placeholder_sql_ex', 'local_apiquery'),
    'placeholder_param_name' => get_string('placeholder_param_name', 'local_apiquery'),
    'placeholder_no_default' => get_string('placeholder_no_default', 'local_apiquery'),
    'placeholder_hint_id'   => 'placeholder-hint',
];

echo $OUTPUT->render_from_template('local_apiquery/edit_form', $form_data);

echo $OUTPUT->footer();
