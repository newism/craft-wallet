---
outline: deep
---

# Google Wallet Setup

Setting up Google Wallet requires a Google Cloud project, an Issuer Account, and a service account key file placed in `config/wallet/google/`.

## Setup Instructions

### 1. Create a Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the **Google Wallet API**:
   - Go to "APIs & Services" > "Library"
   - Search for "Google Wallet API"
   - Click "Enable"

### 2. Get an Issuer Account

1. Go to [Google Pay & Wallet Console](https://pay.google.com/business/console/)
2. Sign in with your Google account
3. Complete the registration process
4. Note your **Issuer ID** (a numeric value like `3388000000022229999`)

### 3. Create a Service Account

1. In Google Cloud Console, go to "IAM & Admin" > "Service Accounts"
2. Click "Create Service Account"
3. Give it a name like `wallet-service-account`
4. Grant it the role: **Wallet Object Issuer** (under Google Wallet API)
5. Click "Done"
6. Click on the service account you just created
7. Go to "Keys" tab
8. Click "Add Key" > "Create new key"
9. Select **JSON** format
10. Save the downloaded file as `service-account.json` in this directory

### 4. Link Service Account to Issuer Account

1. Go back to [Google Pay & Wallet Console](https://pay.google.com/business/console/)
2. Go to "Google Wallet API" > "Manage"
3. Click "Permissions"
4. Add the service account email (from step 3) with "Developer" or "Admin" access

### 5. Configure Environment Variables

Add the following to your `.env` file:

```env
# Google Wallet Configuration
WALLET_GOOGLE_ISSUER_ID="your-issuer-id-here"
WALLET_GOOGLE_CLASS_SUFFIX="membership"
WALLET_GOOGLE_ORG_NAME="Your Organization Name"
```

### 6. Add Images (Optional)

Place images in this directory:
- `logo.png` - 660x660px square logo
- `hero.png` - 1032x336px hero banner

Images are automatically served via `/wallet/google/image/logo.png` and `/wallet/google/image/hero.png`.

### 7. Create the Pass Class

Run the console command to create the pass class in Google Wallet:

```bash
php craft wallet/setup/google-class
```

This needs to be done once before users can add passes to their wallets.

## Environment Variables Reference

All properties on `GoogleSettings` are automatically mapped to env vars with the `WALLET_GOOGLE_` prefix. See the [full configuration reference](./configuration#google-wallet-settings) for the complete list.

Key env vars for setup:

| Variable | Description | Required |
|----------|-------------|----------|
| `WALLET_GOOGLE_ISSUER_ID` | Your Google Wallet Issuer ID | Yes |
| `WALLET_GOOGLE_CLASS_SUFFIX` | Suffix for the pass class ID (e.g., "membership") | Yes |
| `WALLET_GOOGLE_ORG_NAME` | Organization name shown on the pass | No |
| `WALLET_GOOGLE_SERVICE_ACCOUNT_JSON_BASE64` | Base64-encoded service account JSON (serverless) | No |

## Console Commands

```bash
# Create or update the pass class (run during setup and when template changes)
php craft wallet/setup/google-class
```

## Image Requirements

### Logo
- Recommended size: 660x660 pixels
- Square aspect ratio
- PNG or JPEG format
- Must be publicly accessible via HTTPS

### Hero Image
- Recommended size: 1032x336 pixels
- 3:1 aspect ratio
- PNG or JPEG format
- Must be publicly accessible via HTTPS

## Webhooks / Callbacks

Google Wallet sends callbacks when users save or delete passes. The plugin automatically:

1. Verifies the intermediate signing key against Google's root public keys
2. Verifies the message signature using the intermediate key
3. Validates message expiration
4. Logs events to `storage/logs/wallet-google-webhook-*.log`

The callback URL is automatically configured when you run `wallet/setup/google-class`.

### Signature Verification

Callbacks use the **ECv2SigningOnly** protocol with two-step verification:

1. **Intermediate Key Verification**: The `intermediateSigningKey` is verified against Google's root public keys fetched from `https://pay.google.com/gp/m/issuer/keys`
2. **Message Verification**: The `signedMessage` is verified using the intermediate key

Both steps use ECDSA P-256 with SHA-256. Google's public keys are cached for 1 hour.

No additional configuration or external libraries (like Tink) are required - verification is implemented in pure PHP using OpenSSL.

## Craft Cloud / Serverless Environments

In serverless environments you can't upload files to the server. Instead, base64-encode the service account JSON and store it as an environment variable.

Run the setup command to do this automatically:

```bash
php craft wallet/setup/env-base64
```

This reads the file at `google.serviceAccountJsonPath` from your resolved settings, base64-encodes it, and appends `WALLET_GOOGLE_SERVICE_ACCOUNT_JSON_BASE64` to your `.env`. When this env var is set, the plugin uses it automatically. The `service-account.json` file is not needed on the server. Set `serviceAccountJsonPath` to `null` in your config to avoid file-not-found errors on the settings page.

You can also encode manually:

```bash
base64 -i config/wallet/google/service-account.json | tr -d '\n'
```

## Testing

During development, passes are in "demo" mode and can only be saved by accounts added to your test users list in the Google Pay & Wallet Console.

To add test users:
1. Go to [Google Pay & Wallet Console](https://pay.google.com/business/console/)
2. Go to "Google Wallet API" > "Manage"
3. Click "Test accounts"
4. Add email addresses of test users

## Going Live

To make your passes available to all users:
1. Go to [Google Pay & Wallet Console](https://pay.google.com/business/console/)
2. Go to "Google Wallet API" > "Manage"
3. Request publishing access
4. Complete the review process

See: https://developers.google.com/wallet/generic/test-and-go-live/request-publishing-access

## Useful Links

### Consoles
- [Google Pay & Wallet Console](https://pay.google.com/business/console/) - Manage issuer account, permissions, test users
- [Google Cloud Console](https://console.cloud.google.com/) - Manage service accounts, API access, billing

### Documentation
- [Google Wallet API Overview](https://developers.google.com/wallet) - Main documentation hub
- [Generic Pass Type](https://developers.google.com/wallet/generic) - Documentation for generic passes (used by this plugin)
- [Generic Pass Class Reference](https://developers.google.com/wallet/generic/rest/v1/genericclass) - Class object structure
- [Generic Pass Object Reference](https://developers.google.com/wallet/generic/rest/v1/genericobject) - Pass object structure
- [Callbacks (Webhooks)](https://developers.google.com/wallet/generic/use-cases/use-callbacks-for-saves-and-deletions) - Webhook/callback documentation

### Design & UX
- [Pass Design Guidelines](https://developers.google.com/wallet/generic/design-guidelines) - Best practices for pass design
- [Image Guidelines](https://developers.google.com/wallet/generic/resources/images) - Logo and hero image specifications
- [Barcode Types](https://developers.google.com/wallet/generic/resources/barcodes) - Supported barcode formats

### Testing & Deployment
- [Test Your Integration](https://developers.google.com/wallet/generic/test-and-go-live/test-your-integration) - Testing guide
- [Request Publishing Access](https://developers.google.com/wallet/generic/test-and-go-live/request-publishing-access) - Go live checklist
- [Codelabs: Create a Generic Pass](https://codelabs.developers.google.com/codelabs/wallet-generic-web) - Step-by-step tutorial

### API Reference
- [REST API Reference](https://developers.google.com/wallet/generic/rest) - Full REST API documentation
- [JWT & Save Links](https://developers.google.com/wallet/generic/web/prerequisites) - JWT signing requirements
- [Error Codes](https://developers.google.com/wallet/generic/resources/errors) - Common error codes and solutions

