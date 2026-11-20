<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * External function: who the signed-in person is and what Tut may do for them.
 *
 * @package    local_asktut
 * @copyright  2026 Oizn Systems Inc. {@link https://asktut.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_asktut\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;
use local_asktut\local\util;

/** Who is calling: identity, language, time zone, roles, Tut access. */
class me extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }
    public static function execute(): array {
        global $USER, $CFG;
        self::validate_context(\context_system::instance());
        return [
            'id' => (int)$USER->id,
            'username' => $USER->username,
            'fullname' => fullname($USER),
            'email' => (string)$USER->email,
            'lang' => current_language(),
            'timezone' => \core_date::get_user_timezone($USER),
            'roles' => util::roles((int)$USER->id),
            'tutuser' => util::can_use(),
            'tutadmin' => util::can_manage(),
            'siteurl' => $CFG->wwwroot,
            'sitename' => format_string(get_site()->fullname),
        ];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'user id'),
            'username' => new external_value(PARAM_RAW, 'username'),
            'fullname' => new external_value(PARAM_RAW, 'full name'),
            'email' => new external_value(PARAM_RAW, 'email'),
            'lang' => new external_value(PARAM_RAW, 'language'),
            'timezone' => new external_value(PARAM_RAW, 'time zone'),
            'roles' => new external_multiple_structure(new external_value(PARAM_RAW, 'role shortname')),
            'tutuser' => new external_value(PARAM_BOOL, 'may use Tut'),
            'tutadmin' => new external_value(PARAM_BOOL, 'Tut admin'),
            'siteurl' => new external_value(PARAM_RAW, 'site URL'),
            'sitename' => new external_value(PARAM_RAW, 'site name'),
        ]);
    }
}
