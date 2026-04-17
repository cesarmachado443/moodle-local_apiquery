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
 * JavaScript module for export query selection (select all / deselect all).
 *
 * @module     local_apiquery/export_select
 * @copyright  2026 CESW <cesarmachado443@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {

    return {
        /**
         * Initialize the export selection controls.
         */
        init: function() {
            // Select all / deselect all buttons.
            $('#export-select-all').on('click', function() {
                $('.export-qid').prop('checked', true);
            });
            $('#export-deselect-all').on('click', function() {
                $('.export-qid').prop('checked', false);
            });

            // Header checkbox toggle (moved from inline onchange).
            $('#export-check-all').on('change', function() {
                $('.export-qid').prop('checked', this.checked);
            });
        }
    };
});
