<?php
/** Isolated amount/protocol regressions; no network, database, or WordPress install. */
define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
class WP_Error {
    public function __construct(public $code, public $message) {}
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function __($text, $domain) { return $text; }
function apply_filters($name, $value) { return isset($GLOBALS['filters'][$name]) ? $GLOBALS['filters'][$name]($value) : $value; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; $GLOBALS['ttls'][$key] = $ttl; }
function wp_remote_get($url, $args) { ++$GLOBALS['requests']; $GLOBALS['http_args']=$args; return $GLOBALS['http']; }
function wp_remote_retrieve_response_code($response) { return $response['response']['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function wc_get_price_decimals() { return 2; }
function wc_format_decimal($value, $dp) { return number_format((float)$value,$dp,'.',''); }
function sanitize_text_field($value) { return strip_tags($value); }
function wp_unslash($value) { return stripslashes($value); }
require dirname(__DIR__) . '/includes/class-mojito-sinpe-payment.php';
use Mojito_Sinpe\Mojito_Sinpe_Payment as Payment;
$checks=[];
function check($value,$name) { if (!$value) throw new RuntimeException($name); $GLOBALS['checks'][]=$name; }
$settings=['number'=>'+506 8888-9999','exchange-rate-origin'=>'custom','exchange-rate-custom'=>'510.25'];
check(Payment::phone($settings['number'])==='88889999','CR phone normalization');
foreach(['',[], '11111111','+188889999','888899999','8<script>8889999'] as $phone) check(Payment::phone($phone)==='', 'reject malformed phone '.json_encode($phone));
foreach(['-1','NaN',INF,[],null] as $value) check(is_wp_error(Payment::calculate($value,'CRC',$settings)), 'reject invalid total '.json_encode($value));
check(Payment::calculate(100,'CRC',$settings)['amount']==='100','CRC never multiplied');
check(Payment::calculate(2,'USD',$settings)['amount']==='1021','USD round half up');
check(is_wp_error(Payment::calculate(1,'EUR',$settings)),'unsupported currency');
foreach(['0', '-1','no',INF] as $rate) check(is_wp_error(Payment::calculate(1,'USD',array_merge($settings,['exchange-rate-custom'=>$rate]))),'reject invalid rate '.json_encode($rate));
check(is_wp_error(Payment::calculate(1,'USD',array_merge($settings,['exchange-rate-enable'=>'no']))),'USD needs conversion enabled');
$GLOBALS['filters']['mojito_sinpe_exchange_rate']=fn($rate)=>500;
check(Payment::calculate(2,'USD',$settings)['amount']==='1000','legacy exchange-rate filter');
$GLOBALS['filters']['mojito_sinpe_amount']=fn($amount)=>$amount+5;
check(Payment::calculate(2,'USD',$settings)['amount']==='1005','legacy amount filter');
$GLOBALS['filters']=[];
$GLOBALS['requests']=0;
$GLOBALS['http']=['response'=>['code'=>200],'body'=>'{"dolar":{"venta":{"valor":510.25}}}'];
$settings['exchange-rate-origin']='hacienda';
check(Payment::calculate(2,'USD',$settings)['amount']==='1021','HTTP valid Hacienda rate');
check(Payment::calculate(3,'USD',$settings)['amount']==='1531' && $GLOBALS['requests']===1,'cache avoids repeated HTTP calls');
check($GLOBALS['ttls']['mojito_sinpe_hacienda_rate_v1']===21600,'six-hour success cache');
check($GLOBALS['http_args']['timeout']===5,'HTTP five-second timeout');
foreach([new WP_Error('offline','offline'),['response'=>['code'=>500],'body'=>'{}'],['response'=>['code'=>200],'body'=>'invalid JSON'],['response'=>['code'=>200],'body'=>'{"dolar":{"venta":{"valor":0}}}']] as $response) {
 $GLOBALS['transients']=[]; $GLOBALS['http']=$response;
 check(is_wp_error(Payment::calculate(2,'USD',$settings)),'Hacienda failure does not use rate 1');
 $before=$GLOBALS['requests']; Payment::calculate(2,'USD',$settings);
 check($before===$GLOBALS['requests'],'brief failure cache');
}
$message=Payment::message(['amount'=>'1234','recipient'=>'88889999']);
check($message==='Pase 1234 88889999','stable bank command');
check(Payment::sms_url('2627',$message,'Android')==='sms:2627?body=Pase%201234%2088889999','Android shortcode not internationalized');
check(Payment::sms_url('2627',$message,'iPhone')==='sms:2627&body=Pase%201234%2088889999','iPhone separator');
check(Payment::sms_url('2627&body=bad',$message,'Android')==='','reject protocol injection');
echo json_encode(['php'=>PHP_VERSION,'checks'=>count($checks),'passed'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
