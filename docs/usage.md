# Usage

## Template Examples

The plugin provides `craft.wallet` for querying passes and `WalletBehavior` methods on User elements.

```twig
{% if currentUser %}

    {# Check if user has a membership pass #}
    {% if currentUser.hasPass('membership') %}
        <p>You have a membership pass.</p>
    {% endif %}

    {# Get a specific pass #}
    {% set pass = currentUser.getPass('membership') %}
    {% if pass %}
        <p>Pass UID: {{ pass.uid }}</p>
        <p>Created: {{ pass.dateCreated|date('M j, Y') }}</p>
    {% endif %}

    {# Check if user has any wallet passes at all #}
    {% if currentUser.hasWalletPasses %}
        <p>You have wallet passes.</p>
    {% endif %}

{% endif %}
```

### Query Builder

Use `craft.wallet.passes` for more complex queries:

```twig
{# All passes for the current user #}
{% set allPasses = craft.wallet.passes.userId(currentUser.id).all() %}

{# A specific pass by generator #}
{% set pass = craft.wallet.passes.userId(currentUser.id).generatorHandle('membership').one() %}

{# Check if a pass exists #}
{% if craft.wallet.passes.userId(currentUser.id).generatorHandle('membership').exists() %}
    <p>Membership pass exists.</p>
{% endif %}
```

## User Behavior

The plugin attaches a `WalletBehavior` to all `User` elements:

| Method / Property    | Return Type    | Description                                         |
|----------------------|----------------|-----------------------------------------------------|
| `hasWalletPasses`    | `bool`         | `true` if the user has any passes                   |
| `getPass($handle)`   | `Pass\|null`   | Returns a single pass for a generator handle        |
| `getPasses($handle)` | `Pass[]`       | Returns all passes, optionally filtered by generator |
| `hasPass($handle)`   | `bool`         | Checks if the user has a pass for a generator       |

## Admin UI

The plugin adds a **Wallet Passes** screen to the user edit page in the control panel. Each pass is displayed as a card with an action menu for adding to wallet, copying a shareable URL, or deleting the pass.

## GraphQL

**Currently disabled** pending rebuild for the unified Pass model.

## Console Commands

:::consolecommand
command: php craft wallet/setup/google-class
---
Create or update the Google Wallet pass class. Run once during setup, and again when updating the pass template.
:::

:::consolecommand
command: php craft wallet/setup/env-base64
---
Base64-encode wallet credentials and write them to `.env`. For Craft Cloud / serverless deployments.
:::
