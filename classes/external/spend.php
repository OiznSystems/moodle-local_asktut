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
 * External function: this month's spend.
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
use core_external\external_value;
use local_asktut\local\util;

/** This calendar month's Tut spend in USD: the caller's and the whole site's (for limits). */
class spend extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }
    public static function execute(): array {
        global $DB, $USER;
        self::validate_context(\context_system::instance());
        util::require_tut_user();
        $start = make_timestamp((int)date('Y'), (int)date('n'), 1, 0, 0, 0, 'UTC');
        $user = (float)$DB->get_field_sql("SELECT COALESCE(SUM(cost_usd), 0) FROM {local_asktut_usage} WHERE userid = ? AND timecreated >= ?", [$USER->id, $start]);
        $org = (float)$DB->get_field_sql("SELECT COALESCE(SUM(cost_usd), 0) FROM {local_asktut_usage} WHERE timecreated >= ?", [$start]);
        return ['user' => $user, 'org' => $org];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'user' => new external_value(PARAM_FLOAT, 'USD this month, this user'),
            'org' => new external_value(PARAM_FLOAT, 'USD this month, whole site'),
        ]);
    }
}
