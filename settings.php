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
 * Plugin settings and admin menu entries.
 *
 * @package    local_apiquery
 * @copyright  2026 CESW <cesarmachado443@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Add the main node under "Local plugins".
    $ADMIN->add('localplugins', new admin_category(
        'local_apiquery_settings',
        get_string('pluginname', 'local_apiquery')
    ));

    // Query list and management page.
    $ADMIN->add('local_apiquery_settings', new admin_externalpage(
        'local_apiquery_queries',
        get_string('manage_queries', 'local_apiquery'),
        new moodle_url('/local/apiquery/admin/index.php'),
        'local/apiquery:manage'
    ));

    // Execution logs page.
    $ADMIN->add('local_apiquery_settings', new admin_externalpage(
        'local_apiquery_logs',
        get_string('execution_logs', 'local_apiquery'),
        new moodle_url('/local/apiquery/admin/logs.php'),
        'local/apiquery:manage'
    ));
}
