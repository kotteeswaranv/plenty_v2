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
        
        $paymentHelper->createMopIfNotExists();
        // Register the Novalnet payment methods in the payment method container
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
		    $this->getLogger(__METHOD__)->error('nnBasketRepo', $basketRepository->load());
		    $this->getLogger(__METHOD__)->error('nnBasket', $basketRepository);
                    $this->getLogger(__METHOD__)->error('TEST', $event->getMop());
                    if($paymentHelper->isNovalnetPaymentMethod($event->getMop()))
                    {
		     $paymentKey = $paymentHelper->getPaymentKeyByMop($event->getMop());
		       $this->getLogger(__METHOD__)->error('GURANTEESTATUS', $paymentService->getGuaranteeStatus($basketRepository->load(), $paymentKey));                       
                        if(in_array($paymentKey, ['NOVALNET_INVOICE', 'NOVALNET_PREPAYMENT', 'NOVALNET_CASHPAYMENT']))
                        {
							$processDirect = true;                          
			    
							if($paymentKey == 'NOVALNET_INVOICE')
							{
								$guaranteeStatus = $paymentService->getGuaranteeStatus($basketRepository->load(), $paymentKey);
								if($guaranteeStatus == 'error')
								{
									$processDirect = false;
									$contentType = 'errorCode';
									//$content = 'Die Zahlung kann nicht verarbeitet werden, weil die grundlegenden Anforderungen nicht erfüllt wurden.';
									$content = 'The payment cannot be processed, because the basic requirements haven`t been met.';
								}
								else if($guaranteeStatus == 'guarantee')
								{
									$processDirect = false;
									$paymentProcessUrl = $paymentService->getProcessPaymentUrl();
									$content = $twig->render('Novalnet::PaymentForm.Invoice', [
														'nnPaymentProcessUrl' => $paymentProcessUrl,
														'paymentMopKey'     =>  $paymentKey,
														'nnDobValue' => ''
									]);
									$contentType = 'htmlContent';
								 }
							}
							if($processDirect)
							{
								$serverRequestData = $paymentService->getRequestParameters($basketRepository->load(), $paymentKey);
								$sessionStorage->getPlugin()->setValue('nnPaymentData', $serverRequestData['data']);
								$response = $paymentHelper->executeCurl($serverRequestData['data'], $serverRequestData['url']);
								$responseData = $paymentHelper->convertStringToArray($response['response'], '&');
								$responseData['payment_id'] = (!empty($responseData['payment_id'])) ? $responseData['payment_id'] : $responseData['key'];
								$sessionStorage->getPlugin()->setValue('nnPaymentData', array_merge($serverRequestData['data'], $responseData));
								$content = '';
								$contentType = 'continue';
							}
                            
                        } else if (in_array($paymentKey, ['NOVALNET_SEPA', 'NOVALNET_CC']))
                        {   
                            if($paymentKey == 'NOVALNET_SEPA'){
                                $paymentProcessUrl = $paymentService->getProcessPaymentUrl();
				$nnDetails = [];
			        $contentType = 'htmlContent';
				$nnDetails['vendor'] = $paymentHelper->getNovalnetConfig('vendor_id');
				$nnDetails['authcode'] = $paymentHelper->getNovalnetConfig('auth_code');
				$nnDetails['sepauniqueid'] = uniqid();
				$nnDetails['sepavendormsg'] = $paymentHelper->getTranslatedText('sepa_vendormsg');
				$nnDetails['sepacardmsg'] = $paymentHelper->getTranslatedText('sepa_cardmsg');
				$nnDetails['sepaacceptmandate'] = $paymentHelper->getTranslatedText('sepa_acceptmandate');
				$nnDetails['sepacountryerror'] = $paymentHelper->getTranslatedText('sepa_countryerror');
			        $nnDetails['sepadoberror'] = $paymentHelper->getTranslatedText('doberror');
				$nnCountryList = $paymentHelper->getCountryList($sessionStorage->getLocaleSettings()->language);
				$guaranteeStatus = $paymentService->getGuaranteeStatus($basketRepository->load(), $paymentKey);    
				if($guaranteeStatus == 'error')
				{
				    $contentType = 'errorCode';
				    //$content = 'Die Zahlung kann nicht verarbeitet werden, weil die grundlegenden Anforderungen nicht erfüllt wurden.';
				    $content = 'The payment cannot be processed, because the basic requirements haven`t been met.';
				}
			        else
			        {
				    $content = $twig->render('Novalnet::PaymentForm.Sepa', [
                                                                    'nnPaymentProcessUrl' => $paymentProcessUrl,
                                                                    'paymentMopKey'     =>  $paymentKey,
								    'nnSepaHiddenValue' => $nnDetails,
								    'nnGuaranteeStatus' => $guaranteeStatus,
																	'nnCountry' =>$nnCountryList
                                     ]);
			        }
                            }
                            else 
                            {
                                $paymentProcessUrl = $paymentService->getProcessPaymentUrl();
                                $encodedKey = base64_encode(trim($paymentHelper->getNovalnetConfig('activation_key')) . '&' . $paymentHelper->getRemoteAddress() . '&' . $paymentHelper->getServerAddress());
                                $nnIframeSource = 'https://secure.novalnet.de/cc?signature=' . $encodedKey . '&ln=' . $sessionStorage->getLocaleSettings()->language;
				$nnFormDesign = $paymentService->getCcDesignConfig();
                                $content = $twig->render('Novalnet::PaymentForm.Cc', [
                                                                    'nnCcFormUrl' => $nnIframeSource,
                                                                    'nnPaymentProcessUrl' => $paymentProcessUrl,
                                                                    'paymentMopKey'     =>  $paymentKey,
								    'nnFormDesign'	=>  $nnFormDesign
                                       ]);
				$contentType = 'htmlContent';
                            }
                            
                        } 
                        else
                        {
                            $serverRequestData = $paymentService->getRequestParameters($basketRepository->load(), $paymentKey);                            
							$sessionStorage->getPlugin()->setValue('nnPaymentData', $serverRequestData['data']);
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
