# Mollie Tracks Events Flow

## All events include these common properties
| Property | Example |
|---|---|
| `plugin_version` | `8.1.6` |
| `store_url` | `example.com` (stripped of `https://` and `www.`) |

---

## Event Flow Diagrams

### 1. Plugin Activation

```
Plugin activated via WP admin
  |
  v
register_activation_hook
  |
  v
Set option: mollie_tracks_plugin_activated = 1
  |
  v
Next admin page load
  |
  v
Option exists? ---No---> [Do nothing]
  |
  Yes
  |
  v
Delete option
  |
  v
Already connected? ---Yes---> [Do nothing]
  |
  No
  |
  v
FIRE: mollie_plugin_activated
```

**Event:** `mollie_plugin_activated`

| Parameter | Type | Example |
|---|---|---|
| `plugin_version` | `string` | `8.1.6` |
| `store_url` | `string` | `example.com` |

| Fires | Does NOT fire |
|---|---|
| First admin page load after activation (not connected) | Any admin page load without activation flag |
| | Second+ admin page loads (flag consumed) |
| | Re-activation when already connected to Mollie |

---

### 2. API Keys Viewed

```
Mollie settings page rendered
  |
  v
Already viewed once? ---Yes (gate set)---> [Do nothing]
  |
  No
  |
  v
Section = api_keys or empty? ---No (other section)---> [Do nothing]
  |
  Yes
  |
  v
Set option: mollie_tracks_api_keys_viewed = 1
  |
  v
FIRE: mollie_api_keys_viewed
```

**Event:** `mollie_api_keys_viewed`

| Parameter | Type | Example |
|---|---|---|
| `plugin_version` | `string` | `8.1.6` |
| `store_url` | `string` | `example.com` |

| Fires | Does NOT fire |
|---|---|
| First visit to API keys tab | Second+ visits (one-time gate set) |
| | Other settings sections (payment_methods, advanced) |

---

### 3. API Key Saved + Connection Result

```
WooCommerce settings saved
  |
  v
page=wc-settings AND tab=mollie_settings? ---No---> [Do nothing]
  |
  Yes
  |
  v
section = api_keys or empty? ---No (e.g. advanced)---> [Do nothing]
  |
  Yes
  |
  v
FIRE: mollie_api_key_saved
  |
  v
Check connection
  |
  v
Connected? ---Yes---> FIRE: mollie_connection_success
  |
  No
  |
  v
FIRE: mollie_connection_failed
```

**Event:** `mollie_api_key_saved`

| Parameter | Type | Example |
|---|---|---|
| `payment_mode` | `test` \| `live` | Current Mollie Payment Mode setting |
| `has_test_key` | `boolean` | `true` if test API key exists in DB |
| `has_live_key` | `boolean` | `true` if live API key exists in DB |

**Event:** `mollie_connection_success`

| Parameter | Type | Example |
|---|---|---|
| `payment_mode` | `test` \| `live` | Active payment mode |

**Event:** `mollie_connection_failed`

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

```
Mollie webhook received
  |
  v
Already tracked? ---Yes (option exists)---> [Do nothing]
  |
  No
  |
  v
Payment is paid? ---No---> [Do nothing]
  |
  Yes
  |
  v
Payment mode = test? ---No (live)---> [Do nothing]
  |
  Yes
  |
  v
Set option: first_test_payment_tracked = 1
  |
  v
FIRE: mollie_first_test_payment_complete
```

**Event:** `mollie_first_test_payment_complete`

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

```
1. plugin_activated --> 2. api_keys_viewed --> 3. api_key_saved --> Connection result
                                                                      |
                                                        OK -----------+----------- Fail
                                                        |                           |
                                                        v                           v
                                                connection_success        connection_failed
                                                        |                      :
                                                        v                      : Retry
                                                4. first_test_payment    ......'
                                                   _complete
```

---

## One-Time Event Gates

Three events use persistent one-time gates stored as WordPress options:

| Event | Gate Option | Trigger Option | Behavior |
|---|---|---|---|
| `plugin_activated` | `mollie_tracks_plugin_activated_tracked` | `mollie_tracks_plugin_activated` | Trigger set on activation, consumed on admin_init; gate prevents re-firing; skipped if already connected |
| `api_keys_viewed` | `mollie_tracks_api_keys_viewed` | — | Set after first view, persists |
| `first_test_payment_complete` | `mollie_tracks_first_test_payment_tracked` | `mollie_tracks_first_test_payment_pending` | Set after first test payment, persists |

### What resets these gates

| Action | What resets |
|---|---|
| **Plugin deactivation** (merchant NOT connected) | All gates and triggers cleared — full funnel re-fires on reactivation |
| **Plugin deactivation** (merchant connected) | Gates preserved — no events re-fire on reactivation |
| **Clear DB** (Mollie > Advanced > "Clear now") | All Mollie options deleted, including all tracking gates |
| **Plugin uninstall** (with "Remove data on uninstall" enabled) | All Mollie options deleted, including all tracking gates |
