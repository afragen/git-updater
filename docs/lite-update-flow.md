# Git Updater Lite: Download Flow & Private-Package Access

This document explains how `git-updater-lite` handles plugin and theme updates and how
private packages are protected.

## The Problem: "Why were my updates failing?"
Previously, when `git-updater-lite` checked for an update, the server provided a download link that was valid for **12 hours**. However, `git-updater-lite` caches this information for **6 hours** to prevent slowing down your site.

If a user waited longer than 12 hours to click the "Update Now" button (even though the update notice was still cached), the download link would expire, resulting in a `403: Download link has expired` error.

## The Security Goal: Keeping Credentials Safe
Before diving into the solution, it's important to understand *why* we use a proxy at all. If you host private repositories on GitHub, GitLab, or Bitbucket, your server requires an **Access Token** (a secret key) to download the files.

If we sent this Access Token directly to the user's WordPress site to handle the download, we would risk exposing it. If the user's site was hacked, or if they viewed the network traffic, they could steal your private Access Token and use it to access your other private repositories.

To solve this, `git-updater` acts as a **secure middleman**:
1. The user's site asks for the update.
2. Your `git-updater` server uses its **private, stored Access Token** to fetch the file from GitHub/GitLab.
3. The server immediately streams the file to the user's site, **without ever sending them the Access Token**.

## The Solution: The Two-Step Download
Instead of receiving a long-term download link, `git-updater-lite` receives a **"Token URL"**.
1. When you click "Update Now", the plugin uses this Token URL to instantly request a **fresh, 60-second download link** from the server.
2. The server verifies the request and, if valid, generates a temporary link to its secure proxy endpoint.
3. The plugin uses that fresh link to download the file.

Because the download link is generated *at the exact moment* you update, it can never expire before you use it. This completely eliminates the cache mismatch error while ensuring the upstream access tokens remain perfectly safe on your server.

## Security Feature: Approved Lite Clients
For **private packages** (plugins/themes that require authentication), the server requires the
caller to present an **approved client key**.

A package is treated as private based on what the git host reports about the repository — this
is detected for every supported host (GitHub, GitLab, Gitea, Bitbucket, Bitbucket Server).
The gate is therefore **not** a flag you switch on per package; it applies automatically to
every private package.

### How the key works
Each `git-updater-lite` site generates its **own** key the first time it needs a private
package, stores it locally, and sends it as the `X-GU-Lite-Key` header. The server:
1. Records the unknown key as a **pending client** for that package.
2. Withholds the package until an administrator clicks **Approve** once on the **Lite Clients**
   tab. After approval, every request carrying that key is served until it is revoked.

- The key is a 256-bit random value generated on the client. The server stores **only its
  SHA-256 hash**, so a database or settings leak yields nothing usable.
- **Public packages never need a key and are never gated.** A client that only uses public
  packages never generates one.
- **One key per client, one approval per client per package.** A package served to several
  sites shows several approvable clients, each identified by a display-only label (the site's
  host and name) and a short key fingerprint. Revoking one client does not affect the others.

> **Private packages need an approved client.** If a private package has no approved clients,
> it is withheld from the API — including git-updater-lite clients. Approve each client on the
> **Lite Clients** tab, or it will stop receiving updates.

## Who is Affected?

### For `git-updater-lite` Users (The Client)
**You don't need to do anything** for public packages.
The update process is reliable. When you click "Update Now", you might notice a tiny,
split-second delay as the plugin requests the fresh download token, but it is virtually
unnoticeable. Public repositories continue to update exactly as they always have.

A package must carry an `Update URI:` header to be served to a lite client — that header is
how the client finds its update server.

For private packages, your site generates a key automatically and appears on the server's
**Lite Clients** tab as a pending client; the package author approves it once.

### For `git-updater` Developers (The Server)
If you host private packages for your clients, use the **"Lite Clients"** tab in your Git
Updater settings.
- **Automatic Detection**: The system scans your private repositories and shows them for approval.
- **Approved vs. pending**: Approve a client once per package; revoke to withdraw access.
- **The "Uses Git Updater Lite" Checkbox**: In the **Additions** tab, you can flag a package
  for distribution to lite clients. It does not make a package private — privacy comes from the
  repository itself.

## What is NOT Affected?

- **Public Repositories**: If your plugin or theme is public, the client-key gate does not
  apply. Updates work for everyone, everywhere.
- **The Main Git Updater Plugin, public packages**: Public packages keep returning direct
  download links with no credential required.
- **Hard-Blocked Packages**: If you marked a package as a "Private Package" on the Additions
  tab, that setting overrides everything else — the package is never served to anyone.

## What DID Change

- **Private packages require a credential on every API route**, including `plugins-api`,
  `themes-api` and `update-api`. Callers must present the shared REST API key or an approved
  lite client key (`X-GU-Lite-Key`).
- **The `X-GU-Site-Domain` header and the "Lite Client Domains" tab were removed.** The old
  domain check was self-asserted (any caller could claim an authorized domain), so it provided
  no confidentiality. It is replaced by the per-client key + one-time approval above.
- **git-updater-lite must be a version that sends `X-GU-Lite-Key`** for private packages.
  Earlier clients send no key on that route and are denied once the gate applies. See
  `docs/private-package-rollout.md` for the enforcement order.
- **The key reset on the Remote Management tab is POST-only** (nonce and capability were
  already required).

## Summary
- **Security First**: Upstream Access Tokens are never exposed to the client.
- **Reliability**: Updates no longer fail due to cached, expired links.
- **Security for Clients**: Private packages require an approved per-client key.
- **Simplicity**: Public repos need no configuration.
