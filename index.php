<?php

add_action( 'rcl_payments_gateway_init', 'rcl_gateway_sberbank_init', 10 );
function rcl_gateway_sberbank_init() {
	rcl_gateway_register( 'sberbank', 'Rcl_Sberbank_Payment' );
}

class Rcl_Sberbank_Payment extends Rcl_Gateway_Core {

	public $currency_codes = array(
		'USD'	 => '840',
		'UAH'	 => '980',
		'RUB'	 => '643',
		'RON'	 => '946',
		'KZT'	 => '398',
		'KGS'	 => '417',
		'JPY'	 => '392',
		'GBR'	 => '826',
		'EUR'	 => '978',
		'CNY'	 => '156',
		'BYR'	 => '974',
		'BYN'	 => '933'
	);

	function __construct() {
		parent::__construct( array(
			'request'	 => 'sbr-payment',
			'name'		 => 'Сбербанк',
			'submit'	 => __( 'Оплатить через Сбербанк' ),
			'icon'		 => rcl_addon_url( 'icon.jpg', __FILE__ )
		) );
	}

	function get_options() {

		return array(
			array(
				'type'	 => 'text',
				'slug'	 => 'sbr_login',
				'title'	 => __( 'Логин' ),
				'notice' => __( 'Укажите логин выданный банком' )
			),
			array(
				'type'	 => 'password',
				'slug'	 => 'sbr_password',
				'title'	 => __( 'Пароль' ),
				'notice' => __( 'Укажите пароль выданный банком' )
			),
			array(
				'type'	 => 'select',
				'slug'	 => 'sbr_test',
				'title'	 => __( 'Режим подключения' ),
				'values' => [
					__( 'Рабочий' ),
					__( 'Тестовый' )
				]
			),
			array(
				'type'		 => 'select',
				'slug'		 => 'sbr_fn',
				'title'		 => __( 'Фискализация платежа' ),
				'values'	 => array(
					__( 'Отключено' ),
					__( 'Включено' )
				),
				'childrens'	 => array(
					1 => array(
						array(
							'type'	 => 'select',
							'slug'	 => 'sbr_tax',
							'title'	 => __( 'Система налогообложения' ),
							'values' => array(
								0	 => __( 'ОСН' ),
								1	 => __( 'УСН Доходы' ),
								2	 => __( 'УСН Доходы-Расходы' ),
								3	 => __( 'ЕНВД' ),
								4	 => __( 'ЕСН' ),
								5	 => __( 'ПСН' )
							)
						),
						array(
							'type'	 => 'select',
							'slug'	 => 'sbr_nds',
							'title'	 => __( 'Ставка НДС' ),
							'values' => array(
								0	 => __( 'без НДС' ),
								1	 => __( 'НДС по ставке 0%' ),
								2	 => __( 'НДС по ставке 10%' ),
								3	 => __( 'НДС по ставке 18%' ),
								4	 => __( 'НДС по ставке 10/110' ),
								5	 => __( 'НДС по ставке 18/118' ),
								6	 => __( 'НДС по ставке 20%' ),
								7	 => __( 'НДС по ставке 20/120' )
							)
						)
					)
				)
			) );
	}

