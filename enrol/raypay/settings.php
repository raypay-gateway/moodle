<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    enrol_raypay
 * @copyright  RayPay
 * @author     Hanieh Ramzanpour
 * @license    https://raypay.ir/
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    //--- my order history ------------------------------------------------------------------------------------------
    $previewnode = $PAGE->navigation->add((get_string('raypay_history', 'enrol_raypay')), new moodle_url('/enrol/raypay/raypay_log.php'), navigation_node::TYPE_CONTAINER);
    $previewnode->make_active();

    //--- settings ------------------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_raypay_settings', '', get_string('pluginname_desc', 'enrol_raypay')));

    $settings->add(new admin_setting_configtext('enrol_raypay/user_id', get_string('user_id', 'enrol_raypay'), '', '', PARAM_RAW));

    $settings->add(new admin_setting_configtext('enrol_raypay/marketing_id', get_string('marketing_id', 'enrol_raypay'), '', '', PARAM_RAW));

    $settings->add(new admin_setting_configtext('enrol_raypay/currency', get_string('currency', 'enrol_raypay'), '', '', PARAM_RAW));

    $settings->add(new admin_setting_configcheckbox('enrol_raypay/sandbox', get_string('sandbox', 'enrol_raypay'), '', 0));

    $settings->add(new admin_setting_configcheckbox('enrol_raypay/mailstudents', get_string('mailstudents', 'enrol_raypay'), '', 0));

    $settings->add(new admin_setting_configcheckbox('enrol_raypay/mailteachers', get_string('mailteachers', 'enrol_raypay'), '', 0));

    $settings->add(new admin_setting_configcheckbox('enrol_raypay/mailadmins', get_string('mailadmins', 'enrol_raypay'), '', 0));

    // Note: let's reuse the ext sync constants and strings here, internally it is very similar,
    //       it describes what should happen when users are not supposed to be enrolled any more.
    $options = array(
        ENROL_EXT_REMOVED_KEEP => get_string('extremovedkeep', 'enrol'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        ENROL_EXT_REMOVED_UNENROL => get_string('extremovedunenrol', 'enrol'),
    );

    $settings->add(new admin_setting_configselect('enrol_raypay/expiredaction', get_string('expiredaction', 'enrol_raypay'), get_string('expiredaction_help', 'enrol_raypay'), ENROL_EXT_REMOVED_SUSPENDNOROLES, $options));



    //--- enrol instance defaults ----------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_raypay_defaults',
        get_string('enrolinstancedefaults', 'admin'), get_string('enrolinstancedefaults_desc', 'admin')));

    $options = array(ENROL_INSTANCE_ENABLED => get_string('yes'),
        ENROL_INSTANCE_DISABLED => get_string('no'));
    $settings->add(new admin_setting_configselect('enrol_raypay/status',
        get_string('status', 'enrol_raypay'), get_string('status_desc', 'enrol_raypay'), ENROL_INSTANCE_DISABLED, $options));

    $settings->add(new admin_setting_configtext('enrol_raypay/cost', get_string('cost', 'enrol_raypay'), '', 0, PARAM_FLOAT, 4));

    $raypaycurrencies = enrol_get_plugin('raypay')->get_currencies();
    $settings->add(new admin_setting_configselect('enrol_raypay/currency', get_string('currency', 'enrol_raypay'), '', 'USD', $raypaycurrencies));

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_raypay/roleid',
            get_string('defaultrole', 'enrol_raypay'), get_string('defaultrole_desc', 'enrol_raypay'), $student->id, $options));
    }

    $settings->add(new admin_setting_configduration('enrol_raypay/enrolperiod',
        get_string('enrolperiod', 'enrol_raypay'), get_string('enrolperiod_desc', 'enrol_raypay'), 0));

}
