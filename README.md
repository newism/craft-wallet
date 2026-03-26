<p align="center"><img src="src/icon.svg" width="100" height="100" alt="Wallet Passes icon"></p>
<h1 align="center">Wallet Passes for Craft CMS</h1>

Digital membership and loyalty cards for Apple Wallet and Google Wallet, built into Craft CMS. Bring your own members, generate your own passes, own the data end to end.

![apple-pass.png](docs/screenshots/apple-pass.png)

![google-pass.png](docs/screenshots/google-pass.png)

## Features

- **Apple Wallet + Google Wallet** — works on every iPhone and Android phone
- **Own Your Data** — member records stay in your Craft CMS database; you hold the Apple and Google credentials directly
- **Customisable Passes** — brand colours, logos, strip imagery, and field content are yours to tune via config and events
- **Extensible Generators** — ship beyond the built-in membership card: event tickets, vouchers, bookings, or any custom pass type driven by your own Craft data
- **Automatic Updates** — pass updates push to the member's phone when their profile changes, via APNs and the Google Wallet API
- **QR Code Scanning** — every pass carries the member's ID as a QR code for any standard scanner
- **GraphQL Ready** — query `walletPasses` on the `User` type through the Craft GraphQL API

## Requirements

- Craft CMS 5.6+
- PHP 8.2+

## Installation

```bash
composer require newism/craft-wallet
php craft plugin/install wallet
```

## Documentation

Full documentation is available at https://plugins.newism.com.au/wallet.

## Support

For support, please [open an issue on GitHub](https://github.com/newism/craft-wallet/issues).
