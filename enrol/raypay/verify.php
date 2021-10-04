﻿<?php
/**
 * @package    enrol_raypay
 * @copyright  RayPay
 * @author     Hanieh Ramzanpour
 * @license    https://raypay.ir/
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once("lib.php");
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/filelib.php');
global $CFG, $_SESSION, $USER, $DB, $OUTPUT;
$systemcontext = context_system::instance();
$plugininstance = new enrol_raypay_plugin();
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_url('/enrol/raypay/verify.php');
echo $OUTPUT->header();
$Price = $_SESSION['totalcost'];
$Authority = $_GET['Authority'];
$plugin = enrol_get_plugin('raypay');
$today = date('Y-m-d');




$order_id = $_GET['order_id'];
// Dosent exist order
if (!$order = $DB->get_record('enrol_raypay', ['id' => $order_id])) {
    $msg = 'سفارش پیدا نشد.';
    echo '<div style="color:red; font-family:tahoma; direction:rtl; text-align:right">' . $msg . '<br/></div>';
    die;
}
    // Send request to raypay and get response
    $url = 'https://api.raypay.ir/raypay/api/v1/payment/verify';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($_POST));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json',));
    $result = curl_exec($ch);
    $result = json_decode($result);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($http_status != 200) {
        $msg = sprintf('خطا هنگام استعلام تراکنش. وضعیت خطا: %s - کد خطا: %s - پیام خطا: %s', $http_status, $result->StatusCode, $result->Message);
        $order->log = $msg;
        $DB->update_record('enrol_raypay', $order);
        echo '<div style="color:red; font-family:tahoma; direction:rtl; text-align:right">' . $msg . '<br/></div>';
        exit();

    } else {

        $verify_status = empty($result->Data->Status) ? NULL : $result->Data->Status;
        $verify_invoice_id = empty($result->Data->InvoiceID) ? NULL : $result->Data->InvoiceID;

        if (empty($verify_status) || empty($verify_invoice_id)  || $verify_status !== 1) {
            $msg = 'پرداخت شما ناموفق بود. لطفا دوباره تلاش کنید یا با مدیریت سایت تماس بگیرید.';
            $order->log = $msg;
            $DB->update_record('enrol_raypay', $order);
            echo '<div style="color:red; font-family:tahoma; direction:rtl; text-align:right">' . $msg . '<br/></div>';
            die;

        } else {
                $Refnumber = $res->RefID; //Transaction number
                $Resnumber = $res->RefID;//Your Order ID
                $Status = $res->Status;
                $PayPrice = ($Price / 10);
                $coursecost = $DB->get_record('enrol', ['enrol' => 'raypay', 'courseid' => $data->courseid]);
                $time = strtotime($today);
                $paidprice = $coursecost->cost;
                $order->payment_status = $status;
                $order->timeupdated = time();

                if (!$user = $DB->get_record("user", ["id" => $order->userid])) {
                    message_raypay_error_to_admin('کاربر پیدا نشد.', $order);
                    die;
                }
                if (!$course = $DB->get_record("course", ["id" => $order->courseid])) {
                    message_raypay_error_to_admin('درس پیدا نشد.', $order);
                    die;
                }
                if (!$context = context_course::instance($course->id, IGNORE_MISSING)) {
                    message_raypay_error_to_admin('محتوا پیدا نشد.', $order);
                    die;
                }
                if (!$plugin_instance = $DB->get_record("enrol", ["id" => $order->instanceid, "status" => 0])) {
                    message_raypay_error_to_admin('پلاگین پرداخت فعال نیست.', $order);
                    die;
                }

                $coursecontext = context_course::instance($course->id, IGNORE_MISSING);

                if ((float)$plugin_instance->cost <= 0) {
                    $cost = (float)$plugin->get_config('cost');
                } else {
                    $cost = (float)$plugin_instance->cost;
                }

                // Use the same rounding of floats as on the enrol form.
                $cost = format_float($cost, 2, false);

                // Use the queried course's full name for the item_name field.
                $data->item_name = $course->fullname;

                // ALL CLEAR !
                $DB->update_record('enrol_raypay', $order);

                if ($plugin_instance->enrolperiod) {
                    $timestart = time();
                    $timeend = $timestart + $plugin_instance->enrolperiod;
                } else {
                    $timestart = 0;
                    $timeend = 0;
                }

                // Enrol user    die('s');
                $plugin->enrol_user($plugin_instance, $user->id, $plugin_instance->roleid, $timestart, $timeend);

                // Pass $view=true to filter hidden caps if the user cannot see them
                if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC', '', '', '', '', false, true)) {
                    $users = sort_by_roleassignment_authority($users, $context);
                    $teacher = array_shift($users);
                } else {
                    $teacher = false;
                }

                $mailstudents = $plugin->get_config('mailstudents');
                $mailteachers = $plugin->get_config('mailteachers');
                $mailadmins = $plugin->get_config('mailadmins');
                $shortname = format_string($course->shortname, true, array('context' => $context));

                if (!empty($mailstudents)) {
                    $a = new stdClass();
                    $a->coursename = format_string($course->fullname, true, array('context' => $coursecontext));
                    $a->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id";
                    $eventdata = new \core\message\message();
                    $eventdata->courseid = $course->id;
                    $eventdata->modulename = 'moodle';
                    $eventdata->component = 'enrol_raypay';
                    $eventdata->name = 'raypay_enrolment';
                    $eventdata->userfrom = empty($teacher) ? core_user::get_noreply_user() : $teacher;
                    $eventdata->userto = $user;
                    $eventdata->subject = get_string("enrolmentnew", 'enrol', $shortname);
                    $eventdata->fullmessage = get_string('welcometocoursetext', '', $a);
                    $eventdata->fullmessageformat = FORMAT_PLAIN;
                    $eventdata->fullmessagehtml = '';
                    $eventdata->smallmessage = '';
                    message_send($eventdata);
                }

                if (!empty($mailteachers) && !empty($teacher)) {
                    $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
                    $a->user = fullname($user);

                    $eventdata = new \core\message\message();
                    $eventdata->courseid = $course->id;
                    $eventdata->modulename = 'moodle';
                    $eventdata->component = 'enrol_raypay';
                    $eventdata->name = 'raypay_enrolment';
                    $eventdata->userfrom = $user;
                    $eventdata->userto = $teacher;
                    $eventdata->subject = get_string("enrolmentnew", 'enrol', $shortname);
                    $eventdata->fullmessage = get_string('enrolmentnewuser', 'enrol', $a);
                    $eventdata->fullmessageformat = FORMAT_PLAIN;
                    $eventdata->fullmessagehtml = '';
                    $eventdata->smallmessage = '';
                    message_send($eventdata);
                }

                if (!empty($mailadmins)) {
                    $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
                    $a->user = fullname($user);
                    $admins = get_admins();

                    foreach ($admins as $admin) {
                        $eventdata = new \core\message\message();
                        $eventdata->courseid = $course->id;
                        $eventdata->modulename = 'moodle';
                        $eventdata->component = 'enrol_raypay';
                        $eventdata->name = 'raypay_enrolment';
                        $eventdata->userfrom = $user;
                        $eventdata->userto = $admin;
                        $eventdata->subject = get_string("enrolmentnew", 'enrol', $shortname);
                        $eventdata->fullmessage = get_string('enrolmentnewuser', 'enrol', $a);
                        $eventdata->fullmessageformat = FORMAT_PLAIN;
                        $eventdata->fullmessagehtml = '';
                        $eventdata->smallmessage = '';
                        message_send($eventdata);
                    }
                }

                $msgForSaveDataTDataBase = "کد پیگیری :  $verify_invoice_id ";
                $order->log = $msgForSaveDataTDataBase;
                $DB->update_record('enrol_raypay', $order);
                echo '<h3 style="text-align:center; color: green;">با تشکر از شما، پرداخت شما با موفقیت انجام شد و به  درس انتخاب شده افزوده شدید.</h3>';
                echo "<h3 style='text-align:center; color: green;'>کد پیگیری :  $verify_invoice_id  </h3>";
                echo '<div class="single_button" style="text-align:center;"><a href="' . $CFG->wwwroot . '/course/view.php?id=' . $course->id . '"><button>ورود به درس خریداری شده</button></a></div>';

        }

    }

//----------------------------------------------------- HELPER FUNCTIONS --------------------------------------------------------------------------


function message_raypay_error_to_admin($subject, $data)
{
    echo $subject;
    $admin = get_admin();
    $site = get_site();
    $message = "$site->fullname:  Transaction failed.\n\n$subject\n\n";
    foreach ($data as $key => $value) {
        $message .= "$key => $value\n";
    }
    $eventdata = new \core\message\message();
    $eventdata->modulename = 'moodle';
    $eventdata->component = 'enrol_raypay';
    $eventdata->name = 'raypay_enrolment';
    $eventdata->userfrom = $admin;
    $eventdata->userto = $admin;
    $eventdata->subject = "raypay ERROR: " . $subject;
    $eventdata->fullmessage = $message;
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml = '';
    $eventdata->smallmessage = '';
    message_send($eventdata);

}
echo $OUTPUT->footer();
