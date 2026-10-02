# Private Package Rollout: enforcing the REST API gate

Git Updater withholds private repositories from every REST API route unless the caller
presents the shared REST API key or an **approved Lite client key** for that slug. That gate
is described in [lite-update-flow.md](./lite-update-flow.md). This note covers how to turn it
on without breaking sites that are still running an older client.

## Why order matters

`git-updater-lite` is a **Composer library vendored into a consuming plugin or theme** —
it is not a plugin that updates itself. A site only gets a newer client when the
*consuming* plugin ships a new bundle and the site updates that plugin.

Consequently, enforcement is server-side but the fix is client-side. If you enforce
before your users have the newer client, a private package stops updating, and the
failure is **silent**: on a non-200 the client returns before registering any hooks, so
there is no update row, no "View details" link, and no negative caching. Nothing appears
in the admin to explain it, and because the client is what fetches `update-api`, an
affected site cannot pull down its own fix.

## Required client version

| Client | Behaviour |
|---|---|
| New client (sends `X-GU-Lite-Key`) | Generates a key lazily on its first private package and sends it; the server lists it as a pending client to approve. |
| Older client (sends no key) | No key on `update-api`. Denied for private packages. |

A pending entry appearing on the **Lite Clients** tab is the signal that a caller has the
updated client and has requested a private package. You do not need a separate version
marker to check coverage.

## Recommended order

1. **Publish the client version that sends `X-GU-Lite-Key`.** The header is inert against an
   older server — servers ignore unknown request headers — so this is safe ahead of enforcement.
2. **Re-vendor and ship.** Update the consuming plugin/theme's `vendor/` copy and release
   it. Your customers pick up the new client when they update that plugin/theme.
3. **Approve clients.** As each updated site requests a private package it appears as a
   pending client on the **Lite Clients** tab; click **Approve** once per client per package.
4. **Enforce.** The gate is on by default in this release. If your fleet is not ready, see
   the escape hatch below.

## Escape hatch

Return `false` from `gu_enforce_private_package_gate` to serve private metadata
unauthenticated while you finish the rollout:

```php
add_filter( 'gu_enforce_private_package_gate', '__return_false' );
```

The filter receives the package slug as its second argument if you need to stage this
per package:

```php
add_filter(
	'gu_enforce_private_package_gate',
	function ( $gate, $slug ) {
		return 'my-plugin' === $slug ? false : $gate;
	},
	10,
	2
);
```

It applies to both `get_api_data()` and `get_download_token()`, so a staged rollout
cannot leave the two endpoints disagreeing. Default is `true`.

Treat this as a **stopgap, not a steady state** — while it returns `false`, private
package metadata is readable by anyone who knows the slug. Note also that the
`private_package` hard block on the Additions tab is **not** filterable; it always denies.

## Migration from the removed domain allow-list

The `X-GU-Site-Domain` header, the `git_updater_lite_domains` option, and the "Lite Client
Domains" tab are gone (they were a self-asserted, non-security check). The obsolete option is
deleted on upgrade. Configure private-package access exclusively through approved clients on
the **Lite Clients** tab.

## Caveat: one site, several consumers

`Lite` is defined behind a `class_exists()` guard, so **whichever consuming plugin or
theme loads first defines the class for the whole site.** A site with two plugins that
each vendor a different copy can hold an updated bundle in one and have an older copy win.

The practical effect: updating a single plugin may not be enough for such a site, and the
symptom is confusing — "one plugin updated fine, another silently stopped". If you ship
to customers who bundle more than one of your packages, either ship them together or
point affected users at the escape hatch above until every consumer is updated.
