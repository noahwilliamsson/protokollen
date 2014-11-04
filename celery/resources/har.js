if (!Date.prototype.toISOString) {
	Date.prototype.toISOString = function () {
		function pad(n) { return n < 10 ? '0' + n : n; }
		function ms(n) { return n < 10 ? '00'+ n : n < 100 ? '0' + n : n }
		return this.getFullYear() + '-' +
			pad(this.getMonth() + 1) + '-' +
			pad(this.getDate()) + 'T' +
			pad(this.getHours()) + ':' +
			pad(this.getMinutes()) + ':' +
			pad(this.getSeconds()) + '.' +
			ms(this.getMilliseconds()) + 'Z';
	}
}

function createHAR(address, title, startTime, resources) {
	var entries = [];

	resources.forEach(function (resource) {
		var request = resource.request,
			startReply = resource.startReply,
			endReply = resource.endReply;

		if (!request || !startReply || !endReply) {
			return;
		}

		// Exclude Data URI from HAR file because
		// they aren't included in specification
		if (request.url.match(/(^data:image\/.*)/i)) {
			return;
		}

		entries.push({
			startedDateTime: request.time.toISOString(),
			time: endReply.time - request.time,
			request: {
				method: request.method,
				url: request.url,
				httpVersion: "HTTP/1.1",
				cookies: [],
				headers: request.headers,
				queryString: [],
				headersSize: -1,
				bodySize: -1
			},
			response: {
				status: endReply.status,
				statusText: endReply.statusText,
				httpVersion: "HTTP/1.1",
				cookies: [],
				headers: endReply.headers,
				redirectURL: "",
				headersSize: -1,
				bodySize: startReply.bodySize,
				content: {
					size: startReply.bodySize,
					mimeType: endReply.contentType
				}
			},
			cache: {},
			timings: {
				blocked: 0,
				dns: -1,
				connect: -1,
				send: 0,
				wait: startReply.time - request.time,
				receive: endReply.time - startReply.time,
				ssl: -1
			},
			pageref: address
		});
	});

	return {
		log: {
			version: '1.2',
			creator: {
				name: "PhantomJS",
				version: phantom.version.major + '.' + phantom.version.minor +
					'.' + phantom.version.patch
			},
			pages: [{
				startedDateTime: startTime.toISOString(),
				id: address,
				title: title,
				pageTimings: {
					onLoad: page.endTime - page.startTime
				}
			}],
			entries: entries
		}
	};
}




var system = require('system');
if (system.args.length === 1) {
	console.log('Usage: ' + system.args[0] + ' <some URL> [har-output.json]');
	phantom.exit(1);
}

var	page = require('webpage').create(),
	fs = require('fs');

page.settings.resourceTimeout = 10000;
page.settings.userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X) AppleWebKit/534.34 (KHTML, like Gecko) PhantomJS/1.9.2 Safari/534.34 (siteintegrity; har generator)';
var slowTimeout = page.settings.resourceTimeout / 3;

page.address = system.args[1];
page.resources = [];

var harFilename = null;
if(system.args.length === 3)
	harFilename = system.args[2];


page.onLoadStarted = function () {
	page.startTime = new Date();
};

page.onResourceRequested = function (req) {
	page.resources[req.id] = {
		request: req,
		startReply: null,
		endReply: null
	};
};

page.onResourceTimeout = function (r) {
	console.log('WARN: ' + r.url + ' (' + r.errorString + ')');
}

page.onResourceReceived = function (res) {
	if (res.stage === 'start') {
		page.resources[res.id].startReply = res;
	}
	else if (res.stage === 'end') {
		page.resources[res.id].endReply = res;
	}

	var t, now = new Date();
	now = now.valueOf();

	var n = 0;
	page.resources.forEach(function (r) {
		if(r.endReply == null && r.request.time.valueOf() + slowTimeout < now)
			n++;
	});

	var i = 0;
	page.resources.forEach(function (r) {
		t = r.request.time.valueOf();
		if(r.endReply || t + slowTimeout >= now) return;
		i++;
		console.log('WARN: [' + i + '/' + n + '] Still waiting after ' + (now - t) + 'ms for: ' + r.request.url);
	});
};

page.open(page.address, function (status) {
	var har;
	if (status !== 'success') {
		console.log('FAIL to load the address');
		phantom.exit(1);
	} 

	window.setTimeout(function () {
		page.endTime = new Date();
		page.title = page.evaluate(function () {
			return document.title;
		});

		har = createHAR(page.address, page.title, page.startTime, page.resources);

		if(!harFilename) {
			var m = page.address.match(/^https?\:\/\/([^\/:?#]+)(?:[\/:?#]|$)/i);
			var hostname = m && m[1];
			if(!hostname)
				hostname = 'default';
			harFilename = hostname + '.json';
		}

		fs.write(harFilename, JSON.stringify(har, undefined, 4), 'w');
		console.log('Wrote HAR to ' + harFilename);
		phantom.exit();
	}, 10000);
});
