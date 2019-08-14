<?php
require_once 'Soap/XMLSecurityKey.php';

require_once 'Exceptions/ServerException.php';
require_once 'Exceptions/ClientException.php';
require_once 'Exceptions/RequirementsException.php';
require_once 'Client.php';
require_once 'Receipt.php';

/**
 * Receipt for Ministry of Finance
 */
class EET_Dispatcher
{

    const SERVICE_PRODUCTION = 1;
    const SERVICE_PLAYGROUND = 0;

    /**
     * Certificate key
     * @var string
     */
    private $key;

    /**
     * Certificate
     * @var string
     */
    private $cert;

    /**
     * WSDL path or URL
     * @var string
     */
    private $service;

    /**
     * @var boolean
     */
    public $trace;

    /**
     *
     * @var EET_Client
     */
    private $soapClient;

    /**
     * @var array [warning code => message]
     */
    private $warnings;

    /**
     * @var \stdClass
     */
    private $wholeResponse;

    /**
     * @param string $key
     * @param string $cert
     * @param int service
     */
    public function __construct($key, $cert, $service = self::SERVICE_PLAYGROUND)
    {
        $this->service  = $service;
        $this->key      = $key;
        $this->cert     = $cert;
        $this->warnings = [];
    }

    /**
     *
     * @param EET_Receipt $receipt
     * @return boolean|string
     */
    public function check(EET_Receipt $receipt)
    {
        try {
            return $this->send($receipt, true);
        } catch (ServerException $e) {
            return false;
        }
    }

    /**
     *
     * @param boolean $tillLastRequest optional If not set/false connection time till now is returned.
     * @return float
     */
    public function getConnectionTime($tillLastRequest = false)
    {
        !$this->trace && $this->throwTraceNotEnabled();
        return $this->getSoapClient()->__getConnectionTime($tillLastRequest);
    }

    /**
     *
     * @return int
     */
    public function getLastResponseSize()
    {
        !$this->trace && $this->throwTraceNotEnabled();
        return mb_strlen($this->getSoapClient()->__getLastResponse(), '8bit');
    }

    /**
     *
     * @return int
     */
    public function getLastRequestSize()
    {
        !$this->trace && $this->throwTraceNotEnabled();
        return mb_strlen($this->getSoapClient()->__getLastRequest(), '8bit');
    }

    /**
     *
     * @return float time in ms
     */
    public function getLastResponseTime()
    {
        !$this->trace && $this->throwTraceNotEnabled();
        return $this->getSoapClient()->__getLastResponseTime();
    }

    /**
     *
     * @throws ClientException
     */
    private function throwTraceNotEnabled()
    {
        throw new ClientException('Trace is not enabled! Set trace property to true.');
    }

    /**
     *
     * @param EET_Receipt $receipt
     * @return array
     */
    public function getCheckCodes(EET_Receipt $receipt)
    {
        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $objKey->loadKey($this->key, false);

        $arr  = [
          $receipt->vat_id,
          $receipt->shop_id,
          $receipt->checkout_id,
          $receipt->id,
          date('c', $receipt->date),
          $this->formatPrice($receipt->total)
        ];

        $sign = $objKey->signData(join('|', $arr));

        return [
          'pkp' => [
            '_' => $sign,
            'digest' => 'SHA256',
            'cipher' => 'RSA2048',
            'encoding' => 'base64'
          ],
          'bkp' => [
            '_' => $this->getBKP(sha1($sign)),
            'digest' => 'SHA1',
            'encoding' => 'base16'
          ]
        ];
    }

    /**
     *
     * @param EET_Receipt $receipt
     * @param boolean $check
     * @return boolean|string
     */
    public function send(EET_Receipt $receipt, $check = false)
    {
        $this->initSoapClient();

        $response            = $this->processData($receipt, $check);
        $this->wholeResponse = $response;

        isset($response->Chyba) && $this->processError($response->Chyba);
        isset($response->Varovani) && $this->warnings = $this->processWarnings($response->Varovani);
        return $check ? true : ['fik' => $response->Potvrzeni->fik, 'bkp' => $response->Hlavicka->bkp];
    }

    /**
     * Returns array of warnings if the last response contains any, empty array otherwise.
     *
     * @return array [warning code => message]
     */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * Get (or if not exists: initialize and get) SOAP client.
     *
     * @return SoapClient
     */
    public function getSoapClient()
    {
        !isset($this->soapClient) && $this->initSoapClient();
        return $this->soapClient;
    }

    /**
     * Require to initialize a new SOAP client for a new request.
     *
     * @return void
     */
    private function initSoapClient()
    {
        if ($this->soapClient === null) {
            if ($this->service == self::SERVICE_PRODUCTION) {
                $filename = 'ProductionService.wsdl';
            } else {
                $filename = 'PlaygroundService.wsdl';
            }

            $this->soapClient = new EET_Client(dirname(__FILE__) . '/Schema/' . $filename, $this->key, $this->cert, $this->trace);
        }
    }

