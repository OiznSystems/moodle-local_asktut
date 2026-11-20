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
 * External function: thumbs up / down on an answer.
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

/** Thumbs up/down on one of the caller's own Tut answers. */
class feedback extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'usageid' => new external_value(PARAM_INT, 'usage row'),
            'rating' => new external_value(PARAM_INT, '1 or -1'),
            'comment' => new external_value(PARAM_TEXT, 'comment', VALUE_DEFAULT, ''),
        ]);
    }
    public static function execute(int $usageid, int $rating, string $comment = ''): array {
        global $DB, $USER;
        $p = self::validate_parameters(self::execute_parameters(), ['usageid' => $usageid, 'rating' => $rating, 'comment' => $comment]);
        self::validate_context(\context_system::instance());
        util::require_tut_user();
        $row = $DB->get_record('local_asktut_usage', ['id' => $p['usageid'], 'userid' => $USER->id]);
        if (!$row) {
            return ['ok' => false];
        }
        $DB->update_record('local_asktut_usage', (object)['id' => $row->id, 'rating' => $p['rating'] > 0 ? 1 : -1, 'feedback' => \core_text::substr($p['comment'], 0, 1000)]);
        return ['ok' => true];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(['ok' => new external_value(PARAM_BOOL, 'saved')]);
    }
}
