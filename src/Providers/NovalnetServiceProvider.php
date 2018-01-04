<?php
/**
 * This module is used for real time processing of
 * Novalnet payment module of customers.
 * Released under the GNU General Public License.
 * This free contribution made by request.
 * If you have found this script useful a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated.
 *
 * @author       Novalnet
 * @copyright(C) Novalnet. All rights reserved. <https://www.novalnet.de/>
 */

namespace Novalnet\Providers;

use Plenty\Plugin\ServiceProvider;
use Plenty\Plugin\Events\Dispatcher;
use Plenty\Modules\Payment\Events\Checkout\ExecutePayment;
use Plenty\Modules\Payment\Events\Checkout\GetPaymentMethodContent;
use Plenty\Modules\Basket\Events\Basket\AfterBasketCreate;
use Plenty\Modules\Basket\Events\Basket\AfterBasketChanged;
use Plenty\Modules\Basket\Events\BasketItem\AfterBasketItemAdd;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodContainer;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Plugin\Log\Loggable;
use Novalnet\Helper\PaymentHelper;
use Novalnet\Methods\NovalnetPaymentMethod;
use Novalnet\Services\PaymentService;
use Novalnet\Services\TransactionService;
use Plenty\Plugin\Templates\Twig;

use Novalnet\Methods\NovalnetInvoicePaymentMethod;
use Novalnet\Methods\NovalnetPrepaymentPaymentMethod;
use Novalnet\Methods\NovalnetCcPaymentMethod;
use Novalnet\Methods\NovalnetSepaPaymentMethod;
use Novalnet\Methods\NovalnetSofortPaymentMethod;
use Novalnet\Methods\NovalnetPaypalPaymentMethod;
use Novalnet\Methods\NovalnetIdealPaymentMethod;
use Novalnet\Methods\NovalnetEpsPaymentMethod;
use Novalnet\Methods\NovalnetGiropayPaymentMethod;
use Novalnet\Methods\NovalnetPrzelewyPaymentMethod;
use Novalnet\Methods\NovalnetCashPaymentMethod;


/**
 * Class NovalnetServiceProvider
 *
 * @package Novalnet\Providers
 */
class NovalnetServiceProvider extends ServiceProvider
{
    use Loggable;

    /**
     * Register the route service provider
     */
    public function register()
    {
        $this->getApplication()->register(NovalnetRouteServiceProvider::class);
    }

