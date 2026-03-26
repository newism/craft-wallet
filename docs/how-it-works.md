# How It Works

## Pass Generation

When a member adds your venue's membership card to their digital wallet, the plugin generates a platform-specific pass on the fly.

- **Apple Wallet**: Generates a `.pkpass` file using the StoreCard pass type, the format Apple designed specifically for membership and loyalty cards. The pass contains the member's name, their unique Member ID encoded as a scannable QR code, and your venue's branding (logo, strip image, and colors sourced from `config/wallet/apple/`). The pass is signed with your Apple Developer certificate to ensure authenticity, then delivered as a direct download. The member's device recognises the file type and prompts them to add it to Apple Wallet.
- **Google Wallet**: Creates a Generic Pass object via the Google Wallet REST API containing the same member information and QR code, along with your venue's branding images served from `config/wallet/google/`. Rather than downloading a file, members are redirected to Google's own "Add to Wallet" flow where the pass is saved directly to their Google Wallet account.

Both passes display on the member's lock screen and are ready to scan at any entry point: front desk, bar, gym, or anywhere else your venue checks membership.

## Automatic Updates

When a member's profile is updated in Craft CMS (a name change, a status update, a membership renewal), the plugin automatically queues an update job. There is no manual step; staff update the member record in Craft and the pass updates on the member's phone.

Here is the full flow:

1. A staff member saves a user record in the Craft CMS control panel.
2. The plugin detects the save event and checks whether that user has any wallet passes on file.
3. If passes exist, the plugin pushes an `UpdateUserPassesJob` to the Craft queue.
4. The job regenerates the pass content and compares it against the stored version. Updates are only pushed if something actually changed. This avoids unnecessary API calls and push notifications when unrelated fields are edited.
5. If the content has changed, the plugin pushes the update to each platform:

- **Apple**: The plugin stores the new pass JSON and sends a silent push notification via APNs (Apple Push Notification service) to every device registered for that pass. The device receives the notification, calls back to your server's webhook endpoint, and fetches the updated `.pkpass` file. The member sees the updated card the next time they look at their wallet. No app to open, no action required.
- **Google**: The plugin sends the updated pass object directly to the Google Wallet REST API. Google handles distribution to all of the member's devices. The update appears on the member's pass without any callback or additional round trip.

No manual intervention is needed at any point. Staff manage members in one place (Craft CMS) and the wallet passes stay in sync automatically.

## Webhook Endpoints

The plugin exposes a set of webhook endpoints that Apple and Google use to communicate with your server. Both protocols are handled automatically. You do not need to build or maintain any webhook logic yourself.

- **Apple Wallet** uses a pull-based update protocol defined by Apple's PassKit specification. When a member adds a pass to their device, the device registers itself with your server by calling a registration endpoint, providing a device identifier and push token. When an update is available (triggered by the automatic update flow described above), your server sends a silent push notification to the device. The device then calls back to a separate endpoint to fetch the latest `.pkpass` file. Apple also calls your server when a member removes the pass, so the plugin can clean up device registrations and stop sending push notifications.
- **Google Wallet** uses a callback-based protocol. Google notifies your server when pass events occur: when a member saves the pass, when they delete it, or when other lifecycle events happen. The plugin verifies the cryptographic signature on each callback to ensure it genuinely came from Google, then processes the event accordingly.

Both protocols are registered automatically when the plugin is installed. See [Testing Webhooks](./webhooks) for local development setup with tools like ngrok.

## Why Self-Hosted?

Wallet Passes is a Craft CMS plugin, not a SaaS. It runs inside the system you already use to manage your members, which changes what you can do with it:

- **Your data stays yours.** Member names, IDs, and membership data never leave your server. No third-party data processing agreements. Important for clubs with governance and privacy requirements.
- **Full control of credentials.** You own the Apple Developer account and Google Wallet API credentials. No vendor lock-in. If you ever need to move, your credentials and your members go with you.
- **One system.** Staff already manage the website in Craft CMS. Membership passes live in the same control panel: no switching between systems, no sync issues, no duplicate data.
- **Customisable passes.** Brand colours, logos, layout, and field content are yours to tune. Generators let you ship entirely new pass types — event tickets, vouchers, bookings — driven by your own Craft data.
- **Deep integration.** Because the plugin lives inside Craft, pass updates can hang off any Craft event: user saves, Commerce purchases, membership renewals, or custom business logic via events.

