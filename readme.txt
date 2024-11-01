=== SpotBot ===
Contributors: JakeHelbig
Donate link: http://www.jakehelbig.com/donate
Tags: comments, spam, login, bots
Requires at least: 3.6
Tested up to: 3.7.1
Stable tag: 0.1.8
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

SpotBot aids in controlling spam before it clutters up your server. It stops bots in their tracks before they have a chance to post or force post.

== Description ==
SpotBot was created for the purpose of controlling spam before it clutters up your server. Gone are the days of constant comments about Gucci bags in broken English. SpotBot not only stops spam from comments, but also stops bots from creating fake accounts or even logging in, but the fun doesn't stop there! A custom action has been built in so you can add it into any template to stop access to that page!

= So how does it work? =
Every task that starts or ends in WordPress starts with an action! SpotBot utilizes these actions, to gain control over who has access to your site! But how can it tell who's bad and who's good? SpotBot uses a service from BotScout to determine who the baddies are. They have allowed public access to their database through an API that SpotBot utilizes. This API access is limited though, and only allows around 300 queries to it in any 24-hour period. You could pay BotScout for more access as they're fairly cheap, but to help them keep their costs down and help you have control over your visitors, all IP's are logged to your database. Each time a returning IP is found, it checks the cache first and if nothing is found checks BotScout for more information.
It's as simple as that, SpotBot can be a set-it-and-forget-it plugin, but it can be more than that. Have a problem with a particular user that keeps making user accounts? Flag them as a bot! The harder they try, the more flags they generate and the higher they go on the bot score!
SpotBot comes with a built in search function to make finding those troublesome bots easier than ever. Don't want to save the IP in a list of trouble makers? Make a note of them in each IPs note section for easy searching. Can only remember the first 3 digits of the IP address? Search for it!

== Installation ==
<ol>
<li>Download the spotbot zip from the wordpress plugins directory</li>
<li>Extract the `spotbot` directory from the zip</li>
<li>Upload the `spotbot` directory to the `/wp-content/plugins/` directory</li>
<li>Activate the plugin through the 'Plugins' menu in WordPress</li>
<li>Apply for a BotScout API key from here http://botscout.com/getkey.htm</li>
<li>After you have recieved your key, go to the 'Settings' submenu in SpotBot</li>
<li>Paste the key you recieved from BotScout in the API Key textbox and click 'Check it!'
  <ul>
    <li>If 'Check it!' turns into a green 'OK!', save via the button at the bottom.</li>
    <li>If 'Check it!' turns into a red 'INVALID!' verify that your key is correct.  If it IS correct try again later.</li>
  </ul>
</li>
<li>Set your custom Warning HTML message when '<em>bots</em>' view the comment or login forms.  This message can be composed of any HTML formatting.</li>
</ol>

== Frequently Asked Questions ==
= How to use the custom action! =
<p>SpotBot uses actions within Wordpress to basically do what it does.  There is also a custom action built in that will allow you to block access to the entire website if you see fit.  Although I don't recommend blocking your entire site, it's ultimately your choice on how to use this feature.</p>
<p>While creating your custom template, post the following code within the php file:</p>
<pre>doaction('stopthebot');</pre>
<p>It must be inserted sometime after your header has been called, as none of the scripts required have been loaded before then.</p>
<hr />

= What's an API key, and why you need one! =
<p>An API key will allow you access to the BotScout API.  Each time a user applies for an API key, an accout is generated with BotScout and is used to monitor traffic and query counts.  By default BotScout only allows 300 queries to be run per day from a user.  You can "buy" more if you wish by <a href="http://botscout.com/corp_users.htm" target="_BLANK" alt="buy more queries per day">donating</a> to the BotScout cause.  However signing up is free and easy.  Here's how easy it is:</p>
<p>Fill out the information <a href="http://botscout.com/getkey.htm" target="_BLANK" alt="botscout apply for api key">here</a>.  It may take up to 24 hours for you to get your API key, but it usually is a matter of minutes.  Be sure to check your spam filter, as sometimes email providers block keywords such as bot.</p>
<p>Once you get your email there will be an multi-case alphanumeric code.  It should be around 15 characters long.  Select and copy your key, then paste it in the API Key area in SpotBot settings.</p>
<p>To validate your key, click on "Check it!".  If it says <span style="color: green;">OK!</span>, then your key is valid and working(Don't forget to hit the Save Options button at the bottom!)</p>
<p>However, if your check comes back as <span style="color: red;">INVALID!</span>, there is either something wrong with your key or BotScout has not updated access for you yet.  Wait an hour, and try again.  If problems persist, <a href="http://botscout.com/contact.htm" target="_BLANK" alt="contact botscout">contact BotScout</a> for more details.</p>

== Changelog ==

= 0.1.7 =
* Initial release

= 0.1.8 =
* fixed IP cleanup
* all IP's set to be cleaned from DB will only be removed if the notes section is empty
* added debug mode for easier development
* tested and working for WP 3.7.1