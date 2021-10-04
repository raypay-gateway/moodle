<?php
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
global $CFG, $_SESSION, $USER, $DB,$OUTPUT;

$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);
$plugininstance = new enrol_raypay_plugin();

if ($plugininstance->get_config('currency') !== "IRR") {
  echo $OUTPUT->header();
  echo '<h3 dir="rtl" style="text-align:center; color: red;">' . 'واحد پولی پشتیبانی نمیشود.' . '</h3>';
  echo '<div class="single_button" style="text-align:center;"><a href="' . $CFG->wwwroot . '/enrol/index.php?id=' . $_POST['course_id'] . '"><button> بازگشت به صفحه قبلی  </button></a></div>';
  echo $OUTPUT->footer();
  exit;
}

if (!empty($_POST['multi'])) {
    $instance_array = unserialize($_POST['instances']);
    $ids_array = unserialize($_POST['ids']);
    $_SESSION['idlist'] = implode(',', $ids_array);
    $_SESSION['inslist'] = implode(',', $instance_array);
    $_SESSION['multi'] = $_POST['multi'];
} else {
    $_SESSION['courseid'] = $_POST['course_id'];
    $_SESSION['instanceid'] = $_POST['instance_id'];
}
$_SESSION['totalcost'] = $_POST['amount'];
$_SESSION['userid'] = $USER->id;

//make information
$user_id = $plugininstance->get_config('user_id');
$marketing_id = $plugininstance->get_config('marketing_id');
$invoice_id             = round(microtime(true) * 1000);
$sandbox = (bool)$plugininstance->get_config('sandbox') == 1;
$amount = round($_POST['amount'] , 0);
$mail = $USER->email;
$description = 'وبسایت مودل پرداخت شهریه ' . $_POST['item_name'];
$phone = $USER->phone1;
$user_name = $USER->firstname . ' ' . $USER->lastname;
$item_name = $_POST['item_name'];
$course_id = (int)$_POST['course_id'];
$url = 'https://api.raypay.ir/raypay/api/v1/payment/pay';

$data = new stdClass();
$data->amount = $amount;
$data->courseid = $course_id;
$data->userid = $USER->id;
$data->username = $user_name;
$data->item_name = $item_name;
$data->receiver_email = $mail;
$data->instanceid = $_POST['instance_id'];
$data->log = "در حال انتقال به درگاه";
$order_id = $DB->insert_record("enrol_raypay", $data);
$callback = $CFG->wwwroot . "/enrol/raypay/verify.php?order_id=" .$order_id;

$params = array(
    'factorNumber' => strval($order_id),
    'amount' => strval($amount),
    'userID' => $user_id,
    'marketingID' => $marketing_id,
    'invoiceID'    => strval($invoice_id),
    'fullName' => $user_name,
    'mobile' => $phone,
    'email' => $mail,
    'desc' => $description,
    'redirectUrl' => $callback,
    'enableSandBox' => $sandbox
);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
));

$result = curl_exec($ch);
$result = json_decode($result);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = $DB->get_record('enrol_raypay', ['id' => $order_id]);
$data->raypay_id = $invoice_id;

if ($http_status != 200 || empty($result)) {
    $msg = sprintf('خطا هنگام ایجاد تراکنش. وضعیت خطا: %s - کد خطا: %s - پیام خطا: %s', $http_status, $result->StatusCode, $result->Message);
    $data->log = $msg;
    $DB->update_record('enrol_raypay', $data);
    echo $OUTPUT->header();
    echo '<h3 dir="rtl" style="text-align:center; color: red;">' . $msg . '</h3>';
    echo '<div class="single_button" style="text-align:center;"><a href="' . $CFG->wwwroot . '/enrol/index.php?id=' . $_POST['course_id'] . '"><button> بازگشت به صفحه قبلی  </button></a></div>';
    echo $OUTPUT->footer();
    exit;
} else {
    $DB->update_record('enrol_raypay', $data);
    $token = $result->Data;
    $link='https://my.raypay.ir/ipg?token=' . $token;
    Header("Location: $link");
    exit;
}
