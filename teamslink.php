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
 * Links a Microsoft Teams chat to this Moodle account (one-time, the person confirms).
 *
 * @package    local_asktut
 * @copyright  2026 Oizn Systems Inc. {@link https://asktut.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Ask Tut for Moodle - connect Microsoft Teams (0.4.0, 18 Nov 2026).
// The "Connect your Moodle account" card in Teams opens this page with a one-time code (30 minutes, made by the Tut service).
// The person signs in to Moodle the normal way and clicks Connect. This page then makes THAT person's own Ask Tut web-service
// token ("Ask Tut Teams", 180 days) and sends it with the code, server to server, to the Tut service, which checks it with Moodle
// and keeps it sealed for the Teams chat. Tut in Teams then works as this person, with their own Moodle roles. The person can
// remove it any time: "disconnect" in Teams, or delete the "Ask Tut Teams" key under Preferences -> Security keys.
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php'); // curl
use local_asktut\local\util;

$code = optional_param('c', '', PARAM_ALPHANUMEXT);
require_login(null, false);
$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/asktut/teamslink.php', ['c' => $code]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Ask Tut for Microsoft Teams');
$PAGE->set_heading('Ask Tut for Microsoft Teams');

$box = function(string $html, string $kind = 'info') {
    return '<div class="alert alert-' . $kind . '" style="max-width:640px">' . $html . '</div>';
};
$out = '';
if (!preg_match('/^[A-Za-z0-9_-]{20,64}$/', $code)) {
    $out = $box('This link is not complete. In Microsoft Teams, send any message to Tut to get a new <b>Connect</b> button.', 'warning');
} else if (!util::can_use()) {
    $out = $box('Ask Tut is not turned on for you in this Moodle site. Ask your Moodle admin.', 'warning');
} else if (data_submitted() && confirm_sesskey()) {
    global $DB, $USER;
    $harness = rtrim((string)get_config('local_asktut', 'harnessurl'), '/');
    $service = $DB->get_record('external_services', ['shortname' => util::SERVICE, 'enabled' => 1], '*', MUST_EXIST);
    // one Teams key per person: an older "Ask Tut Teams" key of this person is replaced
    $DB->delete_records_select('external_tokens', 'userid = ? AND externalserviceid = ? AND name = ?', [$USER->id, $service->id, 'Ask Tut Teams']);
    $token = \core_external\util::generate_token(EXTERNAL_TOKEN_PERMANENT, $service, (int)$USER->id, $ctx, time() + 180 * DAYSECS, '', 'Ask Tut Teams');
    $curl = new curl(['ignoresecurity' => false]);
    $curl->setHeader(['Content-Type: application/json']);
    $resp = $curl->post($harness . '/api/teams/link', json_encode(['code' => $code, 'token' => $token]));
    $info = $curl->get_info();
    $j = json_decode((string)$resp, true);
    if ((int)($info['http_code'] ?? 0) === 200 && !empty($j['ok'])) {
        $out = $box('<b>Connected.</b> Go back to Microsoft Teams and ask your question. You can close this tab.<br><span class="text-muted">To disconnect later, type <i>disconnect</i> to Tut in Teams, or delete the "Ask Tut Teams" key under Preferences → Security keys.</span>', 'success');
    } else {
        $DB->delete_records('external_tokens', ['token' => $token]); // nothing half-done
        $why = is_array($j) && !empty($j['error']) ? s($j['error']) : 'The Ask Tut service did not answer (' . (int)($info['http_code'] ?? 0) . ').';
        // 19 Nov 2026 (Mo's screenshot): the service's own message already says what to do -> don't say it twice
        $out = $box('<b>Not connected.</b> ' . $why . (preg_match('/send any message/i', $why) ? '' : ' In Teams, send any message to Tut for a new link.'), 'danger');
    }
} else {
    $out = '<div style="max-width:640px"><p>Connect <b>Ask Tut in Microsoft Teams</b> to your Moodle account, <b>' . s(fullname($USER)) . '</b>?</p>'
        . '<ul><li>Tut in Teams will work as you, with your own Moodle roles — it can see and change only what you already can.</li>'
        . '<li>Every change still needs you to click <b>Approve</b>. Tut never sends e-mail.</li>'
        . '<li>You can disconnect any time (type <i>disconnect</i> in Teams).</li></ul>'
        . '<form method="post"><input type="hidden" name="sesskey" value="' . sesskey() . '"><input type="hidden" name="c" value="' . s($code) . '">'
        . '<button class="btn btn-primary" type="submit">Connect</button></form></div>';
}
echo $OUTPUT->header() . $out . $OUTPUT->footer();
