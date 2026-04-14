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
 * Webservice and function definitions.
 *
 * @package    local_apiquery
 * @copyright  2026 CESW <cesarmachado443@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

// External service grouping all plugin functions.
$services = [
    'Api Custom Queries' => [
        'functions'       => [
            'local_apiquery_execute_query',
            'local_apiquery_list_queries',
        ],
        'restrictedusers' => 0,  // any user with a token can use it
        'enabled'         => 1,
        'shortname'       => 'local_apiquery',
        'downloadfiles'   => 0,
        'uploadfiles'     => 0,
    ],
];

$functions = [
    'local_apiquery_execute_query' => [
        'classname'      => 'local_apiquery\external',
        'methodname'     => 'execute_query',
        'description'    => 'Execute a SQL query configured by the administrator with the given parameters',
        'type'           => 'read',
        'ajax'           => false,
        'loginrequired'  => true,
        'capabilities'   => 'local/apiquery:execute',
        'services'       => ['local_apiquery'],
    ],

    'local_apiquery_list_queries' => [
        'classname'      => 'local_apiquery\external',
        'methodname'     => 'list_queries',
        'description'    => 'List all available queries with their declared parameters',
        'type'           => 'read',
        'ajax'           => false,
        'loginrequired'  => true,
        'capabilities'   => 'local/apiquery:execute',
        'services'       => ['local_apiquery'],
    ],
];
