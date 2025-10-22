# Salah SEO Plugin QA Report

## TL;DR
- **Status:** Needs Fixes
- Background queue lock expires before the default task timeout, so concurrent runs can overlap and corrupt state under load.
- Internal link injector still rewrites keywords inside shortcode/structured blocks, violating the exclusion requirements and risking broken markup.
- Full integration test matrix (Cron, REST, WP-CLI, WooCommerce/Rank Math) could not be executed in this container; only static analysis and linting were completed.

## Test Matrix
| Area | Scenario | Expected Result | Outcome | Evidence |
| --- | --- | --- | --- | --- |
| Scheduler & Background Tasks | Queue run via WP-Cron with batch limit and delay | Single runner processes items sequentially, respects timeout/delay, and never overlaps | ⚠️ Blocked (no WP runtime) | Static review only |
| Scheduler & Background Tasks | Lock timeout vs. long-running batch (>60s) | Runner stays exclusive until completion | ❌ Lock TTL (60s) shorter than task_timeout (120s) so second runner can start concurrently | `acquire_lock()` TTL vs. `task_timeout` defaults 【F:includes/class-salah-seo-helpers.php†L318-L348】【F:includes/class-salah-seo-scheduler.php†L64-L103】 |
| Internal Linking | Content containing `[shortcode]` or block markup | No anchors injected inside shortcodes or protected blocks | ❌ XPath selector ignores shortcodes; DOMDocument mutates shortcode text | `apply_internal_links_to_content()` XPath conditions 【F:includes/class-salah-seo-helpers.php†L420-L516】 |
| Redirect Manager | Change slug twice | Redirect table deduplicates and 301 fires once | ⚠️ Blocked | Static review |
| Schema Integration | Product schema with Rank Math active | JSON-LD merged without duplicates, passes validation | ⚠️ Blocked | Static review |
| Social/OpenGraph | Missing product image | Fallback image emitted once | ⚠️ Blocked | Static review |
| WooCommerce Brands/Attributes | Brand term lacking description | Auto description filled, dry-run lists diff only | ⚠️ Blocked | Static review |
| REST API Security | `POST /salah-seo/v1/autofill` without capability | 401/403 with clear message | ⚠️ Blocked | Static review |
| WP-CLI Commands | `wp salah-seo autofill --dry-run` | Reports diff, makes no changes | ⚠️ Blocked | Static review |
| Dry-Run UX | Dashboard dry-run button | Shows diff modal without saving | ⚠️ Blocked | Static review |

## Performance Measurements
Real execution measurements (TTFB, DB queries, memory, DOMDocument cost) were **not** captured because WordPress is unavailable in this containerized environment. No regression data could be gathered.

## Errors & Warnings
- ❌ **Queue lock duration mismatch:** `Salah_SEO_Helpers::acquire_lock()` hard-codes a 60s transient TTL while the scheduler allows batches to run for 120s+, so a second runner can start after 60s and operate on the same queue simultaneously.【F:includes/class-salah-seo-helpers.php†L332-L347】【F:includes/class-salah-seo-scheduler.php†L64-L112】
- ❌ **Shortcode contamination risk:** `apply_internal_links_to_content()` injects anchors into every text node except a limited list of ancestors. Shortcodes become plain text nodes after DOMDocument parsing, so keywords inside `[shortcode]` or block serialization (e.g., `<!-- wp:... -->`) are still eligible and will be wrapped, violating the exclusion list and potentially corrupting block structures.【F:includes/class-salah-seo-helpers.php†L432-L517】
- ⚠️ **Untested integrations:** Without a WordPress runtime the Cron, REST, WP-CLI, Rank Math, WooCommerce, and cache compatibility checks remain unverified. Manual staging validation is still required.

No PHP warnings/notices were observed during static linting (`php -l`).

## Recommendations
1. **Extend lock TTL dynamically:** When acquiring the queue lock, set the transient lifetime to `max($task_timeout, $batch_size * per_item_budget)` and refresh/heartbeat while the batch runs so long tasks cannot overlap. Also ensure the lock is cleared on fatal exits (e.g., shutdown handler).【F:includes/class-salah-seo-helpers.php†L332-L347】【F:includes/class-salah-seo-scheduler.php†L64-L112】
2. **Harden shortcode/block exclusions:** Before DOM parsing, replace protected regions (shortcodes, HTML comments with `wp:` markers) with placeholders, or post-process to restore them. Alternatively, skip text nodes whose parents originate from shortcode placeholders detected via `has_shortcode`/`parse_blocks`. Add unit tests covering `[shortcode]` and block content to confirm no anchors are injected.【F:includes/class-salah-seo-helpers.php†L432-L517】
3. **Stage full integration suite:** Run the WP-Cron, REST, WP-CLI, Rank Math, WooCommerce, and cache plugin tests on a staging site, capturing TTFB/queries/memory before & after, verifying Dry-Run diff accuracy, redirect creation, schema validation, and og:image fallbacks. Document WP_DEBUG logs to confirm zero notices.

## Assurance Statement
Because the blocking issues above remain unresolved and key scenarios could not be executed, the plugin is **not yet ready for production**. Further fixes and end-to-end validation on a WordPress staging environment are required to guarantee safety, correctness, and performance compliance.
