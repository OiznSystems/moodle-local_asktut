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
 * Privacy API provider.
 *
 * @package    local_asktut
 * @copyright  2026 Oizn Systems Inc. {@link https://asktut.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_asktut\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;

/** What Ask Tut stores and sends (metadata). Export/delete per user: next version. */
class provider implements \core_privacy\local\metadata\provider {
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_asktut_usage', [
            'userid' => 'privacy:metadata:userid', 'question' => 'privacy:metadata:question', 'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:usage');
        $collection->add_database_table('local_asktut_changes', [
            'userid' => 'privacy:metadata:userid', 'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:changes');
        $collection->add_external_location_link('ai', ['question' => 'privacy:metadata:ai:question'], 'privacy:metadata:ai');
        return $collection;
    }
}
