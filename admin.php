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
 * Tut Admin: who can use Tut, rules, AI, cost, alerts, avatar, documents and privacy.
 *
 * @package    local_asktut
 * @copyright  2026 Oizn Systems Inc. {@link https://asktut.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Ask Tut for Moodle - Tut Admin: the rules the Ask Tut service enforces on every request (stored in the plugin config).
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/filelib.php'); // curl (document logo -> the Tut service)
use local_asktut\local\util;
use local_asktut\local\adminui;

admin_externalpage_setup('local_asktut_admin');
require_capability('local/asktut:manage', context_system::instance());

const TUT_MODELS = ['auto' => 'Auto (Tut picks per question)', 'claude-haiku-5-5' => 'Claude Haiku 5.5', 'claude-sonnet-5-5' => 'Claude Sonnet 5.5', 'claude-opus-5-5' => 'Claude Opus 5.5'];
const TUT_READS = ['courses' => 'Courses and course content', 'participants' => 'Participants (enrolled users)', 'assignments' => 'Assignments and submissions',
    'grades' => 'Grades', 'forums' => 'Forums (read)', 'calendar' => 'Calendar and deadlines', 'completion' => 'Activity completion', 'users' => 'Find users'];
const TUT_WRITES = ['calendar_event' => ['Create calendar events', 'No e-mail.'],
    'grade_assignment' => ['Grade an assignment and add feedback', 'Moodle may notify the student, depending on the assignment\'s "Notify students" setting.'],
    'forum_post' => ['Post to forums (new discussion or reply)', 'Moodle e-mails forum posts to subscribers, so this is OFF by default (Tut never sends e-mail on its own).']];
const TUT_SYMBOLS = ['hiero' => 'Hieroglyphs (Egyptian)', 'japanese' => 'Japanese characters', 'greek' => 'Greek letters', 'circuit' => 'Circuit symbols', 'nature' => 'Nature symbols', 'ideas' => 'Ideas',
    'science' => 'Science', 'math' => 'Math', 'music' => 'Music notes', 'space' => 'Stars and space', 'chess' => 'Chess', 'office' => 'Office', 'money' => 'Money', 'arrows' => 'Arrows',
    'braille' => 'Braille', 'runes' => 'Norse runes', 'dots' => 'Simple dots', 'off' => 'None'];

// Who can use Tut (18 Nov 2026 evening, Mo: real switches instead of a table). Each switch sets local/asktut:use on every role
// of that Moodle archetype at site level (the same thing as editing the role on Site administration → Users → Define roles).
const TUT_WHO = ['editingteacher' => ['Teachers', 'Moodle role Teacher (can edit the course).'], 'teacher' => ['Non-editing teachers', 'Moodle role Non-editing teacher.'],
    'coursecreator' => ['Course creators', ''], 'manager' => ['Managers', ''],
    'student' => ['Students', 'Off by default. Students only ever see what Moodle already lets them see.']];
const TUT_PROVIDERS = ['claude', 'foundry', 'azureopenai', 'databricks', 'openai', 'gemini', 'custom'];
// does any role of this archetype have the capability at site level?
$capon = function(string $cap, string $arch) use ($DB): bool {
    $ctx = context_system::instance();
    foreach (get_archetype_roles($arch) as $r) {
        if ((int)$DB->get_field('role_capabilities', 'permission', ['roleid' => $r->id, 'contextid' => $ctx->id, 'capability' => $cap]) === CAP_ALLOW) {
            return true;
        }
    }
    return false;
};
// other AI providers' settings from the "Where the AI runs" widget: plain values only, never anything that looks like a secret
$clean = function($v, int $depth = 0) use (&$clean) {
    if (is_array($v)) {
        if ($depth > 3) {
            return [];
        }
        $o = [];
        foreach ($v as $k => $x) {
            if (preg_match('/key|token|secret|password/i', (string)$k) || !preg_match('/^[A-Za-z0-9_-]{1,40}$/', (string)$k)) {
                continue;
            }
            $o[$k] = $clean($x, $depth + 1);
        }
        return $o;
    }
    return is_bool($v) || is_int($v) || is_float($v) ? $v : core_text::substr(clean_param((string)$v, PARAM_TEXT), 0, 300);
};

// 19 Nov 2026 (D365 parity): the document logo lives with the Tut service (table storage), uploaded as this admin
$tutcall = function(string $path, array $body) {
    $harness = rtrim((string)get_config('local_asktut', 'harnessurl'), '/');
    $curl = new curl(['ignoresecurity' => false]);
    $curl->setHeader(['Content-Type: application/json', 'Authorization: Bearer ' . util::user_token()]);
    $resp = $curl->post($harness . $path, json_encode($body));
    $j = json_decode((string)$resp, true);
    if ((int)($curl->get_info()['http_code'] ?? 0) !== 200) {
        throw new \Exception(is_array($j) && !empty($j['error']) ? (string)$j['error'] : 'The Ask Tut service did not answer.');
    }
    return $j;
};
$p = util::policy();
$p += ['model' => 'auto', 'autoMax' => 'claude-sonnet-5-5', 'instructions' => '', 'reads' => [], 'writes' => [], 'roleRules' => [],
    'cost' => [], 'privacy' => [], 'web' => [], 'avatar' => [], 'company' => ''];
$readon = fn($k) => ($p['reads'][$k] ?? true) !== false;
$writeon = fn($k) => !empty($p['writes'][$k] ?? ($k === 'calendar_event'));

if (data_submitted() && confirm_sesskey()) {
    $n = [];
    $n['version'] = 1;
    $n['company'] = core_text::substr(trim(optional_param('company', '', PARAM_TEXT)), 0, 100);
    $m = optional_param('model', 'auto', PARAM_ALPHANUMEXT);
    $n['model'] = array_key_exists($m, TUT_MODELS) ? $m : 'auto';
    $am = optional_param('autoMax', 'claude-sonnet-5-5', PARAM_ALPHANUMEXT);
    $n['autoMax'] = ($am !== 'auto' && array_key_exists($am, TUT_MODELS)) ? $am : 'claude-sonnet-5-5';
    $n['instructions'] = core_text::substr(optional_param('instructions', '', PARAM_TEXT), 0, 4000);
    $n['reads'] = [];
    foreach (TUT_READS as $k => $l) {
        $n['reads'][$k] = (bool)optional_param('read_' . $k, 0, PARAM_BOOL);
    }
    $n['writes'] = [];
    foreach (TUT_WRITES as $k => $l) {
        $n['writes'][$k] = (bool)optional_param('write_' . $k, 0, PARAM_BOOL);
    }
    // Role rules, websites, prices and limit exceptions come from table rows (18 Nov 2026: tables instead of text boxes)
    $n['roleRules'] = [];
    $rm = optional_param_array('rr_match', [], PARAM_ALPHANUMEXT);
    $ro = optional_param_array('rr_ro', [], PARAM_ALPHA);
    $rx = optional_param_array('rr_max', [], PARAM_ALPHANUMEXT);
    foreach ($rm as $i => $match) {
        if ($match === '') {
            continue;
        }
        $mx = $rx[$i] ?? '';
        $n['roleRules'][] = ['match' => core_text::strtolower($match), 'readOnly' => ($ro[$i] ?? '') === 'yes', 'maxModel' => ($mx !== 'auto' && array_key_exists($mx, TUT_MODELS)) ? $mx : ''];
    }
    $n['teamsReadOnly'] = (bool)optional_param('teamsReadOnly', 0, PARAM_BOOL);
    $n['cost'] = ['monthlyBudget' => max(0, (float)optional_param('monthlyBudget', 50, PARAM_FLOAT)), 'userMonthlyLimit' => max(0, (float)optional_param('userMonthlyLimit', 10, PARAM_FLOAT)), 'models' => []];
    $pin = optional_param_array('price_in', [], PARAM_RAW_TRIMMED);
    $pout = optional_param_array('price_out', [], PARAM_RAW_TRIMMED);
    foreach (array_slice(array_keys(TUT_MODELS), 1) as $m) {
        $n['cost']['models'][$m] = ['in' => max(0, (float)($pin[$m] ?? 0)), 'out' => max(0, (float)($pout[$m] ?? 0))];
    }
    $n['privacy'] = ['keepQuestions' => (bool)optional_param('keepQuestions', 0, PARAM_BOOL)];
    $domains = array_values(array_filter(array_map(fn($d) => strtolower(preg_replace('~^https?://|/.*$~', '', trim($d))), optional_param_array('web_d', [], PARAM_TEXT))));
    $n['web'] = ['enabled' => (bool)optional_param('webEnabled', 0, PARAM_BOOL), 'domains' => array_slice(array_values(array_filter($domains, fn($d) => preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $d))), 0, 30)];
    // Avatar (18 Nov 2026: same choices and order as the other editions, plus "Your own" picture)
    $custom = $p['avatar']['custom'] ?? null;
    $warning = '';
    if (optional_param('avatarRemove', 0, PARAM_BOOL)) {
        util::remove_avatar();
        $custom = null;
    }
    if (!empty($_FILES['avatarFile']['tmp_name']) && is_uploaded_file($_FILES['avatarFile']['tmp_name'])) {
        $res = util::store_avatar($_FILES['avatarFile']['tmp_name']);
        $custom = ['v' => $res['v'], 'gif' => $res['gif']];
        $warning = $res['warning'];
    }
    $names = [];
    foreach (util::AVATARS as $k => $l) {
        $nm = core_text::substr(trim(optional_param('avname_' . $k, '', PARAM_TEXT)), 0, 30);
        if ($nm !== '') {
            $names[$k] = $nm;
        }
    }
    $ch = optional_param('avatar', 'tutlogo', PARAM_ALPHANUMEXT);
    if ($warning !== '' || (!empty($_FILES['avatarFile']['tmp_name']) && $custom)) {
        $ch = 'custom'; // a new picture was just uploaded: use it
    }
    $ch = (array_key_exists($ch, util::AVATARS) && ($ch !== 'custom' || $custom)) ? $ch : 'tutlogo';
    $sym = optional_param('thinkingSymbols', 'hiero', PARAM_ALPHA);
    $n['avatar'] = ['show' => (bool)optional_param('avatarShow', 0, PARAM_BOOL), 'character' => $ch, 'names' => $names,
        'name' => $names[$ch] ?? ($ch === 'custom' ? 'Tut' : util::AVATARS[$ch]), 'letUsersPick' => (bool)optional_param('avatarPick', 0, PARAM_BOOL),
        'thinkingSymbols' => array_key_exists($sym, TUT_SYMBOLS) ? $sym : 'hiero', 'custom' => $custom];
    // Per-person limit exceptions (table rows)
    $n['cost']['userLimits'] = [];
    $uu = optional_param_array('ul_user', [], PARAM_TEXT);
    $ull = optional_param_array('ul_limit', [], PARAM_RAW_TRIMMED);
    foreach ($uu as $i => $who) {
        $who = core_text::strtolower(core_text::substr(trim($who), 0, 120));
        if ($who !== '' && isset($ull[$i]) && is_numeric($ull[$i])) {
            $n['cost']['userLimits'][] = ['user' => $who, 'limit' => max(0, (float)$ull[$i])];
        }
    }
    // Who can use Tut: change the role capability only where the switch differs from today
    $sysctx = context_system::instance();
    foreach (TUT_WHO as $arch => $x) {
        $on = (bool)optional_param('who_' . $arch, 0, PARAM_BOOL);
        if ($on !== $capon('local/asktut:use', $arch)) {
            foreach (get_archetype_roles($arch) as $r) {
                $on ? assign_capability('local/asktut:use', CAP_ALLOW, $r->id, $sysctx->id, true) : unassign_capability('local/asktut:use', $r->id, $sysctx->id);
            }
        }
    }
    if (is_siteadmin()) { // only a site admin may change who can open Tut Admin (a manager could otherwise lock themselves out)
        $on = (bool)optional_param('manage_manager', 0, PARAM_BOOL);
        if ($on !== $capon('local/asktut:manage', 'manager')) {
            foreach (get_archetype_roles('manager') as $r) {
                $on ? assign_capability('local/asktut:manage', CAP_ALLOW, $r->id, $sysctx->id, true) : unassign_capability('local/asktut:manage', $r->id, $sysctx->id);
            }
        }
    }
    // Where the AI runs (no secrets here: keys live in Key Vault; Foundry/Azure can sign in with the service's managed identity)
    $prov = optional_param('provider', 'claude', PARAM_ALPHANUMEXT);
    $dep = [];
    $usdep = [];
    foreach (array_slice(array_keys(TUT_MODELS), 1) as $m) {
        $key = str_replace('-', '_', $m);
        $d = trim(optional_param('fd_' . $key, '', PARAM_TEXT));
        $u = trim(optional_param('fdus_' . $key, '', PARAM_TEXT));
        if ($d !== '') {
            $dep[$m] = clean_param($d, PARAM_ALPHANUMEXT);
        }
        if ($u !== '') {
            $usdep[$m] = clean_param($u, PARAM_ALPHANUMEXT);
        }
    }
    $n['provider'] = ['name' => in_array($prov, TUT_PROVIDERS, true) ? $prov : 'claude', 'processIn' => optional_param('processIn', 'global', PARAM_ALPHA) === 'us' ? 'us' : 'global',
        'foundry' => ['endpoint' => clean_param(trim(optional_param('fdEndpoint', '', PARAM_URL)), PARAM_URL), 'deployments' => $dep, 'usDeployments' => $usdep]];
    $other = json_decode(optional_param('aiOther', '', PARAM_RAW), true);
    foreach (array_slice(TUT_PROVIDERS, 2) as $k) {
        if (is_array($other) && isset($other[$k]) && is_array($other[$k])) {
            $n['provider'][$k] = $clean($other[$k]);
        }
    }
    // 19 Nov 2026 (D365 parity): bulk changes, drafts, alerts, due-date reminders, documents + branding
    $n['bulk'] = ['enabled' => (bool)optional_param('bulkOn', 0, PARAM_BOOL), 'max' => min(50, max(2, optional_param('bulkMax', 25, PARAM_INT)))];
    $n['drafts'] = ['enabled' => (bool)optional_param('draftsOn', 0, PARAM_BOOL)];
    $th = array_values(array_unique(array_filter(array_map('intval', preg_split('/[^0-9]+/', optional_param('alTh', '80, 100', PARAM_TEXT))), fn($x) => $x >= 10 && $x <= 100)));
    sort($th);
    $n['alerts'] = ['enabled' => (bool)optional_param('alOn', 0, PARAM_BOOL), 'notifyUser' => (bool)optional_param('alUser', 0, PARAM_BOOL), 'teams' => true, 'thresholds' => $th ?: [80, 100]];
    $n['due'] = ['enabled' => (bool)optional_param('dueOn', 0, PARAM_BOOL), 'teachers' => (bool)optional_param('dueT', 0, PARAM_BOOL), 'students' => (bool)optional_param('dueS', 0, PARAM_BOOL),
        'hoursBefore' => min(168, max(1, optional_param('dueH', 24, PARAM_INT)))];
    $n['documents'] = ['enabled' => (bool)optional_param('docOn', 0, PARAM_BOOL), 'types' => array_values(array_filter(['word', 'excel', 'pdf'], fn($t) => (bool)optional_param('dt_' . $t, 0, PARAM_BOOL)))];
    $hex = fn($v, $d) => preg_match('/^#?[0-9a-fA-F]{6}$/', (string)$v) ? strtoupper(ltrim((string)$v, '#')) : $d;
    $txt = fn($k, $n2) => core_text::substr(trim(optional_param($k, '', PARAM_TEXT)), 0, $n2);
    $logo = (int)($p['branding']['logo'] ?? 0);
    try {
        if (optional_param('brRemove', 0, PARAM_BOOL)) {
            $tutcall('/api/admin/brandlogo', ['clear' => true]);
            $logo = 0;
        }
        if (!empty($_FILES['brFile']['tmp_name']) && is_uploaded_file($_FILES['brFile']['tmp_name'])) {
            if (filesize($_FILES['brFile']['tmp_name']) > 200 * 1024) {
                $warning .= ' The logo is over 200 KB; please use a smaller file.';
            } else {
                $r = $tutcall('/api/admin/brandlogo', ['data' => base64_encode(file_get_contents($_FILES['brFile']['tmp_name']))]);
                $logo = (int)($r['v'] ?? 0);
            }
        }
    } catch (\Throwable $e) {
        $warning .= ' Logo not saved: ' . $e->getMessage();
    }
    $n['branding'] = ['company' => $txt('brCo', 120), 'header' => $txt('brHead', 120), 'color' => $hex(optional_param('brColor', '', PARAM_TEXT), '1F3A68'), 'accent' => $hex(optional_param('brAccent', '', PARAM_TEXT), 'C9962E'),
        'font' => preg_match('/^[\w .-]{2,40}$/', $txt('brFont', 40)) ? $txt('brFont', 40) : 'Calibri', 'footer' => $txt('brFoot', 160), 'logo' => $logo];
    util::save_policy($n);
    redirect(new moodle_url('/local/asktut/admin.php', ['tab' => optional_param('tab', 'who', PARAM_ALPHA)]), get_string('saved', 'local_asktut') . ($warning ? ' ' . $warning : ''), null,
        $warning ? \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS);
}

