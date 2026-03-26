---
outline: deep
---

# Events

Wallet Passes fires events at key points during pass generation and registration, letting you customise pass content or register entirely new pass types. These follow Craft's standard [event patterns](https://craftcms.com/docs/5.x/extend/events.html).

Register event handlers in your module or plugin's `init()` method:

```php
use craft\base\Event;
use newism\wallet\Wallet;
use newism\wallet\services\ApplePassService;
use newism\wallet\services\GooglePassService;
```

## `EVENT_REGISTER_GENERATORS`

Register custom pass generators to create new pass types beyond the built-in membership card.

**Event class:** `newism\wallet\events\RegisterGeneratorsEvent`

| Property | Type | Description |
|---|---|---|
| `generators` | `array<string, GeneratorInterface>` | Generator instances keyed by handle |

### Example: Register a custom event ticket generator

```php
use newism\wallet\events\RegisterGeneratorsEvent;

Event::on(
    Wallet::class,
    Wallet::EVENT_REGISTER_GENERATORS,
    function(RegisterGeneratorsEvent $event) {
        $event->generators['event-ticket'] = new \modules\wallet\generators\EventTicketGenerator();
    }
);
```

### Example: Register multiple generators

```php
use newism\wallet\events\RegisterGeneratorsEvent;

Event::on(
    Wallet::class,
    Wallet::EVENT_REGISTER_GENERATORS,
    function(RegisterGeneratorsEvent $event) {
        $event->generators['vip-pass'] = new \modules\wallet\generators\VipPassGenerator();
        $event->generators['gift-card'] = new \modules\wallet\generators\GiftCardGenerator();
    }
);
```

## `EVENT_BUILD_APPLE_PASS`

Fires after the generator creates and populates the Apple Wallet pass, but before the service force-sets `passTypeIdentifier`, `webServiceURL`, and `authenticationToken`. Use this to customise the pass -- swap images, change colors, add fields, etc.

**Event class:** `newism\wallet\events\BuildApplePassEvent`

| Property | Type | Description |
|---|---|---|
| `applePass` | `\Passbook\Pass` | The Apple pass object to customise (StoreCard, EventTicket, Coupon, etc.) |
| `pass` | `newism\wallet\models\Pass` | The wallet Pass model -- provides `generatorHandle`, `sourceId`, `getSource()`, `getUser()`, etc. |
| `user` | `craft\elements\User` | The Craft user the pass is for |

### Example: Add a custom back field

```php
use newism\wallet\events\BuildApplePassEvent;
use Passbook\Pass\Field;
use Passbook\Pass\Structure;

Event::on(
    ApplePassService::class,
    ApplePassService::EVENT_BUILD_APPLE_PASS,
    function(BuildApplePassEvent $event) {
        $field = new Field('terms', 'See full terms at example.com/terms');
        $field->setLabel('Terms & Conditions');
        $event->applePass->addBackField($field);
    }
);
```

### Example: Customise pass colors for a specific generator

```php
use newism\wallet\events\BuildApplePassEvent;

Event::on(
    ApplePassService::class,
    ApplePassService::EVENT_BUILD_APPLE_PASS,
    function(BuildApplePassEvent $event) {
        if ($event->pass->generatorHandle !== 'vip-pass') {
            return;
        }

        $event->applePass->setBackgroundColor('rgb(0, 0, 0)');
        $event->applePass->setForegroundColor('rgb(255, 215, 0)');
        $event->applePass->setLabelColor('rgb(200, 200, 200)');
    }
);
```

## `EVENT_BUILD_GOOGLE_PASS_OBJECT`

Fires after the generator builds the Google Wallet pass object payload, but before the service force-sets `id`, `classId`, and `state`. Use this to customise the pass object -- change the card title, header, barcode, images, etc.

**Event class:** `newism\wallet\events\BuildGooglePassObjectEvent`

| Property | Type | Description |
|---|---|---|
| `payload` | `array` | The pass object payload array |
| `pass` | `newism\wallet\models\Pass` | The wallet Pass model -- provides `generatorHandle`, `sourceId`, `getSource()`, `getUser()`, etc. |
| `user` | `craft\elements\User` | The Craft user the pass is for |

### Example: Add a text module to the pass

```php
use newism\wallet\events\BuildGooglePassObjectEvent;

Event::on(
    GooglePassService::class,
    GooglePassService::EVENT_BUILD_GOOGLE_PASS_OBJECT,
    function(BuildGooglePassObjectEvent $event) {
        $event->payload['textModulesData'][] = [
            'header' => 'Rewards Points',
            'body' => '1,250 pts',
            'id' => 'rewards',
        ];
    }
);
```

### Example: Customise the barcode for a specific generator

```php
use newism\wallet\events\BuildGooglePassObjectEvent;

Event::on(
    GooglePassService::class,
    GooglePassService::EVENT_BUILD_GOOGLE_PASS_OBJECT,
    function(BuildGooglePassObjectEvent $event) {
        if ($event->pass->generatorHandle !== 'event-ticket') {
            return;
        }

        $event->payload['barcode'] = [
            'type' => 'QR_CODE',
            'value' => $event->pass->sourceId . '-' . $event->user->id,
            'alternateText' => 'Ticket #' . $event->pass->sourceId,
        ];
    }
);
```

## `EVENT_BUILD_GOOGLE_PASS_CLASS`

Fires after the generator builds the Google Wallet pass class payload, but before the service force-sets `id` and `callbackOptions`. Use this to customise the class template -- card layout, field paths, styling, etc.

After changing the class template, re-run the setup command to push changes to Google:

```bash
php craft wallet/setup/google-class
```

**Event class:** `newism\wallet\events\BuildGooglePassClassEvent`

| Property | Type | Description |
|---|---|---|
| `payload` | `array` | The class payload array |
| `classId` | `string` | The full class ID (`issuerId.classSuffix`) |
| `generator` | `?GeneratorInterface` | The generator that owns this class (null when called from legacy context) |

### Example: Enable smart tap for NFC redemption

```php
use newism\wallet\events\BuildGooglePassClassEvent;

Event::on(
    GooglePassService::class,
    GooglePassService::EVENT_BUILD_GOOGLE_PASS_CLASS,
    function(BuildGooglePassClassEvent $event) {
        $event->payload['enableSmartTap'] = true;
        $event->payload['redemptionIssuers'] = [
            \craft\helpers\App::env('WALLET_GOOGLE_ISSUER_ID'),
        ];
    }
);
```

### Example: Customise the class for a specific generator

```php
use newism\wallet\events\BuildGooglePassClassEvent;

Event::on(
    GooglePassService::class,
    GooglePassService::EVENT_BUILD_GOOGLE_PASS_CLASS,
    function(BuildGooglePassClassEvent $event) {
        if ($event->generator === null || $event->generator::handle() !== 'vip-pass') {
            return;
        }

        $event->payload['hexBackgroundColor'] = '#000000';
        $event->payload['heroImage'] = [
            'sourceUri' => [
                'uri' => 'https://example.com/vip-hero.png',
            ],
        ];
    }
);
```
