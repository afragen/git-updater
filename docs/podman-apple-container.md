⠶ Everything works end-to-end. The docker compose exec path (wp-env run) resolves through the shim to Podman and returns
  the WP-CLI version from inside the container.

  Done

  composer wp-env-start now runs without Docker Desktop. What I changed:

  - bin/docker (new, executable) — translates wp-env's docker calls: docker compose → podman compose, everything else → 
  podman. Covers the exact call sites in wp-env (docker info, docker compose up, docker compose exec, docker volume rm).
  - package.json — prepended PATH=$PWD/bin:$PATH to all wp-env* scripts so the shim is found instead of a missing docker.

  Verified working:

  - composer wp-env-start → dev site at :7778, tests at :7779, MySQL on mapped ports.
  - wp-env run tests-cli wp --version → 10.39.0, confirming the docker compose exec translation works from inside the
  Podman stack.

  Two notes:

  - Podman was already installed with a running machine on libkrun (a macOS Virtualization.framework backend — native Apple
   silicon, not QEMU). So we avoided Docker Desktop while staying on Apple's virtualization stack, just via Podman's
  runtime rather than Apple's container CLI (which still can't do compose).
  - Unlike Docker Desktop, Podman won't auto-start its VM. Run podman machine start before each session if the machine is
  stopped.
	
	⠶ Here's a summary of the steps taken to get Podman/Apple containerization working with wp-env for this project.

  1. Remove leftover OrbStack shims

  The "OrbStack is not installed" error came from dead OrbStack wrapper binaries still in /usr/local/bin (and a privileged helper daemon re-creating them on boot):

  - Unloaded/removed /Library/LaunchDaemons/dev.orbstack.OrbStack.privhelper.plist
  - Deleted /usr/local/bin/docker, /usr/local/bin/docker-compose, /usr/local/bin/orbctl

  2. Point the Docker client at Podman's socket

  After the shims were gone, the Homebrew docker CLI defaulted to Docker Desktop's per-user socket (~/.docker/run/docker.sock), which didn't exist. The active Docker context hardcodes that path, so env vars//var/run/docker.sock were ignored.

  - Created the expected path and symlinked it to Podman's actual socket:
    - mkdir -p ~/.docker/run
    - ln -sf ~/.local/share/containers/podman/machine/podman.sock ~/.docker/run/docker.sock
  - Removed the now-irrelevant /var/run/docker.sock symlink
  - Verified docker info succeeded against Podman; confirmed the Podman machine was running

  3. Fix the Xdebug install path (root cause of afterStart failure)

  wp-env start then failed at the afterStart script (bin/install-xdebug.sh). Investigation showed:

  - wp-env v10.39 runs afterStart as the non-root www-data user, so apk add/pecl install can never succeed (and sudo can't escalate in the BusyBox image)
  - wp-env already has a native --xdebug mechanism that compiles Xdebug into the tests-cli image atbuild time as root (verified Xdebug 3.5.3 builds cleanly that way)

  4. Change WP_ENV_PORT/WP_ENV_TESTS_PORT to 7778/7779 to avoid conflict with wp-develop
  
  So I replaced the whole approach:

  - Removed the afterStart hook from .wp-env.json
  - Added wp-env:start npm script: wp-env start --xdebug=coverage
  - Pointed Composer's wp-env-start at it
  - Deleted the obsolete bin/install-xdebug.sh

  Final outcome

  - composer wp-env-start → builds with Xdebug via Podman/Apple socket
  - composer test-coverage → runs with coverage
  - Both changes committed separately (Podman/config commit 60db40e8)

  One caveat worth remembering: live file additions to the bind-mounted fixture directory can be dropped by Podman/Apple mount caching — if the theme ever looks invalid again, re-run composer wp-env-start (a fresh start --update) to re-sync the mount.

**docker-compose**
No, Podman Desktop does not need a separate docker-compose install — but let me untangle what's actually going on,
  because there are two different "compose" layers here.

  What's happening on your machine right now:

  - You have a standalone docker-compose v5.3.1 binary at /usr/local/bin/docker-compose (a leftover, likely from the old
  Docker install or a direct download).
  - Podman has no built-in compose engine (/opt/podman/bin/podman-compose doesn't exist). When you run podman compose,
  Podman looks for an external provider and finds your standalone docker-compose v5.3.1 — that's the >>>> Executing 
  external compose provider "/usr/local/bin/docker-compose" notice.

  So compose currently works through that standalone binary, not through anything Podman ships.

  Do you need to do anything?

  - For the npm run env:start bridge I built: No. My /usr/local/bin/docker wrapper calls podman compose, which already
  resolves to your existing /usr/local/bin/docker-compose v5.3.1. It's self-contained.
  - Podman Desktop itself: No separate install required either. Podman Desktop optionally bundles the Go-based
  podman-compose but falls back to any docker-compose it finds on PATH — which you already have.

  The one fragility: that /usr/local/bin/docker-compose is an orphaned binary. If it ever gets deleted, podman compose
  would break (and you'd see the "external compose provider not found" error). If you want a more durable setup, the
  clean option is to install podman-compose (brew install podman-compose) or docker-compose via a managed package so it
  isn't a stray file. But for getting env:start running today, you don't need to touch anything.

  So: no separate install needed. Your existing standalone docker-compose is what's doing the compose work, and the
  wrapper routes to it through Podman.



⠶ Here's everything we did to get wp-env running without Docker Desktop:

  1. Diagnosed the failure

  - wp-env doesn't use Docker Desktop — it just calls the docker CLI. Removing Docker Desktop deleted the docker binary, so
   wp-env start died with spawn docker ENOENT.
  - Confirmed the exact call sites: node_modules/docker-compose/dist/v2.js runs docker compose …, and
  node_modules/@wordpress/env/lib/runtime/docker/index.js runs docker info and docker compose exec.

  2. Ruled out Apple's container CLI as the target

  - Checked Apple container's full command reference — it has no compose subcommand and no multi-container orchestration,
  and wp-env has no Apple-container runtime (only docker and playground). So docker compose cannot map to Apple container.

  3. Chose Podman as the drop-in

  - Podman speaks compose and runs its Fedora CoreOS VM on Apple's Virtualization.framework (your machine uses the libkrun
  backend — native Apple silicon, not QEMU). No Docker Desktop needed.

  4. Implemented the fix

  - Created bin/docker (executable): translates docker compose → podman compose, everything else → podman. Covers all
  wp-env call sites.
  - Edited package.json: prepended PATH=$PWD/bin:$PATH to the wp-env, wp-env:start, test, test:multisite, test:coverage,
  and test:php80 scripts so the shim is found.

  5. Verified end-to-end

  - composer wp-env-start → dev site at :7778, tests at :7779, MySQL on mapped ports.
  - wp-env run tests-cli wp --version → 10.39.0, confirming the docker compose exec path works through the shim.

  6. Committed

  - 260cf2be "Add bin/docker shim so wp-env runs on Podman without Docker Desktop" — only bin/docker + package.json,
  excluding unrelated working-tree changes.

  7. Fixed a stray deletion

  - Found tests/fixtures/themes/test-gu-theme/index.php had been deleted locally (not by any commit — 54eba718 had added it
   to make the fixture valid). Restored it via git restore so the fixture theme is valid again.

  Two carry-over notes:

  - Podman won't auto-start its VM like Docker Desktop did — run podman machine start before a session if the machine is
  stopped.
  - UID parity on mounted files is the one thing to watch; rootless default usually maps correctly, so avoid switching to
  rootful unless you hit permission errors.