    /**
     * Boot additional services for the payment method
     *
     * @param paymentHelper $paymentHelper
     * @param PaymentMethodContainer $payContainer
     * @param Dispatcher $eventDispatcher
     * @param PaymentService $paymentService
     * @param BasketRepositoryContract $basketRepository
     * @param PaymentMethodRepositoryContract $paymentMethodService
     * @param FrontendSessionStorageFactoryContract $sessionStorage
     * @param TransactionService $transactionLogData
     */
    public function boot( Dispatcher $eventDispatcher,
                          PaymentHelper $paymentHelper,
                          PaymentService $paymentService,
                          BasketRepositoryContract $basketRepository,
                          PaymentMethodContainer $payContainer,
                          PaymentMethodRepositoryContract $paymentMethodService,
                          FrontendSessionStorageFactoryContract $sessionStorage,
                          TransactionService $transactionLogData,
                          Twig $twig)
    {
        
        //$paymentHelper->createMopIfNotExists();
        // Register the Novalnet payment method in the payment method container
        $payContainer->register('plenty_novalnet::NOVALNET', NovalnetPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_INVOICE', NovalnetInvoicePaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_PREPAYMENT', NovalnetPrepaymentPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_CC', NovalnetCcPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_SEPA', NovalnetSepaPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_SOFORT', NovalnetSofortPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_PAYPAL', NovalnetPaypalPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_IDEAL', NovalnetIdealPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_EPS', NovalnetEpsPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_GIROPAY', NovalnetGiropayPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_PRZELEWY', NovalnetPrzelewyPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_CASHPAYMENT', NovalnetCashPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
            

        // Listen for the event that gets the payment method content
        $eventDispatcher->listen(GetPaymentMethodContent::class,
                function(GetPaymentMethodContent $event) use($paymentHelper, $paymentService, $basketRepository, $paymentMethodService, $sessionStorage, $twig)
                {
                    $this->getLogger(__METHOD__)->error('TEST', $event->getMop());
                    if($paymentHelper->isNovalnetPaymentMethod($event->getMop()))
                    {
                        $paymentKey = $paymentHelper->getPaymentKeyByMop($event->getMop());
                        if(in_array($paymentKey, ['NOVALNET_INVOICE', 'NOVALNET_PREPAYMENT', 'NOVALNET_CASHPAYMENT']))
                        {
                            $serverRequestData = $paymentService->getRequestParameters($basketRepository->load(), $paymentKey);                            
                            $this->getLogger(__METHOD__)->error('TESTREQUEST', $serverRequestData);
                            $sessionStorage->getPlugin()->setValue('nnPaymentData', $serverRequestData['data']);
                            $response = $paymentHelper->executeCurl($serverRequestData['data'], $serverRequestData['url']);
                            $responseData = $paymentHelper->convertStringToArray($response['response'], '&');
                            $this->getLogger(__METHOD__)->error('TESTRES', $response); 
                            $sessionStorage->getPlugin()->setValue('nnPaymentData', array_merge($serverRequestData['data'], $responseData));
                            $content = '';
                            $contentType = 'continue';
                            
                        } else if (in_array($paymentKey, ['NOVALNET_SEPA', 'NOVALNET_CC']))
                        {   
                            if($paymentKey == 'NOVALNET_SEPA'){
                                //$paymentProcessUrl = $paymentService->getProcessPaymentUrl();
                                
	<input type="hidden" id="paymentKey" name="paymentKey" value="NOVALNET_SEPA">
	<input type="hidden" id="nn_sepa_hash" name="nn_sepa_hash">
<input type="hidden" id="nn_vendor" value="">
<input type="hidden" id="nn_auth_code" value="">
<input type="hidden" id="nn_sepa_uniqueid" name="nn_sepa_uniqueid" value="">
<input type="hidden" id="nn_sepa_merchant_valid_message" value="">
<input type="hidden" id="nn_sepa_valid_message" value="">
<input type="hidden" id="nn_sepa_confirm_iban_bic_msg" value="">
<input type="hidden" id="nn_sepa_countryerror_msg" value="">
<input type="hidden" id="nn_sepa_ibanbic_confirm_id">
<input type="hidden" id="sepa_bic_gen">
<input type="hidden" id="sepa_iban_gen">
<input type="hidden" id="url_country" value="">
                                $paymentProcessUrl = '';
                                $content = $twig->render('Novalnet::PaymentForm.Sepa', [
                                                                    'nnPaymentProcessUrl' => $paymentProcessUrl,
                                                                    'paymentMopKey'     =>  $paymentKey
                                       ]);
                            }
                            else 
                            {
                                $paymentProcessUrl = $paymentService->getProcessPaymentUrl();
                                $encodedKey = base64_encode(trim($paymentHelper->getNovalnetConfig('activation_key')) . '&' . $paymentHelper->getRemoteAddress() . '&' . $paymentHelper->getServerAddress());
                                $nnIframeSource = 'https://secure.novalnet.de/cc?signature=' . $encodedKey . '&ln=' . $sessionStorage->getLocaleSettings()->language;
                                $content = $twig->render('Novalnet::PaymentForm.Cc', [
                                                                    'nnCcFormUrl' => $nnIframeSource,
                                                                    'nnPaymentProcessUrl' => $paymentProcessUrl,
                                                                    'paymentMopKey'     =>  $paymentKey
                                       ]);
                            }
                            $contentType = 'htmlContent';
                        } 
                        else
                        {
                            $serverRequestData = $paymentService->getRequestParameters($basketRepository->load(), $paymentKey);                            
                            $content = $twig->render('Novalnet::NovalnetPaymentRedirectForm', [
                                                                'formData'     => $serverRequestData['data'],
                                                                'nnPaymentUrl' => $serverRequestData['url']
                                   ]);

                            $contentType = 'htmlContent';
                            
                        }
                        
                        $event->setValue($content);
                        $event->setType($contentType);
                    }
                });

        // Listen for the event that executes the payment
        $eventDispatcher->listen(ExecutePayment::class,
            function (ExecutePayment $event) use ($paymentHelper, $paymentService, $sessionStorage, $transactionLogData)
            {
                if($paymentHelper->isNovalnetPaymentMethod($event->getMop()))
                {
                    $this->getLogger(__METHOD__)->error('nncheck', $event->getMop());
                    $requestData = $sessionStorage->getPlugin()->getValue('nnPaymentData');
                    $this->getLogger(__METHOD__)->error('nncheck', $requestData);
                    $sessionStorage->getPlugin()->setValue('nnPaymentData',null);
                    if(isset($requestData['status']) && in_array($requestData['status'], ['90', '100']))
                    {
                        $this->getLogger(__METHOD__)->error('nncheck', $requestData);
                        $requestData['order_no'] = $event->getOrderId();
                        $requestData['mop']      = $event->getMop();
                        $paymentService->sendPostbackCall($requestData);

                        $paymentResult = $paymentService->executePayment($requestData);
                        $this->getLogger(__METHOD__)->error('nncheck', $paymentResult);
                        $isPrepayment = (bool)($requestData['payment_id'] == '27' && $requestData['invoice_type'] == 'PREPAYMENT');

                        $transactionData = [
                            'amount'           => $requestData['amount'] * 100,
                            'callback_amount'  => $requestData['amount'] * 100,
                            'tid'              => $requestData['tid'],
                            'ref_tid'          => $requestData['tid'],
                            'payment_name'     => $paymentHelper->getPaymentNameByResponse($requestData['payment_id'], $isPrepayment),
                            'payment_type'     => $requestData['payment_type'],
                            'order_no'         => $requestData['order_no'],
                        ];

                        if($requestData['payment_id'] == '27' || $requestData['payment_id'] == '59' || (in_array($requestData['tid_status'], ['85','86','90'])))
                            $transactionData['callback_amount'] = 0;
                        
                        $this->getLogger(__METHOD__)->error('nncheck', $paymentResult);
                        $transactionLogData->saveTransaction($transactionData);
                        $this->getLogger(__METHOD__)->error('nncheck', $paymentResult);
                    } else {
                        $this->getLogger(__METHOD__)->error('nncheck', 'eooro');
                        $paymentResult['type'] = 'error';
                        $paymentResult['value'] = $paymentHelper->getTranslatedText('payment_not_success');
                    }
                    $event->setType($paymentResult['type']);
                    $event->setValue($paymentResult['value']);
                }
            }
        );
    }
}
