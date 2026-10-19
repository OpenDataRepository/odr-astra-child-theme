# Search gate setup

What to do on each server after deploying this theme. The gate controls the shortcut search
routes — `/<mineral>`, `/ima/<mineral>`, `/amcsd/<mineral>` — and needs two things configured
before it can let anyone through: an **allowlist index**, and **reCAPTCHA keys**.

Until reCAPTCHA keys are in place, anonymous visitors get the minimal 404 on those routes, exactly
as they did before the gate existed. That is the safe failure mode, not a broken one.

**On a site without the mineral name lists** (which is most of them) leave *Mineral name lists*
off and stop after step 1: there is no index to build and no cron to schedule. The gate is then
just logged-in users, verified crawlers if you enable them, and reCAPTCHA for everyone else.

## 1. Settings

**Settings → ODR Search Gate** in wp-admin.

| Field | What to put in it |
| --- | --- |
| Mineral name lists | **Off on every site except the mineral ones.** Off means the gate is login + verified crawlers + reCAPTCHA, nothing is indexed, no files are read, and steps 2 and 3 below don't apply. Everything from *ODR web directory* down is hidden while it's off. |
| reCAPTCHA site key | From [google.com/recaptcha/admin](https://www.google.com/recaptcha/admin). Must be **reCAPTCHA v2, "I'm not a robot" checkbox** — v3 keys will not work with this form. |
| reCAPTCHA secret key | The other half of that pair. |
| Verified crawlers | Leave off unless you want verified Google/Bing/DuckDuckGo crawlers to run searches that aren't on a list. |
| Ban length | `7200` (two hours). Also how long a visitor who passes is trusted, so they aren't re-challenged on every search. |
| ODR web directory | Absolute path of ODR's `web/` directory, e.g. `/home/rruff/data-publisher/web`. The page tells you whether it can see `uploads/IMA` and `uploads/static` underneath it. |
| ODR url path | Where ODR is served on this site, e.g. `/odr_rruff`. Used to build cached-page links. |
| Index file | `<ODR>/var/tmp/odr-search-allowlist.php`, e.g. `/home/rruff/data-publisher/var/tmp/odr-search-allowlist.php`. See *Where to put the index* below. Blank falls back to `wp-content/uploads`. |

Register the reCAPTCHA keys against every hostname that serves the site (`www.rruff.net`,
`beta.rruff.net`, `dev.rruff.net` — Google matches on domain).

### Where to put the index

`<ODR>/var/tmp/` is the right home for it:

- **Writable by everyone that needs it.** ODR's `var/` is already mode `0777` on these servers, so
  the webserver user, your cron user and you can all write there. `wp-content/uploads` is often
  owned by `www-data` alone, which stops a cron running as anyone else.
- **Not reachable over the web.** `var/` sits outside ODR's `web/` directory. A path under
  `web/uploads/` is served — the index holds nothing secret, but a generated PHP file that gets
  `include`d has no business being fetchable.
- **Survives deploys.** It is neither in the theme nor in ODR's tracked tree.

The directory does not exist on a fresh install, and the build reports
`no such directory: …` rather than guessing. Create it once per instance:

    mkdir -p /home/rruff/data-publisher/var/tmp
    chmod 1777 /home/rruff/data-publisher/var/tmp

Mode `1777` matches `var/` itself and lets any of those users write while the sticky bit stops
them replacing each other's files. On a server with untrusted local users, give the directory to
whichever user runs the rebuild (`chown www-data`, `chmod 0755`) and run cron as that user
instead — remembering that an `include`d PHP file in a world-writable directory is only as
trustworthy as the users who can write there.

`var/tmp/` is gitignored in data-publisher, so the generated file never shows up in `git status`.

## 2. Build the index once *(name-list sites only)*

Press **Rebuild index now** on the settings page. It should report something like
`6,903 mineral names and 4,344 AMCSD names indexed.`

If it reports a permission error, the index file location isn't writable by the webserver — set
the **Index file** field to somewhere that is.

## 3. Keep it rebuilt — hourly *(name-list sites only)*

The index is a snapshot of ODR's generated mineral name files. New minerals won't resolve until it
is rebuilt, so this needs to run hourly. There are two ways, and **they are safe to use
together**: whichever runs first rebuilds, and the other finds the index current and exits.

### WP-Cron (already active, nothing to install)

The theme schedules `odr_sg_hourly_rebuild` on its own. The settings page shows when it next
checks. Caveats worth knowing:

- WP-Cron only fires when something requests the site, so "hourly" means "within an hour of the
  next page view". On a quiet site it drifts.
- If `wp-config.php` sets `DISABLE_WP_CRON`, it never fires at all — use the system cron below.
- It rebuilds only when one of ODR's name files has changed, so an hourly check is cheap.

### System cron (more dependable; recommended for production)

This path doesn't load WordPress at all, so it works regardless of traffic or `DISABLE_WP_CRON`.
Run it as a user that can write the index file:

    # rebuild the search allowlist index if ODR's name files have changed
    17 * * * * /usr/bin/php /home/rruff/wordpress/wp-content/themes/odr-astra-child-theme/odr_search_gate.php \
                 --build-index \
                 --source-dir=/home/rruff/data-publisher/web/uploads/IMA \
                 --out=/home/rruff/data-publisher/var/tmp/odr-search-allowlist.php \
                 --if-stale

Adjust all three paths. `--out` must match the **Index file** setting. `--if-stale` is what makes
it safe to run every hour: it exits with `index is current, nothing to do` unless a source file is
newer than the index's record of it.

Check the destination is writable by whoever runs the cron before trusting the crontab:

    sudo -u www-data test -w /home/rruff/data-publisher/var/tmp && echo writable

With `var/tmp` at mode `1777` this holds for any user. If you moved the index somewhere tighter,
run the cron as the user that owns it.

To confirm it works, run the command by hand twice — the first run writes, the second says the
index is current.

## 4. Check it

Anonymous (use a private window, or `curl`):

| Request | Expected |
| --- | --- |
| `/Abellaite` | 302 — to the cached page if that record has one, otherwise to the search |
| `/R040031` | 302 to the RRUFF ID search |
| `/amcsd/augite` | 302 to the AMCSD search |
| `/R12345` | challenge — only `R` plus exactly six digits is allowed |
| `/somethingmadeup` | challenge |
| `/`, `/odr/ima`, `/robots.txt` | unchanged |

Fail a challenge on purpose and the same unlisted request should return a 216-byte 404 **without**
showing another challenge, while `/Abellaite` keeps working. That is the behaviour that keeps
reCAPTCHA usage inside the free tier.

## When a gated url behaves differently on one server

Run the bundled diagnostic on the server in question:

    php tools/odr_gate_check.php /path/to/wordpress <your-public-ip>

It is read-only. The second argument is the address of the browser you are testing from, because
the ban and pass markers are per visitor, not per server. It reports which theme is loaded, how
many gate call sites `functions.php` actually has, every setting, the allowlist index, the crawler
ranges, and whether that visitor is banned, recently passed, or a verified crawler.

Reading the result:

- **`gate loaded : NO`**, or a call-site count that doesn't match the deployed code, means the
  running `functions.php` isn't the one on disk.
- **Everything matches a working server, but the site still behaves like the old code** — reload
  PHP-FPM. With `opcache.validate_timestamps=0` it serves previously compiled code until it is
  cycled, and this script *cannot* see that, because the CLI has its own opcache separate from
  FPM's. Worth adding an FPM reload to the deploy steps.
- **`passed recently : YES`** for your address means the gate is correctly letting you through
  because you passed a challenge inside the ban window. Test from a phone on cellular to see what
  a first-time visitor gets.
- **`allow_verified_crawlers : ON`** means anything inside those published ranges skips the
  challenge by design, so don't test from a datacenter address that might fall in them.

## Notes

- About 40% of the minerals on these lists have no cached static page. Those fall through to the
  normal search, which is why the index stores the UUID but the gate still checks the filesystem.
- The ban list uses WordPress transients keyed by a hash of `REMOTE_ADDR`. They expire on their
  own; there is nothing to clean up. To lift a ban early, delete the transient
  `odr_sg_block_<md5 of the address>`.
- `REMOTE_ADDR` is deliberate: a forwarding header is client-supplied, and a ban that can be shed
  by sending a header is no ban. If this site ever goes behind a CDN, `odr_sg_client_ip()` has to
  become that CDN's header (Cloudflare: `HTTP_CF_CONNECTING_IP`), or every visitor will look like
  one address.