	function get_form( $data ) {

		$login	 = rcl_get_commerce_option( 'sbr_login' );
		$pass	 = rcl_get_commerce_option( 'sbr_password' );

		if ( rcl_get_commerce_option( 'sbr_test' ) ) {
			$action_adr = 'https://3dsec.sberbank.ru/payment/rest/register.do';
		} else {
			$action_adr = 'https://securepayments.sberbank.ru/payment/rest/register.do';
		}

		$language = substr( get_bloginfo( "language" ), 0, 2 );
		if ( $language == 'uk' ) {
			$language = 'ua';
		}

		$args = array(
			'userName'		 => $login,
			'password'		 => $pass,
			'orderNumber'	 => $data->pay_id,
			'amount'		 => $data->pay_summ * 100,
			'language'		 => $language,
			'returnUrl'		 => add_query_arg( array(
				'sbr-payment'	 => $login,
				'payment-id'	 => $data->pay_id
				), get_permalink( $data->page_result ) ),
			'failUrl'		 => add_query_arg( array( 'sbr-login' => $login ), get_permalink( $data->page_fail ) ),
			'currency'		 => $this->currency_codes[$data->currency],
			'description'	 => $data->description,
			'clientId'		 => $data->user_id,
			'jsonParams'	 => json_encode(
				array(
					'CMS:'			 => 'Wordpress ' . get_bloginfo( 'version' ) . " + wp-recall " . RCL()->version,
					'payType'		 => $data->pay_type,
					'baggageData'	 => $data->baggage_data,
					'userId'		 => $data->user_id,
				)
			),
		);

		if ( rcl_get_commerce_option( 'sbr_fn' ) ) {

			$args['taxSystem'] = rcl_get_commerce_option( 'sbr_tax' );

			$items = array();

			if ( $data->pay_type == 1 ) {

				$quantity	 = intval( $data->pay_summ );
				$price		 = number_format( 1, 2, '.', '' ) * 100;

				$items[] = array(
					'positionId'	 => 1,
					'name'			 => __( 'Пополнение личного счета' ),
					'quantity'		 => array(
						'value'		 => $quantity,
						'measure'	 => rcl_get_primary_currency( 0 )
					),
					'itemCode'		 => 'usr-blnc',
					//'itemAmount'		 => $quantity * 100,
					'itemPrice'		 => 1 * 100,
					'tax'			 => [
						'taxType' => rcl_get_commerce_option( 'sbr_nds' ),
					],
					'itemAttributes' => array(
						'attributes' => array(
							array(
								'name'	 => 'paymentMethod',
								'value'	 => 1
							),
							array(
								'name'	 => 'paymentObject',
								'value'	 => 1
							)
						)
					)
				);
			} else if ( $data->pay_type == 2 ) {

				$order = rcl_get_order( $data->pay_id );

				if ( $order ) {

					$price = number_format( $data->pay_summ, 2, '.', '' ) * 100;

					$items[] = array(
						'positionId'	 => 1,
						'quantity'		 => array(
							'value'		 => 1,
							'measure'	 => 'шт'
						),
						'itemPrice'		 => $price,
						'itemCode'		 => "order:$data->pay_id",
						'tax'			 => [
							'taxType' => rcl_get_commerce_option( 'sbr_nds' ),
						],
						'name'			 => __( 'Оплата заказа' ) . ' №' . $order->order_id,
						'itemAttributes' => array(
							'attributes' => array(
								array(
									'name'	 => 'paymentMethod',
									'value'	 => 1
								),
								array(
									'name'	 => 'paymentObject',
									'value'	 => 1
								)
							)
						)
					);
				}
			} else {

				$price = number_format( $data->pay_summ, 2, '.', '' ) * 100;

				$items[] = array(
					'positionId'	 => 1,
					'quantity'		 => array(
						'value'		 => 1,
						'measure'	 => 'шт'
					),
					'itemPrice'		 => $price,
					'itemCode'		 => $data->pay_type,
					'tax'			 => [
						'taxType' => rcl_get_commerce_option( 'sbr_nds' ),
					],
					'name'			 => $data->description,
					'itemAttributes' => array(
						'attributes' => array(
							array(
								'name'	 => 'paymentMethod',
								'value'	 => 1
							),
							array(
								'name'	 => 'paymentObject',
								'value'	 => 1
							)
						)
					)
				);
			}

			$order_bundle = array(
				'customerDetails'	 => array(
					'email' => get_the_author_meta( 'email', $data->user_id )
				),
				'cartItems'			 => array( 'items' => $items )
			);

			/* Заполнение массива данных для запроса c фискализацией */
			$args['orderBundle'] = json_encode( $order_bundle );
		}

		$rbsCurl = curl_init();
		curl_setopt_array( $rbsCurl, array(
			CURLOPT_HTTPHEADER		 => array(
				'CMS: Wordpress ' . get_bloginfo( 'version' ) . " + wp-recall version: " . RCL()->version
			),
			CURLOPT_URL				 => $action_adr,
			CURLOPT_RETURNTRANSFER	 => true,
			CURLOPT_SSL_VERIFYPEER	 => false,
			CURLOPT_POST			 => true,
			CURLOPT_POSTFIELDS		 => http_build_query( $args, '', '&' )
		) );

		$response = curl_exec( $rbsCurl );
		curl_close( $rbsCurl );

		$response = json_decode( $response, true );

		if ( empty( $response['errorCode'] ) ) {

			return parent::construct_form( [
					'onclick'	 => 'location.replace("' . $response['formUrl'] . '");return false;',
					'fields'	 => array(
						'sum'				 => $data->pay_summ,
						'orderNumber'		 => $data->pay_id,
						'customerNumber'	 => $data->user_id,
						'SBR_Type_Pay'		 => $data->pay_type,
						'SBR_Baggage_Data'	 => $data->baggage_data
					)
				] );
		} else {
			return rcl_get_notice( ['type' => 'error', 'text' => __( 'Ошибка #' . $response['errorCode'] . ': ' . $response['errorMessage'], 'woocommerce' ) ] );
		}
	}

