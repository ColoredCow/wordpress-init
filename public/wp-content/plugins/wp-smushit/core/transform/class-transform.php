<?php

namespace Smush\Core\Transform;

use Smush\Core\Parser\Page;
use Smush\Core\Parser\Rest_Content;

interface Transform {
	/**
	 * Should the current page content be transformed? This is for the whole page.
	 * Transforms may choose to ignore individual URLs or elements in the other methods.
	 * @return bool
	 */
	public function should_transform();

	/**
	 * @param $page Page
	 *
	 * @return void
	 */
	public function transform_page( $page );

	public function transform_image_url( $url );
}
