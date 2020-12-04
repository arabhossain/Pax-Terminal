<?php

class Pax
{
    private $ip;
    private $port;
    private $protocol = 'http://';
    private $stx, $etx, $fs, $us;

    //EDC types
    private const EDC_ALL = '00';
    private const EDC_CREDIT_CARD = '01';
    private const EDC_DEBIT_CARD = '02';
    private const EDC_EBT_CARD = '03';
    private const EDC_GIFT_CARD = '04';
    private const EDC_LOYALTY_CARD = '05';
    private const EDC_CASH_CARD = '06';
    private const EDC_CHECK_CARD = '07';
    private const TRANS_TYPE_MENU = '00';


    //Transaction Types
    private const TRANS_TYPE_SALE_REDEEM = '01';
    private const TRANS_TYPE_RETURN = '02';
    private const TRANS_TYPE_AUTH = '03';
    private const TRANS_TYPE_POSTAUTH = '04';
    private const TRANS_TYPE_FORCED = '05';
    private const TRANS_TYPE_ADJUST = '06';
    private const TRANS_TYPE_WITHDRAWAL = '07';
    private const TRANS_TYPE_ACTIVATE = '08';
    private const TRANS_TYPE_ISSUE = '09';
    private const TRANS_TYPE_ADD = '10';
    private const TRANS_TYPE_CASHOUT = '11';
    private const TRANS_TYPE_DEACTIVATE = '12';
    private const TRANS_TYPE_REPLACE = '13';
    private const TRANS_TYPE_MERGE = '14';
    private const TRANS_TYPE_REPORTLOST = '15';
    private const TRANS_TYPE_VOID = '16';
    private const TRANS_TYPE_V_SALE = '17';
    private const TRANS_TYPE_V_RTRN = '18';
    private const TRANS_TYPE_V_AUTH = '19';
    private const TRANS_TYPE_V_POST = '20';
    private const TRANS_TYPE_V_FRCD = '21';
    private const TRANS_TYPE_V_WITHDRAW = '22';
    private const TRANS_TYPE_BALANCE = '23';
    private const TRANS_TYPE_VERIFY = '24';
    private const TRANS_TYPE_REACTIVATE = '25';
    private const TRANS_TYPE_FORCED_ISSUE = '26';
    private const TRANS_TYPE_FORCED_ADD = '27';
    private const TRANS_TYPE_UNLOAD = '28';
    private const TRANS_TYPE_RENEW = '29';
    private const TRANS_TYPE_GET_CONVERT_DETAIL = '30';
    private const TRANS_TYPE_CONVERT = '31';
    private const TRANS_TYPE_TOKENIZE = '32';
    private const TRANS_TYPE_INCREMENTAL_AUTH = '33';
    private const TRANS_TYPE_BALANCE_with_LOCK = '34';
    private const TRANS_TYPE_REDEMPTION_with_UNLOCK = '35';
    private const TRANS_TYPE_REWARDS = '36';
    private const TRANS_TYPE_REENTER = '37';
    private const TRANS_TYPE_TRANSACTION_ADJUSTMENT = '38';
    private const TRANS_TYPE_REVERSAL = '39';


    /*
     * constructor will receive data array
     * required array keys: ip_address, port
     */
    public function __construct($config)
    {
        $this->ip = $config['ip_address'];
        $this->port = $config['port'];

        //ASCII INIT
        $this->stx = chr(02);
        $this->etx = chr(02);
        $this->fs = chr(02);
        $this->us = chr(0x1F);
    }

    /**
     * Initialize Terminal
     * @returns array
     */
    private final function terminalInitialize(): array
    {
        $prams = [
            'command' => 'A00',
            'version' => '1.28'
        ];
        $prams = $this->stx . implode($this->fs, array_values($prams)) . $this->etx;
        $prams = base64_encode($this->_generateStringWithLRC($prams));
        return $this->makeRequest($prams);
    }

    /**
     * function signature
     * @return array
     */
    private final function signature():array
    {
        $prams = [
            'command' => 'A20',
            'version' => '1.28',
            'uploadFlug' => 0,
            'hostReference' => '',
            'EDCType' => self::EDC_ALL,
            'timeout' => 200,
            'continuousScreen' => '',
        ];

        $prams = $this->stx . implode($this->fs, array_values($prams)) . $this->etx;
        $prams = base64_encode($this->_generateStringWithLRC($prams));
        return $this->makeRequest($prams);
    }

    private final function transaction(array $data) : array
    {
        $prams = [
            'command' => 'A20',
            'version' => '1.28',
            'transactionType' => $this->transactionType($data['trans_type'] ?? ''),
            'amountInformation' => $this->getAmountInformation($data),
            'accountInformation' => [
                'account'
            ],
            'traceInformation' => 200,
            'AVSInformation' => '',
            'cashierInformation' => '',
            'commercialInformation' => '',
            'MOTO/E-commerce' => '',
            'AdditionalInformation' => '',
            'POSEchoData' => '',
            'continuousScreen' => 0,
        ];

        //join when key has array value inside
        foreach ($prams as $key => $pram){
            if(is_array($pram)){
                $prams[$key] = implode($this->us, $pram);
            }
        }

        //join top level array and send request and return the response
        $prams = $this->stx . implode($this->fs, array_values($prams)) . $this->etx;
        $prams = base64_encode($this->_generateStringWithLRC($prams));
        return $this->makeRequest($prams);
    }

