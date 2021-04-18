<?php 

include_once 'NimboIntegrationTools.php';

/* TODO: Dynamic getter from database */
$awaitingTransactions = [
    0 => '00000000000000000000000000000000000000000000000000746573742d6964'
];

$NimboTools = new NimboIntegrationTools();

$transactionsToUpdate = $NimboTools->cronCheckTransactions($awaitingTransactions);

if (!empty($transactionsToUpdate)) {

    /* One or more of our checked transactions are validated, update your database with informations of each transaction */

} else {

    /* None of our checked transactions are validated yet so nothing to do here, this else is just for explanations purpose */

}

?>