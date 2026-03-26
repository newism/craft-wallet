---
outline: deep
---

# Generators

By default, Wallet Passes creates membership cards -- one per user, with their name and Member ID. Generators let you go beyond that. A generator is a PHP class that defines a new type of wallet pass: event tickets, gift vouchers, booking confirmations, loyalty rewards -- anything you can represent as an Apple or Google Wallet pass.

Each generator is responsible for:

- Deciding whether a user is eligible for a pass
- Building the Apple Wallet pass (colors, fields, barcode, images)
- Building the Google Wallet pass object and class payloads
- Providing the CP template for managing passes in the user edit screen
- Loading source objects (e.g. the order, event, or booking that the pass represents)

The plugin ships with a `MembershipGenerator` that handles the default membership card behaviour. Your custom generators are registered alongside it and appear in the Control Panel automatically.

## GeneratorInterface

All generators implement `newism\wallet\generators\GeneratorInterface`. The interface is always-plural: generators return arrays of pass data, even for single-pass cases like membership cards. This avoids special-casing single vs multiple throughout the codebase.

| Method | Return Type | Description |
|---|---|---|
| `handle()` | `string` | Static. Unique handle stored in the `generator` column on pass records (e.g. `'membership'`, `'event-ticket'`). |
| `displayName()` | `string` | Static. Human-readable label shown in the CP (e.g. `'Membership Cards'`, `'Event Tickets'`). |
| `userCanCreatePass(User $user)` | `bool` | Whether the given user is eligible to create a pass with this generator. Used to control "Add to Wallet" button visibility. |
| `createApplePass(Pass $pass)` | `\Passbook\Pass` | Build and return a fully populated Apple pass object (`StoreCard`, `EventTicket`, `Coupon`, `BoardingPass`, or `Generic`). Use `$pass->uid` as the serial number. The plugin force-sets `passTypeIdentifier`, `webServiceURL`, and `authenticationToken` after this returns. |
| `getGooglePassType()` | `string` | The Google Wallet API resource type: `'generic'` for generic passes, `'eventTicket'` for event ticket passes. Determines which API endpoints are used. |
| `getGoogleClassSuffix()` | `string` | Suffix for the Google Wallet class ID. Each generator type gets its own class (e.g. `issuerId.membership`, `issuerId.event-ticket`). |
| `createGooglePassObject(Pass $pass)` | `array` | Build the Google Wallet pass object payload array. The plugin force-sets `id`, `classId`, and `state` after this returns. |
| `buildGooglePassClassPayload(string $classId)` | `array` | Build the Google Wallet class (template) payload. Called by `php craft wallet/setup/google-class` to register each generator's class with Google. The plugin force-sets `id` and `callbackOptions` after this returns. |
| `getUserSettingsContentTemplate(User $user, array $passes)` | `array` | Return `[templatePath, variables]` for the generator's section in the CP user edit "Wallet Passes" tab. The generator owns the entire section: eligible items, existing passes, and action buttons. |
| `loadSources(array $sourceIds)` | `array` | Batch-load source objects for the given IDs. Called by `PassQuery` when `with(['source'])` is used. Return a `[sourceId => object]` map. |

## Registering Generators

Register generators in your module or plugin's `init()` method by listening to `Wallet::EVENT_REGISTER_GENERATORS`. Add generator **instances** to the event's `$generators` array, keyed by handle:

```php
use craft\base\Event;
use newism\wallet\Wallet;
use newism\wallet\events\RegisterGeneratorsEvent;

Event::on(
    Wallet::class,
    Wallet::EVENT_REGISTER_GENERATORS,
    function(RegisterGeneratorsEvent $event) {
        $event->generators['voucher'] = new VoucherGenerator();
    }
);
```

The `$generators` array is keyed by handle. The built-in `MembershipGenerator` is always prepended by the plugin -- your generators appear after it.

## Key Concepts

### sourceId and sourceIndex

Every pass record has optional `sourceId` and `sourceIndex` columns. These link a pass back to the thing it represents -- an order, an event, a booking, or whatever your generator works with.

For the built-in Membership generator, `sourceId` is `null` because the source is the user themselves (already available via `$pass->getUser()`). For a generator that creates passes from Commerce orders, `sourceId` would be the order ID.

`sourceIndex` supports generators that produce multiple passes from a single source. For example, an event ticket generator might create one pass per ticket on an order -- `sourceId` is the order ID, and `sourceIndex` distinguishes each ticket.

### loadSources

When querying passes with `PassQuery`, you can eager-load source objects to avoid N+1 queries:

```php
$passes = (new PassQuery())
    ->userId($user->id)
    ->generatorHandle('event-ticket')
    ->with(['source'])
    ->all();

// Each pass now has its source pre-loaded
foreach ($passes as $pass) {
    $event = $pass->source; // The Event element
}
```

The plugin calls your generator's `loadSources()` with the collected source IDs and expects a `[sourceId => object]` map back. The source can be any type -- Craft elements, Yii models, plain objects.

```php
public function loadSources(array $sourceIds): array
{
    return Event::find()
        ->id($sourceIds)
        ->indexBy('id')
        ->all();
}
```

### getUserSettingsContentTemplate

Each generator provides its own section in the "Wallet Passes" tab on the CP user edit screen. The method returns a template path and variables array that are spread directly into the content template renderer.

The generator receives the user being edited and all existing passes for that user and generator (pre-queried by the plugin). It owns the full section: listing eligible items, showing existing passes, and rendering "Add to Wallet" buttons.

```php
public function getUserSettingsContentTemplate(User $user, array $passes): array
{
    $eligibleItems = $this->getEligibleItems($user);

    $passIndex = [];
    foreach ($passes as $pass) {
        $passIndex[$pass->sourceId] = $pass;
    }

    return ['my-module/wallet/event-tickets', [
        'user' => $user,
        'eligibleItems' => $eligibleItems,
        'passIndex' => $passIndex,
        'generatorHandle' => self::handle(),
    ]];
}
```

## Scaffolding a Generator

Use the built-in scaffold command to generate a starter class with all required methods:

```bash
php craft make wallet-pass-generator
```

## Updating Google Wallet Classes

After adding or modifying a generator, re-run the Google class setup command to push the updated class definitions to Google:

```bash
php craft wallet/setup/google-class
```

This iterates all registered generators and creates or updates a Google Wallet class for each one.

