<?php if ( ! defined('CARTTHROB_PATH')) Cartthrob_core::core_error('No direct script access allowed');

class Cartthrob_shipping_cg_custom extends Cartthrob_shipping
{
	public $title = 'Cullgroup Custom Shipping Plugin';
	public $classname = __CLASS__;
	public $settings = array(
			array(
				'name' => 'in_channel_field',
				'short_name' => 'field_name',
				'type' => 'select',
				'note' => 'Select the product channel field that uses the custom shipping fieldtype',
				'attributes' => array(
					'class' => 'all_fields',
				),
			),
			array(
				'name' => 'State selector',
				'short_name' => 'states_multi',
				'note' => "
					Use this to populate the fields below.
					<br/><strong>Selected</strong> states will be used for <strong>\"Near\"</strong>
					<script type='text/javascript'>
						$(function(){
							$('#states_multi').on('change', function(){
								$('#near_states').val($(this).val().join(','));
								$('#far_states').val($(this).find('option').not('[selected]').map(function(){return this.value}).get().join(','));
							}).val($('#near_states').val().split(','));
							$('#near_states').select();
						});
					</script>
				",
				'type' => 'select',
				'attributes' => array(
					'id' => 'states_multi',
					'class' => 'states',
					'multiple' => 'multiple',
					'style' => 'width:150px;height:194px;',
				),
			),
			array(
				'name' => 'US States considered "Near":',
				'short_name' => 'near_states',
				'type' => 'text',
				'attributes' => array(
					'id' => 'near_states',
				),
			),
			array(
				'name' => 'US States considered "Far":',
				'short_name' => 'far_states',
				'type' => 'text',
				'attributes' => array(
					'id' => 'far_states',
				),
			),
			array(
				'name' => 'primary_location_field',
				'short_name' => 'location_field',
				'type' => 'select',
				'default' => 'shipping',
				'options' => array(
					'billing' => 'billing_address',
					'shipping' => 'shipping_address'
	 			)
			),
			array(
				'name'	=> 'default_cost_per_item',
				'short_name' => 'default_rate',
				'note' => 'Will be used if per-product settings fail',
				'type' => 'text',
			),
			array(
				'name' => 'set_shipping_cost_by',
				'short_name' => 'mode',
				'default' => 'product',
				'type' => 'radio',
				'options' => array(
					'product' => 'Flat cost for each product (ignore quantity)',
					'qty' => 'Cost &times; quantity'
				)
			),
		);

	protected $shipping = 0;
	protected $country;
	protected $state;
	protected $near_states;
	protected $far_states;
	protected $wrong_fieldtype = false;

	public function initialize()
	{

		if ($this->core->cart->count() <= 0 || $this->core->cart->shippable_subtotal() <= 0)
		{
			return 0;
		}

		$this->EE =& get_instance(); 
		$this->EE->load->model('cartthrob_field_model');
		$this->EE->load->helper('data_formatting_helper');

		// Check for our fieldtype
		$field_id = str_replace('field_id_','',$this->plugin_settings('field_name'));
		$field_type = $this->EE->cartthrob_field_model->get_field_type($field_id);
		$this->wrong_fieldtype = ($field_type != 'cg_custom_shipping');

		// Grab current customer data
		$customer_info = array_merge($this->core->cart->customer_info(), array_filter($this->core->customer_info_defaults,'trim'));
		if ($this->plugin_settings('location_field') == 'billing')
		{
			$primary_loc = "";
			$backup_loc	 = "shipping_"; 
		}
		else
		{
			$primary_loc = "shipping_";
			$backup_loc	 = "";	
		}
		$this->country 	 = (!empty($customer_info[$primary_loc.'country_code']) ? $customer_info[$primary_loc.'country_code'] : $customer_info[$backup_loc.'country_code']);
		$this->state		 = (!empty($customer_info[$primary_loc.'state'])		  ? $customer_info[$primary_loc.'state']        : $customer_info[$backup_loc.'state']);
 		$this->near_states = preg_split('/\s*,\s*/', trim($this->plugin_settings('near_states')));
 		$this->far_states  = preg_split('/\s*,\s*/', trim($this->plugin_settings('far_states')));

 		$this->shipping = $this->calculate_shipping( $this->core->cart->shippable_items() );
		
		// foreach ($this->plugin_settings('thresholds', array()) as $threshold_setting)
		// {
		// 	$location_array	= preg_split('/\s*,\s*/', trim($threshold_setting['location']));
			
		// 	if (in_array($location, $location_array))
		// 	{
		// 		if ($total_items > $threshold_setting['threshold'])
		// 		{
		// 			$last_rate = $threshold_setting['rate'];
		// 			continue;
		// 		}
		// 		else
		// 		{
		// 			$shipping = ($this->plugin_settings('mode') == 'rate') ? $price * $threshold_setting['rate'] : $threshold_setting['rate'];

		// 			$priced = TRUE;

		// 			break;
		// 		}
		// 		$last_rate = $threshold_setting['rate'];
		// 	}
		// 	elseif (in_array('GLOBAL',$location_array)) 
		// 	{
		// 		if ($total_items > $threshold_setting['threshold'])
		// 		{
		// 			$last_rate = $threshold_setting['rate'];
		// 			continue;
		// 		}
		// 		else
		// 		{
		// 			$shipping = ($this->plugin_settings('mode') == 'rate') ? $price * $threshold_setting['rate'] : $threshold_setting['rate'];

		// 			$priced = TRUE;

		// 			break;
		// 		}
		// 		$last_rate = $threshold_setting['rate'];
		// 	}
		// }

		// if ( ! $priced)
		// {
		// 	$shipping = ($this->plugin_settings('mode') == 'rate') ? $price * $last_rate : $last_rate;
		// }
		
		// foreach ($this->plugin_settings('free_by_location', array()) as $threshold_setting)
		// {
		// 	$free_location_array	= preg_split('/\s*,\s*/', trim($threshold_setting['location']));
		// 	if (in_array($location, $free_location_array) && $price > $threshold_setting['free_over_x'])
		// 	{
		// 		return 0; 
		// 	}
		// }

		// $this->cost = ($shipping + $per_item_shipping);

	}

