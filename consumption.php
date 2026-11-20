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
 * Consumption, answer quality and change history for Ask Tut.
 *
 * @package    local_asktut
 * @copyright  2026 Oizn Systems Inc. {@link https://asktut.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Ask Tut for Moodle - usage tabs of Tut Admin (18 Nov 2026): Consumption, Answer quality, Change history (month picker + CSV).
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
admin_externalpage_setup('local_asktut_consumption');
require_capability('local/asktut:manage', context_system::instance());
$days = optional_param('days', 30, PARAM_INT);
$month = optional_param('month', '', PARAM_RAW_TRIMMED); // YYYY-MM (month picker), else the last N days
$since = time() - max(1, min(366, $days)) * 86400;
$until = time() + 86400;
if (preg_match('/^(\d{4})-(\d{2})$/', $month, $mm)) {
    $since = make_timestamp((int)$mm[1], (int)$mm[2], 1, 0, 0, 0, 99);
    $until = make_timestamp((int)$mm[1] + ((int)$mm[2] === 12 ? 1 : 0), (int)$mm[2] === 12 ? 1 : (int)$mm[2] + 1, 1, 0, 0, 0, 99);
} else {
    $month = '';
}
if (optional_param('download', 0, PARAM_BOOL)) { // CSV for Excel: one row per question
    $rows = $DB->get_records_sql("SELECT t.*, u.firstname, u.lastname FROM {local_asktut_usage} t JOIN {user} u ON u.id = t.userid
        WHERE t.timecreated >= ? AND t.timecreated < ? ORDER BY t.timecreated", [$since, $until]);
    $cell = function($v) { $v = (string)$v; if (preg_match('/^[=+\-@]/', $v)) { $v = "'" . $v; } return '"' . str_replace('"', '""', $v) . '"'; };
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ask-tut-moodle-' . ($month ?: 'last-' . (int)$days . '-days') . '.csv"');
    echo "\xEF\xBB\xBF" . implode(',', array_map($cell, ['When', 'Person', 'Question', 'Model', 'Cost USD', 'Tools', 'Approval', 'Rating', 'Feedback'])) . "\r\n";
    foreach ($rows as $r) {
        echo implode(',', array_map($cell, [date('Y-m-d H:i', $r->timecreated), $r->firstname . ' ' . $r->lastname, $r->question, $r->model,
            sprintf('%.6f', $r->cost_usd), $r->tools, $r->approval, $r->rating === null ? '' : ($r->rating > 0 ? 'up' : 'down'), (string)($r->feedback ?? '')])) . "\r\n";
    }
    die;
}

$tot = $DB->get_record_sql("SELECT COUNT(1) n, COALESCE(SUM(cost_usd),0) cost, COALESCE(SUM(input_tokens+output_tokens+cache_write_tokens+cache_read_tokens),0) tokens,
    COALESCE(SUM(refusals),0) refusals, COUNT(DISTINCT userid) users,
    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) up, SUM(CASE WHEN rating = -1 THEN 1 ELSE 0 END) down
    FROM {local_asktut_usage} WHERE timecreated >= ? AND timecreated < ?", [$since, $until]);
$byuser = $DB->get_records_sql("SELECT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
    COUNT(1) n, SUM(t.cost_usd) cost, MAX(t.timecreated) lastt
    FROM {local_asktut_usage} t JOIN {user} u ON u.id = t.userid WHERE t.timecreated >= ? AND t.timecreated < ? GROUP BY u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename ORDER BY cost DESC", [$since, $until], 0, 50);
$bymodel = $DB->get_records_sql("SELECT model, COUNT(1) n, SUM(cost_usd) cost FROM {local_asktut_usage} WHERE timecreated >= ? AND timecreated < ? GROUP BY model ORDER BY n DESC", [$since, $until]);
$recent = $DB->get_records_sql("SELECT t.*, u.firstname, u.lastname FROM {local_asktut_usage} t JOIN {user} u ON u.id = t.userid WHERE t.timecreated >= ? AND t.timecreated < ? ORDER BY t.timecreated DESC", [$since, $until], 0, 50);

$quality = $DB->get_records_sql("SELECT t.*, u.firstname, u.lastname FROM {local_asktut_usage} t JOIN {user} u ON u.id = t.userid
    WHERE t.timecreated >= ? AND t.timecreated < ? AND t.rating IS NOT NULL ORDER BY t.timecreated DESC", [$since, $until], 0, 200);
$changes = $DB->get_records_sql("SELECT c.*, u.firstname, u.lastname FROM {local_asktut_changes} c JOIN {user} u ON u.id = c.userid
    WHERE c.timecreated >= ? AND c.timecreated < ? ORDER BY c.timecreated DESC", [$since, $until], 0, 200);
$tab = optional_param('tab', '', PARAM_ALPHA);

\local_asktut\local\adminui::setup('usage');
echo $OUTPUT->header();
echo \local_asktut\local\adminui::start('usage', 'Usage of Ask Tut in Moodle — ' . ($month ? userdate($since, '%B %Y') : 'last ' . (int)$days . ' days') . '.');
$opts = ['' => 'Last 30 days'];
for ($i = 0, $t0 = time(); $i < 12; $i++) {
    $ts = strtotime(date('Y-m-01', $t0) . " -$i month");
    $opts[date('Y-m', $ts)] = userdate($ts, '%B %Y');
}
$picker = function(string $id) use ($opts, $month, $days) {
    return '<form method="get" class="row" style="margin-bottom:6px"><input type="hidden" name="tab" value="' . $id . '"><span>Month</span>'
        . html_writer::select($opts, 'month', $month, false, ['onchange' => 'this.form.submit()', 'class' => 'sm'])
        . html_writer::link(new moodle_url('/local/asktut/consumption.php', ['month' => $month, 'days' => $days, 'download' => 1]), 'Download CSV (Excel)') . '</form>';
};
$tbl = function(array $head, array $rows) {
    $h = '<table><thead><tr>';
    foreach ($head as $c) {
        $h .= '<th' . (in_array($c, ['Questions', 'Cost', 'Cost (USD)'], true) ? ' class="n"' : '') . '>' . $c . '</th>';
    }
    $h .= '</tr></thead><tbody>';
    foreach ($rows as $r) {
        $h .= '<tr>' . implode('', array_map(fn($c) => '<td' . (is_numeric($c) || preg_match('/^\$?[\d.,]+$/', (string)$c) ? ' class="n"' : '') . '>' . $c . '</td>', $r)) . '</tr>';
    }
    return $h . ($rows ? '' : '<tr><td colspan="' . count($head) . '" class="hint">Nothing yet for this period.</td></tr>') . '</tbody></table>';
};
$when = fn($t) => userdate($t, get_string('strftimedatetimeshort'));
$budget = (float)((\local_asktut\local\util::policy()['cost']['monthlyBudget'] ?? 0));
$tile = fn($v, $l, $extra = '') => '<div class="tile"><b>' . $v . '</b><span>' . $l . '</span>' . $extra . '</div>';

// Consumption
$bar = $month && $budget > 0 ? '<div class="bar"><i style="width:' . min(100, round($tot->cost / $budget * 100, 1)) . '%"></i></div>' : '';
$h = $picker('cons') . '<div class="tiles">' . $tile((int)$tot->n, 'questions') . $tile('$' . format_float($tot->cost, 2), 'cost' . ($month && $budget > 0 ? ' of $' . format_float($budget, 2) . ' budget' : ' (USD)'), $bar)
    . $tile((int)$tot->users, 'people') . $tile(number_format((int)$tot->tokens), 'tokens') . $tile(count($changes), 'approved changes') . '</div>';
$h .= '<div class="grid2"><div><h3>By person</h3>' . $tbl(['Person', 'Questions', 'Cost (USD)', 'Last'], array_map(fn($r) => [s(fullname($r)), (int)$r->n, '$' . format_float($r->cost, 4), $when($r->lastt)], array_values($byuser)))
    . '</div><div><h3>By model</h3>' . $tbl(['Model', 'Questions', 'Cost (USD)'], array_map(fn($r) => [s($r->model), (int)$r->n, '$' . format_float($r->cost, 4)], array_values($bymodel))) . '</div></div>';
$h .= '<h3>Latest questions</h3>' . $tbl(['When', 'Person', 'Question', 'Model', 'Tools', 'Approval', 'Cost'], array_map(fn($r) => [$when($r->timecreated), s($r->firstname . ' ' . $r->lastname),
    s(shorten_text((string)$r->question, 120)), s($r->model), s($r->tools), s($r->approval), '$' . format_float($r->cost_usd, 4)], array_values($recent)));
echo \local_asktut\local\adminui::section('cons', 'Consumption', 'What Tut cost and who used it (from Tut\'s usage log in Moodle\'s database).', $h);

// Answer quality
$up = (int)$tot->up;
$down = (int)$tot->down;
$h = $picker('quality') . '<div class="tiles">' . $tile($up, '👍 helpful') . $tile($down, '👎 not helpful')
    . $tile($up + $down ? round($up / ($up + $down) * 100) . '%' : '—', 'helpful') . $tile((int)$tot->refusals, 'refused by your rules') . '</div>';
$h .= $tbl(['When', 'Person', 'Question', 'Rating', 'Feedback'], array_map(fn($r) => [$when($r->timecreated), s($r->firstname . ' ' . $r->lastname), s(shorten_text((string)$r->question, 120)),
    $r->rating > 0 ? '👍' : '👎', s((string)($r->feedback ?? ''))], array_values($quality)));
echo \local_asktut\local\adminui::section('quality', 'Answer quality', 'What people said with 👍 / 👎 under Tut\'s answers.', $h);

// Change history
$h = $picker('changes') . $tbl(['When', 'Approved by', 'Change', 'On', 'Before', 'After', 'Result'], array_map(fn($r) => [$when($r->timecreated), s($r->firstname . ' ' . $r->lastname), s($r->operation), s($r->target),
    s(shorten_text((string)$r->before_values, 160)), s(shorten_text((string)$r->after_values, 160)), s($r->result) . ($r->error_text ? ': ' . s($r->error_text) : '')], array_values($changes)));
echo \local_asktut\local\adminui::section('changes', 'Change history', 'Every change Tut made in Moodle, who approved it, before and after.', $h);

echo \local_asktut\local\adminui::end();
echo $OUTPUT->footer();
