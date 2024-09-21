# WDEV Free Notices Module #

WPMU DEV Free Notices module (short wpmu-free-notices) is used in our free plugins hosted on wordpress.org
It will display,

* A welcome message upon plugin activation that offers the user a 5-day introduction email course for the plugin.

* After 7 days a message asking the user to rate the plugin on wordpress.org.

* After 2 days a giveaway notice asking for email subscription.

# How to use it #

1. Insert this repository as **sub-module** into the existing project

2. Include the file `module.php` in your main plugin file.

3. Call the action `wpmudev_register_notices` with the params mentioned below.

4. Done!


# Upgrading to v2.0

The 2.0 release is backward incompatible with the 1.x versions. To accommodate new functionality and fix WordPress coding standards violations, a lot of the hooks/filters have been refactored.
Make sure to change the following:

1. Update the `do_action` hook name from `wdev-register-plugin` to `wpmudev_register_notices`.
2. Update the params to new format (See example below).
3. Both `wdev-email-message-` and `wdev-rating-message-` filters have been changed to `wdev_email_title_`/`wdev_email_message_` and `wdev_rating_title_`/`wdev_rating_message_`

## IMPORTANT:

DO NOT include this submodule in Pro plugins. These notices are only for wp.org versions.


## Code Example : Registering a plugin (from Smush) ##

```
#!php

<?php
add_action( 'admin_init', 'mycustom_free_notices_init' );

function mycustom_free_notices_init() {
        // Load the notices module.
        include_once 'external/free-notices/module.php';
        
        // Register the current plugin for notices.
        do_action(
            'wpmudev_register_notices',
            'smush', // Required: plugin id. Get from the below list.
            array(
                'basename'     => WP_SMUSH_BASENAME, // Required: Plugin basename (for backward compat).
                'title'        => 'Smush', // Plugin title.
                'wp_slug'      => 'wp-smushit', // Plugin slug on wp.org
                'cta_email'    => __( 'Get Fast!', 'ga_trans' ), // Email button CTA.
                'installed_on' => time(), // Plugin installed time (timestamp). Default to current time.
                'screens'      => array( // Screen IDs of plugin pages.
                    'toplevel_page_smush',
                    'smush_page_smush-bulk',
                    'smush_page_smush-directory',
                ),
            )
        );
}
```

> IMPORTANT: Make sure to initialize this on a hook which is executed in admin-ajax requests too. The recommended hook is `admin_init`

## Plugins and IDs
Only wp.org plugins are listed below.

| Plugin      | ID          |
|-------------|-------------|
| Smush       | smush       |
| Hummingbird | hummingbird |
| Defender    | defender    |
| SmartCrawl  | smartcrawl  |
| Forminator  | forminator  |
| Hustle      | hustle      |
| Snapshot    | snapshot    |
| Branda      | branda      |
| Beehive     | beehive     |


## Testing Notices

To see the notices before the due time, you can fake the current time by appending `&wpmudev_notice_time=CUSTOMTIMESTAMP` to the url on a page where the notice should be visible. Please make sure you are using a timestamp after the due time.

## Optional: Customize the notices via filters ##

```
<?php
// The email message contains 1 variable: plugin-name
add_filter(
    'wdev_email_message_smush', // change plugin id.
    'custom_email_message'
);
function custom_email_message( $message ) {
    $message = 'You installed %s! This is a custom <u>email message</u>';
    return $message;
}
```

```
<?php
// The rating message contains 2 variables: user-name, plugin-name
add_filter(
    'wdev_rating_message_smush', // Change plugin id.
    'custom_rating_message'
);
function custom_rating_message( $message ) {
    $message = 'Hi %s, you used %s for a while now! This is a custom <u>rating message</u>';
    return $message;
}
```

```
<?php
// To disable or enable a notice type.
add_filter(
    'wpmudev_notices_is_disabled',
    'custom_rating_message',
    10,
    2
);
function disable_rating_message( $disabled, $type, $plugin ) {
    if ( 'rate' === $type && 'beehive' === $plugin ) {
        return true;
    }
    
    return $disabled;
}
```

# Development

Do not commit anything directly to `master` branch. The `master` branch should always be production ready. All plugins will be using it as a submodule.

## Build Tasks (npm)

Everything should be handled by npm. Note that you don't need to interact with Gulp in a direct way.

| Command              | Action                                                 |
|----------------------|--------------------------------------------------------|
| `npm run watch`      | Compiles and watch for changes.                        |
| `npm run compile`    | Compile production ready assets.                       |
| `npm run build`  | Build production ready submodule inside `/build/` folder |

## Git Workflow

- Create a new branch from `dev` branch: `git checkout -b branch-name`. Try to give it a descriptive name. For example:
    -   `release/X.X.X` for next releases
    -   `new/some-feature` for new features
    -   `enhance/some-enhancement` for enhancements
    -   `fix/some-bug` for bug fixing
- Make your commits and push the new branch: `git push -u origin branch-name`
- File the new Pull Request against `dev` branch
- Assign somebody to review your code.
- Once the PR is approved and finished, merge it in `dev` branch.
- Checkout `dev` branch.
- Run `npm run build` and copy all files and folders from the `build` folder.
- Checkout `master` branch and replace all files and folders with copied content from the build folder.
- Commit and push the `master` branch changes.
- Inform all devs to update the submodule.