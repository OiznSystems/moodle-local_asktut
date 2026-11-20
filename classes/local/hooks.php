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
 * Hook callbacks: adds the Ask Tut button to pages.
 *
 * @package    local_asktut
 * @copyright  2026 Oizn Systems Inc. {@link https://asktut.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_asktut\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Puts the Ask Tut button and side pane on every page for users who may use Tut.
 * The pane itself (pane.php) loads in an iframe only when the user opens it.
 */
class hooks {
    public static function before_footer(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE, $CFG;
        if (during_initial_install() || !isloggedin() || isguestuser()) {
            return;
        }
        if (!get_config('local_asktut', 'enabled') || !get_config('local_asktut', 'harnessurl')) {
            return;
        }
        if (in_array($PAGE->pagelayout, ['embedded', 'popup', 'login', 'maintenance', 'redirect', 'secure', 'frametop'])) {
            return;
        }
        if (strpos($PAGE->url->get_path(), '/local/asktut/') !== false) {
            return;
        }
        try {
            if (!util::can_use()) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }
        $params = [];
        if (!empty($PAGE->course->id) && $PAGE->course->id != SITEID) {
            $params['courseid'] = $PAGE->course->id;
        }
        if (!empty($PAGE->cm->id)) {
            $params['cmid'] = $PAGE->cm->id;
        }
        $src = (new \moodle_url('/local/asktut/pane.php', $params))->out(false);
        $logo = (new \moodle_url('/local/asktut/pix/tut.png'))->out(false);
        $html = '<button type="button" id="asktut-fab" title="Ask Tut" aria-label="Ask Tut" '
            . 'style="position:fixed;right:20px;bottom:20px;z-index:1050;width:56px;height:56px;border-radius:50%;'
            . 'background:#fff;border:2px solid #1B2A4A;box-shadow:0 2px 10px rgba(0,0,0,.3);cursor:pointer;padding:6px">'
            . '<img src="' . s($logo) . '" alt="" style="width:100%;height:100%;object-fit:contain"></button>'
            . '<div id="asktut-drawer" style="display:none;position:fixed;top:0;right:0;bottom:0;width:420px;max-width:100vw;z-index:1060;'
            . 'background:#fff;box-shadow:-4px 0 16px rgba(0,0,0,.25)">'
            . '<button type="button" id="asktut-close" aria-label="Close" style="position:absolute;top:6px;right:8px;z-index:2;border:0;background:none;font-size:22px;cursor:pointer">&times;</button>'
            . '<iframe id="asktut-frame" title="Ask Tut" data-src="' . s($src) . '" style="border:0;width:100%;height:100%"></iframe></div>';
        $hook->add_html($html);
        $PAGE->requires->js_call_amd('local_asktut/fab', 'init', []);
    }
}
