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
 * External function: the Tut Admin rules.
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

/** The Tut admin rules as JSON (saved in Tut Admin by a Tut admin). */
class policy extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }
    public static function execute(): array {
        self::validate_context(\context_system::instance());
        util::require_tut_user();
        $p = util::policy();
        return ['policy' => $p ? json_encode($p) : '', 'savedon' => (string)get_config('local_asktut', 'policysaved')];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'policy' => new external_value(PARAM_RAW, 'JSON, empty = defaults'),
            'savedon' => new external_value(PARAM_RAW, 'when and by whom'),
        ]);
    }
}
