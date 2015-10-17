<?php

require "../vendor/autoload.php";
require "../db/bootstrap.php";

$address = \BitWasp\Bitcoin\Address\AddressFactory::fromString('1J7jgWATD4Vfe9eUs7EL4YdadbPaM7cGgj');
$script = \BitWasp\Bitcoin\Script\ScriptFactory::scriptPubKey()->payToAddress($address);
/** @var \BitWasp\Bitcoin\Transaction\TransactionOutputInterface[] $outputs */
$outputs = [
    new \BitWasp\Bitcoin\Transaction\TransactionOutput(1000000, $script)
];

$signer = new \BitWasp\Bitcoin\PaymentProtocol\PaymentRequestSigner('none');
$builder = new \BitWasp\Bitcoin\PaymentProtocol\PaymentRequestBuilder($signer, 'main', time());
foreach ($outputs as $output) {
    $builder->addOutput($output);
}

$request = $builder->getPaymentRequest();
$value = 1000000;
$amount = new \BitWasp\Bitcoin\Amount();
$list = \BitWasp\Payments\Db\Request::create([
    'slug' => bin2hex(mcrypt_create_iv(32, MCRYPT_DEV_URANDOM)),
    'value' => $value,
    'valueBtc' => $amount->toBtc($value),
    'payment_request' => $request->serialize()
]);

$req = [];
foreach ($outputs as $output) {
    $req[] = \BitWasp\Payments\Db\OutputRequirement::create([
        'request_id' => $list->id,
        'value' => $output->getValue(),
        'valueBtc' => $amount->toBtc($value),
        'script' => $output->getScript()->getBinary(),
        'address' => $address->getAddress()
    ]);
}