// ---------- page (18 Nov 2026: tabs like the Dynamics 365 Tut Admin) ----------
adminui::setup('admin');
$PAGE->requires->js(new moodle_url('/local/asktut/aiwidget.js', ['v' => 1])); // the same "Where the AI runs" widget as every edition
$models = array_slice(TUT_MODELS, 1);
$harness = rtrim((string)get_config('local_asktut', 'harnessurl'), '/');
$saved = get_config('local_asktut', 'policysaved');
$sub = 'Rules for Ask Tut in Moodle. Enforced by the Tut service on every request.';
if ($saved) {
    [$t, $who] = array_pad(explode(' ', $saved, 2), 2, '');
    $sub .= ' Last saved ' . userdate((int)$t, get_string('strftimedatetimeshort')) . ' by ' . s($who) . '.';
}
echo $OUTPUT->header();
echo '<form method="post" enctype="multipart/form-data"><input type="hidden" name="sesskey" value="' . sesskey() . '"><input type="hidden" name="tab" id="tut_tab" value="who"><input type="hidden" name="aiOther" id="aiOther" value="">';
echo adminui::start('admin', $sub);

// Instructions
echo adminui::section('general', 'Instructions', 'Tell Tut about your school in plain words — policies, tone, languages, what to avoid. Tut follows this in every chat. Tut never sends e-mail on its own.',
    '<div class="fld"><label>School or organization name</label><input type="text" name="company" value="' . s($p['company']) . '" placeholder="e.g. Maple Ridge College"></div>'
    . '<div class="fld" style="margin-top:10px"><label>Instructions for Tut</label><textarea name="instructions" placeholder="e.g. Answer in plain English. Our late policy: 10% per day.">' . s($p['instructions']) . '</textarea></div>');

