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

   private $plugin;


   public function __construct()
   {

      $this->EE =& get_instance();
      
      if (!isset($this->EE->cartthrob))
      {
         $this->EE->load->add_package_path(PATH_THIRD.'cartthrob/');
         $this->EE->load->library('cartthrob_loader');
      }

      if (!$this->plugin instanceof Cartthrob_shipping_cg_custom)
      {
         // Get currently selected shipping plugin
         $current_plugin = $this->EE->cartthrob->store->config('shipping_plugin');
         if ($current_plugin != 'Cartthrob_shipping_cg_custom') {
            // show_error();
            trigger_error(lang('wrong_plugin'), E_USER_WARNING);
            return;
         }
         // Save it
         $this->plugin = $this->EE->cartthrob->store->plugin($current_plugin);
      }

   }

   
   public function replace_tag($data, $params = array(), $tagdata = FALSE)
   {

      if (!is_array($data))
         return null;

      if (!$this->plugin instanceof Cartthrob_shipping_cg_custom)
         return null;

      $number = $this->plugin->get_shipping_for_fieldtype($data, $params);

      $this->EE->load->library('number');

      /**
       * CartThrob's number formatting only looks for params in TMPL->tagparams, 
       * which apparently doesn't reflect the actual params passed to a single var
       * fieldtype, so we have to do this manually to avoid overwriting legit tagdata
       */
      if (isset($params['prefix']))          $this->EE->number->set_prefix($params['prefix']);
      if (isset($params['prefix_position'])) $this->EE->number->set_prefix_position($params['prefix_position']);
      if (isset($params['decimals']))        $this->EE->number->set_decimals($params['decimals']);
      if (isset($params['dec_point']))       $this->EE->number->set_dec_point($params['dec_point']);
      if (isset($params['thousands_sep']))   $this->EE->number->set_thousands_sep($params['thousands_sep']);
      
      return  $this->EE->number->format($number);

   }

   public function replace_number($data, $params = array(), $tagdata = FALSE)
   {

      if (!is_array($data))
         return null;

      if (!$this->plugin instanceof Cartthrob_shipping_cg_custom)
         return null;

      $number = $this->plugin->get_shipping_for_fieldtype($data, $params);

      return (float) sanitize_number($number);

   }

   
}

/* End of file ft.agco_custom_shipping.php */
/* Location: ./system/expressionengine/third_party/agco_custom_shipping/ft.agco_custom_shipping.php */