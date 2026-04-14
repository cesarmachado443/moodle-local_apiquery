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
 * Upgrade steps for local_apiquery.
 *
 * @package    local_apiquery
 * @copyright  2026 CESW <cesarmachado443@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool
 */
function xmldb_local_apiquery_upgrade(int $oldversion): bool {
    // Future upgrade steps will go here.
    // Example:
    // if ($oldversion < 2026040702) {
    //     $dbman = $DB->get_manager();
    //     ... alter table ...
    //     upgrade_plugin_savepoint(true, 2026040702, 'local', 'apiquery');
    // }
    return true;
}
