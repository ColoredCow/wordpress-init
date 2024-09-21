# WPMUDEV Analytics

This module:

### Tracks events:
The module counts the number of plugin’s events triggered in a day.
 
### Sets a limit:
Each plugin can set a limit.

### Prevents excess events: 
 
Once the limit is reached:

The module triggers a new event `exceeded_daily_limit` to Mixpanel.

The module stops sending all Mixpanel events from that site for 24 hours.

## Devs

This module exposes a single class `WPMUDEV_Analytics` which is a thin wrapper over the MixPanel class from the MixPanel composer dependency.

This means that all the methods that can be called on an instance of the MixPanel class can also be called on an instance of WPMUDEV_Analytics class. 

Internally WPMUDEV_Analytics keeps track of event counts and stops sending events if the limit is exceeded.

Please note that a scoped version of the composer dependency is already included within this package so it does not need to be included by plugins.

Example usage:
```
    if ( ! class_exists( 'WPMUDEV_Analytics' ) ) {
        require_once YOUR_SUBMODULES_DIR . '/wpmudev-analytics/autoload.php';
    }
    $analytics = new WPMUDEV_Analytics( 'slug', 'Plugin Name', $event_limit, $token, $mixpanel_options );
    $analytics->identify( 'unique_id' );
    $analytics->registerAll( $super_properties );
    $analytics->track($event, $properties);
```

## QA Testing

In production the limit will be high and when it is reached, the site will stop sending events for 24 hours.

To make testing convenient two constants have been included that can override the default behavior:

*WPMUDEV_ANALYTICS_EVENT_LIMIT*

This overrides the limit.

*WPMUDEV_ANALYTICS_TIME_WINDOW_SECONDS*

This overrides the time duration for which the site will be blocked from sending new events.

Let's say the following constants are defined:
```
define( 'WPMUDEV_ANALYTICS_TIME_WINDOW_SECONDS', 5 * 60 );
define( 'WPMUDEV_ANALYTICS_EVENT_LIMIT', 3 );
```
If more than 3 events are generated within 5 minutes then only 3 of those events will be tracked and one new `exceeded_daily_limit` event will be generated. 

The site will not send any new events for the rest of the current 5-minute window.

In other words when the above constants are defined then this module ensures that 3 _or fewer_ events are generated every 5 minutes.
