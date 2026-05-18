# Mollie Tracks Events Flow

## All events include these common properties
| Property | Example |
|---|---|
| `plugin_version` | `8.1.6` |
| `store_url` | `example.com` (stripped of `https://` and `www.`) |

---

## Event Flow Diagrams

### 1. Plugin Activation

```mermaid
flowchart TD
    A[Plugin activated via WP admin] --> B[register_activation_hook]
    B --> C[Set option: mollie_tracks_plugin_activated = 1]
    C --> D[Next admin page load]
    D --> E{Option exists?}
    E -->|No| F[Do nothing]
    E -->|Yes| G[Delete option]
    G --> H["FIRE: wcadmin_mollie_plugin_activated"]

    style H fill:#22c55e,color:#fff
    style F fill:#ef4444,color:#fff
```

**Event:** `wcadmin_mollie_plugin_activated`
**Parameters:** _(common only)_

| Fires | Does NOT fire |
|---|---|
| First admin page load after activation | Any admin page load without activation flag |
| | Second+ admin page loads (flag consumed) |

---

### 2. API Keys Viewed

```mermaid
flowchart TD
    A[Mollie settings page rendered] --> AA{Already viewed once?}
    AA -->|Yes - gate set| AB[Do nothing]
    AA -->|No| F{Section = api_keys or empty?}
    F -->|No - other section| G[Do nothing]
    F -->|Yes| GA[Set option: mollie_tracks_api_keys_viewed = 1]
    GA --> H["FIRE: wcadmin_mollie_api_keys_viewed"]

    style H fill:#22c55e,color:#fff
    style AB fill:#ef4444,color:#fff
    style G fill:#ef4444,color:#fff
```

**Event:** `wcadmin_mollie_api_keys_viewed`
**Parameters:** _(common only)_

| Fires | Does NOT fire |
|---|---|
| First visit to API keys tab | Second+ visits (one-time gate set) |
| | Other settings sections (payment_methods, advanced) |

---

### 3. API Key Saved + Connection Result

```mermaid
flowchart TD
    A[WooCommerce settings saved] --> B{page=wc-settings AND tab=mollie_settings?}
    B -->|No| C[Do nothing]
    B -->|Yes| D{section = api_keys or empty?}
    D -->|No - e.g. advanced| E[Do nothing]
    D -->|Yes| F["FIRE: wcadmin_mollie_api_key_saved"]
    F --> G[Check connection]
    G --> H{Connected?}
    H -->|Yes| I["FIRE: wcadmin_mollie_connection_success"]
    H -->|No| J["FIRE: wcadmin_mollie_connection_failed"]

    style F fill:#22c55e,color:#fff
    style I fill:#22c55e,color:#fff
    style J fill:#f59e0b,color:#fff
    style C fill:#ef4444,color:#fff
    style E fill:#ef4444,color:#fff
```

**Event:** `wcadmin_mollie_api_key_saved`

| Parameter | Type | Example |
|---|---|---|
| `payment_mode` | `test` \| `live` | Current Mollie Payment Mode setting |
| `has_test_key` | `boolean` | `true` if test API key exists in DB |
| `has_live_key` | `boolean` | `true` if live API key exists in DB |

**Event:** `wcadmin_mollie_connection_success`

| Parameter | Type | Example |
|---|---|---|
| `payment_mode` | `test` \| `live` | Active payment mode |

**Event:** `wcadmin_mollie_connection_failed`

| Parameter | Type | Example |
|---|---|---|
| `payment_mode` | `test` \| `live` | Active payment mode |
| `error_code` | `int` | `401` |
| `error_message` | `string` | `Error executing API call (401: Unauthorized Request)...` |

| Fires | Does NOT fire |
|---|---|
| Save on API keys section | Save on advanced section |
| Save on empty section (defaults to api keys) | Save on non-Mollie WC settings page |
| One api_key_saved + one connection result per save | |

---

### 4. First Test Payment

```mermaid
flowchart TD
    A[Mollie webhook received] --> B{Already tracked?}
    B -->|Yes - option exists| C[Do nothing]
    B -->|No| D{Payment is paid?}
    D -->|No| E[Do nothing]
    D -->|Yes| F{Payment mode = test?}
    F -->|No - live| G[Do nothing]
    F -->|Yes| H[Set option: first_test_payment_tracked = 1]
    H --> I["FIRE: wcadmin_mollie_first_test_payment_complete"]

    style I fill:#22c55e,color:#fff
    style C fill:#ef4444,color:#fff
    style E fill:#ef4444,color:#fff
    style G fill:#ef4444,color:#fff
```

**Event:** `wcadmin_mollie_first_test_payment_complete`

| Parameter | Type | Example |
|---|---|---|
| `payment_method` | `string` | `ideal`, `creditcard`, `bancontact` |

| Fires | Does NOT fire |
|---|---|
| First paid test payment via Mollie webhook | Live payments |
| Once per store (until reset, see below) | Unpaid/pending payments |
| | Second+ test payments (flag set) |

**Note:** This event requires Mollie webhooks to reach the site. Local development environments without a tunnel (e.g. ngrok) will never trigger this event.

---

## Complete Activation Funnel

```mermaid
flowchart LR
    A["1. plugin_activated"] --> B["2. api_keys_viewed"]
    B --> C["3. api_key_saved"]
    C --> D{"Connection\nresult"}
    D -->|OK| E["connection_success"]
    D -->|Fail| F["connection_failed"]
    F -.->|Retry| C
    E --> G["4. first_test_payment_complete"]

    style A fill:#6366f1,color:#fff
    style B fill:#6366f1,color:#fff
    style C fill:#6366f1,color:#fff
    style E fill:#22c55e,color:#fff
    style F fill:#f59e0b,color:#fff
    style G fill:#22c55e,color:#fff
```

---

## One-Time Event Gates

Three events use one-time flags stored as WordPress options:

| Event | Option | Behavior |
|---|---|---|
| `plugin_activated` | `mollie_tracks_plugin_activated` | Set on activation, deleted after firing |
| `api_keys_viewed` | `mollie_tracks_api_keys_viewed` | Set after first view, persists |
| `first_test_payment_complete` | `mollie_tracks_first_test_payment_tracked` | Set after first test payment, persists |

### What resets these gates

| Action | What resets |
|---|---|
| **Plugin deactivation** (merchant NOT connected) | All three gates cleared — full funnel re-fires on reactivation |
| **Plugin deactivation** (merchant connected) | Only `plugin_activated` re-fires on reactivation; other gates preserved |
| **Clear DB** (Mollie > Advanced > "Clear now") | All Mollie options deleted, including all tracking gates |
| **Plugin uninstall** (with "Remove data on uninstall" enabled) | All Mollie options deleted, including all tracking gates |

"Connected" means the merchant has a test or live API key saved in the database.
