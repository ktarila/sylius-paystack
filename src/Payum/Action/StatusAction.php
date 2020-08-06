<?php

declare(strict_types=1);

namespace Ktarila\SyliusPaystackPlugin\Payum\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\GetStatusInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;

final class StatusAction implements ActionInterface
{
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var SyliusPaymentInterface $payment */
        $payment = $request->getFirstModel();

        $details = $payment->getDetails();


        //The parameter after verify/ is the transaction reference to be verified
        $url = "https://api.paystack.co/transaction/verify/{$details['token']}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            [
                "Authorization: Bearer sk_test_change_the_api_key"]
        );
        $response = curl_exec($ch);
        // Check if any error occurred -- return null
        if (curl_errno($ch)) {
            $request->markFailed();
            return;
        }
        curl_close($ch);

        if ($response) {
            $result = json_decode($response, true);
            if ($result) {
                if ($result['status'] === false) {
                    $request->markFailed();
                }

                if ($result['data']) {
                    //something came in
                    if ($result['data']['status'] === 'success') {
                        $request->markCaptured();
                        return;
                    } else {
                        // dump("Transaction was not successful: Last gateway response was: " . $result['data']['gateway_response']);
                        $request->markFailed();
                    }
                } else {
                    // dump($result['message']);
                    $request->markFailed();
                }
            } else {
                $request->markFailed();
            }
        } else {
            $request->markFailed();
        }

        $request->markFailed();
    }

    public function supports($request): bool
    {
        return
            $request instanceof GetStatusInterface &&
            $request->getFirstModel() instanceof SyliusPaymentInterface
        ;
    }
}
