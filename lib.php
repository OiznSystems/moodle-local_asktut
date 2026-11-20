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
 * Library functions for Ask Tut.
 *
 * @package    local_asktut
 * @copyright  2026 Oizn Systems Inc. {@link https://asktut.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Ask Tut for Moodle - serves the admin's own avatar picture (Tut Admin -> Avatar -> Your own) to signed-in users.
defined('MOODLE_INTERNAL') || die();

/**
 * Serves local_asktut/avatar/avatar.png|gif from the system context.
 */
function local_asktut_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_SYSTEM || $filearea !== 'avatar') {
        return false;
    }
    require_login(null, false);
    $filename = array_pop($args);
    if (!in_array($filename, ['avatar.png', 'avatar.gif'], true)) {
        return false;
    }
    $file = get_file_storage()->get_file($context->id, 'local_asktut', 'avatar', 0, '/', $filename);
    if (!$file) {
        return false;
    }
    send_stored_file($file, 86400, 0, false, $options);
}
