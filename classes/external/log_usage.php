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
 * External function: record one Tut request.
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

/** Record one Tut request. The row is always stamped with the caller (nobody can write rows in someone else's name). */
class log_usage extends external_api {
    const INTS = ['input_tokens', 'output_tokens', 'cache_write_tokens', 'cache_read_tokens', 'steps', 'duration_ms', 'refusals', 'courseid'];
    const CHARS = ['channel' => 20, 'model' => 60, 'routed' => 100, 'provider' => 30, 'tools' => 255, 'approval' => 20,
        'pending_id' => 100, 'answered_id' => 100, 'roles_applied' => 255];
    const TEXTS = ['question' => 2000, 'error_text' => 2000];
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(['row' => new external_value(PARAM_RAW, 'JSON row')]);
    }
    public static function execute(string $row): array {
        global $DB, $USER;
        ['row' => $row] = self::validate_parameters(self::execute_parameters(), ['row' => $row]);
        self::validate_context(\context_system::instance());
        util::require_tut_user();
        $in = json_decode($row, true) ?: [];
        $rec = (object)['userid' => $USER->id, 'timecreated' => time(), 'cost_usd' => max(0, min(1000, (float)($in['cost_usd'] ?? 0)))];
        foreach (self::INTS as $k) {
            $rec->$k = isset($in[$k]) ? max(0, (int)$in[$k]) : ($k === 'courseid' ? null : 0);
        }
        foreach (self::CHARS as $k => $len) {
            $rec->$k = isset($in[$k]) ? \core_text::substr(clean_param((string)$in[$k], PARAM_TEXT), 0, $len) : null;
        }
        foreach (self::TEXTS as $k => $len) {
            $rec->$k = isset($in[$k]) ? \core_text::substr((string)$in[$k], 0, $len) : null;
        }
        return ['id' => (int)$DB->insert_record('local_asktut_usage', $rec)];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(['id' => new external_value(PARAM_INT, 'usage row id')]);
    }
}
