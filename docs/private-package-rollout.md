# Private Package Rollout: enforcing the REST API gate

Git Updater withholds private repositories from every REST API route unless the caller
presents the shared REST API key or an **approved Lite client key** for that slug. The gate
is **unconditional**: there is no filter that disables it. This note covers how to migrate a
fleet without a silent update outage.

## Why order matters

`git-updater-lite` is a **Composer library vendored into a consuming plugin or theme** —
it is not a plugin that updates itself. A site only gets a newer client when the
*consuming* plugin ships a new bundle and the site updates that plugin.

Consequently, enforcement is server-side but the fix is client-side. If you enforce
before your users have a usable credential, a private package stops updating, and the
failure is **silent**: on a non-200 the client returns before registering any hooks, so
there is no update row, no "View details" link, and no negative caching. Nothing appears
in the admin to explain it, and because the client is what fetches `update-api`, an
affected site may not be able to pull down its own fix.

## Client credentials

| Client | Behaviour |
|---|---|
| Lite **3.3.0+** (proactive) | On a 200 response whose `is_private` is true, marks the slug and generates/sends `X-GU-Lite-Key` on the next request. With enforcement already on, the 403 fallback does the same. The server lists it as pending until an administrator approves it. |
| Lite **3.2.x** | Sends `X-GU-Lite-Key` only after a 403. The server lists it as pending until approved. |
| Lite **< 3.2.0** | Sends no key (only the removed `X-GU-Site-Domain` header). Use the provisioned-key shim below. No server-side settings can authorize it safely on its own. |

A pending entry appearing on the **Lite Clients** tab is the signal that a caller has a
usable client and has requested a private package. A site that never appears there is either
public-only or still running a client that cannot identify itself.

## Recommended order

1. **Publish Lite 3.3.0** (proactive key generation). The header is inert against an older
   server — servers ignore unknown request headers — so this is safe ahead of enforcement.
2. **Re-vendor and ship.** Update every consuming plugin/theme's `vendor/` copy and release
   it. Mind the `class_exists()` first-wins caveat below: every bundled copy must be updated.
3. **Ship the server with pre-gate pending recording.** A presented key is recorded as
   pending before the (unconditional) gate denies the request, so a keyed client can be
   approved even though its first private request is a 403.
4. **Deploy the shim to legacy clients** that cannot update Lite (below). Provision one key
   per site.
5. **Approve clients.** Approve each pending key once per package on the **Lite Clients**
   tab, or pre-approve headlessly with the server-side `git_updater_lite_client_key` filter.
   Only private packages are listed; public packages never need a key.
6. **Verify and monitor.** Confirm private updates work for approved clients; watch 403s and
   new pending entries for a defined period.

There is no escape hatch. Do not reintroduce a domain or IP allowlist to accommodate an old
client; those checks are self-asserted or brittle and reopen the vulnerability.

## Legacy clients: provisioned-key shim

A Lite < 3.2.0 client sends no credential, so the server cannot distinguish it from an
attacker. Give it a real credential out-of-band with the shim shipped at
`git-updater-lite/mu/git-updater-lite-key-shim.php` (or the equivalent filter in the
consuming plugin):

```php
add_filter(
	'http_request_args',
	function ( $args, $url ) {
		if ( ! str_contains( $url, 'wp-json/git-updater/v1/' ) ) {
			return $args;
		}

		$key = defined( 'GIT_UPDATER_LITE_SHIM_KEY' ) ? (string) GIT_UPDATER_LITE_SHIM_KEY : '';
		if ( '' === $key ) {
			return $args;
		}

		$args['headers']['X-GU-Lite-Key']     = $key;
		$args['headers']['X-GU-Client-Label'] = home_url();
		return $args;
	},
	10,
	2
);
```

- **Provisioning:** generate one random 256-bit key per site and define it as
  `GIT_UPDATER_LITE_SHIM_KEY` in `wp-config.php` or the mu-plugin (the shim also exposes it
  through the `git_updater_lite_shim_key` filter). The server records it as a pending client;
  approve it on the **Lite Clients** tab (or pre-approve it with
  `git_updater_lite_client_key`).
- **Coverage:** the filter applies to both the `update-api` metadata request and the
  `download-token` request made at “Update Now” time.
- **Security:** the shim sends a real secret over TLS; the server authorizes on approval, not
  on a self-asserted header. Per-site keys are revocable individually. The shared REST `key`
  also works but is coarser and must be added to both requests.
- **Cost:** this is still a code deployment to the client, just not a Lite library update. If
  the consuming plugin can ship a new bundle, ship Lite 3.3.0 instead.

## Migration from the removed domain allow-list

The `X-GU-Site-Domain` header, the `git_updater_lite_domains` option, and the "Lite Client
Domains" tab are gone (they were a self-asserted, non-security check). The obsolete option is
deleted on upgrade. Configure private-package access exclusively through approved clients on
the **Lite Clients** tab, or through the shim's provisioned key for legacy clients.

## Caveat: one site, several consumers

`Lite` is defined behind a `class_exists()` guard, so **whichever consuming plugin or
theme loads first defines the class for the whole site.** A site with two plugins that
each vendor a different copy can hold an updated bundle in one and have an older copy win.

The practical effect: updating a single plugin may not be enough for such a site, and the
symptom is confusing — "one plugin updated fine, another silently stopped." If you ship
to customers who bundle more than one of your packages, ship them together, or use the
provisioned-key shim for sites where an older bundled copy wins.
