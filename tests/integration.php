<?php
/** Run with SINPE_TEST_SITE=1 wp eval-file tests/integration.php in a disposable DB. */
if ( '1' !== getenv( 'SINPE_TEST_SITE' ) || ! str_starts_with( DB_NAME, 'sinpe_qa_' ) ) {
    throw new RuntimeException('Integration tests require a disposable sinpe_qa_ database and SINPE_TEST_SITE=1.');
}
use Mojito_Sinpe\Mojito_Sinpe;
use Mojito_Sinpe\Mojito_Sinpe_Gateway as Gateway;
use Mojito_Sinpe\Mojito_Sinpe_Payment as Payment;
use Automattic\WooCommerce\StoreApi\Payments\PaymentContext;
use Automattic\WooCommerce\StoreApi\Payments\PaymentResult;
$GLOBALS['checks']=[];
function sinpe_check($condition,$name) { if (!$condition) throw new RuntimeException($name); $GLOBALS['checks'][]=$name; }
function sinpe_order($currency='CRC') {
    $order=wc_create_order();
    $order->set_currency($currency);
    $order->set_payment_method(Gateway::ID);
    $order->set_billing_email('customer@example.test');
    $order->set_total(12.50);
    $order->save();
    return $order;
}
wp_set_current_user(1);
update_option('woocommerce_currency','CRC');
$settings=['enabled'=>'yes','number'=>'88889999','show-banks-list-in-checkout'=>'yes','ask-voucher-id'=>'yes','show-in-checkout'=>'yes','show-in-email'=>'yes','show-in-thankyou-page'=>'yes','exchange-rate-enable'=>'yes','exchange-rate-origin'=>'custom','exchange-rate-custom'=>'510.25'];
update_option('woocommerce_mojito-sinpe_settings',$settings);
$gateway=Mojito_Sinpe::gateway();
sinpe_check($gateway instanceof Gateway,'gateway registered with original ID');
$gateway->init_settings(); $gateway->enabled='yes';
sinpe_check($gateway->get_option('number')==='88889999','real gateway settings key preserved');
$data=['mojito_sinpe_bank'=>'bn','mojito_sinpe_voucher_id'=>'TEST-REFERENCE'];
$order=sinpe_order();
foreach([[],['mojito_sinpe_bank'=>'unknown','mojito_sinpe_voucher_id'=>'x'],['mojito_sinpe_bank'=>[]],['mojito_sinpe_bank'=>'bn','mojito_sinpe_voucher_id'=>'  ']] as $bad) {
 sinpe_check(is_wp_error($gateway->process_sinpe_order($order,$bad)),'server rejects missing or malformed payment data');
}
$before_email=null;
$observer=static function($id) use (&$before_email) { $before_email=wc_get_order($id)->get_meta('_mojito_sinpe_payment'); };
add_action('woocommerce_order_status_pending_to_on-hold',$observer,1);
sinpe_check($gateway->process_sinpe_order($order,$data)===true,'shared processor accepts valid CRC payment');
remove_action('woocommerce_order_status_pending_to_on-hold',$observer,1);
$fresh=wc_get_order($order->get_id());
sinpe_check($fresh->has_status('on-hold'),'persisted on-hold status');
sinpe_check($fresh->get_meta('mojito_sinpe_bank')==='bn','bank persists through order CRUD');
sinpe_check(is_array($before_email)&&$before_email['amount']==='13','snapshot present before status email hooks');
sinpe_check(Payment::for_order($fresh,$settings)['amount']==='13','CRC snapshot round half up');
$notes=count(wc_get_order_notes(['order_id'=>$fresh->get_id()]));
sinpe_check($gateway->process_sinpe_order($order,$data)===true,'retry succeeds without repeating processing');
sinpe_check(count(wc_get_order_notes(['order_id'=>$fresh->get_id()]))===$notes,'retry does not duplicate notes');
$testcore=new Mojito_Sinpe();
$request=new WP_REST_Request('GET','/mojito-sinpe/v1/open-payment-link/');
$request->set_param('order',$fresh->get_id());$request->set_param('key',$fresh->get_order_key());
sinpe_check($testcore->can_open_payment_link($request)===true,'valid guest order key permits instructions');
$request->set_param('key','tampered');
sinpe_check(is_wp_error($testcore->can_open_payment_link($request)),'tampered key denied');
$request->set_param('key',$fresh->get_order_key());
$paid=wc_get_order($fresh->get_id());$paid->update_status('processing');
sinpe_check(is_wp_error($testcore->can_open_payment_link($request)),'paid order instructions denied');
sinpe_check(is_wp_error($gateway->process_sinpe_order($paid,$data)),'paid order cannot return to on-hold');
sinpe_check(wc_get_order($paid->get_id())->has_status('processing'),'paid status preserved');
$usd=sinpe_order('USD');
sinpe_check($gateway->process_sinpe_order($usd,$data)===true,'USD processing');
$usd=wc_get_order($usd->get_id());
sinpe_check(Payment::for_order($usd,$settings)['amount']==='6378','USD amount converted to CRC');
$newsettings=array_merge($settings,['number'=>'77778888','exchange-rate-custom'=>700]);
sinpe_check(Payment::for_order($usd,$newsettings)['amount']==='6378' && Payment::for_order($usd,$newsettings)['recipient']==='88889999','saved quote survives later rate/recipient changes');
$usd->set_total(20);$usd->save();
sinpe_check(is_wp_error(Payment::for_order(wc_get_order($usd->get_id()),$settings)),'changed total invalidates instructions');
$testcore->regenerate($usd);
sinpe_check(Payment::for_order(wc_get_order($usd->get_id()),$settings)['amount']==='10205','authorized admin regenerates quote');
wp_set_current_user(0);$usd->set_total(21);$usd->save();$testcore->regenerate($usd);
sinpe_check(is_wp_error(Payment::for_order(wc_get_order($usd->get_id()),$settings)),'guest cannot regenerate instructions');
wp_set_current_user(1);
sinpe_check(is_wp_error(Payment::for_order(sinpe_order('USD'),$settings)),'legacy USD cannot invent historical rate');
sinpe_check(!is_wp_error(Payment::for_order(sinpe_order(),$settings)),'legacy CRC remains readable');
$blocked=sinpe_order();$lock='mojito_sinpe_order_'.$blocked->get_id();
add_option($lock,time(),'',false);
sinpe_check(is_wp_error($gateway->process_sinpe_order($blocked,$data)),'active processing lease rejects concurrent request');
update_option($lock,time()-301);
sinpe_check($gateway->process_sinpe_order($blocked,$data)===true,'expired lease recovers');
$classic=sinpe_order();$_POST=$data;
sinpe_check($gateway->process_payment($classic->get_id())['result']==='success','classic gateway result and redirect');
$_POST=[];
$registry=Automattic\WooCommerce\Blocks\Package::container()->get(Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry::class);
$block=$registry->get_registered(Gateway::ID);
sinpe_check($block instanceof \Mojito_Sinpe\Mojito_Sinpe_Gateway_Block,'blocks registered despite plugin load order');
$blockdata=$block->get_payment_method_data();
sinpe_check($blockdata['showBanks']&&$blockdata['askVoucher'],'blocks shares gateway configuration');
$storeorder=sinpe_order();
$context=new PaymentContext();$context->set_payment_method(Gateway::ID);$context->set_order($storeorder);$context->set_payment_data($data);
$result=new PaymentResult();$block->process($context,$result);
sinpe_check($result->__get('status')==='success' && $result->__get('redirect_url'),'Store API sets payment result and redirect');
sinpe_check(wc_get_order($storeorder->get_id())->get_meta('mojito_sinpe_bank')==='bn','Store API bank persisted');
$mail=WC()->mailer()->get_emails()['WC_Email_Customer_On_Hold_Order'];
$storeorder=wc_get_order($storeorder->get_id());
ob_start();$testcore->email($storeorder,false,false,$mail);$html=ob_get_clean();
ob_start();$testcore->email($storeorder,false,true,$mail);$plain=ob_get_clean();
sinpe_check(str_contains($html,'Pase 13 88889999')&&str_contains($html,'key='),'HTML email uses snapshot and authorized URL');
sinpe_check(str_contains($plain,'Pase 13 88889999')&&!str_contains($plain,'<p>'),'text email contains no HTML, even on resend');
wc_load_cart();
$product=new WC_Product_Simple();$product->set_name('SINPE fictitious product');$product->set_regular_price('10');$product->set_virtual(true);$product->save();
WC()->cart->empty_cart();WC()->cart->add_to_cart($product->get_id(),2);WC()->cart->calculate_totals();
$cart=rest_do_request(new WP_REST_Request('GET','/wc/store/v1/cart'));
$cartdata=json_decode(wp_json_encode($cart->get_data()),true);
sinpe_check(isset($cartdata['extensions']['mojito-sinpe'])&&$cartdata['extensions']['mojito-sinpe']['amount']==='20'&&$cartdata['extensions']['mojito-sinpe']['recipient']==='88889999','Store API cart extension provides server amount');
WC()->cart->set_quantity(WC()->cart->find_product_in_cart(WC()->cart->generate_cart_id($product->get_id())),3);
$cartdata=json_decode(wp_json_encode(rest_do_request(new WP_REST_Request('GET','/wc/store/v1/cart'))->get_data()),true);
sinpe_check($cartdata['extensions']['mojito-sinpe']['amount']==='30','Store API amount updates with quantity');
update_option('woocommerce_currency','USD');
$cartdata=json_decode(wp_json_encode(rest_do_request(new WP_REST_Request('GET','/wc/store/v1/cart'))->get_data()),true);
sinpe_check($cartdata['extensions']['mojito-sinpe']['amount']==='15308','Store API USD conversion');
update_option('woocommerce_currency','CRC');
$gateway->settings['show-banks-list-in-checkout']='no';
$gateway->settings['show-in-checkout']='no';
$manual=sinpe_order();
sinpe_check($gateway->process_sinpe_order($manual,[])===true,'bank list hidden permits manual app transfer');
ob_start();$testcore->email(wc_get_order($manual->get_id()),false,true,$mail);$manualtext=ob_get_clean();
sinpe_check(str_contains($manualtext,'88889999')&&!str_contains($manualtext,'open-payment-link'),'hidden bank list still gives recipient instructions');
$gateway->settings=$settings;
update_option('woocommerce_currency','CRC');

