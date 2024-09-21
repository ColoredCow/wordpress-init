import Fetcher from './utils/fetcher';

class LoopbackTester {
	delayTimeOnFailure = 5000;

	performTest() {
		return new Promise( ( resolve, reject ) => {
			this.startTest().then( ( res ) => {
				if ( res?.success ) {
					this.getResult(
						resolve,
						() => {
							setTimeout( () => {
								this.getResult( resolve, reject, reject );
							}, this.delayTimeOnFailure );
						},
						reject
					);
				} else {
					reject( res );
				}
			} ).catch( ( error ) => {
				reject( error );
			} );
		} );
	}

	startTest() {
		return Fetcher.background.backgroundHealthyCheck();
	}

	getResult( successCallback, failedCallback, errorCallback ) {
		return this.fetchResult().then( ( status ) => {
			let data = status?.data;
			if (status?.success && data?.loopback) {
				successCallback(data);
			} else {
				failedCallback(status);
			}
		} ).catch( ( error ) => {
			errorCallback( error );
		} );
	}

	fetchResult() {
		return Fetcher.background.backgroundHealthyStatus();
	}
}

export default ( new LoopbackTester() );
