=== Digitizer Shabbat Keeper ===
Contributors: benkalsky
Tags: shabbat, jewish holidays, woocommerce, maintenance mode, israel
Requires at least: 5.7
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Closes your site, or only its shop and forms, for Shabbat and Jewish holidays. Israeli times, computed on your server - no external service.

== Description ==

Digitizer Shabbat Keeper closes a WordPress site from candle lighting to Havdalah, every Shabbat and every Jewish holiday, without anyone touching it. You pick a city once. From then on the plugin works out the times itself, on your own server, and nothing is sent anywhere.

**It is built for sites in Israel.** The holidays are the Israeli ones (one day each), the cities are Israeli cities, and every time it shows is Israel time. A site outside Israel is not what it is for - see the FAQ.

**While the site is closed, purchases and form submissions are refused.** That is the purpose of the plugin, and it is what your visitors will meet on Friday evening. Administrators are never blocked, so you can always get in.

= Two ways to close =

* **Closed** - every visitor sees a "closed for Shabbat" page, with your title, your message and the time the site reopens. The page answers with HTTP 503 and a Retry-After header, which search engines read as a temporary outage, not as a missing site.
* **Open to read, closed for business** - pages stay readable. Purchases and forms are refused, and a banner at the top of every page says when the site reopens.

= What is refused in business mode =

* WooCommerce purchases - in classic templates, in the cart and checkout blocks, through the Store API, and when a customer tries to pay for an existing order
* Form submissions from Elementor Pro Forms, Contact Form 7, WPForms and Gravity Forms
* Comments
* Optionally, phone and WhatsApp links are hidden

Every refusal happens on the server. Hiding a button is a courtesy; the refusal is what makes it true.

= The times =

* Candle lighting follows the custom of each city: 40 minutes before sunset in Jerusalem, 30 in Haifa, 22 in Beer Sheva, 18 elsewhere. Fifteen cities are built in, and you can enter coordinates for any other place in Israel.
* Havdalah is 42 minutes after sunset, or 72 (Rabbeinu Tam) if you prefer.
* Shabbat, both days of Rosh Hashana, Yom Kippur, the first day of Sukkot, Shmini Atzeret, the first and seventh days of Pesach, and Shavuot. A holiday next to Shabbat is one closure, not two.
* The settings screen lists the next seven closures with their exact times, so you can check them before the first Friday.

= Good to know =

* **Starts working when you activate it**, with Jerusalem's times, until you choose your own city. A notice in wp-admin says so until you have saved the settings once.
* **Preview** the closed site at any time from the settings screen.
* **Override**: force the site open or closed by hand, for the day the calendar does not know about.
* **Page caches** - WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache, WP Fastest Cache, SiteGround Optimizer and Breeze are purged at the start and the end of every closure. A CDN that caches whole pages can hook its own purge to the `digitizer_shabbat_keeper_purge` action. A small script in each page also reloads it once a closure has started or ended, so a copy that a cache kept too long corrects itself.
* **Hebrew** is included.
* **Coming from Digitizer Pro Tools?** The settings of its Shabbat Keeper module are carried over the first time you activate this plugin.

= For developers =

* `digitizer_shabbat_keeper_purge` (action) - fires whenever cached pages should be thrown away: at each transition, after a settings save, on activation and deactivation.
* `digitizer_shabbat_keeper_transition` (action) - fires at each transition with one argument, whether the site is now closed.
* `digitizer_shabbat_keeper_exempt` (filter) - who is never blocked. Administrators by default.
* `digitizer_shabbat_keeper_refuse` (filter) - whether purchases and forms are refused in the current request. Never in WP-Cron, WP-CLI or wp-admin screens by default, so a shop manager can still build an order by hand.

== Installation ==

1. Install the plugin from the Plugins screen, or upload the ZIP, and activate it.
2. Go to Settings > Shabbat Keeper.
3. Choose your city and the way you want to close, and save.
4. Check the list of upcoming closures, and press "Preview the closed site" to see what visitors will see.

== Frequently Asked Questions ==

= Does it work outside Israel? =

It is not built for it. Outside Israel most holidays last two days and this plugin closes for one; its cities and its clock are Israel's. Custom coordinates are limited to Israel for the same reason.

= Does it send my data, or my visitors' data, anywhere? =

No. Sunset and the Hebrew calendar are computed inside the plugin. It contacts no service and loads nothing from another site.

= Will my Google ranking suffer if the site is closed every week? =

The closed page answers with HTTP 503 and a Retry-After header naming the end of the closure. That is the standard way to tell a search engine that a site is temporarily unavailable and should be visited again later.

= Can I get into my own site during Shabbat? =

Yes. Users who can manage options are never blocked, and wp-admin, the REST API and WP-CLI are left alone.

= My site is behind a CDN that caches whole pages. Will it close on time? =

Tell the CDN to purge when the `digitizer_shabbat_keeper_purge` action fires. Without that, the script in each page reloads it once the closure has started, and the reloaded request reaches your server. Purchases and forms are refused by the server in any case, however old the page a visitor is looking at.

= What happens to an order that was in the cart before candle lighting? =

It cannot be completed until the site reopens: checkout is refused on the server, and so is payment for an order that already exists.

= Does it close for fast days or Chol HaMoed? =

No. It closes for Shabbat and for the holidays on which work is forbidden.

= Is there a credit link on my site? =

Not unless you switch one on. The setting is under "Closed page", and it is off by default.

= What does it leave behind when I delete it? =

Nothing. Deleting the plugin removes its one option and its one scheduled event, on every site of a network.

== Screenshots ==

1. The page visitors see in closed mode, with the reopening time.
2. Business mode: the site stays readable, and a banner says when orders and forms reopen.

== Changelog ==

= 1.0.0 =
* First release.
