# Release Notes for Wallet Passes

## 1.0.0

### Added

- Apple Wallet pass generation (.pkpass) via `eo/passbook` with P12 certificate signing
- Google Wallet pass generation via REST API with JWT-based "Add to Wallet" URLs
- Unified Pass model — single `wallet_passes` table for both platforms
- Automatic pass updates when user profiles change (queued via `UpdateUserPassesJob`)
- Apple Wallet webhook protocol — device registration, pass updates via APNs push, `If-Modified-Since` support
- Google Wallet webhook protocol — ECv2SigningOnly signature verification, lifecycle event callbacks
- QR code on every pass with the user's ID for membership scanning
- CP "Wallet Passes" screen on user edit pages with pass cards, action menus, and device management
- User permissions — view/create/delete own passes, view/create/delete other users' passes
- `WalletBehavior` on User elements — `hasWalletPasses`, `getPass()`, `getPasses()`, `hasPass()` available in templates and plugins
- `craft.wallet` Twig variable with `PassQuery` builder and convenience methods
- GraphQL — `walletPasses` and `hasWalletPasses` fields on the `User` type, gated by the `wallet.passes:read` schema permission
- Three customisation events: `EVENT_BUILD_APPLE_PASS`, `EVENT_BUILD_GOOGLE_PASS_OBJECT`, `EVENT_BUILD_GOOGLE_PASS_CLASS`
- ExtendablePass classes for Apple semantic tags (EventTicket, StoreCard, Coupon) without forking `eo/passbook`
- Environment variable configuration with `WALLET_APPLE_*` and `WALLET_GOOGLE_*` prefixes
- Craft Cloud / serverless support via base64-encoded credentials (`WALLET_APPLE_P12_BASE64`, `WALLET_GOOGLE_SERVICE_ACCOUNT_JSON_BASE64`)
- Console command `wallet/setup/google-class` to create or update Google Wallet pass classes
- Console command `wallet/setup/env-base64` to encode credentials and write to `.env`
- Three Monolog log targets: `wallet`, `wallet-apple-webhook`, `wallet-google-webhook` (14-day rotation)
- Config file support at `config/wallet/wallet.php` with starter files copied on install
- Generator system — `GeneratorInterface` for creating custom pass types (event tickets, commerce orders, loyalty cards, etc.) — available in every installation
- `RegisterGeneratorsEvent` for third-party plugins and modules to register generators
- Built-in `MembershipGenerator` for single-pass-per-user membership cards
- `WalletPasses` custom field type for marking products/entries as wallet-eligible
- `PassQuery` with eager loading — `with(['source', 'user'])` delegates to generators for batch source loading
- Generators settings page in the CP listing every registered generator
- `craft make wallet-pass-generator` scaffold for bootstrapping new generators
- VitePress documentation site