// Who can use Tut: real switches (they change Moodle's role permissions for you)
$rolesurl = (new moodle_url('/admin/roles/manage.php'))->out();
$who = '<h3>Who can use Ask Tut</h3>';
foreach (TUT_WHO as $arch => [$l, $help]) {
    $who .= adminui::sw('who_' . $arch, $capon('local/asktut:use', $arch), $l, $help);
}
$who .= '<h3>Who can change these settings</h3>' . (is_siteadmin() ? adminui::sw('manage_manager', $capon('local/asktut:manage', 'manager'), 'Managers', 'Site administrators always can.')
    : '<p class="hint">Managers and site administrators. Only a site administrator can change this.</p>');
echo adminui::section('who', 'Who can use Tut', 'Choose which people see Ask Tut in Moodle. Tut always works as the signed-in person, so it can only see and change what that person already can in Moodle. Ask Tut appears as the floating Tut button on every page for them.',
    $who . '<div class="note">These switches set the Moodle permission <code>local/asktut:use</code> (and <code>local/asktut:manage</code>) on the standard roles, site-wide. Custom roles or course-level exceptions: <a href="' . s($rolesurl) . '" target="_blank">Site administration → Users → Define roles</a>.</div>');

// Avatar
$av = $p['avatar'] + ['character' => 'tutlogo', 'names' => [], 'custom' => null];
$cur = array_key_exists($av['character'], util::AVATARS) ? $av['character'] : 'tutlogo';
$sub2 = ['tutlogo' => 'Our logo', 'samurai' => 'Flat', 'zeus' => 'Flat', 'athena' => 'Flat', 'tut' => '3D character', 'bot' => 'Cartoon', 'guide' => 'Cartoon', 'queen' => 'Cartoon', 'custom' => 'Your own picture'];
$cards = '';
foreach (util::AVATARS as $k => $label) {
    $has = $k !== 'custom' || !empty($av['custom']);
    $img = $k === 'custom' ? ($has ? util::avatar_url($av['custom'], !empty($av['custom']['gif'])) : '') : $harness . '/api/asset/' . $k . '.png';
    $nm = $av['names'][$k] ?? ($k === $cur ? ($av['name'] ?? '') : '') ?: ($k === 'custom' ? '' : $label);
    $cards .= '<div class="avcard' . ($k === $cur ? ' on' : '') . '">' . ($img ? '<img src="' . s($img) . '" alt="">' : '<div class="avempty">Your own<br>PNG or GIF</div>')
        . '<div class="avsub">' . $sub2[$k] . '</div><input type="text" name="avname_' . $k . '" maxlength="30" value="' . s($nm) . '" placeholder="Name it">'
        . '<label class="use"><input type="radio" name="avatar" value="' . $k . '"' . ($k === $cur ? ' checked' : '') . ($has ? '' : ' disabled') . '> Use this one</label>';
    if ($k === 'custom') {
        $cards .= '<input type="file" name="avatarFile" accept="image/png,image/gif" style="font-size:11px;width:100%;margin-top:6px">'
            . ($has ? adminui::sw('avatarRemove', false, 'Remove picture') : '') . '<div class="avsub">Clear background · square · max 1 MB. Uploading selects it.</div>';
    }
    $cards .= '</div>';
}
echo adminui::section('avatar', 'Avatar', 'The character people see in the pane. Rename any of them; the name is used in the greeting, in "… is thinking", and when the assistant introduces itself.',
    '<div class="row">' . adminui::sw('avatarShow', ($av['show'] ?? true) !== false, 'Show an avatar') . adminui::sw('avatarPick', !empty($av['letUsersPick']), 'Let each person pick their own character') . '</div>'
    . '<div class="row" style="margin-top:6px"><span>Thinking symbols</span>' . adminui::select('thinkingSymbols', TUT_SYMBOLS, $av['thinkingSymbols'] ?? 'hiero', ' class="sm"') . '</div>'
    . '<div class="avgrid">' . $cards . '</div>');

