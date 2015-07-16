<?php
/**
 * 2007-2014 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2015 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

class EbayAlert
{
	private $ebay_profile;
	private $ebay;
	private $errors		= array();
	private $warnings	= array();
	private $infos		= array();

	private $alerts;

	public function __construct(Ebay $obj){
		$this->ebay = $obj;
		$this->ebay_profile = $obj->ebay_profile;
	}

	public function getAlerts(){
		$this->checkNumberPhoto();
		$this->checkOrders();
		$this->checkUrlDomain();
		$this->checkCronTask();

		$this->build();

		return $this->alerts;
	}

	private function build(){
		$this->alerts = array_merge($this->errors, $this->warnings, $this->infos);
	}

	private function checkNumberPhoto(){
		if ($this->ebay_profile->getConfiguration('EBAY_PICTURE_PER_LISTING') > 0){
			$link = new EbayCountrySpec();
			$link->getPictureUrl();
			$this->warnings[] = array(
				'type' => 'warning', 
				'message' => $this->ebay->l('You will send more than one image. This can have financial consequences. Please verify @link@this link@/link@'),
				'link_warn' => $link->getPictureUrl()
				);
		}

		if ($this->ebay_profile->getConfiguration('EBAY_PICTURE_PER_LISTING') >= 12)
			$this->errors[] = array(
				'type' => 'error', 
				'message' => $this->ebay->l('You can\'t send more than 12 pictures by product. Please configure that in Advanced Parameters')
				);
	}

	private function checkOrders(){
		$this->checkOrdersCountry();
	}

	private function checkOrdersCountry(){
		if ($countries = EbayOrderErrors::getEbayOrdersCountry()){
			$list = array('country' => '', 'order' => '');

			foreach ($countries as $key => $orders) {
				
				$country = new Country(Country::getByIso($key), (int)Configuration::get('PS_LANG_DEFAULT'));
				
				if ($country->active)
					continue;

				Tools::isEmpty($list['country']) ? ($list['country'] .= $country->name) : ($list['country'] .= ', '.$country->name);

				foreach ($orders as $order)
					Tools::isEmpty($list['order']) ? ($list['order'] .= $order['id_order_seller']) : ($list['order'] .= ', '.$order['id_order_seller']);
			}
			
			$this->errors[] = array(
				'type' => 'error', 
				'message' => $this->ebay->l('You must enable the following countries : ').$list['country'].$this->ebay->l('. In order to import this eBay order(s) : ').$list['order'].'.',
					);
		}
	}

	public function sendDailyMail(){
		$this->getAlerts();

		$template_vars = array(
			'{errors}' 	=> $this->formatErrorForEmail(),
			'{warnings}' 	=> $this->formatWarningForEmail(),
			'{infos}' 	=> $this->formatInfoForEmail(),
		);

		if (empty($template_vars['{errors}']) && empty($template_vars['{errors}']) && empty($template_vars['{errors}']))
			return;

		Mail::Send(
			(int)Configuration::get('PS_LANG_DEFAULT'),
			'ebayAlert',
			Mail::l('Recap of your eBay module', (int)Configuration::get('PS_LANG_DEFAULT')),
			$template_vars,
			strval(Configuration::get('PS_SHOP_EMAIL')),
			null,
			strval(Configuration::get('PS_SHOP_EMAIL')),
			strval(Configuration::get('PS_SHOP_NAME')),
			null,
			null,
			dirname(__FILE__).'/../views/templates/mails/'
		);
	}

	public function formatErrorForEmail(){
		if (empty($this->errors))
			return '';

		$html = '<tr>
					<td style="border:1px solid #d6d4d4;background-color:#f8f8f8;padding:7px 0">
						<table style="width:100%">
							<tbody>
								<tr>
									<td width="10" style="padding:7px 0">&nbsp;</td>
									<td style="padding:7px 0">
										<font size="2" face="Open-sans, sans-serif" color="#555454">
											<p style="border-bottom:1px solid #d6d4d4;margin:3px 0 7px;text-transform:uppercase;font-weight:500;font-size:18px;padding-bottom:10px">';

		$html .= $this->ebay->l('Erreur(s)');

		$html .= 							'</p>';

		foreach ($this->errors as $key => $error) {
			$html .=	'<p style="color:#333;padding-bottom:10px;';

			if (array_key_exists($key+1, $this->errors))
				$html .= 'border-bottom:1px solid #d6d4d4;';

			$html .= '">
							<strong>'.$error['message'].'</strong>
						</p>';
		}

		$html .= '
										
									</font>
								</td>
								<td width="10" style="padding:7px 0">&nbsp;</td>
							</tr>
						</tbody>
					</table>
				</td>
			</tr>
			<tr>
				<td style="padding:0!important">&nbsp;</td>
			</tr>';

		return $html;
	}

	public function formatWarningForEmail(){
		if (empty($this->warnings))
			return '';

		$html = '<tr><td style="border:1px solid #d6d4d4;background-color:#f8f8f8;padding:7px 0">
					<table style="width:100%">
						<tbody>
							<tr>
								<td width="10" style="padding:7px 0">&nbsp;</td>
								<td style="padding:7px 0">
									<font size="2" face="Open-sans, sans-serif" color="#555454">
										<p style="border-bottom:1px solid #d6d4d4;margin:3px 0 7px;text-transform:uppercase;font-weight:500;font-size:18px;padding-bottom:10px">';

		$html .= $this->ebay->l('Warning(s)');

		$html .= 						'</p>';

		foreach ($this->warnings as $key => $warning) {
			$html .=	'<p style="color:#333;padding-bottom:10px;';

			if (array_key_exists($key+1, $this->warnings))
				$html .= 'border-bottom:1px solid #d6d4d4;';

			$html .= '">
							<strong>'.$warning['message'].'</strong>
						</p>';
		}

		$html .= '
										
									</font>
								</td>
								<td width="10" style="padding:7px 0">&nbsp;</td>
							</tr>
						</tbody>
					</table>
				</td>
			</tr>
			<tr>
				<td style="padding:0!important">&nbsp;</td>
			</tr>';

		return $html;
	}

	public function formatInfoForEmail(){
		if (empty($this->infos))
			return '';

		$html = '<tr><td style="border:1px solid #d6d4d4;background-color:#f8f8f8;padding:7px 0">
					<table style="width:100%">
						<tbody>
							<tr>
								<td width="10" style="padding:7px 0">&nbsp;</td>
								<td style="padding:7px 0">
									<font size="2" face="Open-sans, sans-serif" color="#555454">
										<p style="border-bottom:1px solid #d6d4d4;margin:3px 0 7px;text-transform:uppercase;font-weight:500;font-size:18px;padding-bottom:10px">';

		$html .= $this->ebay->l('Information(s)');

		$html .= 						'</p>';

		foreach ($this->infos as $key => $info) {
			$html .=	'<p style="color:#333;padding-bottom:10px;';

			if (array_key_exists($key+1, $this->infos))
				$html .= 'border-bottom:1px solid #d6d4d4;';

			$html .= '">
							<strong>'.$info['message'].'</strong>
						</p>';
		}

		$html .= '
										
									</font>
								</td>
								<td width="10" style="padding:7px 0">&nbsp;</td>
							</tr>
						</tbody>
					</table>
				</td>
			</tr>
			<tr>
				<td style="padding:0!important">&nbsp;</td>
			</tr>';

		return $html;
	}

	public function checkUrlDomain(){
		// check domain
		if (version_compare(_PS_VERSION_, '1.5', '>')) {
			$shop = $this->ebay_profile instanceof EbayProfile ? new Shop($this->ebay_profile->id_shop) : new Shop();
			$wrong_domain = ($_SERVER['HTTP_HOST'] != $shop->domain && $_SERVER['HTTP_HOST'] != $shop->domain_ssl && Tools::getValue('ajax') == false);

		} else
			$wrong_domain = ($_SERVER['HTTP_HOST'] != Configuration::get('PS_SHOP_DOMAIN') && $_SERVER['HTTP_HOST'] != Configuration::get('PS_SHOP_DOMAIN_SSL'));

		if ($wrong_domain) {
			$url_vars = array();
			if (version_compare(_PS_VERSION_, '1.5', '>'))
				$url_vars['controller'] = 'AdminMeta';
			else
				$url_vars['tab'] = 'AdminMeta';
			$warning_url = $this->_getUrl($url_vars);

			$this->warnings[] = array(
				'type' => 'warning', 
				'message' => $this->ebay->l('You are currently connected to the Prestashop Back Office using a different URL @link@ than set up@/link@, this module will not work properly. Please login in using URL_Back_Office'),
				'link_warn' => $warning_url
				);

		}
	}

	private function _getUrl($extra_vars = array())
	{
		$url_vars = array(
			'configure' => Tools::getValue('configure'),
			'token' => version_compare(_PS_VERSION_, '1.5', '>') ? Tools::getAdminTokenLite($extra_vars['controller']) : Tools::getAdminTokenLite($extra_vars['tab']),
			'tab_module' => Tools::getValue('tab_module'),
			'module_name' => Tools::getValue('module_name'),
		);

		return 'index.php?'.http_build_query(array_merge($url_vars, $extra_vars));
	}

	private function checkCronTask(){
		$cron_task = array();

		// PRODUCTS
		if ((int)Configuration::get('EBAY_SYNC_PRODUCTS_BY_CRON') == 1)
		{
			if ($last_sync_datetime = Configuration::get('DATE_LAST_SYNC_PRODUCTS'))
			{
				$warning_date = strtotime(date('Y-m-d').' - 2 days');

				$date = date('Y-m-d', strtotime($last_sync_datetime));
				$time =date('H:i:s', strtotime($last_sync_datetime));
				$msg = $this->ebay->l('Last product synchronization has been done the ').$date.$this->ebay->l(' at ').$time.$this->ebay->l(' and it tried to synchronize ').Configuration::get('NB_PRODUCTS_LAST');

				if (strtotime($last_sync_datetime) < $warning_date)
					$this->warnings[] = array(
						'type' => 'warning',
						'message' => $msg,
						);
				else
					$this->infos[] = array(
						'type' => 'info',
						'message' => $msg,
						);
			}
			else
			{
				$this->errors[] = array(
					'type' => 'error',
					'message' => $this->ebay->l('The product cron job has never been run.'),
					);
			}

			
		}

		// ORDERS
		if ((int)Configuration::get('EBAY_SYNC_ORDERS_BY_CRON') == 1)
		{
			if ($this->ebay_profile->getConfiguration('EBAY_ORDER_LAST_UPDATE') != null)
			{
				$datetime = new DateTime($this->ebay_profile->getConfiguration('EBAY_ORDER_LAST_UPDATE'));

				$date = date('Y-m-d', strtotime($datetime->format('Y-m-d H:i:s'))); 
				$time = date('H:i:s', strtotime($datetime->format('Y-m-d H:i:s')));

				$datetime2 = new DateTime();
				
				$interval = $datetime->diff($datetime2);

				if ($interval->format('%a') >= 1)
					$this->errors[] = array(
						'type' => 'error', 
						'message' => $this->ebay->l('Last order synchronization has been done the ').$date.$this->ebay->l(' at ').$time,
						);
				else
					$this->infos[] = array(
						'type' => 'info', 
						'message' => $this->ebay->l('Last order synchronization has been done the ').$date.$this->ebay->l(' at ').$time,
						);

			}
			else
				$this->errors[] = array(
					'type' => 'error', 
					'message' => $this->ebay->l('Order cron job has never been run.'),
					);
		}   

	}

}