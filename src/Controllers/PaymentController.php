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

namespace Novalnet\Controllers;

use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Novalnet\Helper\PaymentHelper;
use Plenty\Plugin\Log\Loggable;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Novalnet\Services\PaymentService;
use Plenty\Plugin\Templates\Twig;

/**
 * Class PaymentController
 *
 * @package Novalnet\Controllers
 */
class PaymentController extends Controller
{
    use Loggable;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var PaymentHelper
     */
    private $paymentHelper;

    /**
     * @var SessionStorageService
     */
    private $sessionStorage;
    
    /**
     * @var basket
     */
    private $basketRepository;
    
    /**
     * @var PaymentHelper
     */
    private $paymentService;
    
    /**
     * @var Twig
     */
    private $twig;

    /**
     * PaymentController constructor.
     *
     * @param Request $request
     * @param Response $response
     * @param PaymentHelper $paymentHelper
     * @param SessionStorageService $sessionStorage
     */
    public function __construct(  Request $request,
                                  Response $response,
                                  PaymentHelper $paymentHelper,
                                  FrontendSessionStorageFactoryContract $sessionStorage,
                                  BasketRepositoryContract $basketRepository,
                                  PaymentService $paymentService,
                                  Twig $twig
                                )
    {
        $this->request         = $request;
        $this->response        = $response;
        $this->paymentHelper   = $paymentHelper;
        $this->sessionStorage  = $sessionStorage;
        $this->basketRepository          = $basketRepository;
        $this->paymentService  = $paymentService;
        $this->twig            = $twig;
    }

