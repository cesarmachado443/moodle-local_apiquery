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
 * Capability definitions.
 *
 * @package    local_apiquery
 * @copyright  2026 CESW <cesarmachado443@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // Permiso para ejecutar queries via API (se asigna al rol del token webservice).
    // RISK_PERSONAL: puede retornar datos personales de usuarios.
    // RISK_DATALOSS: permite ejecutar DML (INSERT/UPDATE/DELETE) si la query lo tiene.
    'local/apiquery:execute' => [
        'riskbitmask'  => RISK_PERSONAL | RISK_DATALOSS,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Permiso para administrar queries desde la UI (solo admins).
    // RISK_CONFIG: puede crear queries SQL arbitrarias (aunque validadas).
    // RISK_DATALOSS: puede crear queries DML que modifiquen datos de Moodle.
    'local/apiquery:manage' => [
        'riskbitmask'  => RISK_CONFIG | RISK_DATALOSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
