<?php

require "../../vendor/autoload.php";
require "../../db/bootstrap.php";

use BitWasp\Payments\Application\Application;

$zmqContext = new ZMQContext();
$api = new \BitWasp\Payments\Api\RequestApi($zmqContext);


$app = new Application();

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views',
));
$app->register(new \BitWasp\Payments\Application\Service\SyncZmqContextServiceProvider());
$app->register(new \BitWasp\Payments\Application\Service\RequestApiServiceProvider());
$app->register(new \Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new \Silex\Provider\ValidatorServiceProvider());
$app->register(new \Silex\Provider\ServiceControllerServiceProvider());
$app->register(new \Silex\Provider\ValidatorServiceProvider());
$app->register(new \Silex\Provider\SessionServiceProvider());
$app->register(new \Silex\Provider\FormServiceProvider());
$app->register(new \Silex\Provider\TranslationServiceProvider());
$app['debug'] = true;
// ... definitions

$app->get('/', function () {
    $output = 'Hi!';
    return $output;
});

$app->get('/debug/payment/{slug}', function (\Silex\Application $app, $slug) {
    /** @var \BitWasp\Payments\Db\Request $request */
    $request = \BitWasp\Payments\Db\Request::find(['slug'=>$slug]);
    if ($request == null) {
        return '404';
    }

    $paymentRecord = \BitWasp\Payments\Db\Payment::find(['request_id' => $request->id]);
    $data = $paymentRecord->payment;
    $handler = new \BitWasp\Bitcoin\PaymentProtocol\PaymentHandler($data);
    $payment = $handler->getPayment();
    $txs = $handler->getTransactions()->getTransactions();

    foreach ($txs as $tx) {
        \BitWasp\Payments\Db\Transaction::create([
            'transaction' => $tx->getBinary(),
            'request_id' => $request->id,
            'txid' => $tx->getTransactionId()
        ]);
    }

    $info = [];
    if ($payment->hasMerchantData()) {
        $info['merchantData'] = $payment->getMerchantData();
    }

    if ($payment->hasMemo()) {
        $info['memo'] = $payment->getMemo();
    }

    if ($payment->hasRefundTo()) {
        $info['refundScript'] = $payment->getRefundTo(0)->getScript();
        $info['refundValue'] = $payment->getRefundTo(0)->getAmount();
    }

    print_r($info);
    return '';
});




$app->get('/payment/request/{slug}', function (\Silex\Application $app, $slug) {
    /** @var \BitWasp\Payments\Db\Request $request */
    $request = \BitWasp\Payments\Db\Request::find(['slug'=>$slug]);
    if ($request == null) {
        return '404';
    }

    try {
        $app['request_api']->pushGotRequest($slug);
    } catch (\Exception $e) {
        error_log($e->getMessage());
    }

    $filename = 'payment.' . substr($slug, 0, 8) . "." . time();
    $headers = [
        'Content-Type' => 'application/bitcoin-paymentrequest',
        'Content-Disposition' => 'inline; filename=' . $filename,
        'Content-Transfer-Encoding' => 'binary',
        'Expires' => '0',
        'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        'Content-Length' => (string)strlen($request->payment_request)
    ];

    $response = new \Symfony\Component\HttpFoundation\Response($request->payment_request, 200, $headers);

    return $response;
})->bind('request.request');

$app->post('/payment/payment/{slug}', function (\Symfony\Component\HttpFoundation\Request $post, $slug) use ($app) {
    /** @var \BitWasp\Payments\Db\Request $request */
    $request = \BitWasp\Payments\Db\Request::find(['slug'=>$slug]);
    if ($request == null) {
        return '404';
    }

    error_log("Slug: " . $slug);
    try {
        $app['request_api']->pushGotPayment($slug);
        error_log('pushed gotPayment');
    } catch (\Exception $e) {
        error_log($e->getMessage());
    }

    $data= $post->getContent();
    error_log(json_encode([$data]));
    try {
        $paymentHandler = new \BitWasp\Bitcoin\PaymentProtocol\PaymentHandler($data);
        $payment = $paymentHandler->getPayment();
        $txs = $paymentHandler->getTransactions()->getTransactions();

        $info = [
            'request_id' => $request->id,
            'payment' => $data
        ];

        if ($payment->hasMerchantData()) {
            $info['merchantData'] = $payment->getMerchantData();
        }

        if ($payment->hasMemo()) {
            $info['memo'] = $payment->getMemo();
        }

        if ($payment->hasRefundTo()) {
            $info['refundScript'] = $payment->getRefundTo(0)->getScript();
            $info['refundValue'] = $payment->getRefundTo(0)->getAmount();
        }

        \BitWasp\Payments\Db\Payment::create($info);

        foreach ($txs as $tx) {
            \BitWasp\Payments\Db\Transaction::create([
                'transaction' => $tx->getBinary(),
                'request_id' => $request->id,
                'txid' => $tx->getTransactionId()
            ]);
        }

        error_log('past payment handler');
    } catch (\Exception $e) {
        error_log($e->getMessage());
    }

    return '';
})
    ->bind('request.payment');

