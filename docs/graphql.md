# GraphQL

The plugin adds `walletPasses` and `hasWalletPasses` fields to the `User` GraphQL type. Enable "Query for wallet passes" in your GraphQL schema settings.

```graphql
{
  viewer {
    walletPasses {
      id
      uid
      generatorHandle
      sourceId
      sourceIndex
      dateCreated
      dateUpdated
      lastUpdatedAt
    }
    hasWalletPasses
  }
}
```

## Fields

| Field | Type | Description |
|-------|------|-------------|
| `walletPasses` | `[WalletPass!]!` | All wallet passes for the user |
| `hasWalletPasses` | `Boolean!` | Whether the user has any passes |

## WalletPass Type

| Field | Type | Description |
|-------|------|-------------|
| `id` | `Int!` | Pass ID |
| `uid` | `String!` | Unique identifier (used as Apple serial number) |
| `generatorHandle` | `String!` | Generator that created this pass (e.g. `membership`) |
| `sourceId` | `Int` | External source ID (null for membership passes) |
| `sourceIndex` | `Int` | Index for multi-pass sources |
| `dateCreated` | `String!` | ISO 8601 datetime |
| `dateUpdated` | `String!` | ISO 8601 datetime |
| `lastUpdatedAt` | `String` | Last platform update, ISO 8601 datetime |

Sensitive fields (`authToken`, `applePassJson`, `googlePassJson`) are not exposed.
