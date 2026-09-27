Stock Alert

This module has two different features :

- send email notifications when the quantity in stock is under a certain limit.
- allow customers to subscribe to an unavailable product to be notified when it will be available again.    
  
## Installation
 
### Manually

This module requires Thelia in version 2.1

It must be placed into your modules/ directory (local/modules/).

You can download the .zip file of this module or create a git submodule into your project like this :

cd /path-to-thelia
git submodule add https://github.com/thelia-modules/StockAlert.git local/modules/StockAlert

Next, go to your Thelia admin panel for module activation.

### Composer

Add it in your main thelia composer.json file

```
composer require thelia/stock-alert-module ~2.0
```

## Configuration

You can activate or not the admin notifications on the configuration page of the module. You can also define 
a threshold for product quantity and a list of emails.
 
For the customer, the display is managed by hooks. So if you deactivate the hook `product.details-bottom` and 
`product.javascript-initialization` this feature will be deactivated for customers.    
You can also customize the display in redefining the html templates in your frontOffice template. You have to copy
the files inside `templates/frontOffice/default/` in your template, in directory `modules/StockAlert/`. Files should
have the same names.

You can also customize emails sent by the module. You have to copy files from `templates/email/default/` 
in your email template directory, edit them, and select this template in the **Mailing templates** configuration page.

## Hooks

This module adds a new hook:

```
product.stock-alert
```

You can place it anywhere in your template and use it instead of the ```product.details-bottom``` hook if you don't want to use it.

It calls the same function as the ```product.details-bottom``` hook and renders the ```product-details-bottom.html`` template`

**Important:** don't forget to disable the hook you don't want to use (```product.details-bottom``` for example).

## Price drop alert (3.1.0)

A visitor asks to be told when the price of a product sale element goes down. The price seen at
subscription time is recorded; when the effective price (catalog price rule, promotion or catalog
price) falls below it by at least the configured threshold, an email is queued and the subscription
is consumed. Subscriptions expire after a configurable delay.

Settings live on the module configuration page: enabled (off by default), threshold in percent,
expiration in days, maximum subscriptions per address, emails sent per run.

### Cron

Emails are never sent from the request that changed a price. Schedule the command:

```
*/15 * * * * php bin/console stockalert:price-drop:process
0 3 * * *    php bin/console stockalert:price-drop:process --sweep
```

The first run sends the queued alerts, at most `--limit` (default: the module setting) per run, and
removes the expired subscriptions. The `--sweep` run compares every active subscription with the
current price first: it catches price changes that no event announces, such as writes through the
admin API, imports, currency rate updates, or a catalog price rule whose period just opened.

### Theme hook

The product page calls `theme_hook('product.pse.alerts', {pseId, outOfStock, taxedPrice})` inside
the sale element selector; the module renders the restocking alert when the selected sale element
is out of stock and the price drop alert when the feature is enabled.
