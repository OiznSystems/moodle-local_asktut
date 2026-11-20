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
 * The floating Ask Tut button that opens the side pane.
 *
 * @package    local_asktut
 * @copyright  2026 Oizn Systems Inc. {@link https://asktut.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Ask Tut - the floating button: opens/closes the pane (loaded on first open), remembers open state per tab.
define([], function() {
    return {
        init: function() {
            var fab = document.getElementById('asktut-fab'), drawer = document.getElementById('asktut-drawer');
            var frame = document.getElementById('asktut-frame'), close = document.getElementById('asktut-close');
            if (!fab || !drawer || !frame) {
                return;
            }
            var open = function(v) {
                if (v && !frame.getAttribute('src')) {
                    frame.setAttribute('src', frame.getAttribute('data-src'));
                }
                drawer.style.display = v ? 'block' : 'none';
                fab.style.display = v ? 'none' : 'block';
                try { sessionStorage.setItem('asktut.open', v ? '1' : ''); } catch (e) { /* private mode */ }
            };
            fab.addEventListener('click', function() { open(true); });
            close.addEventListener('click', function() { open(false); });
            try { if (sessionStorage.getItem('asktut.open')) { open(true); } } catch (e) { /* private mode */ }
        }
    };
});
