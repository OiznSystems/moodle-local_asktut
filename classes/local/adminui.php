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
 * Shared layout helpers for the Tut Admin pages.
 *
 * @package    local_asktut
 * @copyright  2026 Oizn Systems Inc. {@link https://asktut.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Ask Tut for Moodle - Tut Admin page frame (18 Nov 2026, Mo: "needs to be broken into more tabs instead of one long page"):
// same look as the Dynamics 365 Tut Admin - header with Save & publish, pill tabs that wrap, one card per tab.
// Settings tabs live on admin.php; usage tabs (Consumption, Answer quality, Change history) on consumption.php.
namespace local_asktut\local;

defined('MOODLE_INTERNAL') || die();

class adminui {
    /** Settings tabs (admin.php), in groups (18 Nov 2026 evening, Mo): Access · What Tut knows · AI and cost · Look · Privacy. '|' = thin divider. */
    public const SETTINGS = ['who' => 'Who can use Tut', 'roles' => 'Role rules', 'perm' => 'Permissions', '|1' => '',
        'general' => 'Instructions', 'web' => 'Websites', '|2' => '',
        'model' => 'Model', 'runs' => 'Where the AI runs', 'cost' => 'Cost & limits', 'alerts' => 'Alerts', '|3' => '',
        'avatar' => 'Avatar', 'docs' => 'Documents', '|4' => '', 'privacy' => 'Privacy'];   // + Alerts, Documents & branding (19 Nov 2026, D365 parity)
    /** Usage tabs (consumption.php). */
    public const USAGE = ['cons' => 'Consumption', 'quality' => 'Answer quality', 'changes' => 'Change history'];

    /** Adds the stylesheet and the tab script. Call before $OUTPUT->header(). */
    public static function setup(string $page): void {
        global $PAGE;
        $PAGE->requires->css(new \moodle_url('/local/asktut/admin.css', ['v' => 5]));
        $tabs = json_encode(array_values(array_filter(array_keys($page === 'admin' ? self::SETTINGS : self::USAGE), fn($k) => $k[0] !== '|')));
        $PAGE->requires->js_init_code("(function(){var ids=$tabs,key='tutAdminTabMdl_$page';
function show(id){if(ids.indexOf(id)<0)id=ids[0];ids.forEach(function(k){var s=document.getElementById('sec_'+k);if(s)s.hidden=k!==id;});
document.querySelectorAll('.ta .tabs button[data-id]').forEach(function(b){b.setAttribute('aria-selected',b.getAttribute('data-id')===id?'true':'false');});
try{sessionStorage.setItem(key,id);}catch(e){}var t=document.getElementById('tut_tab');if(t)t.value=id;}
document.querySelectorAll('.ta .tabs button[data-id]').forEach(function(b){b.addEventListener('click',function(){show(b.getAttribute('data-id'));});});
var q=(location.search.match(/[?&]tab=(\\w+)/)||[])[1]||(location.hash||'').slice(1),s=null;try{s=sessionStorage.getItem(key);}catch(e){}
show(q||s||ids[0]);
document.querySelectorAll('.ta [data-add]').forEach(function(btn){btn.addEventListener('click',function(){var tb=document.getElementById(btn.getAttribute('data-add')),
tpl=document.getElementById(btn.getAttribute('data-add')+'_tpl');var tr=tpl.content.firstElementChild.cloneNode(true);tb.appendChild(tr);var i=tr.querySelector('input');if(i)i.focus();});});
function emptyCheck(){document.querySelectorAll('.ta [data-empty]').forEach(function(p){var b=document.getElementById(p.getAttribute('data-empty'));p.hidden=!!(b&&b.children.length);});}
document.addEventListener('click',function(e){if(e.target.classList&&e.target.classList.contains('x')&&e.target.closest('.ta')){e.preventDefault();e.target.closest('tr').remove();}setTimeout(emptyCheck,0);});emptyCheck();})();");
    }

    /** Header (logo, title, last saved, Save button on the settings page) + the tab bar. */
    public static function start(string $page, string $sub): string {
        $harness = rtrim((string)get_config('local_asktut', 'harnessurl'), '/');
        $h = '<div class="ta mdl"><header><img src="' . s($harness . '/api/asset/tutlogo.png') . '" alt=""><div><h1>Tut Admin</h1><div class="s">' . $sub . '</div></div><span class="sp"></span>'
            . ($page === 'admin' ? '<button class="btn" type="submit">Save &amp; publish</button>' : '') . '</header><main><div class="tabs" role="tablist">';
        foreach (self::SETTINGS as $id => $label) {
            if ($id[0] === '|') {
                $h .= '<span class="sep" aria-hidden="true"></span>';
                continue;
            }
            $h .= $page === 'admin' ? '<button type="button" role="tab" data-id="' . $id . '">' . s($label) . '</button>'
                : '<a href="' . (new \moodle_url('/local/asktut/admin.php', ['tab' => $id]))->out() . '">' . s($label) . '</a>';
        }
        $h .= '<span class="sep" aria-hidden="true"></span>'; // thin divider before the usage tabs (Mo: a line, not a "Usage" label)
        foreach (self::USAGE as $id => $label) {
            $h .= $page === 'admin' ? '<a href="' . (new \moodle_url('/local/asktut/consumption.php', ['tab' => $id]))->out() . '">' . s($label) . '</a>'
                : '<button type="button" role="tab" data-id="' . $id . '">' . s($label) . '</button>';
        }
        return $h . '</div>';
    }

    public static function end(): string {
        // Mo, 19 Nov 2026: the company avatar's name and picture replace "Tut" on Tut Admin and Consumption; "Powered by Ask Tut" in the footer.
        $av = (util::policy()['avatar'] ?? []);
        $harness = rtrim((string)get_config('local_asktut', 'harnessurl'), '/');
        $id = (string)($av['character'] ?? ($av['active'] ?? 'tutlogo'));
        $pic = ($id === 'custom' && !empty($av['custom'])) ? util::avatar_url((array)$av['custom'])
            : $harness . '/api/asset/' . rawurlencode($id) . '.png'; // the harness maps bot/guide/queen to their .webp files
        $kit = (string)@file_get_contents(__DIR__ . '/../../brandkit.js');
        $js = '(function(){var r=document.querySelector(".ta.mdl");if(!r||typeof TutBrand==="undefined")return;try{TutBrand.adminPage({avatar:'
            . json_encode($av, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '},{root:r,footerParent:r,skip:".avcard",pic:function(){return '
            . json_encode($pic, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) . ';}});}catch(e){}})();';
        return '</main></div><script>' . $kit . "\n" . $js . '</script>';
    }

    public static function section(string $id, string $title, string $hint, string $body): string {
        return '<section id="sec_' . $id . '"><h2>' . $title . '</h2>' . ($hint ? '<p class="hint">' . $hint . '</p>' : '') . $body . '</section>';
    }

    public static function sw(string $name, bool $on, string $label, string $help = ''): string {
        return '<label class="sw"><input type="checkbox" name="' . $name . '" value="1"' . ($on ? ' checked' : '') . '><span>' . s($label)
            . ($help ? '<small>' . s($help) . '</small>' : '') . '</span></label>';
    }

    public static function select(string $name, array $opts, string $cur, string $extra = ''): string {
        $h = '<select name="' . $name . '"' . $extra . '>';
        foreach ($opts as $k => $l) {
            $h .= '<option value="' . s($k) . '"' . ((string)$k === $cur ? ' selected' : '') . '>' . s($l) . '</option>';
        }
        return $h . '</select>';
    }

    /** An editable table: rows + a hidden template row + "Add …" button (rows posted as name[] arrays). */
    public static function table(string $id, array $head, array $rows, string $tplrow, string $addlabel, string $empty = ''): string {
        $h = '<table><thead><tr>';
        foreach ($head as $c) {
            $h .= '<th' . ($c === '' || $c === 'Read only' ? ' class="c"' : '') . '>' . s($c) . '</th>';
        }
        $h .= '</tr></thead><tbody id="' . $id . '">' . implode('', $rows) . '</tbody></table><template id="' . $id . '_tpl">' . $tplrow . '</template>';
        return $h . ($empty ? '<p class="empty" data-empty="' . $id . '"' . ($rows ? ' hidden' : '') . '>' . $empty . '</p>' : '')
            . '<button class="add" type="button" data-add="' . $id . '">' . s($addlabel) . '</button>';
    }

    public static function removecell(): string {
        return '<td class="c"><button class="x" type="button">Remove</button></td>';
    }
}
