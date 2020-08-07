<?php

declare (strict_types = 1);

namespace Ktarila\SyliusPaystackPlugin\Payum\Action;

use GuzzleHttp\Client;
use Ktarila\SyliusPaystackPlugin\Payum\SyliusApi;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Reply\HttpRedirect;
use Payum\Core\Request\Capture;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\Notify;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;

final class CaptureOffsiteAction implements ActionInterface, ApiAwareInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;
    /** @var Client */
    private $client;
    /** @var SyliusApi */
    private $api;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);
        $token = $request->getToken()->getHash();

        /** @var SyliusPaymentInterface $payment */
        $payment = $request->getModel();
        $payment->setDetails(['token' => $token]);

        // call the paystack api here ... catch call back in controller
        $result = array();
        //Set other parameters as keys in the $postdata array
        $url      = "https://api.paystack.co/transaction/initialize";
        $postdata = array('reference' => $token, 
            'email' => $payment->getOrder()->getCustomer()->getEmail(), 
            'amount' => $payment->getAmount(), 
            'callback_url' => $request->getToken()->getAfterUrl());


        $headers = [
            'Authorization: Bearer ' . $this->api->getApiKey(),
            'Cache-Control: no-cache',
        ];

        $httpRequest = new GetHttpRequest();
        $this->gateway->execute($httpRequest);

        if ($httpRequest->request) {
            $model->replace($httpRequest->request);
            $payment->setDetails(['status' => $response->getStatusCode()]);
        } else {
            $fields_string = http_build_query($postdata);
            //open connection
            $ch = curl_init();

            //set the url, number of POST vars, POST data
            curl_setopt($ch, CURLOPT_URL, 'https://api.paystack.co/transaction/initialize');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            //So that curl_exec returns the contents of the cURL; rather than echoing it
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            //execute post
            $result = curl_exec($ch);

            if (\curl_errno($ch)) {
                $cerr = \curl_error($ch);
            } else {
                $res = json_decode($result, true);
                throw new HttpRedirect(
                    $res['data']['authorization_url'],
                );
            }

            \curl_close($ch);
        }


    }

    public function supports($request): bool
    {
        return
        $request instanceof Capture &&
        $request->getModel() instanceof SyliusPaymentInterface
        ;
    }

    public function setApi($api): void
    {
        if (!$api instanceof SyliusApi) {
            throw new UnsupportedApiException('Not supported. Expected an instance of ' . SyliusApi::class);
        }

        $this->api = $api;
    }
}
