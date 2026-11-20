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
 * External function: record an approved change.
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

/** Record one change Tut made (after the user approved it), before and after values. Stamped with the caller. */
class log_change extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(['row' => new external_value(PARAM_RAW, 'JSON row')]);
    }
    public static function execute(string $row): array {
        global $DB, $USER;
        ['row' => $row] = self::validate_parameters(self::execute_parameters(), ['row' => $row]);
        self::validate_context(\context_system::instance());
        util::require_tut_user();
        $in = json_decode($row, true) ?: [];
        $t = fn($k, $len) => isset($in[$k]) ? \core_text::substr(clean_param((string)$in[$k], PARAM_TEXT), 0, $len) : null;
        $rec = (object)[
            'userid' => $USER->id, 'timecreated' => time(),
            'channel' => $t('channel', 20), 'operation' => $t('operation', 100), 'target' => $t('target', 255),
            'courseid' => isset($in['courseid']) ? (int)$in['courseid'] : null,
            'before_values' => isset($in['before_values']) ? \core_text::substr((string)$in['before_values'], 0, 8000) : null,
            'after_values' => isset($in['after_values']) ? \core_text::substr((string)$in['after_values'], 0, 8000) : null,
            'result' => $t('result', 20), 'error_text' => $t('error_text', 2000), 'approval_id' => $t('approval_id', 100),
        ];
        return ['id' => (int)$DB->insert_record('local_asktut_changes', $rec)];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(['id' => new external_value(PARAM_INT, 'change row id')]);
    }
}