// Role rules
$capopts = ['' => 'No cap'] + $models;
$rrrow = fn($r) => '<tr><td><input type="text" name="rr_match[]" list="tutRoles" value="' . s($r['match']) . '" placeholder="role short name"></td><td class="c">'
    . adminui::select('rr_ro[]', ['no' => 'No', 'yes' => 'Yes'], $r['readOnly'] ? 'yes' : 'no', ' class="sm"') . '</td><td>' . adminui::select('rr_max[]', $capopts, (string)($r['maxModel'] ?? '')) . '</td>' . adminui::removecell() . '</tr>';
$rolelist = '<datalist id="tutRoles">';
foreach (role_get_names(null, ROLENAME_SHORT) as $r) {
    $rolelist .= '<option value="' . s($r->shortname) . '">';
}
echo adminui::section('roles', 'Rules by role', 'Optional extra limits for one Moodle role (short name: editingteacher, teacher, student, manager …): make Tut <b>read only</b> for that role (it can look things up but never change anything), or <b>cap the AI model</b> to keep costs down. If someone has several roles and any of them is read only, read only wins.',
    adminui::table('rrBody', ['Role', 'Read only', 'Highest model', ''], array_map($rrrow, $p['roleRules']), $rrrow(['match' => '', 'readOnly' => false, 'maxModel' => '']), 'Add rule',
        'No rules yet — everyone uses their normal Moodle permissions. Example: add <b>student</b> + Read only, or <b>teacher</b> + Claude Haiku.')
    . $rolelist . '</datalist><h3>Microsoft Teams</h3>' . adminui::sw('teamsReadOnly', !empty($p['teamsReadOnly']), 'Read only in Teams', 'Changes can only be approved in Moodle.'));