	function result( $data ) {

		$login	 = rcl_get_commerce_option( 'sbr_login' );
		$pass	 = rcl_get_commerce_option( 'sbr_password' );

		if ( isset( $_REQUEST['sbr-payment'] ) AND $_REQUEST['sbr-payment'] == $login ) {

			if ( rcl_get_commerce_option( 'sbr_test' ) ) {
				$action_adr = 'https://3dsec.sberbank.ru/payment/rest/getOrderStatusExtended.do';
			} else {
				$action_adr = 'https://securepayments.sberbank.ru/payment/rest/getOrderStatusExtended.do';
			}

			$args = array(
				'userName'	 => $login,
				'password'	 => $pass,
				'orderId'	 => $_REQUEST['orderId'],
			);

			$rbsCurl = curl_init();
			curl_setopt_array( $rbsCurl, array(
				CURLOPT_URL				 => $action_adr,
				CURLOPT_RETURNTRANSFER	 => true,
				CURLOPT_POST			 => true,
				CURLOPT_SSL_VERIFYPEER	 => false,
				CURLOPT_POSTFIELDS		 => http_build_query( $args, '', '&' ),
				CURLOPT_HTTPHEADER		 => array(
					'CMS:' => 'Wordpress ' . get_bloginfo( 'version' ) . " + wp-recall " . RCL()->version
				),
			) );

			$response = curl_exec( $rbsCurl );
			curl_close( $rbsCurl );

			$response = json_decode( $response, true );

			$orderStatus = $response['orderStatus'];
			if ( $orderStatus == '1' || $orderStatus == '2' ) {

				$params = array();
				foreach ( $response['merchantOrderParams'] as $param ) {
					$params[$param['name']] = $param['value'];
				}

				if ( ! parent::get_payment( $response['orderNumber'] ) ) {
					parent::insert_payment( array(
						'pay_id'		 => $response['orderNumber'],
						'pay_summ'		 => $response['amount'] / 100,
						'user_id'		 => $params['userId'],
						'pay_type'		 => $params['payType'],
						'baggage_data'	 => $params['baggageData']
					) );
				}

				wp_redirect( get_permalink( $data->page_successfully ) );
				exit;
			}
		}

		rcl_mail_payment_error();
		wp_redirect( add_query_arg( $_REQUEST, get_permalink( $data->page_fail ) ) );
		exit;
	}

}
