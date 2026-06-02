(function () {
	if (!navigator.sendBeacon || !window.rppBeacon || !rppBeacon.url) {
		return;
	}
	// Fire once per page load. POST with an empty body; post_id rides in the URL.
	navigator.sendBeacon(rppBeacon.url);
})();
