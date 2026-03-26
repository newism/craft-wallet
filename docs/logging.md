# Logging

Three dedicated log files in `storage/logs/` with 14-day rotation:

| Log                           | Content                                            |
|-------------------------------|----------------------------------------------------|
| `wallet-*.log`                | Pass creation, APNs push results, Google API calls |
| `wallet-apple-webhook-*.log`  | Apple device registration, pass fetch requests     |
| `wallet-google-webhook-*.log` | Google callback verification and events            |

