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

$queryId  = optional_param('id', 0, PARAM_INT);
$isEdit   = $queryId > 0;

// Override the page URL set by admin_externalpage_setup (which defaults to the index page).
$PAGE->set_url(new moodle_url('/local/apiquery/admin/edit.php', $isEdit ? ['id' => $queryId] : []));

// Load the existing record when editing.
$existing = $isEdit ? $DB->get_record('local_apiquery_queries', ['id' => $queryId], '*', MUST_EXIST) : null;

// Process submitted form.
$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {

    $shortname   = trim(required_param('shortname', PARAM_ALPHANUMEXT));
    $displayname = trim(required_param('displayname', PARAM_TEXT));
    $description = trim(optional_param('description', '', PARAM_TEXT));
    $sqlquery    = trim(required_param('sqlquery', PARAM_RAW));
    $enabled     = optional_param('enabled', 0, PARAM_INT);

    // Read declared parameters from the form.
    // clean_param_array() is the correct Moodle method for sanitising arrays of inputs.
    // optional_param() only handles single values, not dynamic form arrays.
    $paramNames    = clean_param_array((array)(isset($_POST['param_name'])    ? $_POST['param_name']    : []), PARAM_ALPHANUMEXT);
    $paramTypes    = clean_param_array((array)(isset($_POST['param_type'])    ? $_POST['param_type']    : []), PARAM_ALPHA);
    $paramRequired = (array)(isset($_POST['param_required']) ? $_POST['param_required'] : []);
    $paramDefaults = clean_param_array((array)(isset($_POST['param_default']) ? $_POST['param_default'] : []), PARAM_RAW);

    $declaredParams = [];
    foreach ($paramNames as $i => $name) {
        $name = trim($name);
        if (empty($name)) continue;
        $declaredParams[] = [
            'name'     => $name,
            'type'     => $paramTypes[$i]    ?? 'text',
            'required' => isset($paramRequired[$i]) ? 1 : 0,
            'default'  => $paramDefaults[$i] ?? '',
        ];
    }

    // Basic field validation.
    if (empty($shortname))   $errors[] = get_string('error_shortname_required',   'local_apiquery');
    if (empty($displayname)) $errors[] = get_string('error_displayname_required', 'local_apiquery');
    if (empty($sqlquery))    $errors[] = get_string('error_sql_required',         'local_apiquery');

    // Validate shortname uniqueness (skip when editing the same record).
    if (!empty($shortname)) {
        $existing_check = $DB->get_record('local_apiquery_queries', ['shortname' => $shortname]);
        if ($existing_check && (int)$existing_check->id !== (int)$queryId) {
            $errors[] = get_string('error_shortname_duplicate', 'local_apiquery', $shortname);
        }
    }

    // Validate SQL with the security validator.
    $sqlWarnings = [];
    if (!empty($sqlquery)) {
        $validation   = \local_apiquery\sql_validator::validate($sqlquery);
        $errors       = array_merge($errors, $validation['errors']);
        $sqlWarnings  = $validation['warnings'] ?? [];

        // If there are warnings and the admin has not confirmed, block saving to force confirmation.
        $confirmWarnings = optional_param('confirm_warnings', 0, PARAM_INT);
        if (!empty($sqlWarnings) && !$confirmWarnings) {
            // Not a blocking error — just waiting for explicit confirmation.
            // The confirmation form is shown instead of saving.
        }

        // Validate consistency between declared parameters and SQL placeholders.
        if (empty($validation['errors']) && !empty($declaredParams)) {
            $consistencyErrors = \local_apiquery\sql_validator::validate_params_consistency($sqlquery, $declaredParams);
            $errors = array_merge($errors, $consistencyErrors);
        }
    }

    // If there are warnings and they have not been confirmed, show the confirmation screen.
    $confirmWarnings = optional_param('confirm_warnings', 0, PARAM_INT);
    if (empty($errors) && !empty($sqlWarnings) && !$confirmWarnings) {
        // Do not save yet — show warnings for the admin to confirm.
        $needsConfirmation = true;
    } else {
        $needsConfirmation = false;
    }

    if (empty($errors) && !$needsConfirmation) {
        $record = new stdClass();
        $record->shortname    = $shortname;
        $record->displayname  = $displayname;
        $record->description  = $description;
        $record->sqlquery     = $sqlquery;
        $record->parameters   = json_encode(array_values($declaredParams));
        $record->enabled      = $enabled;
        $record->timemodified = time();
        $record->createdby    = $USER->id;

        if ($isEdit) {
            $record->id = $queryId;
            $DB->update_record('local_apiquery_queries', $record);
            $message = get_string('query_updated', 'local_apiquery');
        } else {
            $record->timecreated = time();
            $DB->insert_record('local_apiquery_queries', $record);
            $message = get_string('query_created', 'local_apiquery');
        }

        redirect(new moodle_url('/local/apiquery/admin/index.php'), $message, null, \core\output\notification::NOTIFY_SUCCESS);
    } // end if empty($errors) && !$needsConfirmation

    // On errors, re-populate with submitted data to avoid losing work.
    if ($existing === null) $existing = new stdClass();
    $existing->shortname   = $shortname;
    $existing->displayname = $displayname;
    $existing->description = $description;
    $existing->sqlquery    = $sqlquery;
    $existing->enabled     = $enabled;
    $existing->parameters  = json_encode($declaredParams);
}