    /**
     * Novalnet redirects to this page if the payment was executed successfully
     *
     */
    public function paymentResponse()
    {
        $requestData = $this->request->all();
        
        $requestData['payment_id'] = (!empty($requestData['payment_id'])) ? $requestData['payment_id'] : $requestData['key'];

        $isPaymentSuccess = isset($requestData['status']) && in_array($requestData['status'], ['90','100']);

        $notifications = json_decode($this->sessionStorage->getPlugin()->getValue('notifications'));
        array_push($notifications,[
                'message' => $this->paymentHelper->getNovalnetStatusText($requestData),
                'type'    => $isPaymentSuccess ? 'success' : 'error',
                'code'    => 0
            ]);
        $this->sessionStorage->getPlugin()->setValue('notifications', json_encode($notifications));

        if($isPaymentSuccess)
        {
            if(!preg_match('/^[0-9]/', $requestData['test_mode']))
            {
                $requestData['test_mode'] = $this->paymentHelper->decodeData($requestData['test_mode'], $requestData['uniqid']);
                $requestData['amount']    = $this->paymentHelper->decodeData($requestData['amount'], $requestData['uniqid']) / 100;
            }

            $paymentRequestData = $this->sessionStorage->getPlugin()->getValue('nnPaymentData');
            $this->sessionStorage->getPlugin()->setValue('nnPaymentData', array_merge($paymentRequestData, $requestData));

            // Redirect to the success page.
            return $this->response->redirectTo('place-order');
        } else {
            // Redirects to the cancellation page.
            return $this->response->redirectTo('checkout');
        }
    }
    
    
    /**
     * Process the Form payment
     *
     */
    public function processPayment()
    {
        $requestData = $this->request->all();
        if(!empty($requestData['paymentKey']) && in_array($requestData['paymentKey'], ['NOVALNET_CC', 'NOVALNET_SEPA']) && (!empty($requestData['nn_pan_hash']) || !empty($requestData['nn_sepa_hash'])))
        $serverRequestData = $this->paymentService->getRequestParameters($this->basketRepository->load(), $requestData['paymentKey']);
        $this->sessionStorage->getPlugin()->setValue('nnPaymentData', $serverRequestData['data']);
        if($requestData['paymentKey'] == 'NOVALNET_CC') {
            $serverRequestData['data']['pan_hash'] = $requestData['nn_pan_hash'];
            $serverRequestData['data']['unique_id'] = $requestData['unique_id'];
            if(!empty($serverRequestData['data']['cc_3d']))
            {
                $this->sessionStorage->getPlugin()->setValue('nnPaymentData', $serverRequestData['data']);
                 return $this->twig->render('Novalnet::NovalnetPaymentRedirectForm', [
                                                                'formData'     => $serverRequestData['data'],
                                                                'nnPaymentUrl' => $serverRequestData['url']
                                   ]);
            }
        }
        else if($requestData['paymentKey'] == 'NOVALNET_SEPA')
        {
            $serverRequestData['data']['sepa_hash'] = $requestData['nn_sepa_hash'];
            $serverRequestData['data']['sepa_unique_id'] = $requestData['nn_sepa_uniqueid'];
            $serverRequestData['data']['bank_account_holder'] = $requestData['sepa_cardholder'];
            $guranteeStatus = $this->paymentService->getGuaranteeStatus($this->basketRepository->load(), $requestData['paymentKey']);
            if('guarantee' == $guranteeStatus)
            {
                if(empty($requestData['nn_sepa_birthday']))
                {
                    $notifications = json_decode($this->sessionStorage->getPlugin()->getValue('notifications'));
                    array_push($notifications,[
                        'message' => 'GUARANTEE_DOB_EMPTY_ERROR',
                        'type'    => 'error',
                        'code'    => ''
                     ]);
                    $this->sessionStorage->getPlugin()->setValue('notifications', json_encode($notifications));
                    return $this->response->redirectTo('checkout');
                 //GUARANTEE_DOB_EMPTY_ERROR   
                } 
                else if(time() < strtotime('+18 years', strtotime($requestData['nn_sepa_birthday'])))
                {
                    $notifications = json_decode($this->sessionStorage->getPlugin()->getValue('notifications'));
                    array_push($notifications,[
                        'message' => 'NOVALNET_INVALID_BIRTHDATE',
                        'type'    => 'error',
                        'code'    => ''
                     ]);
                    $this->sessionStorage->getPlugin()->setValue('notifications', json_encode($notifications));
                }
                else
                {
                    $serverRequestData['data']['payment_type'] = 'GUARANTEED_DIRECT_DEBIT_SEPA';
                    $serverRequestData['data']['key']          = '40';
                    $serverRequestData['data']['birth_date']   = $requestData['nn_sepa_birthday'];                    
                }
            }
            $this->getLogger(__METHOD__)->error('guranteeStatus', $guranteeStatus);
            //nn_sepa_birthday
            //
        } 
        $this->getLogger(__METHOD__)->error('serverRequestData', $serverRequestData);
        $this->getLogger(__METHOD__)->error('RequestData', $requestData);
        $response = $this->paymentHelper->executeCurl($serverRequestData['data'], $serverRequestData['url']);
        $responseData = $this->paymentHelper->convertStringToArray($response['response'], '&');
        $responseData['payment_id'] = (!empty($responseData['payment_id'])) ? $responseData['payment_id'] : $responseData['key'];
        $isPaymentSuccess = isset($responseData['status']) && in_array($responseData['status'], ['90','100']);

        $notifications = json_decode($this->sessionStorage->getPlugin()->getValue('notifications'));
        array_push($notifications,[
                'message' => $this->paymentHelper->getNovalnetStatusText($responseData),
                'type'    => $isPaymentSuccess ? 'success' : 'error',
                'code'    => 0
            ]);
        $this->sessionStorage->getPlugin()->setValue('notifications', json_encode($notifications));

        if($isPaymentSuccess)
        {
            if(!preg_match('/^[0-9]/', $responseData['test_mode']))
            {
                $responseData['test_mode'] = $this->paymentHelper->decodeData($responseData['test_mode'], $responseData['uniqid']);
                $responseData['amount']    = $this->paymentHelper->decodeData($responseData['amount'], $responseData['uniqid']) / 100;
            }

            if(isset($serverRequestData['data']['pan_hash']))
            {
                unset($serverRequestData['data']['pan_hash']);
            }
            elseif(isset($serverRequestData['data']['sepa_hash']))
            {
                unset($serverRequestData['data']['pan_hash']);
            }
            $this->sessionStorage->getPlugin()->setValue('nnPaymentData', array_merge($serverRequestData['data'], $responseData));

            // Redirect to the success page.
            return $this->response->redirectTo('place-order');
        } else {
            // Redirects to the cancellation page.
            return $this->response->redirectTo('checkout');
        }
    }
    
}
