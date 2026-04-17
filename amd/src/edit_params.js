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
 * JavaScript module for parameter row management and SQL placeholder detection.
 *
 * @module     local_apiquery/edit_params
 * @copyright  2026 CESW <cesarmachado443@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {

    return {
        /**
         * Initialize the parameter management controls.
         *
         * @param {Object} strings Language strings for hints and placeholders
         * @param {string} strings.detected - "Detected placeholders:" text
         * @param {string} strings.repeated - "appears multiple times" text
         * @param {string} strings.declare - "Declare them below" text
         * @param {string} strings.placeholder_param_name - Placeholder for param name input
         * @param {string} strings.placeholder_no_default - Placeholder for default value input
         */
        init: function(strings) {
            // Add a parameter row dynamically.
            $('#add-param').on('click', function() {
                var index = $('.param-row').length;

                // Build the new row HTML in JavaScript (no inline template).
                var rowHtml = '<tr class="param-row">' +
                    '<td><input type="text" name="param_name[]" class="form-control form-control-sm font-monospace" placeholder="' + (strings.placeholder_param_name || 'param_name') + '"></td>' +
                    '<td>' +
                        '<select name="param_type[]" class="form-select form-select-sm">' +
                            '<option value="int">int (integer)</option>' +
                            '<option value="text">text (string)</option>' +
                            '<option value="float">float (decimal number)</option>' +
                            '<option value="bool">bool (boolean)</option>' +
                        '</select>' +
                    '</td>' +
                    '<td class="text-center"><input type="checkbox" name="param_required[]" value="' + index + '" class="form-check-input"></td>' +
                    '<td><input type="text" name="param_default[]" class="form-control form-control-sm" placeholder="' + (strings.placeholder_no_default || '') + '"></td>' +
                    '<td><button type="button" class="btn btn-sm btn-outline-danger remove-param">✕</button></td>' +
                '</tr>';

                $('#params-container').append(rowHtml);
            });

            // Remove a parameter row.
            $(document).on('click', '.remove-param', function() {
                $(this).closest('tr').remove();
            });

            // Detect SQL placeholders to suggest parameter declarations.
            $('#id_sqlquery').on('blur', function() {
                var sql = $(this).val();
                var regex = /:([a-zA-Z_][a-zA-Z0-9_]*)/g;
                var allMatches = [];
                var match;
                while ((match = regex.exec(sql)) !== null) {
                    allMatches.push(match[1]);
                }

                // Deduplicate: if :since appears twice in the SQL it is ONE parameter.
                var unique = [...new Set(allMatches)];
                var repeated = allMatches.filter((v, i, a) => a.indexOf(v) !== i);

                if (unique.length > 0) {
                    var hint = strings.detected + unique.map(n => ':' + n).join(', ');
                    if (repeated.length > 0) {
                        hint += ' ⚠️ (' + [...new Set(repeated)].map(n => ':' + n).join(', ') + ' ' + strings.repeated + ')';
                    }
                    hint += ' ' + strings.declare;
                    $('#placeholder-hint').text(hint).show();
                } else {
                    // Hide hint when no placeholders detected.
                    $('#placeholder-hint').hide();
                }
            });
        }
    };
});
