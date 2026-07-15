⠶ Here's a summary of the steps taken to get Podman/Apple containerization working with wp-env for this project.

  1. Remove leftover OrbStack shims

  The "OrbStack is not installed" error came from dead OrbStack wrapper binaries still in /usr/local/bin (and a privileged
  helper daemon re-creating them on boot):

  - Unloaded/removed /Library/LaunchDaemons/dev.orbstack.OrbStack.privhelper.plist
  - Deleted /usr/local/bin/docker, /usr/local/bin/docker-compose, /usr/local/bin/orbctl

  2. Point the Docker client at Podman's socket

  After the shims were gone, the Homebrew docker CLI defaulted to Docker Desktop's per-user socket (~/ 
  .docker/run/docker.sock), which didn't exist. The active Docker context hardcodes that path, so env
  vars//var/run/docker.sock were ignored.

  - Created the expected path and symlinked it to Podman's actual socket:
    - mkdir -p ~/.docker/run
    - ln -sf ~/.local/share/containers/podman/machine/podman.sock ~/.docker/run/docker.sock
  - Removed the now-irrelevant /var/run/docker.sock symlink
  - Verified docker info succeeded against Podman; confirmed the Podman machine was running

  3. Fix the Xdebug install path (root cause of afterStart failure)

  wp-env start then failed at the afterStart script (bin/install-xdebug.sh). Investigation showed:

  - wp-env v10.39 runs afterStart as the non-root www-data user, so apk add/pecl install can never succeed (and sudo can't
  escalate in the BusyBox image)
  - wp-env already has a native --xdebug mechanism that compiles Xdebug into the tests-cli image at build time as root
  (verified Xdebug 3.5.3 builds cleanly that way)

  So I replaced the whole approach:

  - Removed the afterStart hook from .wp-env.json
  - Added wp-env:start npm script: wp-env start --xdebug=coverage
  - Pointed Composer's wp-env-start at it
  - Deleted the obsolete bin/install-xdebug.sh

  4. Fix the test suite (separate concern, surfaced during verification)

  The full suite failed 3 theme tests + 1 error. Root cause: wp_get_themes() cache pollution between tests — a prior test
  primed the themes cache group without the test-gu-theme fixture, so Git Updater's get_theme_meta() never saw it (passed
  in isolation, failed in full suite).

  - Added wp_clean_themes_cache() to GU_Test_Case::tear_down()
  - Added the missing index.php to the test-gu-theme fixture (it was an invalid theme with only style.css)
  - Result: full suite green (1259 tests, 0 failures, 0 errors)

  Final outcome

  - composer wp-env-start → builds with Xdebug via Podman/Apple socket
  - composer test-coverage → runs with coverage
  - Both changes committed separately (Podman/config commit 60db40e8, test fix commit 54eba718)

  One caveat worth remembering: live file additions to the bind-mounted fixture directory can be dropped by Podman/Apple
  mount caching — if the theme ever looks invalid again, re-run composer wp-env-start (a fresh start --update) to re-sync
  the mount.
