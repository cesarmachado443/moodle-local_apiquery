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
 * Plugin version definition.
 *
 * @package    local_apiquery
 * @copyright  2026 CESW <cesarmachado443@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Plugin: local_apiquery
// Allows Moodle administrators to configure custom SQL queries
// and expose them as REST endpoints with typed parameters.

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_apiquery';
$plugin->version   = 2026040800;
$plugin->requires  = 2022112800; // Moodle 4.1+ (oldest supported LTS)
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.0';
