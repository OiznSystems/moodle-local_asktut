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
 * External function: single-use approval claim.
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

/** Approval cards are single use: the first claim of a key wins, every later claim gets claimed = false. */
class claim extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'key' => new external_value(PARAM_ALPHANUMEXT, 'approval key'),
            'approved' => new external_value(PARAM_BOOL, 'approved or denied'),
        ]);
    }
    public static function execute(string $key, bool $approved): array {
        global $DB, $USER;
        $p = self::validate_parameters(self::execute_parameters(), ['key' => $key, 'approved' => $approved]);
        self::validate_context(\context_system::instance());
        util::require_tut_user();
        $k = $USER->id . ':' . \core_text::substr($p['key'], 0, 100);
        if ($DB->record_exists('local_asktut_claims', ['claimkey' => $k])) {
            return ['claimed' => false];
        }
        try {
            $DB->insert_record('local_asktut_claims', (object)['claimkey' => $k, 'userid' => $USER->id, 'approved' => $p['approved'] ? 1 : 0, 'timecreated' => time()]);
        } catch (\dml_exception $e) {
            return ['claimed' => false]; // lost a race with a second click
        }
        return ['claimed' => true];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(['claimed' => new external_value(PARAM_BOOL, 'first claim')]);
    }
}
