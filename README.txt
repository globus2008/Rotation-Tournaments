=== Rotation Tournaments ===
Contributors: globus2008
Tags: tournament, game, ranking, sport, tennis
Requires at least: 6.5
Tested up to: 7.0
Stable tag: 1.6.2
License: GPLv3 or later
License URI: [GNU GPL v3.0](https://www.gnu.org/licenses/gpl-3.0.html)


Do you play Singles or Doubles Tournaments? This plugin manages Rotation Tournaments where players have a different partner in each game.

== Description ==

**Rotation Tournaments** provides a platform for organizing all kinds of sports Singles and Doubles Tournaments, e.g.

- Tennis
- Table Tennis
- Padel
- Badminton
- Beach Volleyball
- Squash

In **Rotation Tournaments**, each participant plays against every other participant and there are no eliminations in individual rounds. To maximize the variety of match combinations, the matches are typically played in a single set.

The **Rotation Tournaments** have a strong social aspect because its goal is to keep players in the tournament until the very end. The situation where weaker players would leave the tournament due to elimination rounds is eliminated. It allows grouping players into a special category with special conditions. This special setting can be utilized, for example, in mixed tournaments.

The participants of a tournament change partners several times during the tournament by a random selection and play against different players. The ratio of games won to games lost is calculated for each participant. The winner of the tournament is the player with this highest ratio. While a Singles Rotation Tournament can be organized without the aid of technology, in the case of a Doubles Rotation Tournament, this is not possible.

**This plugin is the server-side for the "Rotation Tournaments" Android app.**

To get the full experience of managing tournaments on the go, download our mobile app, now available on Google Play!

== Features ==

**General Features:**

- Seamless scheduling of matches.
- Real-time score tracking.
- Customizable settings for each tournament.
- User-friendly interface for administrators and participants.
- Comprehensive overview of player quality.
- No downtime during the tournament.
- Integration with website user accounts.
- Suitable for various sports: tennis, table tennis, squash, padel, badminton, beach volleyball, and more.

**Singles Rotation Tournament:**

- Alternative form of a Singles Tournament where players face each other without elimination rounds.
- Depending on the time options, everyone plays against everyone.

**Doubles Rotation Tournament:**

- Alternative form of a Doubles Tournament where players enter each match with a different partner and in different positions (alternating left and right sides).
- Rotation of teammates, ensuring variety in partnerships.
- Individuals can enter the tournament without a permanent teammate.
- Suitable for odd numbers of players and minimum lineups of 4 players.
- Define special groups of players with unique conditions.
- No eliminations, ensuring all players stay in the game.
- Shorter matches for more participation.
- Tournament can be interrupted or extended without disruption.
- Individual players can join or leave the tournament at any time.
- Ranking based on the ratio of games won to games lost.
- Announcement of the Best Player and Most Ideal Pair at the end of the tournament.

**YouTube quick intro:**

- Video for a quick introduction to the Rotation Tournament: 

[youtube https://www.youtube.com/watch?v=NoL9aPTv8u8]

= More information =

Visit [test page](https://doroto.ltcchrast.cz/) for more information, try to create your own tournament and take a look at [Rules of the Doubles Rotation Tournament](https://doroto.ltcchrast.cz/rules-of-the-doubles-rotation-tournament/).

== Screenshots ==

1. Matches drawn. (Wordpress)
2. Basic overview of the tournament. (Android app)
3. Matches played. (Wordpress)
4. Table of players with rankings. (Android app)
5. Editing tournament parameters. (Wordpress)
6. Matches drawn. (Android app)
7. Tournament table. (Wordpress)
8. Matches played. (Android app)
9. Individual tournament participants enter their results into the WordPress server and share the data with each other.


== Installation ==

**How to install Wordpress plugin:**

1. Install the plugin as usual.
2. After activating the plugin, 4 pages will be created: one for managing tournaments (main page), one with tournament rules, and additional pages for Privacy Policy and Terms of Service. A sample post announcing a newly created tournament will also be added.
3. Start by clicking on "Create a new tournament" under "Tournament Selection …" on the main page.
4. Then you can log in your created tournament and as a tournament´s administrator you can add manually also other players sourced from WP database.
5. If you have a sufficient amount of players then you can close a registration and start playing matches.
6. You can change tournament settings on the main page or you can look at 2 new admin pages that are dedicated for overall environmental settings and for description of all possible shortcodes.
7. At the end close the tournament and look who was announced as the winner.

**How to install the Android app:**

1. Search for "Rotation Tournaments" on the Google Play Store.

2. Or download it directly from this link: https://play.google.com/store/apps/details?id=cz.doroto.app

3. After installation, go to the app's settings and enter the address of your website where this plugin is installed.


== FAQ ==

= Does each player have to have an account created on my website? =

Yes, each tournament participant must have their own account. If a website has disabled user registration, this account must be created by the website administrator.

= Is this plugin suitable for singles tournaments? =

Yes. When creating a tournament, you can choose whether to create a singles or a doubles tournament.

= Will there be an iPhone or iPad version of the app? =

Yes. An iOS version of the Rotation Tournaments app is planned and will be released in the near future.

= Will my website be visible in the Rotation Tournaments Android app? =

Yes. If you install the Rotation Tournaments plugin, allow player registration, and enable visibility in the website list, your site can appear in the Android app. Initially, someone must manually enter your website address into the app. After that, it becomes visible in the list for all users.

= What sports tournaments is this plugin for? =

This plugin can be used for all types of sports where 2 or 4 players compete in singles or doubles.
You can use this plugin for tennis, table tennis, squash, padel, badminton, beach volleyball and probably for more.


== Changelog ==
= 1.6.2 - 2026/10/04 =
* Improvement - the draw chooses the opposing team (and the teammate) by previous meetings too: players meet more different opponents and the same players meet less often (with 14 players on 2 courts, pairs that met 3 or more times dropped from about 5 to 1 per evening)
* Improvement - among players with the same number of new teammates left, the one who played fewer games is drawn first; sides (left/right) alternate for both players of a team
* Add - REST route skip-matches: the organizer skips several ongoing matches at once and new matches are drawn from all free players
= 1.6.1 - 2026/10/02 =
* Fix - removing a payment no longer turns the payment list into a JSON object (the Android app could crash on the statistics page)
* Fix - a deleted account leaves its open tournaments: removed when it has not played yet, otherwise suspended; it also leaves the organizers, special group and payments (it was drawn into matches as "Unknown player")
* Fix - saving the tournament settings rejects an unknown tournament type
* Add - the tournament page has an "Open in the app" link: Android opens the tournament in the app, or Google Play when it is missing
* Add - app tournament links on the central site (?tournament_id=5&doroto_site=<club>) are forwarded to the club's tournament page when the app is not installed (sites from the site directory; other sites get a page with a link)
= 1.6.0 - 2026/10/02 =
* Security - front-end forms check tournament admin rights, not only the nonce (any logged-in user could change players, payments or delete a tournament)
* Security - admin actions sent as links are protected by a nonce (CSRF)
* Security - guided tour actions need a nonce; regenerating the example tournaments is throttled
* Security - final doubles and registration toggle check tournament admin rights
* Security - registration from the app (incl. a new Google account) respects "Anyone can register"
* Security - an account created by an organizer no longer returns a login session of the new player to the organizer
* Security - with registration disabled only tournament organizers can create player accounts from the app (before, any signed-in player could)
* Fix - results entered on the web no longer store last_update = 0, so the Android app refreshes again
* Fix - every write to a tournament updates last_update
* Fix - tournament lock: results entered at the same time on several courts are no longer lost
* Fix - every change of a running tournament (correcting a result, suspending or removing a player, settings, payments, special group, web forms) runs under the tournament lock; correcting a result while another court saved its result could stall the whole tournament
* Fix - several devices signing in or refreshing their session at the same moment no longer sign each other out
* Fix - database upgrade adds new columns also on already installed sites
* Fix - Authorization header is read also on FPM/CGI hosts
* Fix - REST responses are never served from page caches or CDNs
* Change - invitation link only joins the tournament (a second click no longer unregisters the player), redirects to the login page and back, and then to the tournament page
* Change - the app can be signed in on several devices at once (up to 10 per user)
* Change - the default name of a new tournament uses the site time zone, not UTC
* Change - tournament links to the home page ("?tournament_id=5") and the old app QR codes ("/tournament?id=5") open the tournament page
* Add - REST endpoint create-player: the organizer creates a player and adds them to the tournament in one step; the player gets an e-mail to set the password
* Add - optional mode join/leave for the app join endpoint
* Add - the tournament page shows a short link to the Android app (can be turned off in Settings -> Mobile app)
* Change - the review request is shown only to administrators, 14 days after activation once the site has its own tournament, and comes back 7 days after "Remind me" until the plugin is rated
= 1.5.8 - 2026/06/12 =
* Fix - fixed some errors found in Plugin Check

= 1.5.7 - 2026/06/06 =
* Fix - some translations

= 1.5.6 - 2026/05/13 =
* Fix - register and sign in for android app

= 1.5.5 - 2026/05/12 =
* Add - remove admin for android app

= 1.5.4 - 2026/04/22 =
* Fix - rest value is safe now

= 1.5.3 - 2025/12/05 =
* Fix - stable login to android application

= 1.5.2 - 2025/08/26 =
* Fix - existing players are not repeated in the menu Add players

= 1.5.1 - 2025/08/17 =
* Change - Public release of Android app

= 1.5.0 - 2025/08/08 =
* Fix - Add 'last update' to the database also for already installed plugings

= 1.4.9 - 2025/08/07 =
* Fix - Add 'last update' to the database
* Fix - Uninstall also 2 new pages (privacy policy and terms of service)

= 1.4.8 - 2025/08/07 =
* Fix - Improved some functions for Android app

= 1.4.7 - 2025/07/27 =
* Add - Location of the tournament.
* Add - Controls the visibility of the tournament in the public list.
* Add - Support for a Android app
* Add - Tournaments filtering by distance and visibility
* Fix - Correct an error message Undefined array key player['count']
* Add - Info about last update speed up loading
* Add - Terms of Service page
* Add - Privacy Policy page
* Add - Announcement about the opportunity to become an application tester

= 1.4.6 - 2025/06/14 =
* Fix - Parameter When enough players are available had sometimes a wrong value.
* Fix - Log in/out block had a problem

= 1.4.5 - 2025/06/09 =
* Fix - Register and log in a new user only if anyone can register (system settings).
* Fix - Match hiding correction

= 1.4.4 - 2025/05/25 =
* Add - Register and log in a new user.

= 1.4.3 - 2025/05/04 =
* Fix - Translation loading too early warning.

= 1.4.2 - 2025/04/16 =
* Add - Dashboard overview widget.

= 1.4.1 - 2024/12/31 =
* Add - Log-link block.

= 1.4.0 - 2024/12/27 =
* Fix - Sometimes tournament_id was unknown.

= 1.3.9 - 2024/12/21 =
* Fix - Guaranteed visibility of the selected tournament in the tournament table.
* Fix - Preserving stored values on the backend.

= 1.3.8 - 2024/12/15 =
* Fix - Permalink structure also works with the floating help icon.
* Fix - Error with hide parameter.

= 1.3.7 - 2024/12/13 =
* Add - Prevent cashing for logged users.
* Add - Floating help icon.
* Add - More tournament examples for demonstration.
* Fix - Tournament name is not copied from previous one.

= 1.3.6 - 2024/11/02 =
* Add - Special group count.
* Fix - Change game results availability.
* Add - Tournament progress info.
* Fix - Doubles Badminton table also for co-players

= 1.3.5 - 2024/09/28 =
* Fix - Opponent selection.
* Change of information about the end of the tournament round.

= 1.3.4 - 2024/09/26 =
* Fix - Pair selection didn´t take into account number of played games in total.
* Add - Pair selection mechanism explanation on the help page.

= 1.3.3 - 2024/09/13 =
* Fix - Do not show instructions during the tournament presentation.
* Fix - Rights to add other players into tournaments.
* Fix - Rights to add a tournament admin.
* Fix - Rights to add a special group.
* Fix - Rights to Suspension and Resumption.

= 1.3.2 - 2024/07/18 =
* Add - Admin can change a default value for minimum number of games played before a player can be declared a winner.

= 1.3.1 - 2024/07/12 =
* Add - The ability to specify a minimum number of games played before a player can be declared a winner.

= 1.3.0 - 2024/07/06 =
* Fix - Removing a player from a tournament.

= 1.2.9 - 2024/06/23 =
* Fix - Ready for testing in WP Playground.

= 1.2.8 - 2024/06/21 =
* Fix - Solving some database errors with empty values.

= 1.2.7 - 2024/06/07 =
* Fix - Update for WP Playground.

= 1.2.6 - 2024/05/29 =
* Fix - Improved playmate choose.

= 1.2.5 - 2024/05/15 =
* Fix - Problems with removing players during a game.

= 1.2.4 - 2024/05/08 =
* Fix - A currently playing player can´t be removed.

= 1.2.3 - 2024/04/19 =
* Fix - Fix real count of players after their removing.

= 1.2.2 - 2024/04/17 =
* Fix - Fix an error during removing a player from the tournament.

= 1.2.1 - 2024/03/21 =
* Fix - icon format.

= 1.2.0 - 2024/03/20 =
* Fix - Fixed a bug in the database that prevented a tournament record from being created on some servers.
* Modification for WP Playground.
* Fix - Redirection to a tournament in case of a user entry.

= 1.1.9 - 2024/03/09 =
* Fix - Unregistered users can no longer enter results in the singles tournament.

= 1.1.8 - 2024/03/07 =
Possibility to remove players from an already ongoing tournament.

= 1.1.7 - 2024/03/01 =
* Fix - If all tournaments were deleted, a new tournament could not be created.
* New - Optional login prompt.
* New - If the player has not yet registered for any tournament, it is possible to choose whether to show him a quick help.
* New - Expanded the ability for tournament administrators to browse through players they have already met at the tournament.

= 1.1.6 - 2024/02/25 =
Trial tournament improvements.
Added the option to announce the end of the tournament round.

= 1.1.5 - 2024/02/20 =
Improved the visual appearance of the main page.
Badminton added to the list of available tournaments.

= 1.1.4 - 2024/02/18 =
Option to announce separate winners from the special group and outside the special group.

= 1.1.3 - 2024/02/17 =
Notices after a plugin activation.
Added player trend.

= 1.1.2 - 2024/02/13 =
Modified tournament editing tab.
Added option to change tournament type.

= 1.1.1 - 2024/02/09 =
Reactivating the plugin doesn´t affect the already deleted tournament example. 
Unblock offering maximum games count.
Fix tournament change when entering results.
Added quick help for newcomers.
Allow the final match also for singles.

= 1.1.0 - 2024/02/04 =
Added the option for single tournaments. 
Created an environment for additional sports.

= 1.0.4 - 2024/02/02 =
The administrator can now use 'hide' option when editing the match result.
Free score input is allowed. This opens using not only for tennis but for example for table tennis or beach volleyball.

= 1.0.3 - 2024/01/26 =
Minor changes in code:
* Allow to transfer organizer rights to a non-playing user.
* Ensuring an even rotation of serves at the start of the tournament.

= 1.0.2 - 2024/01/21 =
Fixed choosing between left and right side in first matches.

= 1.0.1 - 2024/01/03 =
A small modification of admin screen.

= 1.0.0 - 2024/01/01 =
First release to public.
