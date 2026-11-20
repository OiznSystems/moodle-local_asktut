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
 * External function: budget alerts in the Moodle bell.
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

/**
 * Budget alerts in the Moodle bell (19 Nov 2026, D365 parity). Called by the Tut service after each request.
 * The plugin works the numbers out itself from its own usage table and Tut Admin's rules, and sends each alert ONCE per
 * level per month (popup notification only; e-mail is disallowed for this message type). Nothing the caller sends is trusted.
 */
class budget_check extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }
    public static function execute(): array {
        global $DB, $USER;
        self::validate_context(\context_system::instance());
        util::require_tut_user();
        $p = util::policy();
        $a = ($p['alerts'] ?? []) + ['enabled' => true, 'thresholds' => [80, 100], 'notifyUser' => true];
        if (empty($a['enabled'])) {
            return ['sent' => 0];
        }
        $th = array_values(array_filter(array_map('intval', (array)$a['thresholds']), fn($x) => $x >= 10 && $x <= 100));
        rsort($th);
        $th = $th ?: [100, 80];
        $month = date('Y-m');
        $start = make_timestamp((int)date('Y'), (int)date('n'), 1, 0, 0, 0, 'UTC');
        $user = (float)$DB->get_field_sql("SELECT COALESCE(SUM(cost_usd), 0) FROM {local_asktut_usage} WHERE userid = ? AND timecreated >= ?", [$USER->id, $start]);
        $org = (float)$DB->get_field_sql("SELECT COALESCE(SUM(cost_usd), 0) FROM {local_asktut_usage} WHERE timecreated >= ?", [$start]);
        $cost = $p['cost'] ?? [];
        $limit = (float)($cost['userMonthlyLimit'] ?? 10);
        foreach (($cost['userLimits'] ?? []) as $x) {
            if (in_array(\core_text::strtolower((string)($x['user'] ?? '')), [\core_text::strtolower($USER->username), \core_text::strtolower((string)$USER->email)], true)) {
                $limit = (float)$x['limit'];
            }
        }
        $budget = (float)($cost['monthlyBudget'] ?? 50);
        $money = fn($v) => '$' . number_format($v, 2);
        $url = new \moodle_url('/local/asktut/consumption.php');
        $sent = 0;
        $once = function(string $key): bool {   // insert-only flag per alert per month
            $name = 'alert_' . substr(sha1($key), 0, 30);
            if (get_config('local_asktut', $name)) {
                return false;
            }
            set_config($name, time(), 'local_asktut');
            return true;
        };
        $send = function(\stdClass $to, string $subject, string $text) use ($url, &$sent) {
            $m = new \core\message\message();
            $m->component = 'local_asktut';
            $m->name = 'budgetalert';
            $m->userfrom = \core_user::get_noreply_user();
            $m->userto = $to;
            $m->subject = $subject;
            $m->fullmessage = $text;
            $m->fullmessageformat = FORMAT_PLAIN;
            $m->fullmessagehtml = '<p>' . s($text) . '</p>';
            $m->smallmessage = $subject;
            $m->notification = 1;
            $m->contexturl = $url->out(false);
            $m->contexturlname = 'Tut usage';
            if (message_send($m)) {
                $sent++;
            }
        };
        $admins = array_values(get_admins());
        if ($limit > 0) {
            foreach ($th as $t) {
                if ($user / $limit * 100 >= $t) {
                    if ($once("$month:user:{$USER->id}:$t")) {
                        $subject = $t >= 100 ? 'Tut monthly limit reached' : "Tut: $t% of your monthly limit used";
                        if (!empty($a['notifyUser'])) {
                            $send($USER, $subject, "You've used " . $money($user) . " of your " . $money($limit) . " Tut limit for $month." . ($t >= 100 ? ' Tut pauses for you until the 1st unless a Tut admin raises your limit.' : ''));
                        }
                        if ($t >= 100) {
                            foreach ($admins as $ad) {
                                $send($ad, 'Tut limit reached: ' . fullname($USER), fullname($USER) . " used " . $money($user) . " of a " . $money($limit) . " monthly limit. Raise it in Tut Admin → Cost & limits if needed.");
                            }
                        }
                    }
                    break;
                }
            }
        }
        if ($budget > 0) {
            foreach ($th as $t) {
                if ($org / $budget * 100 >= $t) {
                    if ($once("$month:org:$t")) {
                        foreach ($admins as $ad) {
                            $send($ad, $t >= 100 ? 'Tut monthly budget used up' : "Tut: $t% of the monthly budget used", "Tut has used " . $money($org) . " of the " . $money($budget) . " budget for $month across all users.");
                        }
                    }
                    break;
                }
            }
        }
        return ['sent' => $sent];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(['sent' => new external_value(PARAM_INT, 'notifications sent now')]);
    }
}
