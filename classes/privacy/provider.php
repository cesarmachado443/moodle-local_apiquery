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
 * Privacy API implementation.
 *
 * @package    local_apiquery
 * @copyright  2026 CESW <cesarmachado443@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_apiquery\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;

/**
 * Privacy provider for local_apiquery.
 *
 * This plugin stores query execution logs that include the userid
 * of the user whose webservice token was used to make the API call.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Declares what personal data this plugin stores.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_apiquery_logs',
            [
                'userid'       => 'privacy:metadata:local_apiquery_logs:userid',
                'params_used'  => 'privacy:metadata:local_apiquery_logs:params_used',
                'timecreated'  => 'privacy:metadata:local_apiquery_logs:timecreated',
            ],
            'privacy:metadata:local_apiquery_logs'
        );

        return $collection;
    }

    /**
     * Returns the contexts that contain data for the given user.
     * Only includes the system context if the user has real log entries.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_apiquery_logs} l ON l.userid = :userid
                 WHERE ctx.contextlevel = :contextlevel";
        $params = ['userid' => $userid, 'contextlevel' => CONTEXT_SYSTEM];
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Returns the users who have data within the given context.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $sql = "SELECT DISTINCT userid FROM {local_apiquery_logs} WHERE userid IS NOT NULL";
        $userlist->add_from_sql('userid', $sql, []);
    }

    /**
     * Exports the user's data (required by GDPR).
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $logs = $DB->get_records('local_apiquery_logs', ['userid' => $userid]);

        if (!empty($logs)) {
            $context = \context_system::instance();
            \core_privacy\local\request\writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_apiquery'), get_string('execution_logs', 'local_apiquery')],
                (object)['logs' => array_values($logs)]
            );
        }
    }

    /**
     * Deletes all plugin data within the given context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context instanceof \context_system) {
            $DB->delete_records('local_apiquery_logs');
        }
    }

    /**
     * Deletes data for a specific user.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $DB->delete_records('local_apiquery_logs', ['userid' => $userid]);
    }

    /**
     * Deletes data for multiple users within the given context.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_apiquery_logs', "userid $insql", $inparams);
    }
}