// Permissions
$perm = '<h3>Tut may read</h3>';
foreach (TUT_READS as $k => $l) {
    $perm .= adminui::sw('read_' . $k, $readon($k), $l);
}
$perm .= '<h3>Tut may change (after the user approves)</h3>';
foreach (TUT_WRITES as $k => [$l, $help]) {
    $perm .= adminui::sw('write_' . $k, $writeon($k), $l, $help);
}
$bulk = ($p['bulk'] ?? []) + ['enabled' => true, 'max' => 25];
$perm .= '<h3>Bulk changes</h3><p class="hint">Lets Tut prepare the same kind of change for several people or items at once — for example grading 8 students on one assignment — and show them on <b>one</b> Approve card. Every item still follows the switches above.</p>'
    . adminui::sw('bulkOn', $bulk['enabled'] !== false, 'Allow bulk changes (one approval)') . '<div class="row" style="margin-top:6px"><span>Most changes on one card</span><input type="number" class="sm" name="bulkMax" min="2" max="50" step="1" value="' . s($bulk['max']) . '"></div>'
    . '<h3>Message drafts</h3><p class="hint">Tut never sends messages or forum posts. With this on, it writes a <b>draft</b> the person can copy and send themselves (with a button that opens Moodle messages or the forum).</p>'
    . adminui::sw('draftsOn', ($p['drafts']['enabled'] ?? true) !== false, 'Let Tut write drafts');
echo adminui::section('perm', 'What Tut may read and change', 'These switches apply to everyone. Tut can only work in the areas listed here — Moodle has no "any table" access like Dynamics 365. Every change still needs the person to click <b>Approve</b> first, and Moodle permissions still apply on top.', $perm);

// Websites
$webrow = fn($d) => '<tr><td><input type="text" name="web_d[]" value="' . s($d) . '" placeholder="docs.moodle.org"></td>' . adminui::removecell() . '</tr>';
echo adminui::section('web', 'Trusted websites', 'Public websites Tut may read to answer questions — for example your school\'s policy pages or docs.moodle.org. Tut only reads them (https only) and never signs in. Anything not on this list is refused.',
    adminui::sw('webEnabled', !empty($p['web']['enabled']), 'Tut may read these websites') . adminui::table('webBody', ['Website', ''], array_map($webrow, $p['web']['domains'] ?? []), $webrow(''), 'Add website',
        'No websites yet. Example: <b>docs.moodle.org</b> or <b>www.yourschool.ca</b>.'));

