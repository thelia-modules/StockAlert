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

## Restocking alert (3.2.0)

A visitor asks to be told when a sale element that is out of stock is back. `StockAlertEvents::STOCK_ALERT_SUBSCRIBE`
is dispatched with a `StockAlertEvent`; the module's own listener (priority 128) records the address in
`restocking_alert` and the product being back is detected on the update of a sale element
(`TheliaEvents::PRODUCT_UPDATE_PRODUCT_SALE_ELEMENT`).

### Settings

On the module configuration page, next to the stock thresholds:

| Setting | Default | |
|---|---|---|
| `stockalert_newsletter` | off | Shows a newsletter checkbox on the form. A visitor who ticks it is subscribed in the language of the storefront. Without it the form has no such field. |
| `stockalert_confirmation` | off | Sends the visitor an acknowledgement (`stockalert_subscribed`) when the address is recorded. |

Activating the module again, or updating it, writes a default only for a setting the shop has no value for:
a value set before the activation, or chosen by the merchant, is kept.

### What the visitor reads

A listener of the subscription event may refuse it with a `StockAlert\Exception\SubscriptionRefusedException`
(the size is in stock, the address is already registered, too many requests...). Its message is shown as it is
and must be written for the visitor, in their language. Any other failure is logged and shown as a generic
message, never the text of the exception.

A theme that closes its own window once the subscription is made calls the hook with
`showSuccessMessage: false`: the component then renders nothing and dispatches the browser event
`stockalert:subscribed` (`{pseId}`).

### Messages

`stockalert_customer` (the product is back), `stockalert_subscribed` (acknowledgement) and
`stockalert_administrator` (products running low), each a Twig template pair in `templates/email/default/`
(`<name>.html.twig`, `<name>.txt.twig`) translated in `I18n/email/default/` (en_US, fr_FR, de_DE). The
customer messages receive `locale`, `product_id`, `pse_id`, `product_title`, `product_url` and `combination`;
`StockAlert\Service\RestockingMailer` builds and sends them. Code that sends the messages itself (a shop that
keeps its own list of subscribers) gives the same variables.
