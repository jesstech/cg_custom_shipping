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
            'name'   => 'default_cost_per_item',
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

   public $shipping = 0;
   public $country;
   public $state;
   public $near_states;
   public $far_states;
   public $wrong_fieldtype = false;

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
         $backup_loc  = "shipping_"; 
      }
      else
      {
         $primary_loc = "shipping_";
         $backup_loc  = "";   
      }
      $this->country     = (!empty($customer_info[$primary_loc.'country_code']) ? $customer_info[$primary_loc.'country_code'] : $customer_info[$backup_loc.'country_code']);
      $this->state       = (!empty($customer_info[$primary_loc.'state'])        ? $customer_info[$primary_loc.'state']        : $customer_info[$backup_loc.'state']);
      $this->near_states = preg_split('/\s*,\s*/', trim($this->plugin_settings('near_states')));
      $this->far_states  = preg_split('/\s*,\s*/', trim($this->plugin_settings('far_states')));

      $this->shipping = $this->calculate_shipping_for_items( $this->core->cart->shippable_items() );

   }

   private function calculate_shipping_for_items($items) 
   {

      $shipping    = 0;
      $total_items = 0;

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
         $qty   = $item->quantity();
         $cost = $this->process_rules($rules, array(
            'quantity' => $qty
         ));

         $shipping += ($this->plugin_settings('mode') == 'qty') ? $cost * $qty : $cost;

      }

      return $shipping;

   }

   private function process_rules($rules, $params) {

      $cost = '';
      $qty = (isset($params['quantity'])) ? (int) $params['quantity'] : 1;

      // Loop our product's custom field rules
      foreach ($rules as $i => $rule) {

         // Make sure keys exist and trim them
         $rule = array(
            'from_quantity'  => array_key_exists('from_quantity',$rule) ? trim($rule['from_quantity']) : '',
            'up_to_quantity' => array_key_exists('up_to_quantity',$rule) ? trim($rule['up_to_quantity']) : '',
            'far_cost'       => array_key_exists('far_cost',$rule) ? trim($rule['far_cost']) : '',
            'near_cost'      => array_key_exists('near_cost',$rule) ? trim($rule['near_cost']) : '',
            'global_cost'    => array_key_exists('global_cost',$rule) ? trim($rule['global_cost']) : '',
         );

         // Set defaults for quantity ranges
         $rule['from_quantity']  = ($rule['from_quantity'] !== '') ? $rule['from_quantity'] : 0;
         $rule['up_to_quantity'] = ($rule['up_to_quantity'] !== '') ? $rule['up_to_quantity'] : 999999;

         // Create an ordered array that we can remove blank items from to get the first "defined" cost
         $cost_priority = array(
            $rule['far_cost'],
            $rule['near_cost'],
            $rule['global_cost'],
         );

         if ( ($qty >= $rule['from_quantity'] && $qty <= $rule['up_to_quantity']) == false)
            continue;

         // If customer isn't in USA, try fallbacks.
         if ($this->country != 'USA') {
            $cost = ($rule['global_cost'] !== '') ? $rule['global_cost'] : reset(array_filter($cost_priority,'strlen'));
            break;
         }

         if ( in_array($this->state, $this->near_states) ) 
         {
            $cost = ($rule['near_cost'] !== '') ? $rule['near_cost'] : reset(array_filter($cost_priority,'strlen'));
         } 
         else if ( in_array($this->state, $this->far_states) ) 
         {
            $cost = ($rule['far_cost'] !== '') ? $rule['far_cost'] : $rule['global_cost'];
         }

      }

      if ($cost === '') 
         $cost = $this->plugin_settings('default_rate'); 

      return $cost;

   }

   public function get_shipping_for_fieldtype($rules, $params)
   {
      return $this->process_rules($rules, $params);
   }

   public function get_shipping_for_item($item)
   {
      return $this->calculate_shipping_for_items(array($item));
   }

   public function get_shipping()
   {
      return $this->shipping; 
   }

   private function get_product($entry_id)
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
