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
 * Plugin settings: the Ask Tut service address and the Tut Admin pages.
 *
 * @package    local_asktut
 * @copyright  2026 Oizn Systems Inc. {@link https://asktut.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category('local_asktut_cat', get_string('pluginname', 'local_asktut')));
    $settings = new admin_settingpage('local_asktut', get_string('pluginname', 'local_asktut') . ' - service');
    $settings->add(new admin_setting_configtext('local_asktut/harnessurl', get_string('harnessurl', 'local_asktut'), get_string('harnessurl_desc', 'local_asktut'), '', PARAM_URL));
    $settings->add(new admin_setting_configcheckbox('local_asktut/enabled', get_string('enabled', 'local_asktut'), get_string('enabled_desc', 'local_asktut'), 1));
    $ADMIN->add('local_asktut_cat', $settings);
    $ADMIN->add('local_asktut_cat', new admin_externalpage('local_asktut_admin', get_string('tutadmin', 'local_asktut'), new moodle_url('/local/asktut/admin.php'), 'local/asktut:manage'));
    $ADMIN->add('local_asktut_cat', new admin_externalpage('local_asktut_consumption', get_string('consumption', 'local_asktut'), new moodle_url('/local/asktut/consumption.php'), 'local/asktut:manage'));
}
