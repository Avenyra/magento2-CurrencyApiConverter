# Avenyra Currency Converter Module

A Magento 2 module that integrates with [currencyapi.com](https://currencyapi.com/) to fetch and import real-time currency exchange rates into your Magento store.

## Features

- **Real-time Currency Rates**: Fetch live exchange rates from currencyapi.com API
- **Batch Processing**: Efficiently processes multiple currency conversions in a single API call
- **Magento Integration**: Seamlessly integrates with Magento's native currency import system

## System Requirements

- **PHP**: 8.1+
- **Magento**: 2.4.5+

## Installation

### Via Composer (Recommended)

```bash
composer require avenyra/module-currency-api
php bin/magento module:enable Avenyra_CurrencyApi
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:flush
```

## Configuration

### Step 1: Obtain API Key

1. Visit [currencyapi.com](https://currencyapi.com/)
2. Sign up for a free account or choose a paid plan
3. Generate your API key from the dashboard

### Step 2: Configure in Magento Admin

1. Navigate to **Stores > Configuration > General > Currency Setup**
2. Locate the **CurrencyApi** section
3. Enter your API key in the **API Key** field (encrypted automatically)
4. Set the **Connection Timeout in Seconds** (recommended: 30 seconds)
5. Click **Save Config**

### Step 3: Import Currency Rates

1. Go to **Stores > Currency > Currency Rates**
2. Select **Currency API (currencyapi.com)** from the **Import Service** dropdown
3. Click **Import** to fetch the latest rates

## API Pricing & Disclaimer

⚠️ **Important**: While this module is **free to use**, the currencyapi.com API service has its own pricing structure:

- **Free Plan**: Limited API calls per month (suitable for small stores)
- **Paid Plans**: Higher call limits and additional features available

Please review the currencyapi.com [pricing page](https://currencyapi.com/pricing) to choose the plan that best fits your store's needs.

## Troubleshooting

### "No API Key was specified or an invalid API Key was specified"

- Verify your API key is correctly entered in the admin configuration
- Ensure the API key is valid and active on currencyapi.com

### "We can't retrieve a rate from currencyapi.com for [CURRENCY]"

- Check if the currency code is supported by currencyapi.com
- Verify your API plan supports the requested currency pair

### Connection Timeout Issues

- Increase the **Connection Timeout in Seconds** value in configuration
- Check your server's internet connectivity
- Verify currencyapi.com service status

## Support

For issues or questions:

- Check the Magento logs: `var/log/system.log`
- Visit currencyapi.com [documentation](https://currencyapi.com/docs)
- Found a bug or issue? Please <a href="https://github.com/Avenyra/magento2-CurrencyApiConverter/issues">open an issue</a> on GitHub.

## Author

**Avenyra Solutions**
