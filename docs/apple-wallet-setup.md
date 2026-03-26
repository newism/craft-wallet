---
outline: deep
---

# Apple Wallet Setup

Setting up Apple Wallet requires creating a Pass Type Identifier and signing certificate in the Apple Developer Portal, then placing the files in `config/wallet/apple/`.

## Files

| File                         | Description                               |
|------------------------------|-------------------------------------------|
| `certificate.p12`            | Pass signing certificate (PKCS#12 format) |
| `applewwdrca.pem`            | Apple WWDR G4 intermediate certificate    |
| `icon.png` / `icon@2x.png`   | Pass icon (29x29pt / 58x58px)             |
| `logo.png` / `logo@2x.png`   | Pass logo (max 160x50pt / 320x100px)      |
| `strip.png` / `strip@2x.png` | Pass strip image (375x123pt / 750x246px)  |

## Setup Instructions

### 1. Create an App ID (if you don't have one)

1. Go to [Apple Developer Portal](https://developer.apple.com/account/resources/identifiers/list)
2. Click **+** to create a new identifier
3. Select **App IDs** and click **Continue**
4. Select **App** and click **Continue**
5. Enter a description and Bundle ID (e.g., `com.yourcompany.wallet`)
6. Click **Register**

### 2. Create a Pass Type Identifier

1. Go to [Apple Developer Portal - Identifiers](https://developer.apple.com/account/resources/identifiers/list)
2. Click **+** to create a new identifier
3. Select **Pass Type IDs** and click **Continue**
4. Enter:
   - **Description**: e.g., "Membership Pass"
   - **Identifier**: e.g., `pass.com.yourcompany.membership`
5. Click **Register**

### 3. Create a Pass Type Certificate

1. Go to [Apple Developer Portal - Certificates](https://developer.apple.com/account/resources/certificates/list)
2. Click **+** to create a new certificate
3. Under **Services**, select **Pass Type ID Certificate**
4. Click **Continue**
5. Select your Pass Type ID from step 2
6. Click **Continue**

#### Generate a Certificate Signing Request (CSR)

On your Mac:

1. Open **Keychain Access**
2. Go to **Keychain Access > Certificate Assistant > Request a Certificate From a Certificate Authority**
3. Enter your email and name
4. Select **Saved to disk**
5. Click **Continue** and save the `.certSigningRequest` file

#### Upload CSR and Download Certificate

1. Upload your CSR file to Apple
2. Click **Continue**
3. Click **Download** to get the `.cer` file

### 4. Export as .p12

1. Double-click the downloaded `.cer` file to install it in Keychain Access
2. In Keychain Access, find the certificate (look for your Pass Type ID)
3. Expand it to see the private key
4. Select **both** the certificate and private key
5. Right-click and select **Export 2 items...**
6. Choose **Personal Information Exchange (.p12)** format
7. Save as `certificate.p12`
8. Enter a password (remember it for `.env`)

### 5. Download Apple WWDR Certificate

Download the **Apple Worldwide Developer Relations Certification Authority - G4** certificate:

```bash
curl -o applewwdrca.cer https://www.apple.com/certificateauthority/AppleWWDRCAG4.cer
openssl x509 -inform der -in applewwdrca.cer -out applewwdrca.pem
rm applewwdrca.cer
```

Or download manually from [Apple PKI](https://www.apple.com/certificateauthority/) and convert:
```bash
openssl x509 -inform der -in AppleWWDRCAG4.cer -out applewwdrca.pem
```

### 6. Configure Environment Variables

Add the following to your `.env` file:

```env
# Apple Wallet Configuration
WALLET_APPLE_P12_PASSWORD="your-p12-password"
WALLET_APPLE_PASS_TYPE_ID="pass.com.yourcompany.membership"
WALLET_APPLE_TEAM_ID="XXXXXXXXXX"
WALLET_APPLE_ORG_NAME="Your Organization Name"
```

### 7. Add Pass Images

Place the following images in this directory:

| File           | Size          | Description                  |
|----------------|---------------|------------------------------|
| `icon.png`     | 29x29px       | Small icon for notifications |
| `icon@2x.png`  | 58x58px       | Retina icon                  |
| `logo.png`     | max 160x50px  | Logo on pass header          |
| `logo@2x.png`  | max 320x100px | Retina logo                  |
| `strip.png`    | 375x123px     | Header strip background      |
| `strip@2x.png` | 750x246px     | Retina strip                 |

## Environment Variables Reference

All properties on `AppleSettings` are automatically mapped to env vars with the `WALLET_APPLE_` prefix. See the [full configuration reference](./configuration#apple-wallet-settings) for the complete list.

Key env vars for setup:

| Variable | Description | Required |
|----------|-------------|----------|
| `WALLET_APPLE_PASS_TYPE_ID` | Your Pass Type Identifier | Yes |
| `WALLET_APPLE_TEAM_ID` | Your Apple Developer Team ID | Yes |
| `WALLET_APPLE_ORG_NAME` | Organization name shown on pass | Yes |
| `WALLET_APPLE_P12_PASSWORD` | Password for the .p12 certificate | Yes |
| `WALLET_APPLE_P12_BASE64` | Base64-encoded .p12 certificate (serverless) | No |

## Finding Your Team ID

1. Go to [Apple Developer Account](https://developer.apple.com/account)
2. Scroll down to **Membership details**
3. Your **Team ID** is displayed there (10 character alphanumeric)

## Craft Cloud / Serverless Environments

In serverless environments you can't upload files to the server. Instead, base64-encode the certificate and store it as an environment variable.

Run the setup command to do this automatically:

```bash
php craft wallet/setup/env-base64
```

This reads the file at `apple.p12Path` from your resolved settings, base64-encodes it, and appends `WALLET_APPLE_P12_BASE64` to your `.env`. When this env var is set, the plugin uses it automatically. The `certificate.p12` file is not needed on the server. Set `p12Path` to `null` in your config to avoid file-not-found errors on the settings page.

You can also encode manually:

```bash
base64 -i config/wallet/apple/certificate.p12 | tr -d '\n'
```

## Troubleshooting

### "Invalid signature" error
- Ensure you're using the WWDR G4 certificate (`AppleWWDRCAG4.cer`)
- Verify the .p12 contains both the certificate AND private key
- Check the password is correct

### "Pass Type ID doesn't match" error
- Ensure `WALLET_APPLE_PASS_TYPE_ID` matches the identifier in your certificate

### Pass doesn't update on device
- The web service URL must be publicly accessible (use a tunnel for local dev)
- Check the `wallet-apple-webhook` logs for errors

## Testing with Cloudflare Tunnel

For local development, use `ddev share` to create a public tunnel:

```bash
ddev share
```

Update `PRIMARY_SITE_URL` in `.env` with the tunnel URL, then download a new pass.

## Useful Links

- [Apple Wallet Developer Guide](https://developer.apple.com/library/archive/documentation/UserExperience/Conceptual/PassKit_PG/)
- [Pass Design Guidelines](https://developer.apple.com/design/human-interface-guidelines/wallet)
- [PassKit Package Format](https://developer.apple.com/library/archive/documentation/UserExperience/Reference/PassKit_Bundle/Chapters/Introduction.html)
- [Apple PKI Certificates](https://www.apple.com/certificateauthority/)