// Model + prices
$pr = $p['cost']['models'] ?? [];
$def = ['claude-haiku-5-5' => [0.10, 0.50], 'claude-sonnet-5-5' => [2.00, 10.00], 'claude-opus-5-5' => [4.00, 20.00]];
$rows = '';
foreach ($models as $m => $l) {
    $rows .= '<tr><td>' . s($l) . '</td><td><input type="number" step="0.01" min="0" name="price_in[' . $m . ']" value="' . s($pr[$m]['in'] ?? $def[$m][0]) . '"></td>'
        . '<td><input type="number" step="0.01" min="0" name="price_out[' . $m . ']" value="' . s($pr[$m]['out'] ?? $def[$m][1]) . '"></td></tr>';
}
echo adminui::section('model', 'AI model', 'Which model answers. <b>Auto</b> (recommended) picks per question: a fast, cheap model for simple lookups, a stronger one for changes and reasoning, and the most capable one only if you allow it.',
    '<div class="grid2"><div><label>Model</label>' . adminui::select('model', TUT_MODELS, $p['model']) . '</div><div><label>Highest model Auto may use</label>' . adminui::select('autoMax', $models, $p['autoMax']) . '</div></div>'
    . '<h3>Claude prices (USD per 1 million tokens — what Anthropic charges)</h3><p class="hint">Used to turn usage into dollars on the Consumption tab and for the limits. Other AI companies\' prices are set with their models under "Where the AI runs". <a href="#" id="prCheck">Check latest prices</a> <span id="prOut"></span></p><table><thead><tr><th>Model</th><th>Input</th><th>Output</th></tr></thead><tbody>' . $rows . '</tbody></table>');

// Where the AI runs (the same screen as every edition: Claude, Microsoft Foundry, Databricks, ChatGPT, Gemini, other / own model)
$pv = ($p['provider'] ?? []) + ['name' => 'claude', 'processIn' => 'global', 'foundry' => []];
$fd = ($pv['foundry'] ?? []) + ['endpoint' => '', 'deployments' => [], 'usDeployments' => []];
$rows = '';
foreach ($models as $m => $l) {
    $key = str_replace('-', '_', $m);
    $rows .= '<tr><td>' . s($l) . '</td><td><input type="text" name="fd_' . $key . '" value="' . s($fd['deployments'][$m] ?? '') . '"></td><td><input type="text" name="fdus_' . $key . '" value="' . s($fd['usDeployments'][$m] ?? '') . '"></td></tr>';
}
echo adminui::section('runs', 'Where the AI runs', 'Which AI company or platform answers the questions, and who bills you. The rules, approvals and history work the same whichever you pick. <b>API keys never go on this page</b> — they stay in Azure Key Vault, or the service signs in with its own Azure identity.',
    '<div class="grid2"><div><label>Provider</label>' . adminui::select('provider', ['claude' => 'Claude (Anthropic API)', 'foundry' => 'Microsoft Foundry', 'azureopenai' => 'Microsoft Foundry', 'databricks' => 'Databricks',
        'openai' => 'OpenAI (ChatGPT)', 'gemini' => 'Google Gemini', 'custom' => 'Other / own model'], in_array($pv['name'], TUT_PROVIDERS, true) ? $pv['name'] : 'claude', ' id="prov"') . '</div>'
    . '<div><label>Process in</label>' . adminui::select('processIn', ['global' => 'Global (any region, standard price)', 'us' => 'US only'], $pv['processIn'], ' id="pin"') . '</div></div>'
    . '<p class="hint" id="pvHint" style="margin-top:8px"></p>'
    . '<div id="fdbox"><div class="fld" style="margin-top:10px"><label>Foundry endpoint</label><input type="url" name="fdEndpoint" value="' . s($fd['endpoint']) . '" placeholder="https://<resource>.services.ai.azure.com"></div>'
    . '<p class="hint" style="margin-top:8px">Deployment names from Foundry (Build → Models). A <b>US Data Zone</b> deployment is needed for "US only". Give the Ask Tut app the <b>Foundry User</b> role on the Foundry resource.</p>'
    . '<table style="margin-top:8px"><thead><tr><th>Model</th><th>Global deployment</th><th>US Data Zone deployment</th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
    . '<div id="pvOther" style="margin-top:10px"></div>'
    . '<button class="add" type="button" id="tutPvCheck">Check connection</button> <span id="tutPvOut" class="hint">Checks the saved settings with one tiny request.</span><div id="pvResult" hidden></div>');

// Cost & limits
$ulrow = fn($x) => '<tr><td><input type="text" name="ul_user[]" value="' . s($x['user']) . '" placeholder="username or e-mail"></td><td><input type="number" min="0" step="0.5" name="ul_limit[]" value="' . s($x['limit']) . '"></td>' . adminui::removecell() . '</tr>';
echo adminui::section('cost', 'Cost &amp; limits', 'Keep spending under control, in US dollars per calendar month. At 80% of a limit the person sees a heads-up under the answer; at 100% Tut stops until the 1st of next month. 0 = no limit.',
    '<div class="grid2"><div><label>Monthly budget for the whole site</label><input type="number" min="0" step="1" name="monthlyBudget" value="' . s($p['cost']['monthlyBudget'] ?? 50) . '"></div>'
    . '<div><label>Monthly limit per person</label><input type="number" min="0" step="0.5" name="userMonthlyLimit" value="' . s($p['cost']['userMonthlyLimit'] ?? 10) . '"></div></div>'
    . '<h3>Exceptions (a different limit for some people)</h3>' . adminui::table('ulBody', ['Username or e-mail', 'Monthly limit (USD)', ''], array_map($ulrow, $p['cost']['userLimits'] ?? []), $ulrow(['user' => '', 'limit' => '']), 'Add exception',
        'No exceptions — everyone has the limit above. Example: give a department head <b>25</b> a month.'));

