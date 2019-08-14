<?php
 /**
 * Receipt for Ministry of Finance
  */
class EET_Receipt {

    const TAX_BASE = 1;
    const TAX_ZERO = 0;
    const TAX_LOWER1 = 2;
    const TAX_LOWER2 = 3;
    
    const MODE_NORMAL = 0;
    const MODE_SIMPLE = 1;
    
    /**
     * Head part: first sending
     * @var boolean */
    public $first_attempt = true;

    /** @var string */
    public $vat_id;

    /** @var string */
    public $vat_id_subject;

    /** @var string */
    public $shop_id;

    /** @var string */
    public $checkout_id;

    /** @var string */
    public $id;

    /** @var int */
    public $date;

    /** @var float */
    public $total = 0;
    
    /** @var float */
    public $service_total = 0;
    
    /** @var float */
    public $total_due_to = 0;

    /** @var float */
    public $total_due = 0;

    /** @var int */
    public $mode = 0;
    
    /** @var array */
    protected $taxes = [];
        
    public function setTax($type, $base, $value, $usedProducts = false)
    {
        $used = !!$usedProducts;
        $this->taxes[$type][(integer)$used] = [$base, $value];
        return null;
    }
    
    public function getTax($type, $usedProducts = false)
    {
        $used = !!$usedProducts;
        if(isset($this->taxes[$type]) && isset($this->taxes[$type][(integer)$used])){
            return $this->taxes[$type][(integer)$used];
        }
        return [0,0];
    }
    
    public static function generateUUID()
	{
		return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

			// 32 bits for "time_low"
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),

			// 16 bits for "time_mid"
			mt_rand(0, 0xffff),

			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 4
			mt_rand(0, 0x0fff) | 0x4000,

			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			mt_rand(0, 0x3fff) | 0x8000,

			// 48 bits for "node"
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}

}
