<?php 

/**  
 * This class let you implement Nimbocoin into your website/app/game
 * 
 * There is the expected two flows, deposit and withdrawal. 
 * 
 * Deposit : 
 * 
 * 1. When user want to make a deposit generate an unique payment id via the genPaymentId() method and then store it on your database with a state column that say the transaction is awaiting. 
 * 2. Create a script that will be called regularly via a cron job (each 10 or 20min).
 * 3. This script will call cronCheckTransactions() that will :
 * 
 * - Get our awaited transactions from the method parameter.
 * - Get block count.
 * - Check all transactions from last X blocks (X is the blocksOffset variable).
 * - If an awaiting transaction is found (we check via the Payment id) in the blocks that we check, we update informations about the transaction (set the state to complete, store the amount and timestamp).
 * 
 * Withdrawal :
 * 
 * 1. When the user want to make a withdrawal, simply call the method withdraw(), with according parameters.
 * 
 * 
 * /!\ REMINDER /!\ This class is intended to give you a set of methods for Nimbocoin integration, you are the sole responsible of the user front end experience and 
 * users and their balance management/storage/check on your website/app/game.
 * 
 **/

Class NimboIntegrationTools {

    /* Your RPC wallet url */
    public $walletUrl = "";
    
    /* The number of last blocks to check each time cronCheckTransactions() execute */
    public $blocksOffset = 250;

    /**
     * Generate a payment id from raw text
     * 
     * @param string $text The text to convert to payment ID
     *
     * @return string $paymentId The Payment ID
     */
    public function genPaymentId($text) {

        $converted = array_shift(unpack('H*', $text));
    
        $paymentId = str_pad($converted, 64, "0", STR_PAD_LEFT);

        return $paymentId;
    }

    /**
     * Method to launch from a cron for awaiting transactions checking
     * 
     * @param $awaitingTransactions 
     * 
     * The awaitingTransactions to process, multidimensionnal array (key => value)
     *
     * Sample : 
     * 
     * [
     *  0 => '00000000000000000000000000000000000000000000000000746573742d6964',
     *  1 => '00000000000000000000000000000000000000000000000000746573742d7065'
     * ]
     * 
     * 
     * @return array $transactionsToUpdate The list of transactions found and needed to be updated on your database.
     */
    public function cronCheckTransactions($awaitingTransactions) {
        
        /* First we get awaiting transactions to check */
        $transactionsToCheck = $awaitingTransactions;
        
        /* Ensure that we have transactions to check, if not don't waste time */
        if (is_array($transactionsToCheck) && !empty($transactionsToCheck)) {

            /* Then we get actual block count */
            $blocksCount = $this->getBlocksCount();
            $blocksCountWithOffset = $blocksCount - $this->blocksOffset;

            /* Check transactions */

            /* We do the request */
            $result = $this->requestNetwork($this->walletUrl, '{"params":{"firstBlockIndex":'.(int)$blocksCountWithOffset.',"blockCount":'.(int)$blocksCount.'},"jsonrpc": "2.0", "id": "checktrans","method":"getTransactions"}');
            
            /* Store and json decode the result */
            $result = json_decode($result, true);

            /* Prepare array for storing transactions founded */
            $transactionsToUpdate = [];

            /* For each block */
            foreach ($result['result']['items'] as $block) {
                
                /* If the block have transactions */
                if (is_array($block["transactions"]) && !empty($block["transactions"])) {

                    /* For each transaction */
                    foreach ($block["transactions"] as $transaction) {

                        /* Check if there is a Payment Id and it's one of our search */
                        if (isset($transaction['paymentId']) && $transaction['paymentId'] !== '' && in_array($transaction['paymentId'], $transactionsToCheck)) {

                            /* We divide by 100 here because network didn't use decimal amount so we need to "reformat" it */
                            $amount = $transaction['amount']/100;

                            $blockIndex = $transaction['blockIndex'];
                            $timestamp = $transaction['timestamp'];

                            $transactionsToUpdate[$transaction['paymentId']] = [
                                'amount' => (float)$amount,
                                'blockIndex' => (int)$blockIndex,
                                'timestamp' => (int)$timestamp
                            ];

                        }

                    }

                }

            }

        } else {
            return [];
        }

        return $transactionsToUpdate;
    }

    /**
     * Withdraw nimb amount
     * 
     * @param string $address The Nimb address to transfer to
     * @param int $amount The amount of Nimb to transfer
     * 
     * @return array $return Return the request response json decoded to an array.
     */
    public function withdraw($address, $amount) {

        /* Check amount */
        if ((int)$amount > 0) {

            /* We multiply amount by 100 to remove decimals */
            $amount = (int)$amount*100;

            /* We do the request */
            $result = $this->requestNetwork($this->walletUrl, '{"params": {"anonymity":0, "fee":5000,"transfers":[{"amount":'.$amount.', "address":"'.$address.'"}]},"jsonrpc": "2.0", "id": "withdrawal","method":"sendTransaction"}');
                
            /* Store and json decode the result */
            $result = json_decode($result, true);

            /* Return the result */
            return $result;

            /** 
             * Sample success result :
             *
             * array(3) {
             *   ["id"]=>
             *   string(10) "withdrawal"
             *   ["jsonrpc"]=>
             *   string(3) "2.0"
             *   ["result"]=>
             *   array(1) {
             *       ["transactionHash"]=>
             *       string(64) "45766da328af0ece4aed705b96e6fb9ae3dff509fa33653c0a3195502c184f04"
             *   }
             * }
             *  
             */

        } else {

            /* Amount is equal to 0, check if the returned array has the key "error" when you use this method */
            return [
                'error' => 'Amount cannot be equal to 0.'
            ];

        }
        
    }

    /**
     * Get the current block count
     * 
     * @param int $forceCount If this param is set and not equal to 0, the function will return this value instead of real actual blocks count (for testing purpose)
     * 
     * @return int The current block count
     */
    private function getBlocksCount($forceCount = 0) {

        /* TODO: Dynamic block count from node */
        if ($forceCount > 0) {

            $blocksCount = $forceCount;

        } else {

            /* Get the block count from the network (wallet in this case) */
            $blocksCountRequest = $this->requestNetwork($this->walletUrl, '{"params":{},"jsonrpc": "2.0", "id": "checkstatus","method":"getStatus"}');

            /* Json decode the response */
            $blocksCountRequest = json_decode($blocksCountRequest, true);

            /* We get the actual block count */
            $blocksCount = $blocksCountRequest["result"]["blockCount"];
        }
        
        return $blocksCount;

    }

    /** 
     * Do a request on the Nimbocoin network
     * 
     * @param string $url The url of the Network resource we use (wallet or node)
     * @param string $params The params we will sent
     * @return string $return The response from the Network resource (wallet or node)
     * 
    */
    private function requestNetwork($url, $params) {

        $ch = curl_init();
        $headers = [
            'Content-Type: application/json'
        ];
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);           
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $return = curl_exec($ch);
        curl_close($ch);
        
        return $return;

    }

}

?>