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
 * The Ask Tut side pane.
 *
 * @package    local_asktut
 * @copyright  2026 Oizn Systems Inc. {@link https://asktut.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Ask Tut - the side pane (loaded in an iframe by the Ask Tut button). Talks to the Ask Tut service with the signed-in
// user's own token for the "Ask Tut" web service, so Moodle's roles and capabilities apply to everything Tut reads and changes.
require_once(__DIR__ . '/../../config.php');
use local_asktut\local\util;

require_login(null, false);
$courseid = optional_param('courseid', 0, PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/asktut/pane.php'));
if (!util::can_use()) {
    throw new moodle_exception('nopermissions', 'error', '', 'Ask Tut (local/asktut:use)');
}
$harness = rtrim((string)get_config('local_asktut', 'harnessurl'), '/');
if (!$harness) {
    die(get_string('notenabled', 'local_asktut'));
}
$ctx = ['channel' => 'pane'];
if ($courseid && ($course = $DB->get_record('course', ['id' => $courseid], 'id, shortname, fullname'))) {
    $ctx['courseid'] = (int)$course->id;
    $ctx['course'] = format_string($course->fullname);
}
if ($cmid) {
    try {
        [$c, $cm] = get_course_and_cm_from_cmid($cmid);
        $ctx['cmid'] = (int)$cm->id;
        $ctx['activity'] = format_string($cm->name) . ' (' . $cm->modname . ')';
        $ctx['instance'] = (int)$cm->instance;
        $ctx['modname'] = $cm->modname;
    } catch (Throwable $e) {
        $ctx['cmid'] = null;
    }
}
$p = util::policy();
// a person who cannot grade in this course gets learner suggestions (students only see Tut if an admin allowed it)
$learner = !empty($ctx['courseid']) && !has_capability('moodle/grade:viewall', context_course::instance($ctx['courseid']));
$av = $p['avatar'] ?? [];
$cfg = [
    'harness' => $harness,
    'uid' => (int)$USER->id,
    'token' => util::user_token(),
    'lang' => current_language(),
    'ctx' => $ctx,
    'avatar' => (array_key_exists($av['character'] ?? '', util::AVATARS) && (($av['character'] ?? '') !== 'custom' || !empty($av['custom']))) ? $av['character'] : 'tutlogo', // Tut is always the logo
    'custom' => !empty($av['custom']) ? ['v' => (int)$av['custom']['v'], 'gif' => !empty($av['custom']['gif']), 'png' => util::avatar_url($av['custom']),
        'gifUrl' => !empty($av['custom']['gif']) ? util::avatar_url($av['custom'], true) : ''] : null,
    'pick' => !empty($av['letUsersPick']),
    'names' => $av['names'] ?? [],
    'name' => $av['name'] ?? 'Tut',
    'learner' => $learner,
    'admin' => util::can_manage() ? ['tut' => (new moodle_url('/local/asktut/admin.php'))->out(false), 'consumption' => (new moodle_url('/local/asktut/consumption.php'))->out(false)] : null,
    'show' => ($av['show'] ?? true) !== false,
    'sym' => $av['thinkingSymbols'] ?? 'hiero',
    'logo' => (new moodle_url('/local/asktut/pix/tut.png'))->out(false),
];
header('Cache-Control: no-store, private');
header('X-Frame-Options: SAMEORIGIN');
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="<?php echo s(current_language()); ?>"><head><meta charset="utf-8"><title>Ask Tut</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script id="tut_cfg" type="application/json"><?php echo json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<link rel="stylesheet" href="<?php echo s((new moodle_url('/local/asktut/pane.css', ['v' => 2026111905]))->out(false)); ?>">
</head><body>
<div id="tut_root">
  <div id="tut_head"><img id="tut_avatar" alt=""><div><div id="tut_title">Ask Tut</div><div id="tut_name"></div></div><div id="tut_ctx"></div></div>
  <div id="tut_chat">
    <div id="tut_log"></div>
    <form id="tut_form"><input id="tut_in" autocomplete="off"><button id="tut_send" type="submit">Send</button></form>
    <div id="tut_tools"><a id="tut_new" href="#"></a><a id="tut_pick" href="#" style="display:none"></a><a id="tut_admin" href="#" target="_top" style="display:none">Tut Admin</a><a id="tut_cons" href="#" target="_top" style="display:none">Consumption</a></div>
  </div>
</div>
<script src="<?php echo s((new moodle_url('/local/asktut/pane.js', ['v' => 2026111905]))->out(false)); ?>"></script>
</body></html>