$needsConfirmation = $needsConfirmation ?? false;
$sqlWarnings       = $sqlWarnings ?? [];
$pageTitle = $isEdit ? get_string('edit_query', 'local_apiquery') : get_string('new_query', 'local_apiquery');
$PAGE->set_title($pageTitle);
$PAGE->set_heading($pageTitle);

// Translated strings for inline JS.
$jsHintDetected = addslashes(get_string('hint_placeholders_detected', 'local_apiquery'));
$jsHintRepeated = addslashes(get_string('hint_placeholder_repeated',  'local_apiquery'));
$jsHintDeclare  = addslashes(get_string('hint_declare_once',          'local_apiquery'));

// Minimal JS for the SQL editor and dynamic parameter rows.
$PAGE->requires->js_amd_inline("
require(['jquery'], function(\$) {
    // Add a parameter row dynamically.
    \$('#add-param').on('click', function() {
        var template = \$('#param-row-template').html();
        var index    = \$('.param-row').length;
        template     = template.replace(/__INDEX__/g, index);
        \$('#params-container').append('<tr class=\"param-row\">' + template + '</tr>');
    });

    // Remove a parameter row.
    \$(document).on('click', '.remove-param', function() {
        \$(this).closest('tr').remove();
    });

    // Detect SQL placeholders to suggest parameter declarations.
    \$('#id_sqlquery').on('blur', function() {
        var sql   = \$(this).val();
        var regex = /:([a-zA-Z_][a-zA-Z0-9_]*)/g;
        var allMatches = [];
        var match;
        while ((match = regex.exec(sql)) !== null) {
            allMatches.push(match[1]);
        }

        // Deduplicate: if :since appears twice in the SQL it is ONE parameter.
        var unique   = [...new Set(allMatches)];
        var repeated = allMatches.filter((v, i, a) => a.indexOf(v) !== i);

        if (unique.length > 0) {
            var hint = '{$jsHintDetected}' + unique.map(n => ':' + n).join(', ');
            if (repeated.length > 0) {
                hint += ' ⚠️ (' + [...new Set(repeated)].map(n => ':' + n).join(', ') + ' {$jsHintRepeated})';
            }
            hint += ' {$jsHintDeclare}';
            \$('#placeholder-hint').text(hint);
        }
    });
});
");

echo $OUTPUT->header();
echo $OUTPUT->heading($pageTitle);

// ── DML WARNING CONFIRMATION SCREEN ──────────────────────────────────────
// If the SQL contains DML (DELETE, UPDATE, INSERT, REPLACE) show warnings
// and require explicit confirmation before saving.
if (!empty($needsConfirmation) && empty($errors)):

    echo $OUTPUT->notification(
        '<strong>' . get_string('warning_dml_title', 'local_apiquery') . '</strong><br>' .
        get_string('warning_dml_review', 'local_apiquery'),
        'warning'
    );

    echo html_writer::start_tag('ul', ['class' => 'alert alert-warning']);
    foreach ($sqlWarnings as $w) {
        echo html_writer::tag('li', htmlspecialchars($w));
    }
    echo html_writer::end_tag('ul');

    // Confirmation form — resubmits all data with confirm_warnings=1.
    $formAction = new moodle_url('/local/apiquery/admin/edit.php', $isEdit ? ['id' => $queryId] : []);
?>
    <form method="post" action="<?= $formAction ?>">
      <input type="hidden" name="sesskey"          value="<?= sesskey() ?>">
      <input type="hidden" name="confirm_warnings" value="1">
      <input type="hidden" name="shortname"        value="<?= htmlspecialchars($existing->shortname ?? '') ?>">
      <input type="hidden" name="displayname"      value="<?= htmlspecialchars($existing->displayname ?? '') ?>">
      <input type="hidden" name="description"      value="<?= htmlspecialchars($existing->description ?? '') ?>">
      <input type="hidden" name="sqlquery"         value="<?= htmlspecialchars($existing->sqlquery ?? '') ?>">
      <input type="hidden" name="enabled"          value="<?= (int)($existing->enabled ?? 1) ?>">
      <?php
      $currentParams = json_decode($existing->parameters ?? '[]', true) ?: [];
      foreach ($currentParams as $i => $p):
      ?>
        <input type="hidden" name="param_name[<?= $i ?>]"     value="<?= htmlspecialchars($p['name'] ?? '') ?>">
        <input type="hidden" name="param_type[<?= $i ?>]"     value="<?= htmlspecialchars($p['type'] ?? 'text') ?>">
        <input type="hidden" name="param_required[<?= $i ?>]" value="<?= (int)($p['required'] ?? 0) ?>">
        <input type="hidden" name="param_default[<?= $i ?>]"  value="<?= htmlspecialchars($p['default'] ?? '') ?>">
      <?php endforeach; ?>

      <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-warning">
          ⚠️ <?= get_string('confirm_dml', 'local_apiquery') ?>
        </button>
        <a href="<?= new moodle_url('/local/apiquery/admin/edit.php', $isEdit ? ['id' => $queryId] : []) ?>"
           class="btn btn-outline-secondary">
          <?= get_string('back_to_edit', 'local_apiquery') ?>
        </a>
      </div>
    </form>
<?php
    echo $OUTPUT->footer();
    exit;
endif;
// ── END DML WARNING SCREEN ────────────────────────────────────────────────

// Display validation errors.
foreach ($errors as $error) {
    echo $OUTPUT->notification($error, 'error');
}

// Current data for pre-populating the form.
$currentParams = json_decode($existing->parameters ?? '[]', true) ?: [];

$sesskey = sesskey();
$formAction = new moodle_url('/local/apiquery/admin/edit.php', $isEdit ? ['id' => $queryId] : []);

?>

<form method="post" action="<?= $formAction ?>">
<input type="hidden" name="sesskey" value="<?= $sesskey ?>">

<div class="mform">

  <!-- SECTION: Identification -->
  <fieldset class="card mb-4">
    <div class="card-header fw-bold"><?= get_string('section_identification', 'local_apiquery') ?></div>
    <div class="card-body">

      <div class="mb-3">
        <label for="shortname" class="form-label fw-semibold">
          <?= get_string('shortname', 'local_apiquery') ?> <span class="text-danger">*</span>
          <small class="text-muted"><?= get_string('field_shortname_hint', 'local_apiquery') ?></small>
        </label>
        <input type="text" id="shortname" name="shortname" class="form-control font-monospace"
               value="<?= htmlspecialchars($existing->shortname ?? '') ?>"
               placeholder="<?= get_string('placeholder_shortname_ex', 'local_apiquery') ?>" pattern="[a-zA-Z0-9_]+" required>
        <small class="form-text text-muted"><?= get_string('field_shortname_apicall', 'local_apiquery') ?></small>
      </div>

      <div class="mb-3">
        <label for="displayname" class="form-label fw-semibold"><?= get_string('displayname', 'local_apiquery') ?> <span class="text-danger">*</span></label>
        <input type="text" id="displayname" name="displayname" class="form-control"
               value="<?= htmlspecialchars($existing->displayname ?? '') ?>"
               placeholder="<?= get_string('placeholder_displayname_ex', 'local_apiquery') ?>" required>
      </div>

      <div class="mb-3">
        <label for="description" class="form-label fw-semibold"><?= get_string('description', 'local_apiquery') ?></label>
        <textarea id="description" name="description" class="form-control" rows="2"
                  placeholder="<?= get_string('placeholder_description_ex', 'local_apiquery') ?>"><?= htmlspecialchars($existing->description ?? '') ?></textarea>
      </div>

      <div class="form-check">
        <input type="checkbox" id="enabled" name="enabled" value="1" class="form-check-input"
               <?= ($existing->enabled ?? 1) ? 'checked' : '' ?>>
        <label for="enabled" class="form-check-label"><?= get_string('field_enabled_label', 'local_apiquery') ?></label>
      </div>
    </div>
  </fieldset>

  <!-- SECTION: SQL Query -->
  <fieldset class="card mb-4">
    <div class="card-header fw-bold"><?= get_string('section_sql', 'local_apiquery') ?></div>
    <div class="card-body">
      <div class="alert alert-warning py-2">
        <strong><?= get_string('sql_security_title', 'local_apiquery') ?></strong>
        <?= get_string('sql_security_desc', 'local_apiquery') ?>
      </div>

      <label for="id_sqlquery" class="form-label fw-semibold">SQL <span class="text-danger">*</span></label>
      <textarea id="id_sqlquery" name="sqlquery" class="form-control font-monospace" rows="12"
                placeholder="SELECT gg.userid, gg.finalgrade, gg.timemodified, gi.itemmodule&#10;FROM {grade_grades} gg&#10;JOIN {grade_items} gi ON gi.id = gg.itemid&#10;WHERE gg.timemodified > :since&#10;AND gi.courseid IN (:courseids)"><?= htmlspecialchars($existing->sqlquery ?? '') ?></textarea>
      <div id="placeholder-hint" class="form-text text-info mt-1"></div>
    </div>
  </fieldset>

  <!-- SECTION: Parameters -->
  <fieldset class="card mb-4">
    <div class="card-header fw-bold"><?= get_string('section_params', 'local_apiquery') ?></div>
    <div class="card-body">
      <p class="text-muted"><?= get_string('parameters', 'local_apiquery') ?>: <code>:placeholders</code></p>

      <table class="table table-bordered" id="params-table">
        <thead class="table-light">
          <tr>
            <th><?= get_string('param_col_name',     'local_apiquery') ?></th>
            <th><?= get_string('param_col_type',     'local_apiquery') ?></th>
            <th><?= get_string('param_col_required', 'local_apiquery') ?></th>
            <th><?= get_string('param_col_default',  'local_apiquery') ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody id="params-container">
          <?php foreach ($currentParams as $i => $param): ?>
          <tr class="param-row">
            <td><input type="text" name="param_name[<?= $i ?>]" class="form-control form-control-sm font-monospace"
                       value="<?= htmlspecialchars($param['name']) ?>" placeholder="since"></td>
            <td>
              <select name="param_type[<?= $i ?>]" class="form-select form-select-sm">
                <option value="int"   <?= ($param['type'] ?? '') === 'int'   ? 'selected' : '' ?>>int (integer)</option>
                <option value="text"  <?= ($param['type'] ?? '') === 'text'  ? 'selected' : '' ?>>text (string)</option>
                <option value="float" <?= ($param['type'] ?? '') === 'float' ? 'selected' : '' ?>>float (decimal number)</option>
                <option value="bool"  <?= ($param['type'] ?? '') === 'bool'  ? 'selected' : '' ?>>bool (boolean)</option>
              </select>
            </td>
            <td class="text-center">
              <input type="checkbox" name="param_required[<?= $i ?>]" value="1"
                     <?= ($param['required'] ?? 0) ? 'checked' : '' ?>>
            </td>
            <td><input type="text" name="param_default[<?= $i ?>]" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($param['default'] ?? '') ?>" placeholder="<?= get_string('placeholder_no_default', 'local_apiquery') ?>"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-param">✕</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Hidden row template for JS -->
      <script type="text/template" id="param-row-template">
        <td><input type="text" name="param_name[__INDEX__]" class="form-control form-control-sm font-monospace" placeholder="<?= get_string('placeholder_param_name', 'local_apiquery') ?>"></td>
        <td>
          <select name="param_type[__INDEX__]" class="form-select form-select-sm">
            <option value="int">int (integer)</option>
            <option value="text">text (string)</option>
            <option value="float">float (decimal number)</option>
            <option value="bool">bool (boolean)</option>
          </select>
        </td>
        <td class="text-center"><input type="checkbox" name="param_required[__INDEX__]" value="1"></td>
        <td><input type="text" name="param_default[__INDEX__]" class="form-control form-control-sm" placeholder="<?= get_string('placeholder_no_default', 'local_apiquery') ?>"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-param">✕</button></td>
      </script>

      <button type="button" id="add-param" class="btn btn-outline-secondary btn-sm"><?= get_string('add_param', 'local_apiquery') ?></button>
    </div>
  </fieldset>

  <!-- BUTTONS -->
  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">
      <?= $isEdit ? '💾 ' . get_string('savechanges') : '✅ ' . get_string('create', 'local_apiquery') ?>
    </button>
    <a href="<?= new moodle_url('/local/apiquery/admin/index.php') ?>" class="btn btn-outline-secondary">
      <?= get_string('cancel') ?>
    </a>
  </div>

</div><!-- .mform -->
</form>

<?php
echo $OUTPUT->footer();
