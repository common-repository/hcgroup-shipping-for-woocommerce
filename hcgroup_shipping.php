<?php
/**
* Plugin Name: Hcgroup Shipping for Woocommerce
* Plugin URI: http://www.hcgroup.cl/
* Description: Custom Shipping Method for WooCommerce
* Version: 2.2.5
* Author: HCGROUP
* Author URI: http://www.hcgroup.cl/
* License: GPL-3.0+
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
* Text Domain: hcgroup
*/
if ( ! defined( 'WPINC' ) ) {
	die;
}
/*
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	
	function hcgroup_shipping_method() {
		if ( ! class_exists( 'Hcgroup_Shipping_Method' ) ) {
			class Hcgroup_Shipping_Method extends WC_Shipping_Method {

				public function __construct() {
					$this->id                 = 'hcgroup'; 
					$this->method_title       = __( 'HCGroup Shipping for Woocommerce', 'hcgroup' );  
					$this->method_description = __( 'Custom Shipping Method for HCGROUP', 'hcgroup' ); 

					// Availability & Countries
					$this->availability = 'including';
					$this->countries = array('CL');

					$this->enabled = 'yes';
					$this->title = 'HCGROUP mas que distribucion';
					
					$this->init();					
				}

				function init() {
					// Load the settings API
					$this->init_form_fields(); 
					$this->init_settings();

					// Save settings in admin if you have any defined
					add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
				}

				function init_form_fields() { 
					$this->form_fields = array(
						'token' => array(
							'title' => __( 'Token Cliente', 'hcgroup' ),
							'type' => 'text',
							'description' => __( 'Token cliente validado', 'hcgroup' ),
							'default' => ''
						),
						'servicio' => array(
							'title' => __( 'Servicio', 'hcgroup' ),
							'type' => 'text',
							'description' => __( 'Nombre del servicio contratado validado', 'hcgroup' ),
							'default' => ''
						)
					);
				}

				/**
				* This function is used to calculate the shipping cost. Within this function we can check for weights, dimensions and other parameters.
				*/
				public function calculate_shipping( $package = array() ) {
					$peso = 0;
					$volumen = 0;
					$total = 0;
					$cost = 0;
					$country = $package["destination"]["country"];

					//conversor a Centimetros
					$um = get_option('woocommerce_dimension_unit');
					if ($um=="mm") $factor=0.1;
					if ($um=="cm") $factor=1;
					if ($um=="m") $factor=100;
					if ($um=="in") $factor=2.54;
					if ($um=="yd") $factor=91.44;

					foreach ( $package['contents'] as $item_id => $values ) 
					{ 
						$_product = $values['data']; 

						$peso = $peso + $_product->get_weight() * $values['quantity']; 

						$vol=wc_format_dimensions($_product->get_dimensions(false));
						$l=$_product->get_length()*$factor;
						$w=$_product->get_width()*$factor;
						$h=$_product->get_height()*$factor;

						$volumen=$volumen+round($l*$w*$h);

						$total = $total + $values['quantity'];

					}

					$peso = wc_get_weight( $peso, 'kg' );

					$hcgShippingMethod = new Hcgroup_Shipping_Method();
					$token = $hcgShippingMethod->settings['token'];
					$servicio = $hcgShippingMethod->settings['servicio'];
					$comuna = WC()->customer->get_shipping_city();
					//get_shipping_state
					//get_shipping_country

					$cost=preguntar_a_webservice_hcgroup($peso,$volumen,$total,$token,$comuna,$servicio);

					if ($cost<0){
						$this->add_rate(null);
					} else {
						$rate = array(
							'id' => $this->id,
							'label' => $this->title,
							'cost' => $cost
						);
						$this->add_rate( $rate );
					}
				}
			}
		}
	}
	add_action( 'woocommerce_shipping_init', 'hcgroup_shipping_method' );

	function add_hcgroup_shipping_method( $methods ) {
		$methods['hcgroup'] = 'Hcgroup_Shipping_Method';
		return $methods;
	}
	add_filter( 'woocommerce_shipping_methods', 'add_hcgroup_shipping_method' );

	function debug($info){
		$fichero = dirname(__FILE__).'/php-error.log';
		try {
			$actual = @file_get_contents($fichero);
			if ($actual === false) {
				file_put_contents($fichero, $info."\n");
				chmod($fichero, 0777);
			} else {
				$actual .= $info."\n";
				file_put_contents($fichero, $actual);
			}
		} catch (Exception $e) {
			echo $e;
		}
	}

	function preguntar_a_webservice_hcgroup($peso, $volumen, $total, $token, $comuna, $servicio) {
		$comuna = urlencode($comuna);
		if ($comuna!=""){
			$servicio = urlencode($servicio);
			$url='http://www.hcgroup.cl/ws/ecomerce.asmx/get_tarifa_despacho_JSON?token_cliente='.$token.'&servicio='.$servicio.'&comuna_destino='.$comuna.'&peso='.$peso.'&cantidad='.$total.'&volumen='.$volumen;
			
			date_default_timezone_set('America/Santiago');
            $ti=round(microtime(true) * 1000);
 
			$response = wp_remote_get( $url, array('timeout' => 120, 'httpversion' => '1.1') );
			$tf=round(microtime(true) * 1000);
            $tt=$tf-$ti;
            $np="";

			if ( is_wp_error( $response ) ) {
				$http_code = wp_remote_retrieve_response_code( $response );
				return -1;
			} else {
				$body = wp_remote_retrieve_body( $response );
				$dataArray=json_decode($body, true);
				$tarifa=$dataArray["tarifa"];
				
				$resp=implode(",", $dataArray);
				$plataforma="woocommerce";
                $url_log='https://www.oktaplus.cl/wslog.php?option=1&p='.$plataforma.'&t='.$token.'&url='.urlencode($url).'&r='.urlencode($resp).'&tms='.$tt.'&np='.$np;
                $response = wp_remote_get( $url_log, array('timeout' => 120, 'httpversion' => '1.1') );
            
				return $tarifa;
			}
		}
		return -1;
	}

	function get_seguimiento_order( $order ) {
	    $sm=$order->get_shipping_method();

		if ($sm=="HCGROUP mas que distribucion"){
			$isadmin=is_admin();

			$order_id=$order->get_order_number();
			$tn=get_post_meta( $order_id, '_iddespacho_hcgroup', true );

			$array_whc = get_option('woocommerce_hcgroup_settings');
			$token = $array_whc['token'];
			$servicio = $array_whc['servicio'];

			if ($tn!=""){
				if ($isadmin){
					validar_del_despacho($token, $order_id);
				}

				$token = $array_whc['token'];
				$url='http://www.hcgroup.cl/ws/ecomerce.asmx/get_seguimiento_JSON?token_cliente='.$token.'&id_despacho='.$tn.'&numero_seguimiento=';
				
				date_default_timezone_set('America/Santiago');
				$ti=round(microtime(true) * 1000);
				$response = wp_remote_get( $url, array('timeout' => 120, 'httpversion' => '1.1') );
				$tf=round(microtime(true) * 1000);
				$tt=$tf-$ti;

				if ( is_wp_error( $response ) ) {
					$http_code = wp_remote_retrieve_response_code( $response );
					echo 'Error='.$http_code;
				} else {
					$body = wp_remote_retrieve_body( $response );
					$dataArray=json_decode($body, true);
					$estado=$dataArray["estado"];
					$url_tracking=$dataArray["url_seguimiento"];
					$msn_error=$dataArray["mensaje_error"];

					$msgTemp="";
					if ($msn_error==""){
						if ($url_tracking==""){
							update_post_meta( $order_id, '_tracking_number', $estado );
							echo '<b>Actual estado:</b><br>'.get_post_meta( $order_id, '_tracking_number', true );
							$msgTemp="Actual estado:";
						} else {
							update_post_meta( $order_id, '_tracking_number', $url_tracking );
							echo '<b>Tracking Number:</b><br><a href="'.$url_tracking.'">'.get_post_meta( $order_id, '_tracking_number', true ).'</a>';
							$msgTemp="Tracking Number:";
						}				
					} else {
						echo '<b>Actual estado:</b><br>'.$msn_error;
						$msgTemp="Actual estado:";
					}
					
					$np=$order_id;
					$resp=$msgTemp." | ".implode(",", $dataArray);
					$plataforma="woocommerce";
					$url_log='https://www.oktaplus.cl/wslog.php?option=1&p='.$plataforma.'&t='.$token.'&url='.urlencode($url).'&r='.urlencode($resp).'&tms='.$tt.'&np='.$np;
					$response = wp_remote_get( $url_log, array('timeout' => 120, 'httpversion' => '1.1') );
				}
			} else {
				if ($isadmin){
					validar_add_despacho($token, $servicio, $order_id);
				}
			}
		}
	}
	
	add_action('woocommerce_order_details_after_order_table', 'get_seguimiento_order', 10, 1);
	add_action('woocommerce_admin_order_data_after_billing_address', 'get_seguimiento_order', 10, 3);

	function get_status_order($this_get_id, $this_status_transition_from, $this_status_transition_to, $instance) {

		$orderTmp = wc_get_order( $instance );
	    $sm=$orderTmp->get_shipping_method();
	    if ($sm=="HCGROUP mas que distribucion"){

			$array_whc = get_option('woocommerce_hcgroup_settings');
			$token = $array_whc['token'];
			$servicio = $array_whc['servicio'];

			$nombreJoin=$instance->get_shipping_first_name()." ".$instance->get_shipping_last_name();
			$nombre = urlencode($nombreJoin);
			$empresa = urlencode($instance->get_shipping_company());
			$cargo="";
			$referencia="";
			$latitud="";
			$longitud="";
			$documento_tributario="";
			$fecha_inicio_distribucion="";
			$fecha_limite_distribucion="";
			$bultos=1;
			$direccion = urlencode($instance->get_shipping_address_1()." ".$instance->get_shipping_address_2());
			$comuna = urlencode($instance->get_shipping_city());
			$telefono = urlencode($instance->get_billing_phone());
			$servicio = urlencode($servicio);
			$email = $instance->get_billing_email();
			$peso = get_post_meta( $this_get_id, '_cart_total_weight', true );
			$id_order=$this_get_id;
			$cantidad=$instance->get_item_count();
			$total_shipping=$instance->get_subtotal();

			$status=$this_status_transition_to;
			if ($status=="processing"){
				//call WS
				$url='http://www.hcgroup.cl/ws/ecomerce.asmx/add_despacho_JSON?token_cliente='.$token.'&nombre='.$nombre.'&empresa='.$empresa.'&cargo='.$cargo.'&direccion='.$direccion.'&comuna='.$comuna.'&referencia='.$referencia.'&telefono='.$telefono.'&correo_electronico='.$email.'&peso='.$peso.'&latitud='.$latitud.'&longitud='.$longitud.'&numero_seguimiento='.$id_order.'&documento_tributario='.$documento_tributario.'&servicio='.$servicio.'&fecha_inicio_distribucion='.$fecha_inicio_distribucion.'&fecha_limite_distribucion='.$fecha_limite_distribucion.'&cantidad='.$cantidad.'&monto='.$total_shipping.'&bultos='.$bultos;

				date_default_timezone_set('America/Santiago');
				$ti=round(microtime(true) * 1000);
				$response = wp_remote_get( $url, array('timeout' => 120, 'httpversion' => '1.1') );
				$tf=round(microtime(true) * 1000);
				$tt=$tf-$ti;

				if ( is_wp_error( $response ) ) {
					$http_code = wp_remote_retrieve_response_code( $response );
				} else {
					$body = wp_remote_retrieve_body( $response );
					$dataArray=json_decode($body, true);
					$numero=$dataArray["id_despacho"];
					$msn_error=$dataArray["mensaje_error"];
					
					$np=$id_order;
					$resp=implode(",", $dataArray);
					$plataforma="woocommerce";
					$url_log='https://www.oktaplus.cl/wslog.php?option=1&p='.$plataforma.'&t='.$token.'&url='.urlencode($url).'&r='.urlencode($resp).'&tms='.$tt.'&np='.$np;
					$response = wp_remote_get( $url_log, array('timeout' => 120, 'httpversion' => '1.1') );
					
					if ($msn_error==""){
						update_post_meta( $this_get_id, '_iddespacho_hcgroup', $numero );
					}
				}
			}
			if ($status=="cancelled"){
				//call WS
				$tn=get_post_meta( $this_get_id, '_iddespacho_hcgroup', true );
				$url='http://www.hcgroup.cl/ws/ecomerce.asmx/del_despacho_JSON?token_cliente='.$token.'&id_despacho='.$tn.'&numero_seguimiento=';
				
				date_default_timezone_set('America/Santiago');
				$ti=round(microtime(true) * 1000);
				$response = wp_remote_get( $url, array('timeout' => 120, 'httpversion' => '1.1') );
				$tf=round(microtime(true) * 1000);
				$tt=$tf-$ti;

				if ( is_wp_error( $response ) ) {
					$http_code = wp_remote_retrieve_response_code( $response );
				} else {
					$body = wp_remote_retrieve_body( $response );
					$dataArray=json_decode($body, true);
					$exito=$dataArray["exito"];
					$msn_error=$dataArray["mensaje_error"];
					
					$np=$tn;
					$resp=implode(",", $dataArray);
					$plataforma="woocommerce";
					$url_log='https://www.oktaplus.cl/wslog.php?option=1&p='.$plataforma.'&t='.$token.'&url='.urlencode($url).'&r='.urlencode($resp).'&tms='.$tt.'&np='.$np;
					$response = wp_remote_get( $url_log, array('timeout' => 120, 'httpversion' => '1.1') );

					if ($exito==1){
						delete_post_meta( $this_get_id, '_iddespacho_hcgroup', $tn );
						delete_post_meta( $this_get_id, '_tracking_number');
					}
				}
			}
		}
	}
	add_action('woocommerce_order_status_changed', 'get_status_order', 10, 4);

	function woo_add_cart_weight($order_id) {
		global $woocommerce;
		$weight = $woocommerce->cart->cart_contents_weight;
		update_post_meta( $order_id, '_cart_total_weight', $weight );
	}
	add_action('woocommerce_checkout_update_order_meta', 'woo_add_cart_weight', 10, 3);

	function validar_add_despacho($token, $servicio, $order_id){		
		$order = wc_get_order( $order_id );
		$data = $order->get_data();
		
		$nombreJoin=$data['shipping']['first_name']." ".$data['shipping']['last_name'];
		$nombre = urlencode($nombreJoin);
		$empresa = urlencode($data['shipping']['company']);
		$direccion = urlencode($data['shipping']['address_1']." ".$data['shipping']['address_2']);
		$comuna = urlencode($data['shipping']['city']);
		$telefono = urlencode($data['billing']['phone']);
		$email = urlencode($data['billing']['email']);
		$cargo="";
		$referencia="";
		$latitud="";
		$longitud="";
		$documento_tributario="";
		$fecha_inicio_distribucion="";
		$fecha_limite_distribucion="";
		$bultos=1;
		$peso = get_post_meta( $order_id, '_cart_total_weight', true );
		$orderData = wc_get_order( $order_id );
		$cantidad= count( $orderData->get_items() ); 
		$total_shipping=round($data['shipping_total']);

		$url='http://www.hcgroup.cl/ws/ecomerce.asmx/valida_add_despacho_JSON?token_cliente='.$token.'&nombre='.$nombre.'&empresa='.$empresa.'&cargo='.$cargo.'&direccion='.$direccion.'&comuna='.$comuna.'&referencia='.$referencia.'&telefono='.$telefono.'&correo_electronico='.$email.'&peso='.$peso.'&latitud='.$latitud.'&longitud='.$longitud.'&numero_seguimiento='.$order_id.'&documento_tributario='.$documento_tributario.'&servicio='.$servicio.'&fecha_inicio_distribucion='.$fecha_inicio_distribucion.'&fecha_limite_distribucion='.$fecha_limite_distribucion.'&cantidad='.$cantidad.'&monto='.$total_shipping.'&bultos='.$bultos;
		
		date_default_timezone_set('America/Santiago');
        $ti=round(microtime(true) * 1000);
		$response = wp_remote_get( $url, array('timeout' => 120, 'httpversion' => '1.1') );
		$tf=round(microtime(true) * 1000);
        $tt=$tf-$ti;
		
		if ( is_wp_error( $response ) ) {
			$http_code = wp_remote_retrieve_response_code( $response );
		} else {
			$body = wp_remote_retrieve_body( $response );
			$dataArray=json_decode($body, true);
			$msn_error=$dataArray["mensaje_error"];
			
			$msgTemp="";
			if ($msn_error!=""){
				echo "<p style='color:red;'>ERROR ENVIAR : ".$msn_error."</p><br>";
				$msgTemp="ERROR ENVIAR";
			} else {
				echo "<p style='color:green;'>Estado : Datos para predespacho ok</p><br>";
				$msgTemp="Estado : Datos para predespacho ok";
			}
			
			$np=$order_id;
			$resp=$msgTemp." | ".implode(",", $dataArray);
			$plataforma="woocommerce";
            $url_log='https://www.oktaplus.cl/wslog.php?option=1&p='.$plataforma.'&t='.$token.'&url='.urlencode($url).'&r='.urlencode($resp).'&tms='.$tt.'&np='.$np;
            $response = wp_remote_get( $url_log, array('timeout' => 120, 'httpversion' => '1.1') );
		}
	}

	function validar_del_despacho($token, $tn){
		$url='http://www.hcgroup.cl/ws/ecomerce.asmx/valida_del_despacho_JSON?token_cliente='.$token.'&id_despacho=&numero_seguimiento='.$tn;
		
		date_default_timezone_set('America/Santiago');
        $ti=round(microtime(true) * 1000);
		$response = wp_remote_get( $url, array('timeout' => 120, 'httpversion' => '1.1') );
		$tf=round(microtime(true) * 1000);
        $tt=$tf-$ti;
		
		if ( is_wp_error( $response ) ) {
			$http_code = wp_remote_retrieve_response_code( $response );
		} else {
			$body = wp_remote_retrieve_body( $response );
			$dataArray=json_decode($body, true);
			$msn_error=$dataArray["mensaje_error"];
			$msgTemp="";
			if ($msn_error!=""){
				echo "<p style='color:red;'>AVISO ESTADO CANCELAR : ".$msn_error."</p><br>";
				$msgTemp="ERROR CANCELAR";
			} else {
				echo "<p style='color:green;'>Estado : Datos para cancelar ok</p><br>";
				$msgTemp="Estado : Datos para cancelar ok";
			}
			
			$np=$tn;
			$resp=$msgTemp." | ".implode(",", $dataArray);
			$plataforma="woocommerce";
            $url_log='https://www.oktaplus.cl/wslog.php?option=1&p='.$plataforma.'&t='.$token.'&url='.urlencode($url).'&r='.urlencode($resp).'&tms='.$tt.'&np='.$np;
            $response = wp_remote_get( $url_log, array('timeout' => 120, 'httpversion' => '1.1') );
		}
	}
}
?>
