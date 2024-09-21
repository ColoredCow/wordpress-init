import Fetcher from './fetcher';

class Tracker {
	track( event, properties = {} ) {
		if ( ! this.allowToTrack() ) {
			return;
		}

		return Fetcher.common.track( event, properties );
	}

	allowToTrack() {
		return !! ( window.wp_smush_mixpanel?.opt_in );
	}
}

const tracker = new Tracker();

export default tracker;
