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
 * This plugin stores:
 * - Query execution logs that include the userid of the user whose webservice token was used.
 * - Custom queries that include the createdby field identifying the user who created them.
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

        $collection->add_database_table(
            'local_apiquery_queries',
            [
                'createdby'    => 'privacy:metadata:local_apiquery_queries:createdby',
                'timecreated'  => 'privacy:metadata:local_apiquery_queries:timecreated',
            ],
            'privacy:metadata:local_apiquery_queries'
        );

        return $collection;
    }

    /**
     * Returns the contexts that contain data for the given user.
     * Includes the system context if the user has log entries or created queries.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Add context if user has execution logs.
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_apiquery_logs} l ON l.userid = :userid
                 WHERE ctx.contextlevel = :contextlevel";
        $params = ['userid' => $userid, 'contextlevel' => CONTEXT_SYSTEM];
        $contextlist->add_from_sql($sql, $params);

        // Add context if user created queries.
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_apiquery_queries} q ON q.createdby = :userid2
                 WHERE ctx.contextlevel = :contextlevel2";
        $params = ['userid2' => $userid, 'contextlevel2' => CONTEXT_SYSTEM];
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

        // Users who executed queries.
        $sql = "SELECT DISTINCT userid FROM {local_apiquery_logs} WHERE userid IS NOT NULL";
        $userlist->add_from_sql('userid', $sql, []);

        // Users who created queries.
        $sql = "SELECT DISTINCT createdby FROM {local_apiquery_queries} WHERE createdby IS NOT NULL";
        $userlist->add_from_sql('createdby', $sql, []);
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
        $context = \context_system::instance();

        // Export execution logs.
        $logs = $DB->get_records('local_apiquery_logs', ['userid' => $userid]);
        if (!empty($logs)) {
            \core_privacy\local\request\writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_apiquery'), get_string('execution_logs', 'local_apiquery')],
                (object)['logs' => array_values($logs)]
            );
        }

        // Export queries created by the user.
        $queries = $DB->get_records('local_apiquery_queries', ['createdby' => $userid]);
        if (!empty($queries)) {
            \core_privacy\local\request\writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_apiquery'), get_string('manage_queries', 'local_apiquery')],
                (object)['queries' => array_values($queries)]
            );
        }
    }

    /**
     * Deletes all plugin data within the given context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context instanceof \context_system) {
            // Delete all execution logs.
            $DB->delete_records('local_apiquery_logs');

            // Anonymize createdby field for all queries (do not delete queries themselves).
            $DB->set_field('local_apiquery_queries', 'createdby', null);
        }
    }

    /**
     * Deletes data for a specific user.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;

        // Delete execution logs for this user.
        $DB->delete_records('local_apiquery_logs', ['userid' => $userid]);

        // Anonymize queries created by this user (do not delete queries themselves).
        $DB->set_field('local_apiquery_queries', 'createdby', null, ['createdby' => $userid]);
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

        $userids = $userlist->get_userids();
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Delete execution logs for these users.
        $DB->delete_records_select('local_apiquery_logs', "userid $insql", $inparams);

        // Anonymize queries created by these users (do not delete queries themselves).
        // Use set_field_select() instead of execute() for better compatibility and performance.
        $DB->set_field_select('local_apiquery_queries', 'createdby', null, "createdby $insql", $inparams);
    }
}
