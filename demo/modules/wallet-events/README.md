# Wallet Events Demo Module

A reference implementation showing how to create a custom wallet pass generator for [Verbb Events](https://verbb.io/craft-plugins/events) tickets.

## Purpose

This module demonstrates the Wallet Passes generator system. Use it as a reference when building custom generators for your own integrations.

## Features

- Creates Apple/Google Wallet passes from Verbb Events PurchasedTicket elements
- Pass includes: event name, date/time, venue
- QR code contains the Verbb Events check-in URL

## Requirements

- Craft CMS 5.x
- Wallet Passes plugin
- Verbb Events 3.x

## Installation

### 1. Enable the module

Add to `config/app.php`:

```php
use modules\walletevents\WalletEvents;

return [
    'modules' => [
        'wallet-events' => WalletEvents::class,
    ],
    'bootstrap' => ['wallet-events'],
];
```

### 2. Create Google Wallet class

```bash
php craft wallet/setup/google-class
```

### 3. Add "Add to Wallet" buttons

In your event ticket templates:

```twig
{# Check if user has a pass for this ticket #}
{% set hasPass = craft.wallet.passes.userId(purchasedTicket.getUser().id).generatorHandle('event-ticket').sourceId(purchasedTicket.id).exists() %}

{% if hasPass %}
    <span>Pass added ✓</span>
{% else %}
    <a href="{{ craft.wallet.appleWalletUrl('event-ticket', purchasedTicket.id) }}">
        Add to Apple Wallet
    </a>
    <a href="{{ craft.wallet.googleWalletUrl('event-ticket', purchasedTicket.id) }}">
        Add to Google Wallet
    </a>
{% endif %}
```

## How It Works

### Generator Registration

The module listens to `Wallet::EVENT_REGISTER_GENERATORS` and adds `EventTicketGenerator`:

```php
Event::on(
    Wallet::class,
    Wallet::EVENT_REGISTER_GENERATORS,
    function (RegisterGeneratorsEvent $event) {
        if (!Craft::$app->plugins->isPluginInstalled('events')) {
            return;
        }
        $event->generators[EventTicketGenerator::handle()] = EventTicketGenerator::class;
    }
);
```

### Generator Interface

Each generator implements `GeneratorInterface`:

| Method | Purpose |
|--------|---------|
| `handle()` | Unique identifier (e.g., `'event-ticket'`) |
| `displayName()` | Human-readable name (e.g., `'Event Ticket'`) |
| `buildPassesData()` | Build pass content from user + sourceId |
| `getApplePassType()` | Apple pass layout (`'eventTicket'`) |
| `getGoogleClassSuffix()` | Google Wallet class suffix |
| `buildGooglePassClassPayload()` | Google class template |

### Pass Data

`buildPassesData()` returns an array of pass data arrays:

```php
return [[
    'id' => $sourceId,
    'fullName' => $user->fullName ?: $user->username,
    'eventName' => $event?->title ?? 'Event',
    'sessionDate' => '2024-03-15',
    'sessionTime' => '19:00',
    'venue' => 'Conference Room A',
    'checkInUrl' => 'https://example.com/events/tickets/check-in?uid=...',
]];
```

The `id` field becomes the QR code content. Other fields are available for customisation via events.

## Adapting for Other Integrations

### 1. Create Your Generator Class

```php
class MyGenerator implements GeneratorInterface
{
    public static function handle(): string
    {
        return 'my-integration';
    }

    // ... implement other methods
}
```

### 2. Register It

```php
Event::on(
    Wallet::class,
    Wallet::EVENT_REGISTER_GENERATORS,
    function (RegisterGeneratorsEvent $event) {
        $event->generators['my-integration'] = MyGenerator::class;
    }
);
```

### 3. Build Pass Data

```php
public function buildPassesData(User $user, ?int $sourceId = null): array
{
    // Load your source entity (Order, Ticket, etc.)
    $source = $this->loadSource($sourceId);
    
    // Return array of pass data arrays
    return [[
        'id' => $source->id,
        'fullName' => $user->fullName ?: $user->username,
        // ... your custom fields
    ]];
}
```

### 4. Apple Pass Type

Choose the right pass type for your use case:

| Type | Use Case |
|------|----------|
| `storeCard` | Membership, loyalty cards |
| `coupon` | Vouchers, promotions |
| `eventTicket` | Event admissions |
| `boardingPass` | Travel passes |
| `generic` | Generic passes |

### 5. Customise via Events

After generating pass data, customise using wallet events:

```php
Event::on(
    ApplePassService::class,
    ApplePassService::EVENT_BUILD_APPLE_PASS,
    function (BuildApplePassEvent $event) {
        // Customise the StoreCard
        $event->storeCard->setBackgroundColor('rgb(255, 0, 0)');
    }
);
```

## File Structure

```
wallet-events/
├── WalletEvents.php    # Module + Generator class
└── README.md           # This file
```

## API Reference

### GeneratorInterface

See: `newism/wallet/src/generators/GeneratorInterface.php`

### Wallet Events

See: `newism/wallet/src/services/ApplePassService.php` for `EVENT_BUILD_APPLE_PASS`

### Twig Variable

See: `newism/wallet/src/web/twig/WalletVariable.php`

## Troubleshooting

### Generator not appearing

- Verify the module is enabled in `config/app.php`
- Check Craft logs for errors
- Run `php craft wallet/setup/google-class` to create the Google class

### Pass not generating

- Check that `sourceId` is being passed correctly
- Verify the PurchasedTicket element exists
- Check Craft logs for generation errors