    private final function getAmountInformation(array $data, string $type)
    {
        $amountInfo = [ //separate with us
            'transactionAmount' => $data['amount'] ?? 0,
            'tipAmount' => $data['tip'] ?? 0,
            'cashBackAmount' => $data['cash_back'] ?? 0,
            'merchantFee' => $data['merchant_fee'] ?? 0,
            'taxAmount' => $data['tax'] ?? 0,
            'serviceFee' => $data['service_fee'] ?? 0,
        ];

        //@todo
        switch ($type){
            case self::TRANS_TYPE_AUTH:
                unset($amountInfo['tipAmount']);
                unset($amountInfo['cashBackAmount']);
                unset($amountInfo['merchantFee']);
                unset($amountInfo['taxAmount']);
                unset($amountInfo['serviceFee']);
                break;
            case self::TRANS_TYPE_AUTH:
                unset($amountInfo['tipAmount']);
                unset($amountInfo['cashBackAmount']);
                unset($amountInfo['merchantFee']);
                unset($amountInfo['taxAmount']);
                unset($amountInfo['serviceFee']);
                break;
            case self::TRANS_TYPE_SALE_REDEEM:


        }

        return $amountInfo;
    }

    /*
     * generate LRC from a string and returns joined that string with LRC
     * @prams $str
     * @returns string
     */
    private final function _generateStringWithLRC(string $str):string
    {
        //Performing LRC on the string
        $bytes = [];
        for ($i = 0; $i < strlen($str); $i++)
            $bytes[] = ord($str[$i]);

        $LRC = 0;
        for ($count = 1; $count < count($bytes); $count++)
            $LRC ^= $bytes[$count];

        return $str . chr($LRC);
    }

    /*
     * send request to terminal server and returns it's response
     * @pram $prams
     * @return array
     */
    private function makeRequest(string $prams) : array
    {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $this->protocol . $this->ip . ':' . $this->port . '/?' . $prams,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
            ));
            $response = curl_exec($curl);
            curl_close($curl);

            //return response
            return (new TerminalResponse($response))->toArray();
        } catch (Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }

    private function transactionType(?string $type): string
    {
        switch ($type) {
            case 'return':
                $transactionType = self::TRANS_TYPE_RETURN;
                break;
            case 'void':
                $transactionType = self::TRANS_TYPE_VOID;
                break;
            case 'auth':
                $transactionType = self::TRANS_TYPE_AUTH;
                break;
            case 'sale':
            default:
                $transactionType = self::TRANS_TYPE_SALE_REDEEM;
                break;
        }
        return $transactionType;
    }
}

/*
 * Terminal Response decode and made simplified
 */
class TerminalResponse
{
    /*
     * Regular expression to extract
     * command
     * response status
     * command version
     */
    const REGX_COMM_CODE_VERSION = '/^(0|1)+([A-Z]\d\d)+(\d.\d\d)+(\d{6})+/';

    /*
     * Transaction key constants
     */
    private $transactionRaw;
    private array $transactionDetails = [];

    private stdClass $rawResponse;
    private ?string $rawResponseString;
    private $status = [
        '000000' => 'OK',
        '000100' => 'DECLINE',
        '100001' => 'TIMEOUT',
        '100002' => 'ABORTED',
        '100003' => 'INVALID'
    ];
    private $commands = [
        'A00' => 'Initialize - Request',
        'A01' => 'Initialize - Response',

        'A20' => 'Do Signature - Request',
        'A21' => 'Do Signature - Response',

        'T00' => 'Do Credit - Request',
        'T01' => 'Do Credit - Response',
    ];

    public function __construct(?string $rawResponse)
    {
        $this->rawResponseString = $rawResponse;
        $this->extract();
    }

    private final function extract(): void
    {
        /*
         * Decode response for A01 A21 T01 ... Response String
         */
        preg_match_all('/^+(.*)+(?:)/mi', $this->rawResponseString, $matches, PREG_SET_ORDER, 0);
        $response = str_replace('', '', $matches[0][0]);
        $response = str_replace('', '', $response);
        $response = explode('', $response);
        for ($count = 0; $count < count($response); $count++){
            if (preg_match('/'.''.'/', $response[$count])) {
                $response[$count] = explode('', $response[$count]);
            }
        }

        list($status, $command, $version, $responseCode, $responseMessage) = $response;

        $this->rawResponse = new stdClass();
        $this->transactionRaw = null;
        if (!empty($response)) {
            $this->rawResponse->status = $status;
            $this->rawResponse->command = $command;
            $this->rawResponse->version = $version;
            $this->rawResponse->responseCode = $responseCode;

            /*
             * Fetch in case DO CREDIT Response
             */
            if ($command === 'T01') {
                list($status, $command, $version, $responseCode, $responseMessage, $hostInformation, $transactionType, $amountInfo, $accountInfo, $traceInfo, $avsInfo, $commercialInfo) = $response;
                $this->transactionRaw = (object)[
                    'hostDetails' => $hostInformation,
                    'amountDetails' => $amountInfo,
                    'accountDetails' => $accountInfo,
                    'traceDetails' => $traceInfo,
                ];
            }
        }
    }

