<?php

declare(strict_types=1);

namespace J7\PowerPayment\Domains\Payment\Ecpay\Core;

use J7\PowerPayment\Domains\Payment\Ecpay\Abstracts\PaymentService;
use J7\PowerPayment\Domains\Payment\Ecpay\Model\RequestParams;
use J7\PowerPayment\Domains\Payment\AbstractPaymentGateway;
use J7\PowerPayment\Domains\Payment\Ecpay\Utils\Base as EcpayUtils;

/** Service */
final class Service extends PaymentService {
	use \J7\WpUtils\Traits\SingletonTrait;

	/** @var string 服務 ID */
	public string $id = 'ecpay-aio';

	/** @var 'prod' | 'test' 模式 */
	public string $mode = 'test';

	/** @var string 綠界特店編號 */
	public string $merchant_id;

	/** @var string HashKey */
	public string $hash_key;

	/** @var string HashIV */
	public string $hash_iv;

	/** @var string CheckMacValue */
	public string $check_mac_value;

	/** @var string 綠界 AioCheckOut 端點 */
	public string $aio_checkout_endpoint;

	/** @var string 綠界 QueryTradeInfo 端點 */
	public string $query_trade_info_endpoint;

	/** @var string 綠界 SPCreateTrade 端點 */
	public string $sptoken_endpoint;


	/** Constructor */
	public function __construct() {
		// TODO 從 db 取得設定 可以抽象到 parrent 執行?
		$this->mode = 'test';
		$this->set_properties();
		parent::__construct();
	}

	/**
	 * 添加付款方式
	 *
	 * @param array<string> $methods 付款方式
	 *
	 * @return array<string>
	 */
	public function add_method( array $methods ): array {
		$methods[] = Atm::class;
		$methods[] = WebAtm::class;
		$methods[] = Credit::class;
		$methods[] = CreditInstallment::class;
		$methods[] = Barcode::class;
		$methods[] = CVS::class;
		return $methods;
	}


	/**
	 * 取得參數
	 *
	 * @param \WC_Order              $order 訂單
	 * @param AbstractPaymentGateway $gateway 付款方式
	 * @return array<string, mixed> 綠界參數
	 * @throws \Exception 如果參數不符合規定
	 *  */
	public function get_params( \WC_Order $order, AbstractPaymentGateway $gateway ): array {
		$params_dto = RequestParams::instance( $order, $gateway );
		return $params_dto->to_array();
	}

	/**
	 * 生成 CheckMacValue
	 *
	 * @see https://developers.ecpay.com.tw/?p=2902
	 *
	 * @param array<string, string|int> $args 參數
	 * @param string                    $hash_algo 'sha256' | 'md5' 雜湊演算法
	 * @return string CheckMacValue
	 * @throws \Exception 如果雜湊演算法不符合規定
	 */
	public function get_check_value( array $args, string $hash_algo ): string {

		if ( ! in_array( $hash_algo, [ 'sha256', 'md5' ], true ) ) {
			throw new \Exception( __( 'Invalid hash algorithm', 'power_payment' ) );
		}

		unset( $args['CheckMacValue'] ); // 確保不會用 CheckMacValue 生成
		ksort( $args, SORT_STRING | SORT_FLAG_CASE );   // 依照 key 字母排序

		$args_string   = [];
		$args_string[] = "HashKey={$this->hash_key}";// 開頭加上 HashKey
		foreach ( $args as $key => $value ) {
			$args_string[] = "{$key}={$value}";
		}
		$args_string[] = "HashIV={$this->hash_iv}";// 結尾加上 HashIV

		$args_string = implode( '&', $args_string ); // 用 & 連接
		$args_string = EcpayUtils::urlencode( $args_string ); // 綠界要求 urlencode 的規則
		$args_string = strtolower( $args_string ); // 轉小寫
		$check_value = hash( $hash_algo, $args_string ); // 生成 CheckMacValue
		$check_value = strtoupper( $check_value ); // 轉大寫

		return $check_value;
	}




	/**TODO 看有沒要補充的
	 * 設定屬性
	 */
	private function set_properties(): void {
		switch ($this->mode) {
			case 'prod':
				$this->merchant_id               = '3002599';
				$this->hash_key                  = 'spPjZn66i0OhqJsQ';
				$this->hash_iv                   = 'hT5OJckN45isQTTs';
				$this->aio_checkout_endpoint     = 'https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5';
				$this->query_trade_info_endpoint = 'https://payment.ecpay.com.tw/Cashier/QueryTradeInfo/V5';
				$this->sptoken_endpoint          = 'https://payment.ecpay.com.tw/SP/CreateTrade';
				break;
			default: // test
				$this->merchant_id               = '3002599';
				$this->hash_key                  = 'spPjZn66i0OhqJsQ';
				$this->hash_iv                   = 'hT5OJckN45isQTTs';
				$this->aio_checkout_endpoint     = 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5';
				$this->query_trade_info_endpoint = 'https://payment-stage.ecpay.com.tw/Cashier/QueryTradeInfo/V5';
				$this->sptoken_endpoint          = 'https://payment-stage.ecpay.com.tw/SP/CreateTrade';
				break;
		}
	}
}
