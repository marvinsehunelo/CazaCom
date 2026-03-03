<?php
require_once __DIR__ . '/../models/Ussd.php';
require_once __DIR__ . '/../models/Wallet.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/Data.php';

class UssdController {
    private $db;
    private $ussd;
    private $wallet;
    private $transaction;
    private $data;

    public function __construct($db){
        $this->db = $db;
        $this->ussd = new Ussd($db);
        $this->wallet = new Wallet($db);
        $this->transaction = new Transaction($db);
        $this->data = new Data($db);
    }

    public function handleRequest($data){
        $session_id = $data['session_id'] ?? null;
        $user_id = $data['user_id'] ?? null;
        $text = trim($data['text'] ?? '');

        if (!$user_id || !$session_id) {
            return ['response'=>'Invalid session or user','action'=>'end'];
        }

        $session = $this->ussd->getSession($session_id);
        $step = $session['step'] ?? 'main';

        switch($step){
            case 'main':
                if ($text == '') {
                    $this->ussd->saveSession($session_id, $user_id, '', 'main');
                    return ['response'=>"Welcome to CazaCom!\n1. Check balance\n2. Buy Data\n3. Recharge\nReply with choice:", 'action'=>'continue'];
                }
                switch($text){
                    case '1':
                        $balance = $this->wallet->balance($user_id);
                        return ['response'=>"Your balance: $balance units","action"=>"end"];
                    case '2':
                        $this->ussd->saveSession($session_id, $user_id, $text, 'buy_data');
                        return ['response'=>"Select bundle:\n1. 500MB - 5 units\n2. 1GB - 10 units\nReply with number:","action"=>"continue"];
                    case '3':
                        $this->ussd->saveSession($session_id, $user_id, $text, 'recharge');
                        return ['response'=>"Enter amount to recharge:","action"=>"continue"];
                    default:
                        return ['response'=>"Invalid choice, try again.","action"=>"continue"];
                }
                break;

            case 'buy_data':
                $bundleMap = ['1'=>['500MB',5],'2'=>['1GB',10]];
                if (!isset($bundleMap[$text])) {
                    return ['response'=>"Invalid choice. Select 1 or 2.","action"=>"continue"];
                }
                $bundleName = $bundleMap[$text][0];
                $bundlePrice = $bundleMap[$text][1];

                $balance = $this->wallet->balance($user_id);
                if ($balance < $bundlePrice) {
                    $this->ussd->clearSession($session_id);
                    return ['response'=>"Insufficient balance. Please recharge.","action"=>"end"];
                }

                // Deduct wallet
                $this->wallet->deduct($user_id, $bundlePrice, "Purchased $bundleName bundle via USSD");

                // Log transaction
                $this->transaction->log($user_id, 'data_purchase', $bundlePrice, "Purchased $bundleName via USSD");

                // Activate data
                $this->data->activateBundle($user_id, $bundleName);

                $this->ussd->clearSession($session_id);
                return ['response'=>"You have successfully purchased $bundleName.","action"=>"end"];

            case 'recharge':
                $amount = floatval($text);
                if ($amount <= 0) {
                    return ['response'=>"Invalid amount. Enter a valid number.","action"=>"continue"];
                }

                $this->wallet->add($user_id, $amount, "USSD recharge");
                $this->transaction->log($user_id, 'recharge', $amount, "Wallet recharge via USSD");

                $this->ussd->clearSession($session_id);
                return ['response'=>"Your wallet has been recharged with $amount units.","action"=>"end"];

            default:
                $this->ussd->clearSession($session_id);
                return ['response'=>"Session error. Start again.","action"=>"end"];
        }
    }
}
