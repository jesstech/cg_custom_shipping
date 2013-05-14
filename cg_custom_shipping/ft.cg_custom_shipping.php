<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

include_once PATH_THIRD.'cartthrob/config.php';
require_once PATH_THIRD.'cartthrob/fieldtypes/ft.cartthrob_matrix.php';

$this->EE->lang->loadfile('cg_custom_shipping');

/**
 * @property EE_EE $EE
 * @property Cartthrob_cart $cart
 * @property Cartthrob_store $store
 */
class Cg_custom_shipping_ft extends Cartthrob_matrix_ft
{
	public $info = array(
		'name' => 'Cullgroup Custom Shipping Cost',
		'version' => '1.0',
	);
	
	public $default_row = array(
		'from_quantity' => '',
		'up_to_quantity' => '',
		'near_cost' => '',
		'far_cost' => '',
		'global_cost' => '',
	);

	public function pre_process($data)
	{
		$data = parent::pre_process($data);

		$this->EE->load->add_package_path(PATH_THIRD.'cartthrob/');
		
		$this->EE->load->library('cartthrob_loader');
		
		$this->EE->load->library('number');
		
		foreach ($data as &$row)
		{
			if (isset($row['price']) && $row['price'] !== '')
			{
				$row['price_plus_tax'] = $row['price'];
 				
				if ($plugin = $this->EE->cartthrob->store->plugin($this->EE->cartthrob->store->config('tax_plugin')))
				{
					$row['price_plus_tax'] = $plugin->get_tax($row['price']) + $row['price'];
 				}
				
				$row['price_numeric'] = $row['price'];
				$row['price_plus_tax_numeric'] = $row['price:plus_tax_numeric'] =  $row['price_numeric:plus_tax'] = $row['price_plus_tax'];
				
				$row['price'] = $this->EE->number->format($row['price']);
				$row['price_plus_tax'] = $row['price:plus_tax'] = $this->EE->number->format($row['price_plus_tax']);
			}
		}
		
		// var_dump( $data );

		return $data;
	}
	
	public function replace_price($data, $params= array(), $tagdata = FALSE)
	{
		$this->EE->load->add_package_path(PATH_THIRD.'cartthrob/');
		$this->EE->load->library('number');
		
		return $this->EE->number->format($this->cartthrob_price($data));
	}
	
	public function cartthrob_price($data, $item = NULL)
	{

		// var_dump($item);

		if ( ! is_array($data))
		{
			$serialized = $data;
			
			if ( ! isset($this->EE->session->cache['cartthrob']['price_quantity_thresholds']['cartthrob_price'][$serialized]))
			{
				$this->EE->session->cache['cartthrob']['price_quantity_thresholds']['cartthrob_price'][$serialized] = _unserialize($data, TRUE);
			}
			
			$data = $this->EE->session->cache['cartthrob']['price_quantity_thresholds']['cartthrob_price'][$serialized];
		}
		reset($data); 

		// var_dump($data);
		
		while(($row = current($data)) !== FALSE)
		{
			// if quantity is within the thresholds
			// OR if we get to the end of the array
			// the last row will set the price, no matter what
			if (next($data) === FALSE || ($item instanceof Cartthrob_item && $item->quantity() >= $row['from_quantity'] && $item->quantity() <= $row['up_to_quantity']))
			{
				return $row['price'];
			}
		}

		return 0;
	}
}

/* End of file ft.agco_custom_shipping.php */
/* Location: ./system/expressionengine/third_party/agco_custom_shipping/ft.agco_custom_shipping.php */