// Alerts (19 Nov 2026, D365 parity): budget alerts (Moodle bell + Teams) and due-date reminders (Teams)
$al = ($p['alerts'] ?? []) + ['enabled' => true, 'notifyUser' => true, 'thresholds' => [80, 100]];
$du = ($p['due'] ?? []) + ['enabled' => false, 'teachers' => true, 'students' => true, 'hoursBefore' => 24];
echo adminui::section('alerts', 'Alerts', 'Tut can warn people in the <b>Moodle bell</b> and in <b>Microsoft Teams</b> — never by e-mail.',
    '<h3>Budget alerts</h3><p class="hint">One alert per level per month: to the person when they reach the levels below of their own monthly limit (Moodle bell, and Teams if they use Tut there), and to site administrators when the site budget reaches them or anyone hits 100%.</p>'
    . adminui::sw('alOn', $al['enabled'] !== false, 'Send budget alerts') . adminui::sw('alUser', $al['notifyUser'] !== false, 'Also tell the person about their own limit')
    . '<div class="row" style="margin-top:6px"><span>Alert at (% used)</span><input type="text" class="sm" name="alTh" value="' . s(implode(', ', (array)$al['thresholds'])) . '" placeholder="80, 100"></div>'
    . '<h3>Due-date reminders</h3><p class="hint">Once an hour, Tut looks for assignments due soon and tells people <b>in Teams</b>, using each person\'s own Moodle access: <b>teachers</b> hear how many students have not submitted yet; <b>students</b> (if they may use Tut) hear about their own work not handed in. Each assignment is mentioned once.</p>'
    . adminui::sw('dueOn', !empty($du['enabled']), 'Send due-date reminders') . adminui::sw('dueT', $du['teachers'] !== false, 'To teachers') . adminui::sw('dueS', $du['students'] !== false, 'To students', 'Only students who may use Tut (Who can use Tut).')
    . '<div class="row" style="margin-top:6px"><span>Remind this many hours before the due date</span><input type="number" class="sm" name="dueH" id="dueH" min="1" max="168" step="1" value="' . s($du['hoursBefore']) . '"></div>'
    . '<button class="add" type="button" id="dueTest">Show what I would get now</button> <span id="dueOut" class="hint">Looks at your own courses only; sends to your Teams if you use Ask Tut there.</span>');

// Documents & branding (19 Nov 2026, D365 parity)
$dc = ($p['documents'] ?? []) + ['enabled' => true, 'types' => ['word', 'excel', 'pdf']];
$br = ($p['branding'] ?? []) + ['company' => '', 'header' => '', 'color' => '1F3A68', 'accent' => 'C9962E', 'font' => 'Calibri', 'footer' => 'Prepared by Tut from live Moodle data', 'logo' => 0];
$types = '';
foreach (['word' => 'Word', 'excel' => 'Excel', 'pdf' => 'PDF'] as $k => $l) {
    $types .= adminui::sw('dt_' . $k, in_array($k, (array)$dc['types'], true), $l);
}
$logoimg = !empty($br['logo']) ? '<img src="' . s($harness . '/api/media/brandlogo?v=' . (int)$br['logo']) . '" alt="" style="max-height:48px;max-width:180px;border:1px solid #e3e6ee;border-radius:6px;padding:4px;background:#fff">' : '<span class="hint">No logo yet.</span>';
echo adminui::section('docs', 'Documents &amp; branding', 'Tut can make Word, Excel and PDF files from Moodle data — a class list, a missing-work report, a handout — with a <b>Download</b> button (in Teams, a download link that works for 24 hours). Your school\'s name, colours and logo go on every document.',
    adminui::sw('docOn', $dc['enabled'] !== false, 'Let Tut make documents') . '<div class="row">' . $types . '</div>'
    . '<h3>Branding</h3><div class="grid2"><div><label>School name on documents</label><input type="text" name="brCo" value="' . s($br['company']) . '" placeholder="' . s($p['company'] ?: 'e.g. Maple Ridge College') . '"></div>'
    . '<div><label>Header text (optional)</label><input type="text" name="brHead" value="' . s($br['header']) . '" placeholder="e.g. Faculty of Science"></div>'
    . '<div><label>Main colour</label><input type="color" name="brColor" value="#' . s($br['color']) . '"></div><div><label>Accent colour</label><input type="color" name="brAccent" value="#' . s($br['accent']) . '"></div>'
    . '<div><label>Font (Word)</label><input type="text" name="brFont" value="' . s($br['font']) . '" placeholder="Calibri"></div><div><label>Footer text</label><input type="text" name="brFoot" value="' . s($br['footer']) . '"></div></div>'
    . '<h3>Logo</h3><p class="hint">PNG or JPG, up to 200 KB. Shown at the top of every page.</p><div class="row">' . $logoimg . ' <input type="file" name="brFile" accept="image/png,image/jpeg" style="font-size:12px"></div>'
    . (!empty($br['logo']) ? adminui::sw('brRemove', false, 'Remove logo') : ''));

// Privacy
echo adminui::section('privacy', 'Privacy', 'What Tut keeps about each question. Everything is stored in your own Moodle database, never with Oizn.',
    adminui::sw('keepQuestions', ($p['privacy']['keepQuestions'] ?? true) !== false, 'Keep the question text in Consumption', 'Off = only who, when, model and cost are kept.'));

