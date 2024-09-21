<?php

$baseDir = __DIR__;

$class_map = array(
	'WPMUDEV_Analytics'                                             => $baseDir . '/core/class-wpmudev-analytics.php',
	'WPMUDEV_Analytics_Vendor\\Base_MixpanelBase'                   => $baseDir . '/vendor_prefixed/Base/MixpanelBase.php',
	'WPMUDEV_Analytics_Vendor\\ConsumerStrategies_AbstractConsumer' => $baseDir . '/vendor_prefixed/ConsumerStrategies/AbstractConsumer.php',
	'WPMUDEV_Analytics_Vendor\\ConsumerStrategies_CurlConsumer'     => $baseDir . '/vendor_prefixed/ConsumerStrategies/CurlConsumer.php',
	'WPMUDEV_Analytics_Vendor\\ConsumerStrategies_FileConsumer'     => $baseDir . '/vendor_prefixed/ConsumerStrategies/FileConsumer.php',
	'WPMUDEV_Analytics_Vendor\\ConsumerStrategies_SocketConsumer'   => $baseDir . '/vendor_prefixed/ConsumerStrategies/SocketConsumer.php',
	'WPMUDEV_Analytics_Vendor\\Mixpanel'                            => $baseDir . '/vendor_prefixed/Mixpanel.php',
	'WPMUDEV_Analytics_Vendor\\Producers_MixpanelBaseProducer'      => $baseDir . '/vendor_prefixed/Producers/MixpanelBaseProducer.php',
	'WPMUDEV_Analytics_Vendor\\Producers_MixpanelEvents'            => $baseDir . '/vendor_prefixed/Producers/MixpanelEvents.php',
	'WPMUDEV_Analytics_Vendor\\Producers_MixpanelGroups'            => $baseDir . '/vendor_prefixed/Producers/MixpanelGroups.php',
	'WPMUDEV_Analytics_Vendor\\Producers_MixpanelPeople'            => $baseDir . '/vendor_prefixed/Producers/MixpanelPeople.php',
);

spl_autoload_register( function ( $class_name ) use ( $class_map ) {
	if ( isset( $class_map[ $class_name ] ) && file_exists( $class_map[ $class_name ] ) ) {
		require $class_map[ $class_name ];
	}
} );
