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

$queryId = required_param('id', PARAM_INT);
$query   = $DB->get_record('local_apiquery_queries', ['id' => $queryId], '*', MUST_EXIST);

// Override the page URL set by admin_externalpage_setup (which defaults to the index page).
$PAGE->set_url(new moodle_url('/local/apiquery/admin/test.php', ['id' => $queryId]));
$params  = json_decode($query->parameters ?? '[]', true) ?: [];

$results    = null;
$execError  = null;
$execMs     = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {

    $sqlParams = [];
    foreach ($params as $param) {
        $value = optional_param('tp_' . $param['name'], $param['default'] ?? null, PARAM_RAW);

        if ($value === null || $value === '') {
            if ($param['required']) {
                $execError = get_string('error_param_required', 'local_apiquery', $param['name']);
                break;
            }
            continue;
        }

        $sqlParams[$param['name']] = match($param['type']) {
            'int'   => (int) $value,
            'float' => (float) $value,
            'bool'  => (bool) $value,
            default => (string) $value,
        };
    }

    if (!$execError) {
        try {
            $start = microtime(true);

            // Expand duplicate placeholders: if :since appears twice,
            // Moodle needs 2 values — rename the 2nd occurrence to :since_dup2.
            $expandedParams  = $sqlParams;
            $occurrenceCount = [];
            $expandedSql = preg_replace_callback(
                '/:([a-zA-Z_][a-zA-Z0-9_]*)/',
                function ($matches) use (&$occurrenceCount, &$expandedParams, $sqlParams) {
                    $name = $matches[1];
                    if (!isset($occurrenceCount[$name])) {
                        $occurrenceCount[$name] = 1;
                        if (array_key_exists($name, $sqlParams)) {
                            $expandedParams[$name] = $sqlParams[$name];
                        }
                        return ':' . $name;
                    } else {
                        $occurrenceCount[$name]++;
                        $dupName = $name . '_dup' . $occurrenceCount[$name];
                        if (array_key_exists($name, $sqlParams)) {
                            $expandedParams[$dupName] = $sqlParams[$name];
                        }
                        return ':' . $dupName;
                    }
                },
                $query->sqlquery
            );

            // Detect DML vs SELECT to use the correct Moodle database method.
            $isDml = (bool) preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE)\b/i', $expandedSql);
            if ($isDml) {
                $DB->execute($expandedSql, $expandedParams);
                $records = [['result' => get_string('dml_success', 'local_apiquery')]];
            } else {
                $recordset = $DB->get_recordset_sql($expandedSql, $expandedParams);
                $records = [];
                foreach ($recordset as $r) { $records[] = (array) $r; }
                $recordset->close();
            }
            $results = $records;
            $execMs  = (int)((microtime(true) - $start) * 1000);
        } catch (Exception $e) {
            $execError = $e->getMessage();
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

// Test parameters form.
$sesskey = sesskey();
?>
<div class="card mb-4">
  <div class="card-header fw-bold"><?= get_string('test_params_card', 'local_apiquery') ?></div>
  <div class="card-body">
    <form method="post">
      <input type="hidden" name="sesskey" value="<?= $sesskey ?>">
      <?php if (empty($params)): ?>
        <p class="text-muted"><?= get_string('no_params_declared', 'local_apiquery') ?></p>
      <?php else: ?>
        <?php foreach ($params as $p): ?>
        <div class="mb-3 row align-items-center">
          <label class="col-sm-3 col-form-label fw-semibold">
            <code>:<?= htmlspecialchars($p['name']) ?></code>
            <span class="badge bg-light text-dark ms-1"><?= $p['type'] ?></span>
            <?php if ($p['required']): ?>
              <span class="text-danger">*</span>
            <?php endif; ?>
          </label>
          <div class="col-sm-5">
            <input type="text" name="tp_<?= htmlspecialchars($p['name']) ?>"
                   class="form-control"
                   value="<?= htmlspecialchars(optional_param('tp_' . $p['name'], $p['default'] ?? '', PARAM_RAW)) ?>"
                   placeholder="<?= htmlspecialchars($p['default'] ?? '') ?>">
          </div>
          <div class="col-sm-4 text-muted small">
            <?= $p['required'] ? get_string('param_required_label', 'local_apiquery') : get_string('param_optional_label', 'local_apiquery') ?>
            <?= !empty($p['default']) ? " · default: {$p['default']}" : '' ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <button type="submit" class="btn btn-primary"><?= get_string('execute_btn', 'local_apiquery') ?></button>
    </form>
  </div>
</div>

<?php if ($execError): ?>
  <div class="alert alert-danger"><strong>Error:</strong> <?= htmlspecialchars($execError) ?></div>

<?php elseif ($results !== null): ?>
  <?php
    $successData = new stdClass();
    $successData->ms   = $execMs;
    $successData->rows = count($results);
  ?>
  <div class="alert alert-success">
    ✅ <?= get_string('test_success', 'local_apiquery', $successData) ?>
  </div>

  <?php if (empty($results)): ?>
    <p class="text-muted"><?= get_string('no_rows_returned', 'local_apiquery') ?></p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-striped table-bordered">
        <thead class="table-dark">
          <tr>
            <?php foreach (array_keys($results[0]) as $col): ?>
              <th><?= htmlspecialchars($col) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_slice($results, 0, 100) as $row): ?>
          <tr>
            <?php foreach ($row as $val): ?>
              <td><?= htmlspecialchars((string)($val ?? 'NULL')) ?></td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (count($results) > 100): ?>
        <p class="text-muted small"><?= get_string('showing_first_rows', 'local_apiquery', count($results)) ?></p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<!-- Current SQL (read-only) -->
<div class="card mt-4">
  <div class="card-header"><?= get_string('sql_card_title', 'local_apiquery') ?></div>
  <div class="card-body">
    <pre class="bg-dark text-light p-3 rounded mb-0"><?= htmlspecialchars($query->sqlquery) ?></pre>
  </div>
</div>

<?php
echo $OUTPUT->footer();