    /**
     *
     * @param EET_Receipt $receipt
     * @param boolean $check
     * @return array
     */
    private function prepareData(EET_Receipt $receipt, $check = false)
    {
        $head = [
          'uuid_zpravy' => $receipt->generateUUID(),
          'dat_odesl' => time(),
          'prvni_zaslani' => (boolean)$receipt->first_attempt,
          'overeni' => $check
        ];

        $baseTax   = $receipt->getTax(21);
        $lower1Tax = $receipt->getTax(15);
        $lower2Tax = $receipt->getTax(10);
        $zeroTax   = $receipt->getTax(0);

        $baseTaxUsed   = $receipt->getTax(21, true);
        $lower1TaxUsed = $receipt->getTax(15, true);
        $lower2TaxUsed = $receipt->getTax(10, true);

        $mode = ($receipt->mode == EET_Receipt::MODE_SIMPLE) ? 1 : 0;

        $body = [
          'dic_popl' => $receipt->vat_id,
          'dic_poverujiciho' => $receipt->vat_id_subject,
          'id_provoz' => $receipt->shop_id,
          'id_pokl' => $receipt->checkout_id,
          'porad_cis' => $receipt->id,
          'dat_trzby' => date('c', $receipt->date),
          'celk_trzba' => $this->formatPrice($receipt->total),
          'zakl_nepodl_dph' => $this->formatPrice($zeroTax['total']),
          'zakl_dan1' => $this->formatPrice($baseTax['base']),
          'dan1' => $this->formatPrice($baseTax['tax']),
          'zakl_dan2' => $this->formatPrice($lower1Tax['base']),
          'dan2' => $this->formatPrice($lower1Tax['tax']),
          'zakl_dan3' => $this->formatPrice($lower2Tax['base']),
          'dan3' => $this->formatPrice($lower2Tax['tax']),
          'cest_sluz' => $this->formatPrice($receipt->service_total),
          'pouzit_zboz1' => $this->formatPrice($baseTaxUsed['total']),
          'pouzit_zboz2' => $this->formatPrice($lower1TaxUsed['total']),
          'pouzit_zboz3' => $this->formatPrice($lower2TaxUsed['total']),
          'urceno_cerp_zuct' => $this->formatPrice($receipt->total_due_to),
          'cerp_zuct' => $this->formatPrice($receipt->total_due),
          'rezim' => $mode
        ];

        return [
          'Hlavicka' => $head,
          'Data' => $body,
          'KontrolniKody' => $this->getCheckCodes($receipt)
        ];
    }

    /**
     *
     * @param EET_Receipt $receipt
     * @param boolean $check
     * @return object
     */
    private function processData(EET_Receipt $receipt, $check = false)
    {
        $data = $this->prepareData($receipt, $check);

        return $this->getSoapClient()->OdeslaniTrzby($data);
    }

    /**
     * @param $error
     * @throws ServerException
     */
    private function processError($error)
    {
        if ($error->kod) {
            $msgs = [
              -1 => 'Docasna technicka chyba zpracovani – odeslete prosim datovou zpravu pozdeji',
              2 => 'Kodovani XML neni platne',
              3 => 'XML zprava nevyhovela kontrole XML schematu',
              4 => 'Neplatny podpis SOAP zpravy',
              5 => 'Neplatny kontrolni bezpecnostni kod poplatnika (BKP)',
              6 => 'DIC poplatnika ma chybnou strukturu',
              7 => 'Datova zprava je prilis velka',
              8 => 'Datova zprava nebyla zpracovana kvuli technicke chybe nebo chybe dat',
            ];
            $msg  = isset($msgs[$error->kod]) ? $msgs[$error->kod] : '';
            throw new ServerException($msg, $error->kod);
        }
    }

    /**
     * @param \stdClass|array $warnings
     * @return array [warning code => message]
     */
    private function processWarnings($warnings)
    {
        $result = array();
        if (\count($warnings) === 1) {
            $result[\intval($warnings->kod_varov)] = $this->getWarningMsg($warnings->kod_varov);
        } else {
            foreach ($warnings as $warning) {
                $result[\intval($warning->kod_varov)] = $this->getWarningMsg($warning->kod_varov);
            }
        }
        return $result;
    }

    /**
     * @param int $id warning code
     * @return string warning message
     */
    private function getWarningMsg($id)
    {
        $result = 'Nezname varovani, zkontrolujte technickou specifikaci';
        $msgs   = [
          1 => 'DIC poplatnika v datove zprave se neshoduje s DIC v certifikatu',
          2 => 'Chybny format DIC poverujiciho poplatnika',
          3 => 'Chybna hodnota PKP',
          4 => 'Datum a cas prijeti trzby je novejsi nez datum a cas prijeti zpravy',
          5 => 'Datum a cas prijeti trzby je vyrazne v minulosti',
        ];
        if (\array_key_exists($id, $msgs)) {
            $result = $msgs[$id];
        }
        return $result;
    }

    /**
     * @return \stdClass
     */
    public function getWholeResponse()
    {
        return $this->wholeResponse;
    }


    private static function getBKP($code)
    {
        $r = '';
        for ($i = 0; $i < 40; $i++) {
            if ($i % 8 == 0 && $i != 0) {
                $r .= '-';
            }
            $r .= $code[$i];
        }
        return $r;
    }

    private static function formatPrice($value)
    {
        return number_format(round($value, 2), 2, '.', '');
    }
}
