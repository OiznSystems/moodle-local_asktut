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
 * English strings.
 *
 * @package    local_asktut
 * @copyright  2026 Oizn Systems Inc. {@link https://asktut.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
$string['pluginname'] = 'Ask Tut';
$string['asktut:use'] = 'Use Ask Tut';
$string['asktut:manage'] = 'Administer Ask Tut (Tut Admin, Consumption)';
$string['privacy:metadata'] = 'Ask Tut stores, per request: the user, the question (if the admin keeps questions), the model, tokens and cost; and every change Tut made after the user approved it. Questions and the data Tut reads are sent to the AI provider chosen by the site (Claude API or Microsoft Foundry) through the Ask Tut service that runs in the site\'s own Azure.';
$string['privacy:metadata:usage'] = 'One row per Tut request';
$string['privacy:metadata:changes'] = 'Change history';
$string['privacy:metadata:userid'] = 'The user';
$string['privacy:metadata:question'] = 'The question asked';
$string['privacy:metadata:timecreated'] = 'When';
$string['privacy:metadata:ai'] = 'The AI provider chosen by the site';
$string['privacy:metadata:ai:question'] = 'The question and the Moodle data Tut read to answer it';
$string['harnessurl'] = 'Ask Tut service URL';
$string['harnessurl_desc'] = 'The Ask Tut service (Azure Function) in your own Azure, e.g. https://func-asktut-moodle-xxxx.azurewebsites.net';
$string['enabled'] = 'Show Ask Tut on every page';
$string['enabled_desc'] = 'Users with the capability local/asktut:use see the Ask Tut button.';
$string['tutadmin'] = 'Tut Admin';
$string['consumption'] = 'Tut Consumption';
$string['changes'] = 'Tut change history';
$string['saved'] = 'Tut Admin saved.';
$string['notenabled'] = 'Ask Tut is not set up yet: the site admin must set the Ask Tut service URL.';
$string['error'] = '{$a}';
$string['messageprovider:budgetalert'] = 'Ask Tut budget alerts (monthly limit and site budget)';