// A physical order reserves/reduces stock once; its caller sees the updated state.
update_option('woocommerce_manage_stock','yes');
$physical=new WC_Product_Simple();$physical->set_name('SINPE physical fictitious');$physical->set_regular_price('10');$physical->set_manage_stock(true);$physical->set_stock_quantity(10);$physical->save();
$stockorder=sinpe_order();$stockorder->add_product($physical,2);$stockorder->calculate_totals();$stockorder->save();
sinpe_check($gateway->process_sinpe_order($stockorder,$data)===true,'physical order processed');
sinpe_check($stockorder->has_status('on-hold'),'caller order refreshed after processing');
sinpe_check(wc_get_product($physical->get_id())->get_stock_quantity()===8,'physical stock reduced once');
$gateway->process_sinpe_order($stockorder,$data);
sinpe_check(wc_get_product($physical->get_id())->get_stock_quantity()===8,'retry leaves inventory unchanged');
$gateway->enabled='no';
sinpe_check(is_wp_error($gateway->process_sinpe_order(sinpe_order(),$data)),'disabled gateway cannot process Store API payment');
$gateway->enabled='yes';

$customer=get_user_by('login','sinpe-fixture-customer');
if(!$customer){$id=wp_insert_user(['user_login'=>'sinpe-fixture-customer','user_pass'=>'Local-only-fixture-2026','user_email'=>'fixture@example.test','role'=>'customer']);if(is_wp_error($id))throw new RuntimeException($id->get_error_message());$customer=get_user_by('id',$id);}
$registered=sinpe_order();$registered->set_customer_id($customer->ID);$registered->save();
wp_set_current_user($customer->ID);
sinpe_check($gateway->process_sinpe_order($registered,$data)===true,'registered customer order processed');
sinpe_check(wc_get_order($registered->get_id())->get_customer_id()===$customer->ID,'registered customer ownership preserved');
wp_set_current_user(1);
$hpos=Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
global $wpdb;
if($hpos) {
 sinpe_check('no'===get_option('woocommerce_custom_orders_table_data_sync_enabled'),'HPOS synchronization disabled');
 $meta=$wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->prefix}wc_orders_meta WHERE order_id=%d AND meta_key='mojito_sinpe_bank'",$storeorder->get_id()));
 sinpe_check($meta==='bn','bank physically stored in HPOS order table');
}
echo json_encode(['wordpress'=>get_bloginfo('version'),'woocommerce'=>WC_VERSION,'php'=>PHP_VERSION,'hpos'=>$hpos,'checks'=>count($GLOBALS['checks']),'passed'=>$GLOBALS['checks']],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
