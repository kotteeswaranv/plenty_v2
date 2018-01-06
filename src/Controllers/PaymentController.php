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
                                  PaymentService $paymentService
                                )
    {
        $this->request         = $request;
        $this->response        = $response;
        $this->paymentHelper   = $paymentHelper;
        $this->sessionStorage  = $sessionStorage;
        $this->basketRepository          = $basketRepository;
        $this->paymentService  = $paymentService;
    }

    /**
     * Novalnet redirects to this page if the payment was executed successfully
     *
     */
    public function paymentResponse()
    {
        $requestData = $this->request->all();

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
        }
        else
        {
            $serverRequestData['data']['sepa_hash'] = $requestData['nn_sepa_hash'];
            $serverRequestData['data']['sepa_unique_id'] = $requestData['unique_id'];
        } 
        $this->getLogger(__METHOD__)->error('serverRequestData', $serverRequestData);
        //$response = $this->paymentHelper->executeCurl($serverRequestData['data'], $serverRequestData['url']);
        $responseData = $this->paymentHelper->convertStringToArray($response['response'], '&');
        
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
