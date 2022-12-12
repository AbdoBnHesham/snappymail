
(doc=>{
	Array.prototype.unique = function() { return this.filter((v, i, a) => a.indexOf(v) === i); };
	Array.prototype.validUnique = function(fn) {
		return this.filter((v, i, a) => (fn ? fn(v) : v) && a.indexOf(v) === i);
	};

	Date.defineRelativeTimeFormat = config => relativeTime = config;

	// full = Monday, December 12, 2022 at 12:16:21 PM Central European Standard Time
	// long = December 12, 2022 at 12:16:21 PM GMT+1
	// medium = Dec 12, 2022, 12:16:21 PM
	// short = 12/12/22, 12:16 PM
	let formats = {
		LT   : {timeStyle: 'short'},
//		LT   : {hour: 'numeric', minute: 'numeric'},
		L    : {},
		LL   : {dateStyle: 'medium'},
//		LL   : {year: 'numeric', month: 'short', day: 'numeric'},
		LLL  : {dateStyle: 'long', timeStyle: 'short'},
//		LLL  : {year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: 'numeric'},
		LLLL : {dateStyle: 'full', timeStyle: 'short'},
//		LLLL : {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: 'numeric'},
		'd M': {day: '2-digit', month: 'short'}
	},
	phpFormats = {
		// Day
		d: {day: '2-digit'},
		D: {weekday: 'short'},
		j: {day: 'numeric'},
		l: {weekday: 'long'},
		z: {},
		// Month
		F: {month: 'long'},
		m: {month: '2-digit'},
		M: {month: 'short'},
		n: {month: 'numeric'},
		Y: {year: 'numeric'},
		y: {year: '2-digit'},
		// Time
		A: {hour12: true},
//		A: {hourCycle: 'h12'}, // h11, h12, h23, h24
		g: {hour: 'numeric', hourCycle:'h11'},
		G: {hour: 'numeric', hourCycle:'h23'},
		h: {hour: '2-digit', hourCycle:'h11'},
		H: {hour: '2-digit', hourCycle:'h23'},
		i: {minute: '2-digit'},
		s: {second: '2-digit'},
		u: {fractionalSecondDigits: 6},
		v: {fractionalSecondDigits: 3},
		Z: {timeZone: 'UTC'}
	},
	relativeTime = {
		// see /snappymail/v/0.0.0/app/localization/relativetimeformat/
	},
	pad2 = v => 10 > v ? '0' + v : v;

	// Format momentjs/PHP date formats to Intl.DateTimeFormat
	// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Intl/DateTimeFormat
	Date.prototype.format = function (options, UTC, hourCycle) {
		if (typeof options == 'string') {
			if ('Y-m-d\\TH:i:s' == options) {
				return this.getFullYear() + '-' + pad2(1 + this.getMonth()) + '-' + pad2(this.getDate())
					+ 'T' + pad2(this.getHours()) + ':' + pad2(this.getMinutes()) + ':' + pad2(this.getSeconds());
			}
			if (formats[options]) {
				options = formats[options];
			} else {
				let o, s = options + (UTC?'Z':''), i = s.length;
				console.log('Date.format('+s+')');
				options = {};
				while (i--) {
					o = phpFormats[s[i]];
					o && Object.entries(o).forEach(([k,v])=>options[k]=v);
				}
				formats[s] = options;
			}
		}
		if (hourCycle) {
			options.hourCycle = hourCycle;
		}
		return new Intl.DateTimeFormat(doc.documentElement.lang, options).format(this);
	};

	// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Intl/RelativeTimeFormat
	Date.prototype.fromNow = function() {
		let unit = 'second',
			value = Math.round((this.getTime() - Date.now()) / 1000),
			t = [[60,'minute'],[3600,'hour'],[86400,'day'],[2628000,'month'],[31536000,'year']],
			i = 5,
			abs = Math.abs(value);
		while (i--) {
			if (t[i][0] <= abs) {
				value = Math.round(value / t[i][0]);
				unit = t[i][1];
				break;
			}
		}
		if (Intl.RelativeTimeFormat) {
			let rtf = new Intl.RelativeTimeFormat(doc.documentElement.lang);
			return rtf.format(value, unit);
		}
		abs = Math.abs(value);
		let rtf = relativeTime.long[unit][0 > value ? 'past' : 'future'],
			plural = relativeTime.plural(abs);
		return (rtf[plural] || rtf).replace('{0}', abs);
	};

	Element.prototype.closestWithin = function(selector, parent) {
		const el = this.closest(selector);
		return (el && el !== parent && parent.contains(el)) ? el : null;
	};

	Element.fromHTML = string => {
		const template = doc.createElement('template');
		template.innerHTML = string.trim();
		return template.content.firstChild;
	};

	/**
	 * Every time the function is executed,
	 * it will delay the execution with the given amount of milliseconds.
	 */
	if (!Function.prototype.debounce) {
		Function.prototype.debounce = function(ms) {
			let func = this, timer;
			return function(...args) {
				timer && clearTimeout(timer);
				timer = setTimeout(()=>{
					func.apply(this, args)
					timer = 0;
				}, ms);
			};
		};
	}

	/**
	 * No matter how many times the event is executed,
	 * the function will be executed only once, after the given amount of milliseconds.
	 */
	if (!Function.prototype.throttle) {
		Function.prototype.throttle = function(ms) {
			let func = this, timer;
			return function(...args) {
				if (!timer) {
					timer = setTimeout(()=>{
						func.apply(this, args)
						timer = 0;
					}, ms);
				}
			};
		};
	}

})(document);
