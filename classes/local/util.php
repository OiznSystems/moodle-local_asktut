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
 * Shared helpers.
 *
 * @package    local_asktut
 * @copyright  2026 Oizn Systems Inc. {@link https://asktut.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_asktut\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Ask Tut helpers: who may use Tut, the user's Ask Tut token, the stored admin rules.
 */
class util {
    /** Shortname of the external service defined in db/services.php. */
    const SERVICE = 'local_asktut';

    /**
     * May this user use Tut? Site admins, anyone with local/asktut:use at site level, or anyone holding a role
     * (in any course or category) that allows local/asktut:use. Teachers hold their role in courses, not at site level.
     */
    public static function can_use(int $userid = 0): bool {
        global $USER, $DB;
        $userid = $userid ?: (int)$USER->id;
        if (!$userid || isguestuser($userid)) {
            return false;
        }
        if (is_siteadmin($userid) || has_capability('local/asktut:use', \context_system::instance(), $userid)) {
            return true;
        }
        $roles = get_roles_with_capability('local/asktut:use', CAP_ALLOW, \context_system::instance());
        if (!$roles) {
            return false;
        }
        [$in, $params] = $DB->get_in_or_equal(array_keys($roles), SQL_PARAMS_NAMED);
        $params['userid'] = $userid;
        return $DB->record_exists_select('role_assignments', "userid = :userid AND roleid $in", $params);
    }

    public static function can_manage(int $userid = 0): bool {
        global $USER;
        $userid = $userid ?: (int)$USER->id;
        return is_siteadmin($userid) || has_capability('local/asktut:manage', \context_system::instance(), $userid);
    }

    /** Role shortnames the user holds anywhere (for Tut Admin role rules). */
    public static function roles(int $userid): array {
        global $DB;
        $sql = "SELECT DISTINCT r.shortname FROM {role_assignments} ra JOIN {role} r ON r.id = ra.roleid WHERE ra.userid = ?";
        $out = array_values($DB->get_fieldset_sql($sql, [$userid]));
        if (is_siteadmin($userid)) {
            $out[] = 'siteadmin';
        }
        return $out;
    }

    /**
     * The signed-in user's token for the "Ask Tut" service (created on first use, valid 8 hours, renewed when under 1 hour).
     * Same mechanism as the Moodle mobile app: the token only reaches the functions in the service, as this user.
     */
    public static function user_token(): string {
        global $DB, $USER;
        $service = $DB->get_record('external_services', ['shortname' => self::SERVICE, 'enabled' => 1], '*', MUST_EXIST);
        $now = time();
        $sql = "userid = ? AND externalserviceid = ? AND tokentype = ? AND validuntil > ?";
        $rows = $DB->get_records_select('external_tokens', $sql, [$USER->id, $service->id, EXTERNAL_TOKEN_PERMANENT, $now + 3600], 'validuntil DESC', 'id, token', 0, 1);
        if ($rows) {
            return reset($rows)->token;
        }
        // Tidy up this user's expired Ask Tut tokens.
        $DB->delete_records_select('external_tokens', "userid = ? AND externalserviceid = ? AND validuntil > 0 AND validuntil < ?", [$USER->id, $service->id, $now]);
        return \core_external\util::generate_token(EXTERNAL_TOKEN_PERMANENT, $service, (int)$USER->id, \context_system::instance(), $now + 8 * 3600, '', 'Ask Tut pane');
    }

    /** Stored admin rules (JSON). The service merges them over its defaults and enforces them. */
    public static function policy(): array {
        $raw = get_config('local_asktut', 'policy');
        $p = $raw ? json_decode($raw, true) : null;
        return is_array($p) ? $p : [];
    }

    public static function save_policy(array $p): void {
        global $USER;
        set_config('policy', json_encode($p), 'local_asktut');
        set_config('policysaved', time() . ' ' . fullname($USER), 'local_asktut');
    }

    /** Avatars in the same order as the other editions; the 3D Tut is back as a choice (18 Nov 2026 evening, Mo: consistent with every edition). */
    public const AVATARS = ['tutlogo' => 'Tut', 'samurai' => 'Samurai', 'zeus' => 'Zeus', 'athena' => 'Athena', 'tut' => 'Tut', 'bot' => 'Bot', 'guide' => 'Guide', 'queen' => 'Queen', 'custom' => 'Your own'];

    /**
     * Stores the admin's own avatar (Tut Admin -> Avatar -> Your own): PNG = still, GIF = animated (+ a still first frame).
     * Clear background recommended; at least 64 x 64; max 1 MB. Files: system context, local_asktut/avatar/0/avatar.png|gif.
     * @return array ['v' => version, 'gif' => bool, 'warning' => string]
     */
    public static function store_avatar(string $path): array {
        if (!is_readable($path) || filesize($path) > 1048576) {
            throw new \moodle_exception('error', 'local_asktut', '', 'The picture is over 1 MB; please use a smaller file.');
        }
        $info = @getimagesize($path);
        if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_GIF])) {
            throw new \moodle_exception('error', 'local_asktut', '', 'Use a PNG or GIF picture.');
        }
        [$w, $h] = $info;
        if ($w < 64 || $h < 64) {
            throw new \moodle_exception('error', 'local_asktut', '', 'The picture is too small (at least 64 x 64 pixels).');
        }
        $raw = file_get_contents($path);
        $src = @imagecreatefromstring($raw); // a GIF gives its first frame
        if (!$src) {
            throw new \moodle_exception('error', 'local_asktut', '', 'That picture could not be read.');
        }
        $k = min(1, 360 / max($w, $h));
        $cw = max(1, (int)round($w * $k));
        $ch = max(1, (int)round($h * $k));
        $dst = imagecreatetruecolor($cw, $ch);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $cw, $ch, $w, $h);
        $clear = false;
        foreach ([[0, 0], [$cw - 1, 0], [0, $ch - 1], [$cw - 1, $ch - 1]] as [$x, $y]) {
            $clear = $clear || ((imagecolorat($dst, $x, $y) >> 24) & 0x7F) >= 64;
        }
        ob_start();
        imagepng($dst);
        $png = ob_get_clean();
        imagedestroy($dst);
        imagedestroy($src);
        $gif = $info[2] === IMAGETYPE_GIF;
        $ctx = \context_system::instance();
        $fs = get_file_storage();
        $fs->delete_area_files($ctx->id, 'local_asktut', 'avatar');
        $rec = ['contextid' => $ctx->id, 'component' => 'local_asktut', 'filearea' => 'avatar', 'itemid' => 0, 'filepath' => '/'];
        $fs->create_file_from_string($rec + ['filename' => 'avatar.png', 'mimetype' => 'image/png'], $png);
        if ($gif) {
            $fs->create_file_from_string($rec + ['filename' => 'avatar.gif', 'mimetype' => 'image/gif'], $raw);
        }
        return ['v' => time(), 'gif' => $gif, 'warning' => $clear ? '' : 'The corners of the picture are not see-through, so it may show as a box.'];
    }

    public static function remove_avatar(): void {
        get_file_storage()->delete_area_files(\context_system::instance()->id, 'local_asktut', 'avatar');
    }

    /** Public URL of the stored avatar picture (any signed-in user may load it, like the logo). */
    public static function avatar_url(array $custom, bool $gif = false): string {
        $u = \moodle_url::make_pluginfile_url(\context_system::instance()->id, 'local_asktut', 'avatar', 0, '/', $gif ? 'avatar.gif' : 'avatar.png');
        $u->param('v', (int)($custom['v'] ?? 0));
        return $u->out(false);
    }

    public static function require_tut_user(): void {
        if (!self::can_use()) {
            throw new \moodle_exception('nopermissions', 'error', '', 'Ask Tut (local/asktut:use)');
        }
    }
}
