# Git Updater Lite: New Download Flow & Security Features

This document explains the recent changes to how `git-updater-lite` handles plugin and theme updates. We have moved to a **Two-Step Download Flow** with optional **Domain Validation** to fix reliability issues and add security features for private packages.

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
We have fixed the reliability issue by changing *how* the download happens behind the scenes, while maintaining this high level of security.

Instead of receiving a long-term download link, `git-updater-lite` now receives a **"Token URL"**. 
1. When you click "Update Now", the plugin uses this Token URL to instantly request a **fresh, 60-second download link** from the server.
2. The server verifies the request and, if valid, generates a temporary link to its secure proxy endpoint.
3. The plugin uses that fresh link to download the file.

Because the download link is generated *at the exact moment* you update, it can never expire before you use it. This completely eliminates the cache mismatch error while ensuring the upstream access tokens remain perfectly safe on your server.

## New Security Feature: Domain Validation
For developers distributing **private packages** (plugins/themes that require authentication), we have added an optional layer of security called **Domain Validation**.

### How it works
If enabled, the server checks the **domain name** of the website requesting the update (e.g., `example.com`). Even if someone intercepts a download link, they cannot use it unless their website domain matches the "allowed list" you have configured.

### Smart Subdomain Handling
You only need to add your base domain (e.g., `example.com`) to the allowed list. The system will automatically accept updates from:
- `www.example.com`
- `staging.example.com`
- `dev.example.com`
- Any other subdomain

## Who is Affected?

### For `git-updater-lite` Users (The Client)
**You don't need to do anything.** 
The update process is now more reliable. When you click "Update Now", you might notice a tiny, split-second delay as the plugin requests the fresh download token, but it is virtually unnoticeable. Public repositories will continue to update exactly as they always have.

### For `git-updater` Developers (The Server)
If you host private packages for your clients, you now have a new **"Lite Client Domains"** tab in your Git Updater settings.
- **Automatic Detection**: The system will automatically scan your private repositories and suggest adding them to the domain list.
- **The "Uses Git Updater Lite" Checkbox**: In the **Additions** tab, you can now check a box labeled "Uses Git Updater Lite" for any package. This explicitly flags it for domain configuration.

## What is NOT Affected?

- **Public Repositories**: If your plugin or theme is public, this new domain validation is completely ignored. Updates will work for everyone, everywhere.
- **The Main Git Updater Plugin**: If you use the main `git-updater` plugin (not the "lite" version), **absolutely nothing changes**. You will continue to receive direct, long-term download links exactly as you always have.
- **Hard-Blocked Packages**: If you previously marked a package as a "Private Package" (which blocks it from the API entirely), that setting still overrides everything else.

## Summary
- **Security First**: Upstream Access Tokens are never exposed to the client.
- **Reliability**: Updates no longer fail due to cached, expired links.
- **Security for Clients**: Private packages can now be locked to specific customer domains.
- **Simplicity**: The system is smarter, requiring no manual configuration for public repos or standard updates.