$app->get('/info/{slug}', function (\Silex\Application $app, $slug) {

    /** @var \BitWasp\Payments\Db\Request $request */
    $request = \BitWasp\Payments\Db\Request::find(['slug'=>$slug]);
    if ($request == null) {
        return '404';
    }

    /** @var \BitWasp\Payments\Db\OutputRequirement[] $requirements */
    $requirements = \BitWasp\Payments\Db\OutputRequirement::find('all', ['request_id'=>$request->id]);
    $reqs = [];
    foreach ($requirements as $req) {
        $info = $req->to_array();
        $info['scriptHex'] = bin2hex($info['script']);
        $reqs[] = $info;
    }

    $transactions = \BitWasp\Payments\Db\Transaction::find(['request_id' => $request->id]);
    print_r($transactions);
    // bitcoin:1Git6aSVJKHK34VGS2AFubnPK92rt9Wjg?amount=0.000377&r=https%3A%2F%2Fbitpay.com%2Fi%2FHztrqbJH3piKUskxkBfr8b
    $url = urlencode($app->url('request.request', ['slug' => $request->slug]));
    $bitcoinUrl = "bitcoin:" . $requirements[0]->address . "?amount=" . $request->valuebtc . "&r=" . $url;
    //$bitcoinUrl = "bitcoin:?r=" . $url;

    //echo $bitcoinUrl . "\n";
    return $app['twig']
        ->render('info.html.twig', [
            'request'=>$request->to_array(),
            'requirements' => $reqs,
            'address' => $reqs[0]['address'],
            'url' => $url,
            'bitcoinUrl' => $bitcoinUrl
        ]);
})
    ->bind('info');

$app->match(
    '/new',
    function (\Symfony\Component\HttpFoundation\Request $request) use ($app) {
        // some default data for when the form is displayed the first time

        /** @var \Symfony\Component\Form\FormFactory $factory */
        $factory = $app['form.factory'];
        $form = $factory->createBuilder()
            ->add('address', 'text', array(
                'attr' => array('class' => 'form-control', 'placeholder' => 'Bitcoin address'),
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\NotBlank(),
                    new \BitWasp\Payments\Application\Validator\BitcoinAddress()
                ]
            ))
            ->add('value', 'text', array(
                'attr' => array('class' => 'form-control', 'placeholder' => 'Amount in satoshis'),
                'constraints' => new \Symfony\Component\Validator\Constraints\NotBlank()
            ))
            ->add('send', 'submit', array(
                'attr' => array('class' => 'btn btn-lg btn-primary btn-block')
            ))
            ->getForm();

        if ('POST' == $request->getMethod()) {
            $form->bind($request);
            if ($form->isValid()) {
                $data = $form->getData();
                $address = \BitWasp\Bitcoin\Address\AddressFactory::fromString($data['address']);
                $script = \BitWasp\Bitcoin\Script\ScriptFactory::scriptPubKey()->payToAddress($address);
                $txOut = new \BitWasp\Bitcoin\Transaction\TransactionOutput($data['value'], $script);

                $slug = bin2hex(openssl_random_pseudo_bytes(32));

                $signer = new \BitWasp\Bitcoin\PaymentProtocol\PaymentRequestSigner('none');
                $builder = new \BitWasp\Bitcoin\PaymentProtocol\PaymentRequestBuilder($signer, 'main', time());
                $builder->addOutput($txOut);

                $details = $builder->getPaymentDetails();
                $details->setPaymentUrl($app->url('request.payment', ['slug' => $slug]));

                $request = $builder->getPaymentRequest();
                $totalValue = $data['value'];

                $amount = new \BitWasp\Bitcoin\Amount();
                $dbRequest = \BitWasp\Payments\Db\Request::create([
                    'slug' => $slug,
                    'value' => $totalValue,
                    'valueBtc' => $amount->toBtc($data['value']),
                    'payment_request' => $request->serialize()
                ]);

                $output = \BitWasp\Payments\Db\OutputRequirement::create([
                    'request_id' => $dbRequest->id,
                    'value' => $data['value'],
                    'valueBtc' => $amount->toBtc($data['value']),
                    'address' => $data['address'],
                    'script' => $txOut->getScript()->getBinary()
                ]);

                $app['request_api']->pushRequest($slug, [$output]);

                return $app->redirect($app['url_generator']->generate('info', array('slug' => $dbRequest->slug)));
            }
        }

        return $app['twig']->render('new.twig', [
                'form' => $form->createView(),
                'errors' => $form->getErrors()
            ]);
    }
)->bind('new');

$app->run();