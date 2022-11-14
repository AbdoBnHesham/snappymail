(doc => {

const
	eId = id => doc.getElementById('rl-'+id),
	app = eId('app'),
	admin = app && '1' == app.dataset.admin,
	layout = doc.cookie.match(/(^|;) ?rllayout=([^;]+)/) || '',
	redirect = path => doc.location.replace('./?/'+path),

	showError = msg => {
		let div = eId('loading-error');
		div.append(' ' + msg);
		eId('loading').hidden = true;
		div.hidden = false;
	},

	loadScript = src => {
		if (!src) {
			throw new Error('src should not be empty.');
		}
		return new Promise((resolve, reject) => {
			const script = doc.createElement('script');
			script.onload = () => resolve();
			script.onerror = () => reject('Failed loading ' + src);
			script.src = src;
//			script.async = true;
			doc.head.append(script);
		});
	};

navigator.cookieEnabled || redirect('NoCookie');
[].flat || redirect('BadBrowser');

let RL_APP_DATA = {};

doc.documentElement.classList.toggle('rl-mobile', 'mobile' === layout[2] || (!layout && 1000 > innerWidth));

window.rl = {
	adminArea: () => admin,

	settings: {
		get: name => RL_APP_DATA[name],
		set: (name, value) => RL_APP_DATA[name] = value,
		app: name => RL_APP_DATA.System[name]
	},

	setTitle: title =>
		doc.title = (title || '') + (RL_APP_DATA.Title ? (title ? ' - ' : '') + RL_APP_DATA.Title : ''),

	initData: appData => {
		RL_APP_DATA = appData;
		const url = appData.StaticLibsJs,
			cb = () => rl.app.bootstart();
		loadScript(url)
			.then(() => loadScript(url.replace('/libs.', `/${admin?'admin':'app'}.`)))
			.then(() => appData.PluginsLink ? loadScript(appData.PluginsLink) : Promise.resolve())
			.then(() => rl.app
					? cb()
					: doc.addEventListener('readystatechange', () => 'complete' == doc.readyState && cb())
			)
			.catch(e => {
				showError(e);
				throw e;
			});
	},

	setData: appData => {
		RL_APP_DATA = appData;
		rl.app.refresh();
	},

	loadScript: loadScript
};

loadScript(`./?/${admin ? 'Admin' : ''}AppData/0/${Math.random().toString().slice(2)}/`)
	.catch(e => showError(e));

})(document);
