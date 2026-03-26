---
outline: deep
---

# Customising Passes

The plugin builds sensible defaults (membership card with user name and Member ID as QR code), then fires events so your custom module can modify the pass before it's signed and sent. These follow Craft's standard [event patterns](https://craftcms.com/docs/5.x/extend/events.html).

Register event handlers in your module or plugin's `init()` method:

```php
use craft\base\Event;
use newism\wallet\services\ApplePassService;
use newism\wallet\services\GooglePassService;
```

A complete working example is included in [`demo/modules/wallet-demo/`](https://github.com/newism/craft-wallet/tree/main/demo/modules/wallet-demo): a bare-bones Craft module that applies a black and gold theme to both Apple and Google passes.

## `EVENT_BUILD_APPLE_PASS`

Fires when an Apple Wallet pass is being generated. Modify the `StoreCard` to change colors, barcode, fields, and images.

**Event class:** `newism\wallet\events\BuildApplePassEvent`

| Property | Type | Description |
|---|---|---|
| `applePass` | `\Passbook\Pass` | The Apple pass object, pre-populated with defaults |
| `pass` | `newism\wallet\models\Pass` | The unified Pass model (generatorHandle, sourceId, etc.) |
| `user` | `craft\elements\User` | The user the pass is being generated for |

**Forced after your listener:** `passTypeIdentifier`, `webServiceURL`, `authenticationToken`

### Example: Black and gold theme

```php
use newism\wallet\events\BuildApplePassEvent;
use newism\wallet\services\ApplePassService;
use yii\base\Event;

Event::on(
    ApplePassService::class,
    ApplePassService::EVENT_BUILD_APPLE_PASS,
    function(BuildApplePassEvent $event) {
        $applePass = $event->applePass;
        $user = $event->user;
        $pass = $event->pass;

        // Change colors
        $applePass->setBackgroundColor('rgb(0, 0, 0)');
        $applePass->setForegroundColor('rgb(255, 255, 255)');
        $applePass->setLabelColor('rgb(200, 200, 200)');

        // Replace the structure with custom fields
        $structure = new \Passbook\Pass\Structure();

        $memberIdField = new \Passbook\Pass\Field('memberId', (string)$user->id);
        $memberIdField->setLabel('Member ID');
        $structure->addHeaderField($memberIdField);

        $nameField = new \Passbook\Pass\Field('name', $user->fullName);
        $nameField->setLabel('Name');
        $structure->addPrimaryField($nameField);

        $applePass->setStructure($structure);
    }
);
```

## `EVENT_BUILD_GOOGLE_PASS_OBJECT`

Fires when a Google Wallet pass object is being generated. Modify the `payload` array to change the pass content.

**Event class:** `newism\wallet\events\BuildGooglePassObjectEvent`

| Property | Type | Description |
|---|---|---|
| `payload` | `array` | The Google pass object payload, pre-populated with defaults |
| `pass` | `newism\wallet\models\Pass` | The unified Pass model (generatorHandle, sourceId, etc.) |
| `user` | `craft\elements\User` | The user the pass is being generated for |

**Forced after your listener:** `id`, `classId`, `state`

### Example: Change background color and card title

```php
use newism\wallet\events\BuildGooglePassObjectEvent;
use newism\wallet\services\GooglePassService;
use yii\base\Event;

Event::on(
    GooglePassService::class,
    GooglePassService::EVENT_BUILD_GOOGLE_PASS_OBJECT,
    function(BuildGooglePassObjectEvent $event) {
        $event->payload['hexBackgroundColor'] = '#000000';
        $event->payload['cardTitle']['defaultValue']['value'] = 'My Organisation';
    }
);
```

## `EVENT_BUILD_GOOGLE_PASS_CLASS`

Fires when a Google Wallet pass class (template) is being created or updated. Modify the `payload` array to control which fields are displayed on the card layout.

**Event class:** `newism\wallet\events\BuildGooglePassClassEvent`

| Property | Type | Description |
|---|---|---|
| `payload` | `array` | The Google pass class payload, pre-populated with defaults |
| `classId` | `string` | The full class ID (`issuerId.classSuffix`) |
| `generator` | `?GeneratorInterface` | The generator that owns this class, or `null` in legacy context |

**Forced after your listener:** `id`, `callbackOptions`

::: warning
After changing the pass class template, re-run `php craft wallet/setup/google-class` to push the updated class to Google.
:::

### Example: Custom card layout

```php
use newism\wallet\events\BuildGooglePassClassEvent;
use newism\wallet\services\GooglePassService;
use yii\base\Event;

Event::on(
    GooglePassService::class,
    GooglePassService::EVENT_BUILD_GOOGLE_PASS_CLASS,
    function(BuildGooglePassClassEvent $event) {
        $event->payload['classTemplateInfo'] = [
            'cardTemplateOverride' => [
                'cardRowTemplateInfos' => [
                    [
                        'oneItem' => [
                            'item' => [
                                'firstValue' => [
                                    'fields' => [
                                        ['fieldPath' => "object.textModulesData['memberId']"],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
);
```