echo adminui::end() . '</form>';
$tok = '';
try {
    $tok = util::user_token();
} catch (\Throwable $e) {
    $tok = ''; // no Ask Tut web service token for this admin: the check then reports the error
}
// "Where the AI runs" widget + "Check connection" (one tiny call through the Tut service, as the signed-in admin, with the saved settings)
$PAGE->requires->js_init_code('(function(){var H=' . json_encode($harness) . ',T=' . json_encode($tok) . ',PV=' . json_encode($p['provider'] ?? new stdClass()) . ',tries=0,A=null;
function $(i){return document.getElementById(i);}function esc(x){return String(x==null?"":x).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;","\\"":"&quot;"}[c];});}
function api(q){return fetch(H+"/api/provider?"+q,{headers:{Authorization:"Bearer "+T}}).then(function(r){return r.json().then(function(j){if(!r.ok)throw new Error(j.error||r.status);return j;});});}
function hint(){var n=$("prov").value,us=$("pin").value==="us";$("fdbox").hidden=n!=="foundry";if(A)A.show(n,$("pin").value);
$("pvHint").textContent=A&&A.has(n)?A.label(n)+(us?", US only: Tut sends questions only if you confirm below that this account or endpoint keeps processing in the US.":": pick the model for each of Tut\'s three slots below."):n==="foundry"?(us?"Foundry, US only: Tut uses the US Data Zone deployments below. If a model has none, Tut refuses that question instead of running it globally.":"Foundry, Global: Microsoft may process questions in any region. Billed on your Azure invoice."):(us?"Claude API, US only: every question is processed on US servers, priced about 10% higher.":"Claude API, Global: Anthropic may process questions in any region. Standard price.");}
(function go(){if(!window.TutOAI){if(tries++<100)setTimeout(go,50);else $("pvOther").innerHTML="<p class=\\"bad\\">The AI choices could not load. Reload the page.</p>";return;}
A=window.TutOAI;A.mount($("pvOther"),{listModels:function(n){return api("models="+encodeURIComponent(n)).then(function(j){return j.models||[];});}});A.set(PV);$("prov").value=PV.name||"claude";hint();})();
$("prov").addEventListener("change",hint);$("pin").addEventListener("change",hint);
$("prov").form.addEventListener("submit",function(e){if(!A)return;var err=A.validate($("prov").value,$("pin").value);if(err){e.preventDefault();$("tutPvOut").innerHTML="<span class=\\"bad\\">"+esc(err)+"</span>";var b=document.querySelector(".ta .tabs button[data-id=runs]");if(b)b.click();return;}$("aiOther").value=JSON.stringify(A.get());});
$("tutPvCheck").onclick=function(){var o=$("tutPvOut"),r=$("pvResult");o.textContent="Checking (one tiny request)…";r.hidden=true;api("test=1").then(function(j){var t=j.test||{};o.innerHTML=t.ok?"<span class=\\"ok\\">Connected.</span>":"<span class=\\"bad\\">Not working yet.</span>";
r.innerHTML="<table><tr><td>Provider</td><td>"+esc(A&&A.has(j.provider)?A.label(j.provider):j.provider==="foundry"?"Microsoft Foundry · Claude (Anthropic)":"Claude (Anthropic API)")+"</td></tr><tr><td>Process in</td><td>"+(j.processIn==="us"?"US only":"Global")+"</td></tr>"
+(A&&A.has(j.provider)?A.result(j,esc):j.provider==="foundry"?"<tr><td>Sign-in</td><td>"+esc((j.foundry||{}).auth)+"</td></tr>":"<tr><td>API key in Key Vault</td><td>"+((j.claudeApi||{}).keyConfigured?"yes":"NO")+"</td></tr>")
+"<tr><td>Test request</td><td>"+(t.ok?"OK · "+esc(t.model)+" · ran "+(t.processIn==="us"?"in the US":"globally")+" · "+(t.ms/1000).toFixed(1)+" s":"<span class=\\"bad\\">"+esc(t.error||"failed")+"</span>")+"</td></tr></table>";r.hidden=false;})
.catch(function(e){o.innerHTML="<span class=\\"bad\\">"+esc(e.message)+"</span>";});};
function post(p,b){return fetch(H+p,{method:"POST",headers:{Authorization:"Bearer "+T,"Content-Type":"application/json"},body:JSON.stringify(b||{})}).then(function(r){return r.json().then(function(j){if(!r.ok)throw new Error(j.error||r.status);return j;});});}
$("prCheck").onclick=function(e){e.preventDefault();var o=$("prOut");o.textContent="Reading Anthropic\u2019s pricing page\u2026";post("/api/prices",{force:true}).then(function(j){var n=0;Object.keys(j.models||{}).forEach(function(m){var x=j.models[m];if(!x)return;var i=document.querySelector("input[name=\\"price_in["+m+"]\\"]"),u=document.querySelector("input[name=\\"price_out["+m+"]\\"]");if(i&&u){i.value=x.in;u.value=x.out;n++;}});
o.innerHTML=n?"<span class=\\"ok\\">Filled in "+n+" price"+(n>1?"s":"")+" from "+esc(j.source)+".</span> Click Save &amp; publish to use them."+(j.problems&&j.problems.length?" Not found: "+esc(j.problems.join(", "))+".":""):"<span class=\\"bad\\">No prices found. Enter them by hand.</span>";}).catch(function(x){o.innerHTML="<span class=\\"bad\\">"+esc(x.message)+"</span>";});};
$("dueTest").onclick=function(){var o=$("dueOut");o.textContent="Looking\u2026";post("/api/due/test",{}).then(function(r){var it=r.preview||[];o.innerHTML=r.users?(it.length?"You would get "+it.length+": "+it.map(function(x){return esc(x.name)+" ("+esc(x.course)+")"+(x.missing!=null?" \u2014 "+x.missing+" of "+x.total+" not submitted":"");}).join("; ")+(r.sent?". Sent to your Teams.":"."):"Nothing is due for you in the next "+esc($("dueH").value)+" hours (saved setting).")
:"You don\u2019t use Ask Tut in Teams yet, so there is nowhere to send reminders. Add the Ask Tut app in Teams and connect once.";}).catch(function(x){o.innerHTML="<span class=\\"bad\\">"+esc(x.message)+"</span>";});};
})();');
echo $OUTPUT->footer();