    public function getVersion(): ?string
    {
        return $this->rawResponse->version ?? null;
    }

    public function getRawResponse(): stdClass
    {
        return $this->rawResponse;
    }

    public function getRawResponseString(): ?string
    {
        return $this->rawResponseString;
    }

    public function setRawResponseString(?string $rawResponseString): void
    {
        $this->rawResponseString = $rawResponseString;
        $this->extract();
    }

    public final function toArray(): array
    {
        return [
            'success' => $this->isSucceed(),
            'data' => [
                'code' => $this->rawResponse->responseCode ?? null,
                'message' => $this->getCode(),
                'command' => $this->rawResponse->command ?? null,
                'commandString' => $this->getCommand(),
                'creditDetails' => $this->processCreditDetails()
            ],
        ];
    }

    public function isSucceed(): bool
    {
        return ($this->rawResponse->responseCode ?? null) == '000000';
    }

    public function getCode(): ?string
    {
        return $this->status[($this->rawResponse->responseCode ?? null)] ?? null;
    }

    public function getCommand(): ?string
    {
        return $this->commands[$this->rawResponse->command ?? null] ?? null;
    }

    private final function processCreditDetails()
    {
        /*
         * Host Details Information
         */
        if (!empty($this->transactionRaw->hostDetails)) {
            $this->transactionDetails['hostDetails'] = [
                'responseCode' => $this->transactionRaw->hostDetails[0] ?? null,
                'responseMessage' => $this->transactionRaw->hostDetails[1] ?? null,
                'authCode' => $this->transactionRaw->hostDetails[2] ?? null,
                'refNumber' => $this->transactionRaw->hostDetails[3] ?? null,
                'traceNumber' => $this->transactionRaw->hostDetails[4] ?? null,
                'batchNumber' => $this->transactionRaw->hostDetails[5] ?? null,
                'transactionIdentifier' => $this->transactionRaw->hostDetails[6] ?? null,
                'gatewayTransactionID' => $this->transactionRaw->hostDetails[7] ?? null,
            ];
        }

        /*
        * Amount Details Information
        */
        if (!empty($this->transactionRaw->amountDetails)) {
            $this->transactionDetails['amountDetails'] = [
                'amount' => $this->transactionRaw->amountDetails[0] ?? null,
                'due' => $this->transactionRaw->amountDetails[1] ?? null,
                'tip' => $this->transactionRaw->amountDetails[2] ?? null,
                'cashBack' => $this->transactionRaw->amountDetails[3] ?? null,
                'merchantFee' => $this->transactionRaw->amountDetails[4] ?? null,
                'tax' => $this->transactionRaw->amountDetails[5] ?? null,
                'balance1' => $this->transactionRaw->amountDetails[6] ?? null,
                'balance2' => $this->transactionRaw->amountDetails[7] ?? null,
                'serviceFee' => $this->transactionRaw->amountDetails[8] ?? null,
                'transactionRemainingAmount' => $this->transactionRaw->amountDetails[9] ?? null,
            ];
        }

        /*
        * Account Details Information
        */
        if (!empty($this->transactionRaw->accountDetails)) {
            $this->transactionDetails['accountDetails'] = [
                'account' => $this->transactionRaw->accountDetails[0] ?? null,
                'entryMode' => $this->transactionRaw->accountDetails[1] ?? null,
                'expireDate' => $this->transactionRaw->accountDetails[2] ?? null,
                'ebtType' => $this->transactionRaw->accountDetails[3] ?? null,
                'voucherNumber' => $this->transactionRaw->accountDetails[4] ?? null,
                'newAccountNo' => $this->transactionRaw->accountDetails[5] ?? null,
                'cardType' => $this->transactionRaw->accountDetails[6] ?? null,
                'crdHolder' => $this->transactionRaw->accountDetails[7] ?? null,
                'CVDApprovalCode' => $this->transactionRaw->accountDetails[8] ?? null,
            ];
        }

        /*
        * Trace Details Information
        */
        if (!empty($this->transactionRaw->traceDetails)) {
            $this->transactionDetails['traceDetails'] = [
                'transactionNumber' => $this->transactionRaw->traceDetails[0] ?? null,
                'referenceNumber' => $this->transactionRaw->traceDetails[1] ?? null,
                'timeStamp' => $this->transactionRaw->traceDetails[2] ?? null,
                'InvNum' => $this->transactionRaw->traceDetails[3] ?? null,
            ];
        }

        return $this->transactionDetails;
    }
}
