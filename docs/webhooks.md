# Testing Webhooks

Both Apple and Google need to reach your site over the public internet to deliver webhook callbacks. For local development, use [DDEV share](https://ddev.readthedocs.io/en/stable/users/topics/sharing/) to create a public tunnel:

```bash
ddev share
```

Then update `PRIMARY_SITE_URL` in your `.env` to the share URL.

## Apple Wallet

1. **Test on a real device.** The iOS Simulator does not support Wallet passes.
2. **Make sure the device is fully powered** and not in Low Power Mode. iOS throttles background network activity (including pass update callbacks) when the battery is low.
3. **Check the logs** at `storage/logs/wallet-apple-webhook-*.log` for device registration and pass fetch requests.
4. **Webhook URLs are baked into the pass.** If you change your site URL, you must generate and install a new pass; existing passes will still point to the old URL.

## Google Wallet

1. **Check the logs** at `storage/logs/wallet-google-webhook-*.log` for callback verification and events.
2. **The webhook callback URL is set when you run `php craft wallet/setup/google-class`.** If you change your site URL, run it again to update the callback URL in Google.
3. During development, passes are in "demo" mode. Add test users in the [Google Pay & Wallet Console](https://pay.google.com/business/console/) under **Google Wallet API &rarr; Manage &rarr; Test accounts**.
