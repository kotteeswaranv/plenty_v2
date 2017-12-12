<?php

namespace Novalnet\Migrations;

use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Novalnet\Helper\PaymentHelper;

/**
 * Migration to create payment mehtods
 *
 * Class CreatePaymentMethod
 *
 * @package Novalnet\Migrations
 */
class CreatePaymentMethod
{
    /**
     * @var PaymentMethodRepositoryContract
     */
    private $paymentMethodRepository;

    /**
     * @var PaymentHelper
     */
    private $paymentHelper;

    /**
     * CreatePaymentMethod constructor.
     *
     * @param PaymentMethodRepositoryContract $paymentMethodRepository
     * @param PaymentHelper $paymentHelper
     */
    public function __construct(PaymentMethodRepositoryContract $paymentMethodRepository,
                                PaymentHelper $paymentHelper)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->paymentHelper = $paymentHelper;
    }

    /**
     * Run on plugin build
     *
     * Create Method of Payment ID for Novalnet payment if they don't exist
     */
    public function run()
    {
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET', 'Novalnet');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_INVOICE', 'Invoice');
    }
    
    
    /**
	 * Create payment method with given parameters if it doesn't exist
	 *
	 * @param string $paymentKey
	 * @param string $name
	 */
    private function createNovalnetPaymentMethodByPaymentKey($paymentKey, $name)
	{        
        if ($this->paymentHelper->getPaymentMethod($paymentKey) == 'no_paymentmethod_found')
        {
            $paymentMethodData = ['pluginKey'  => 'plenty_novalnet',
                                  'paymentKey' => $paymentKey,
                                  'name'       => $name];
            $this->paymentMethodRepository->createPaymentMethod($paymentMethodData);
        }        
    }
}
