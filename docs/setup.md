# Setup

## 1. Apple Wallet

Setting up Apple Wallet requires creating a Pass Type Identifier and signing certificate in the Apple Developer Portal, then placing the files in `config/wallet/apple/`.

**[Detailed Apple Wallet setup guide →](./apple-wallet-setup)**

Quick summary:

1. Create a **Pass Type Identifier** in the [Apple Developer Portal](https://developer.apple.com/account/resources/identifiers/list) (e.g. `pass.com.yourcompany.membership`)
2. Create a **Pass Type Certificate** and export it as `certificate.p12`
3. Download the **Apple WWDR G4** intermediate certificate
4. Place `certificate.p12` and `applewwdrca.pem` in `config/wallet/apple/`
5. Replace the placeholder pass images with your own:

| File                         | Size                    | Description                  |
|------------------------------|-------------------------|------------------------------|
| `icon.png` / `icon@2x.png`   | 29x29 / 58x58 px        | Small icon for notifications |
| `logo.png` / `logo@2x.png`   | max 160x50 / 320x100 px | Logo on the pass header      |
| `strip.png` / `strip@2x.png` | 375x123 / 750x246 px    | Header strip background      |

6. Add the environment variables to your `.env`:

```env
WALLET_APPLE_P12_PASSWORD="your-p12-password"
WALLET_APPLE_PASS_TYPE_ID="pass.com.yourcompany.membership"
WALLET_APPLE_TEAM_ID="XXXXXXXXXX"
WALLET_APPLE_ORG_NAME="Your Organization"
```

## 2. Google Wallet

Setting up Google Wallet requires a Google Cloud project, an Issuer Account, and a service account key file placed in `config/wallet/google/`.

**[Detailed Google Wallet setup guide →](./google-wallet-setup)**

Quick summary:

1. Create a [Google Cloud project](https://console.cloud.google.com/) and enable the **Google Wallet API**
2. Register an Issuer Account in the [Google Pay & Wallet Console](https://pay.google.com/business/console/)
3. Create a **Service Account** with the **Wallet Object Issuer** role
4. Download the service account JSON key and save it as `config/wallet/google/service-account.json`
5. Link the service account to your Issuer Account
6. Optionally replace the placeholder images:

| File       | Size        | Description |
|------------|-------------|-------------|
| `logo.png` | 660x660 px  | Square logo |
| `hero.png` | 1032x336 px | Hero banner |

7. Add the environment variables to your `.env`:

```env
WALLET_GOOGLE_ISSUER_ID="your-issuer-id"
WALLET_GOOGLE_CLASS_SUFFIX="membership"
WALLET_GOOGLE_ORG_NAME="Your Organization"
```

8. Create the pass class in Google Wallet:

```bash
php craft wallet/setup/google-class
```

## 3. Verify

Once both platforms are configured, visit **Users &rarr; (any user) &rarr; Wallet Passes** in the control panel to add an Apple pass or Google pass.

## Security Warning

**Never commit credential files or base64-encoded secrets to your repository.**

- `certificate.p12` and `service-account.json` should be listed in your `.gitignore`
- `WALLET_APPLE_P12_BASE64` and `WALLET_GOOGLE_SERVICE_ACCOUNT_JSON_BASE64` should only be stored in `.env` (which should also be gitignored) or in your hosting provider's environment variable settings
- If credentials are accidentally committed, rotate them immediately: revoke the Apple certificate and generate a new service account key

