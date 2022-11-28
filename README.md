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