	private function calculate_shipping($items) 
	{
 		$shipping          = 0;
		$total_items       = 0;

		// Loop through all items in the cart
		foreach ($items as $row_id => $item)
		{

			// whoa
			if (!$item->product_id())
				continue;

			$product = $this->core->store->product($item->product_id());
			$product_field = $product->meta($this->plugin_settings('field_name'));

			// Default to field contents if not our custom fieldtype
			if ($this->wrong_fieldtype) {
				$shipping += $this->core->round($product_field);
				continue;
			}

			$rules = _unserialize($product_field, TRUE);
			$qty = $item->quantity();
			$cost = 0;


			// Loop our product's custom field rules
			foreach ($rules as $i => $rule) {

				// var_dump($rule);

				if ( ($qty >= $rule['from_quantity'] && $qty <= $rule['up_to_quantity']) == false)
					continue;

				// If customer isn't in USA, try fallbacks.
				if ($this->country != 'USA') {
					$cost = (!empty($rule['global_cost'])) ? $rule['global_cost'] : ((!empty($rule['far_cost'])) ? $rule['far_cost'] : $rule['near_cost']);
					break;
				}

				if ( in_array($this->state, $this->near_states) ) 
				{
					$cost = (!empty($rule['near_cost'])) ? $rule['near_cost'] : ((!empty($rule['far_cost'])) ? $rule['far_cost'] : $rule['global_cost']);
				} 
				else if ( in_array($this->state, $this->far_states) ) 
				{
					$cost = (!empty($rule['far_cost'])) ? $rule['far_cost'] : $rule['global_cost'];
				}

				// var_dump('#'.$item->product_id().' (qty '.$qty.') matched rule '.$i.': $'.$cost);

			}

			if ($cost === 0) 
				$cost = $this->plugin_settings('default_rate'); 
			
			$shipping += ($this->plugin_settings('mode') == 'qty') ? $cost * $qty : $cost;

			// var_dump('Cost: '.$cost.', Total: '.$shipping);

 		}

 		// var_dump('Shipping: '.$shipping);
 		// var_dump('-----------------------------------');
 		return $shipping;

	}

	public function get_shipping_for_item($item)
	{
		return $this->calculate_shipping(array($item));
	}

	public function get_shipping()
	{
		return $this->shipping; 
	}

	public function get_product($entry_id)
	{
		$this->EE->load->model('product_model');
		return $this->EE->product_model->get_product($entry_id);
	}

	private function array_key_exists_r($needle, $haystack) 
	{
		$result = array_key_exists($needle, $haystack);
		if ($result) return $result;
			foreach ($haystack as $v) {
				if (is_array($v)) {
					$result = $this->array_key_exists_r($needle, $v);
				}
			if ($result) return $result;
		}
		return $result;
	}

}//END CLASS
