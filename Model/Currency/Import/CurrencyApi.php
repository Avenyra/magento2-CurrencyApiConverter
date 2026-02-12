<?php
/**
 * Copyright © Avenyra. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Avenyra\CurrencyApi\Model\Currency\Import;

use Exception;
use Laminas\Http\Request;
use Magento\Directory\Model\Currency\Import\AbstractImport;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\App\Config\ScopeConfigInterface as ScopeConfig;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\ZendClient;
use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Store\Model\ScopeInterface;

/**
 * Currency API converter (https://currencyapi.com/).
 */
class CurrencyApi extends AbstractImport
{
    public const CURRENCY_CONVERTER_URL = 'https://api.currencyapi.com/v3/latest?apikey={{ACCESS_KEY}}&currencies={{CURRENCY_RATES}}&base_currency={{BASE_CURRENCY}}';
    private const CONFIG_PATH_API_KEY = 'currency/currencyapi/api_key';
    private const CONFIG_PATH_TIMEOUT = 'currency/currencyapi/timeout';

    /**
     * @var ZendClientFactory
     */
    private ZendClientFactory $httpClientFactory;

    /**
     * Core scope config
     *
     * @var ScopeConfig
     */
    private ScopeConfig $scopeConfig;

    /**
     * @var string
     */
    private string $currencyConverterServiceHost = '';

    /**
     * @var string
     */
    private string $serviceUrl = '';

    /**
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * @param CurrencyFactory $currencyFactory
     * @param ScopeConfig $scopeConfig
     * @param ZendClientFactory $httpClientFactory
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        CurrencyFactory $currencyFactory,
        ScopeConfig $scopeConfig,
        ZendClientFactory $httpClientFactory,
        EncryptorInterface $encryptor
    ) {
        parent::__construct($currencyFactory);
        $this->scopeConfig = $scopeConfig;
        $this->httpClientFactory = $httpClientFactory;
        $this->encryptor = $encryptor;
    }

    /**
     * @inheritdoc
     */
    public function fetchRates(): array
    {
        $data = [];
        $currencies = $this->_getCurrencyCodes();
        $defaultCurrencies = $this->_getDefaultCurrencyCodes();

        foreach ($defaultCurrencies as $currencyFrom) {
            if (!isset($data[$currencyFrom])) {
                $data[$currencyFrom] = [];
            }
            $data = $this->convertBatch($data, $currencyFrom, $currencies);
            ksort($data[$currencyFrom]);
        }
        return $data;
    }

    /**
     * Return currencies convert rates in batch mode
     *
     * @param array $data
     * @param string $currencyFrom
     * @param array $currenciesTo
     * @return array
     */
    private function convertBatch(array $data, string $currencyFrom, array $currenciesTo): array
    {
        $url = $this->getServiceURL($currencyFrom, $currenciesTo);
        if (empty($url)) {
            $data[$currencyFrom] = $this->makeEmptyResponse($currenciesTo);
            return $data;
        }
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        set_time_limit(0);
        try {
            $response = $this->getServiceResponse($url);
        } finally {
            ini_restore('max_execution_time');
        }

        if (!$this->validateResponse($response)) {
            $data[$currencyFrom] = $this->makeEmptyResponse($currenciesTo);
            return $data;
        }

        $response = $response['data'];
        // $response = [
        //     'AUD' => ['code' => 'AUD', 'value' => 2.0528424423 ],
        //     'KRW' => ['code' => 'KRW', 'value' => 1324.0528424423 ],
        //     ...
        // ];

        foreach ($currenciesTo as $to) {
            if ($currencyFrom === $to) {
                $data[$currencyFrom][$to] = $this->_numberFormat(1);
            } else {
                if (!isset($response[$to])) {
                    $serviceHost =  $this->getServiceHost($url);
                    $this->_messages[] = __('We can\'t retrieve a rate from %1 for %2.', $serviceHost, $to);
                    $data[$currencyFrom][$to] = null;
                } else {
                    $data[$currencyFrom][$to] = $this->_numberFormat(
                        (double)$response[$to]['value']
                    );
                }
            }
        }

        return $data;
    }

    /**
     * Get currency converter service host.
     *
     * @param string $url
     * @return string
     */
    private function getServiceHost(string $url): string
    {
        if (!$this->currencyConverterServiceHost) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            $this->currencyConverterServiceHost = parse_url($url, PHP_URL_SCHEME) . '://'
                // phpcs:ignore Magento2.Functions.DiscouragedFunction
                . parse_url($url, PHP_URL_HOST);
        }
        return $this->currencyConverterServiceHost;
    }

    /**
     * Return service URL.
     *
     * @param string $currencyFrom
     * @param array $currenciesTo
     * @return string
     */
    private function getServiceURL(string $currencyFrom, array $currenciesTo): string
    {
        if (!$this->serviceUrl) {
            // Get access key
            $accessKey = $this->scopeConfig->getValue(self::CONFIG_PATH_API_KEY, ScopeInterface::SCOPE_STORE);
            $accessKey = $this->encryptor->decrypt($accessKey);
            if (empty($accessKey)) {
                $this->_messages[] = __('No API Key was specified or an invalid API Key was specified.');
                return '';
            }
            // Get currency rates request
            //$currenciesTo = array_diff($currenciesTo, [$currencyFrom]);
            $currencyRates = implode('%2C', $currenciesTo);
            $this->serviceUrl = str_replace(
                ['{{ACCESS_KEY}}', '{{BASE_CURRENCY}}', '{{CURRENCY_RATES}}'],
                [$accessKey, $currencyFrom, $currencyRates],
                self::CURRENCY_CONVERTER_URL
            );
        }
        return $this->serviceUrl;
    }

    /**
     * Get currencyapi.com service response
     *
     * @param string $url
     * @param int $retry
     * @return array
     */
    private function getServiceResponse(string $url, int $retry = 0): array
    {
        $httpClient = $this->httpClientFactory->create();
        $response = [];

        try {
            $httpClient->setUri($url);
            $httpClient->setConfig(
                [
                    'timeout' => $this->scopeConfig->getValue(
                        self::CONFIG_PATH_TIMEOUT,
                        ScopeInterface::SCOPE_STORE
                    ),
                ]
            );
            $httpClient->setMethod(Request::METHOD_GET);
            $jsonResponse = $httpClient->request()->getBody();

            $response = json_decode($jsonResponse, true) ?: [];
        } catch (Exception $e) {
            if ($retry == 0) {
                $response = $this->getServiceResponse($url, 1);
            }
        }
        return $response;
    }

    /**
     * Validate rates response.
     *
     * @param array $response
     * @return bool
     */
    private function validateResponse(array $response): bool
    {
        if (!isset($response['errors'])) {
            return true;
        }

        foreach ($response['errors'] as $value) {
            $this->_messages[] = $value[0];
        }
        return false;
    }

    /**
     * Make empty rates for provided currencies.
     *
     * @param array $currenciesTo
     * @return array
     */
    private function makeEmptyResponse(array $currenciesTo): array
    {
        return array_fill_keys($currenciesTo, null);
    }

    /**
     * @inheritdoc
     */
    protected function _convert($currencyFrom, $currencyTo): float|int
    {
        return 1;
    }
}

